"""
Backend สำหรับรับข้อมูลจากฟอร์ม Contact ของเว็บไซต์ "สมุดบัญชี & ภาษี"

สิ่งที่ทำ:
1. รับข้อมูลจากฟอร์ม (POST /api/contact) เป็น JSON
2. ตรวจสอบความถูกต้องของข้อมูล (validation)
3. บันทึกลงฐานข้อมูล SQLite (contacts.db) เก็บไว้เป็นหลักฐาน
4. ส่งอีเมลแจ้งเตือนไปยังทีมงาน ผ่าน SMTP (เช่น Gmail)

วิธีรัน:
    pip install -r requirements.txt
    cp .env.example .env      # แล้วแก้ค่าในไฟล์ .env ให้เป็นของจริง
    python app.py

Endpoint จะอยู่ที่ http://localhost:5000/api/contact
"""

import os
import re
import time
import sqlite3
import smtplib
import xml.etree.ElementTree as ET
from datetime import datetime
from email.mime.text import MIMEText
from email.mime.multipart import MIMEMultipart

import requests
from flask import Flask, request, jsonify
from flask_cors import CORS
from dotenv import load_dotenv

load_dotenv()

app = Flask(__name__)

# อนุญาตให้หน้าเว็บ (ที่อาจรันคนละ origin เช่น เปิดไฟล์ html ตรง ๆ หรือรันผ่าน live-server)
# เรียก API นี้ได้ ปรับ origins ให้ตรงกับโดเมนจริงตอน deploy
CORS(app, resources={r"/api/*": {"origins": "*"}})

DB_PATH = os.path.join(os.path.dirname(__file__), "contacts.db")

SMTP_HOST = os.getenv("SMTP_HOST", "smtp.gmail.com")
SMTP_PORT = int(os.getenv("SMTP_PORT", "587"))
SMTP_USER = os.getenv("SMTP_USER", "")
SMTP_PASSWORD = os.getenv("SMTP_PASSWORD", "")
CONTACT_RECEIVER = os.getenv("CONTACT_RECEIVER", SMTP_USER)

EMAIL_RE = re.compile(r"^[^@\s]+@[^@\s]+\.[^@\s]+$")


def init_db():
    """สร้างตาราง contacts ถ้ายังไม่มี"""
    conn = sqlite3.connect(DB_PATH)
    conn.execute(
        """
        CREATE TABLE IF NOT EXISTS contacts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            email TEXT NOT NULL,
            phone TEXT,
            message TEXT NOT NULL,
            created_at TEXT NOT NULL
        )
        """
    )
    conn.commit()
    conn.close()


def save_to_db(name, email, phone, message):
    conn = sqlite3.connect(DB_PATH)
    conn.execute(
        "INSERT INTO contacts (name, email, phone, message, created_at) VALUES (?, ?, ?, ?, ?)",
        (name, email, phone, message, datetime.now().isoformat(timespec="seconds")),
    )
    conn.commit()
    conn.close()


def send_notification_email(name, email, phone, message):
    """ส่งอีเมลแจ้งเตือนไปยังทีมงาน ผ่าน SMTP"""
    if not SMTP_USER or not SMTP_PASSWORD:
        # ยังไม่ตั้งค่า SMTP — ข้ามการส่งอีเมล แต่ข้อมูลยังถูกบันทึกลง DB ตามปกติ
        app.logger.warning("ยังไม่ได้ตั้งค่า SMTP_USER / SMTP_PASSWORD จึงข้ามการส่งอีเมล")
        return False

    msg = MIMEMultipart()
    msg["From"] = SMTP_USER
    msg["To"] = CONTACT_RECEIVER
    msg["Subject"] = f"[ติดต่อจากเว็บไซต์] ข้อความใหม่จาก {name}"

    body = f"""มีข้อความติดต่อใหม่จากหน้าเว็บไซต์

ชื่อ: {name}
อีเมล: {email}
เบอร์โทร: {phone or "-"}

ข้อความ:
{message}

---
ส่งเมื่อ: {datetime.now().strftime("%d/%m/%Y %H:%M")}
"""
    msg.attach(MIMEText(body, "plain", "utf-8"))

    with smtplib.SMTP(SMTP_HOST, SMTP_PORT) as server:
        server.starttls()
        server.login(SMTP_USER, SMTP_PASSWORD)
        server.send_message(msg)
    return True


@app.route("/api/contact", methods=["POST"])
def contact():
    data = request.get_json(silent=True) or {}

    name = (data.get("name") or "").strip()
    email = (data.get("email") or "").strip()
    phone = (data.get("phone") or "").strip()
    message = (data.get("message") or "").strip()

    errors = {}
    if not name:
        errors["name"] = "กรุณากรอกชื่อ-นามสกุล"
    if not email:
        errors["email"] = "กรุณากรอกอีเมล"
    elif not EMAIL_RE.match(email):
        errors["email"] = "รูปแบบอีเมลไม่ถูกต้อง"
    if not message:
        errors["message"] = "กรุณากรอกข้อความ"

    if errors:
        return jsonify({"ok": False, "errors": errors}), 400

    try:
        save_to_db(name, email, phone, message)
    except Exception as exc:
        app.logger.exception("บันทึกข้อมูลลงฐานข้อมูลไม่สำเร็จ")
        return jsonify({"ok": False, "error": "เกิดข้อผิดพลาดในการบันทึกข้อมูล กรุณาลองใหม่"}), 500

    try:
        send_notification_email(name, email, phone, message)
    except Exception as exc:
        # แม้ส่งอีเมลไม่สำเร็จ ข้อมูลก็ถูกบันทึกไว้แล้ว จึงยังตอบกลับว่าสำเร็จ แต่ log ไว้เพื่อตรวจสอบ
        app.logger.exception("ส่งอีเมลแจ้งเตือนไม่สำเร็จ")

    return jsonify({"ok": True, "message": "ส่งข้อความเรียบร้อยแล้ว ทีมงานจะติดต่อกลับโดยเร็วที่สุด"})


@app.route("/api/health", methods=["GET"])
def health():
    return jsonify({"status": "ok"})


# ===================== ข่าวกรมสรรพากร (RSS) =====================

RD_RSS_URL = "https://www.rd.go.th/publish.xml"  # RSS ข่าวประชาสัมพันธ์ ของกรมสรรพากร
RD_CACHE_SECONDS = 30 * 60  # cache ไว้ 30 นาที กันยิงไปที่เว็บสรรพากรถี่เกินไป

_rd_cache = {"data": None, "fetched_at": 0}

THAI_MONTHS = {
    1: "ม.ค.", 2: "ก.พ.", 3: "มี.ค.", 4: "เม.ย.", 5: "พ.ค.", 6: "มิ.ย.",
    7: "ก.ค.", 8: "ส.ค.", 9: "ก.ย.", 10: "ต.ค.", 11: "พ.ย.", 12: "ธ.ค.",
}


def _format_thai_date(pub_date_str):
    """แปลง pubDate แบบ RFC822 ('Mon, 17 Aug 2026 11:16:40 +0700') เป็นวันที่ไทย"""
    try:
        # ตัด timezone offset ออกก่อน parse (รูปแบบ RSS มาตรฐาน)
        dt = datetime.strptime(pub_date_str[:25].strip(), "%a, %d %b %Y %H:%M:%S")
    except ValueError:
        return pub_date_str
    be_year = dt.year + 543
    return f"{dt.day} {THAI_MONTHS.get(dt.month, '')} {be_year}"


def _fetch_rd_news(limit=4):
    """ดึงข่าวล่าสุดจาก RSS ของกรมสรรพากร แล้ว parse เป็น list of dict"""
    now = time.time()
    if _rd_cache["data"] and (now - _rd_cache["fetched_at"] < RD_CACHE_SECONDS):
        return _rd_cache["data"][:limit]

    resp = requests.get(RD_RSS_URL, timeout=10, headers={"User-Agent": "Mozilla/5.0"})
    resp.raise_for_status()
    root = ET.fromstring(resp.content)

    items = []
    for item in root.findall(".//item"):
        title = (item.findtext("title") or "").strip()
        link = (item.findtext("link") or "").strip()
        description = (item.findtext("description") or "").strip()
        pub_date = (item.findtext("pubDate") or "").strip()

        items.append({
            "title": title,
            "link": link,
            "excerpt": description,
            "date": _format_thai_date(pub_date) if pub_date else "",
        })

    _rd_cache["data"] = items
    _rd_cache["fetched_at"] = now
    return items[:limit]


@app.route("/api/rd-news", methods=["GET"])
def rd_news():
    limit = request.args.get("limit", default=4, type=int)
    limit = max(1, min(limit, 20))

    try:
        items = _fetch_rd_news(limit=limit)
        return jsonify({"ok": True, "source": "กรมสรรพากร", "items": items})
    except Exception:
        app.logger.exception("ดึงข่าวจาก RSS กรมสรรพากรไม่สำเร็จ")
        # ถ้าดึงสดไม่ได้ แต่มี cache เก่าอยู่ ให้ส่งอันเก่ากลับไปก่อน ดีกว่าไม่มีเลย
        if _rd_cache["data"]:
            return jsonify({
                "ok": True,
                "source": "กรมสรรพากร",
                "items": _rd_cache["data"][:limit],
                "stale": True,
            })
        return jsonify({"ok": False, "error": "ไม่สามารถดึงข่าวจากกรมสรรพากรได้ในขณะนี้"}), 502


if __name__ == "__main__":
    init_db()
    app.run(host="0.0.0.0", port=5000, debug=True)
