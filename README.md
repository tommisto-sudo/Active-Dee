# Backend สำหรับฟอร์ม Contact

Flask API เล็ก ๆ ที่รับข้อมูลจากฟอร์มติดต่อในหน้า `contactus.html`, บันทึกลง SQLite และส่งอีเมลแจ้งเตือน

## วิธีติดตั้งและรัน

```bash
cd backend
python3 -m venv venv
source venv/bin/activate        # Windows: venv\Scripts\activate
pip install -r requirements.txt

cp .env.example .env
# แก้ค่าใน .env ให้เป็นบัญชีอีเมลจริง (ดูวิธีทำ App Password ของ Gmail ในคอมเมนต์ไฟล์ .env.example)

python app.py
```

เมื่อรันสำเร็จ จะเปิดที่ `http://localhost:5000` และมี endpoint หลักคือ

- `POST /api/contact` — รับข้อมูลฟอร์ม `{ name, email, phone, message }`
- `GET /api/health` — เช็คว่า server ทำงานอยู่
- `GET /api/rd-news?limit=4` — ดึงข่าวประชาสัมพันธ์ล่าสุดจาก RSS ของกรมสรรพากร (`rd.go.th/publish.xml`)
  แปลงเป็น JSON ให้หน้าแรกเรียกใช้แสดงผลอัตโนมัติ มีการ cache ไว้ 30 นาทีต่อครั้ง
  เพื่อไม่ให้ยิงไปที่เว็บกรมสรรพากรถี่เกินไป ถ้าดึงสดไม่ได้จะส่งข้อมูลที่ cache ไว้ล่าสุดกลับไปแทน

## เชื่อมกับหน้าเว็บ

ในไฟล์ `contactus.html` มีตัวแปร `CONTACT_API_URL` อยู่ในสคริปต์ท้ายไฟล์ ตอนนี้ตั้งเป็น
`http://localhost:5000/api/contact` สำหรับทดสอบในเครื่อง — พอ deploy จริงให้เปลี่ยนเป็น URL ของ backend จริง เช่น
`https://api.yourdomain.co.th/api/contact`

## ข้อมูลที่บันทึก

ทุกครั้งที่มีคนกรอกฟอร์ม ข้อมูลจะถูกบันทึกลงไฟล์ `contacts.db` (SQLite) โดยอัตโนมัติ แม้อีเมลจะส่งไม่สำเร็จ
ข้อมูลก็ยังไม่หาย สามารถเปิดดูด้วยโปรแกรม เช่น DB Browser for SQLite

## Deploy จริง

แนะนำให้ deploy ด้วย gunicorn แทนการรัน `python app.py` ตรง ๆ (dev server ของ Flask ไม่เหมาะกับ production):

```bash
pip install gunicorn
gunicorn -w 2 -b 0.0.0.0:5000 app:app
```

แล้วนำไปวางหลัง reverse proxy เช่น Nginx หรือ deploy บนแพลตฟอร์มอย่าง Render / Railway / Fly.io ก็ได้
