# Backend PHP — Active Dee Consult

ไฟล์ฝั่งเซิร์ฟเวอร์สำหรับเว็บไซต์ www.activedeeconsult.com
รองรับ hosting ที่มี PHP + MySQL + SMTP (ไม่ต้องใช้ Python และไม่ต้องใช้ Composer)

## ไฟล์ในชุดนี้

| ไฟล์ | หน้าที่ |
|---|---|
| `contact.php` | รับข้อมูลจากฟอร์มหน้า "ติดต่อเรา" → บันทึกลง MySQL → ส่งอีเมลแจ้งเตือน |
| `rd-news.php` | ดึงข่าวประชาสัมพันธ์ล่าสุดจาก RSS กรมสรรพากร แปลงเป็น JSON ให้หน้าเว็บ |
| `SimpleSmtpMailer.php` | คลาสส่งอีเมลผ่าน SMTP เขียนด้วย PHP ล้วน |
| `config.php` | เก็บค่า DB และ SMTP — **ต้องแก้เป็นของจริงก่อนใช้** |
| `schema.sql` | สร้างฐานข้อมูลและตาราง `contacts` |

## ขั้นตอนติดตั้ง

1. **สร้างฐานข้อมูล**
   ```bash
   mysql -u root -p < schema.sql
   ```
   หรือ import ผ่าน phpMyAdmin

   > ถ้าเคยสร้างตารางจากเวอร์ชันก่อนแล้ว ให้รันเพิ่มเพื่อรองรับช่อง "บริการที่สนใจ":
   > ```sql
   > ALTER TABLE contacts ADD COLUMN service VARCHAR(120) DEFAULT NULL AFTER phone;
   > ```

2. **แก้ `config.php`** ใส่ค่าจริงของ DB และ SMTP

3. **อัปโหลดไฟล์ PHP ทั้งหมดไว้โฟลเดอร์เดียวกับไฟล์ HTML**
   เพราะหน้าเว็บเรียกแบบ path สัมพัทธ์ (`contact.php`, `rd-news.php`)

   โครงสร้างที่ควรเป็นบน server:
   ```
   public_html/
     index.html  aboutus.html  services.html  packages.html
     blog.html   article.html  contactus.html  style.css
     contact.php  rd-news.php  config.php  SimpleSmtpMailer.php
   ```

   ถ้าต้องการแยกไฟล์ PHP ไว้คนละโฟลเดอร์ ให้แก้ตัวแปรในไฟล์ HTML:
   - `contactus.html` → `const CONTACT_API = "contact.php";`
   - `index.html` และ `blog.html` → `const RD_API = "rd-news.php?limit=4";`

4. **ตั้งสิทธิ์โฟลเดอร์ให้เขียนไฟล์ได้** (สำหรับ cache ข่าวสรรพากร)
   `rd-news.php` จะสร้างไฟล์ `cache_rd_news.json` อัตโนมัติ
   ถ้าเขียนไม่ได้ ระบบยังทำงานได้ปกติ เพียงแต่จะดึง RSS ใหม่ทุกครั้ง

## ทดสอบว่าใช้งานได้

- เปิด `https://www.activedeeconsult.com/rd-news.php?limit=4` ในเบราว์เซอร์
  ควรเห็น JSON ข่าวจากกรมสรรพากร
- กรอกฟอร์มในหน้า "ติดต่อเรา" แล้วตรวจว่ามีแถวใหม่ในตาราง `contacts`

## หมายเหตุสำคัญ

- ต้องเปิดหน้าเว็บผ่าน `http://` หรือ `https://` เท่านั้น — เปิดไฟล์ตรง ๆ แบบ `file://` จะเรียก PHP ไม่ได้
- ถ้าอีเมลส่งไม่ออก **ข้อมูลฟอร์มยังถูกบันทึกลงฐานข้อมูลตามปกติ ไม่หาย** ให้ตรวจ error log ของ PHP
- Gmail ต้องใช้ **App Password** (สร้างที่ `myaccount.google.com/apppasswords`) ไม่ใช่รหัสผ่านบัญชีปกติ
- ถ้า SMTP ของ host ใช้พอร์ต 465 (SSL ตรง ไม่ใช่ STARTTLS) ต้องปรับ `SimpleSmtpMailer.php` ให้เชื่อมผ่าน `ssl://` — แจ้งได้ถ้าต้องการเวอร์ชันนั้น
- `rd-news.php` ต้องให้ server เรียกเน็ตออกภายนอกได้ (allow_url_fopen หรือ cURL) — ไฟล์นี้รองรับทั้งสองแบบอยู่แล้ว
