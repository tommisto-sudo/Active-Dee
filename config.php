<?php
/**
 * ไฟล์ตั้งค่า — แก้ค่าตรงนี้ให้เป็นของจริงก่อนใช้งาน
 * ห้าม commit ไฟล์นี้ขึ้น git สาธารณะ (ใส่ config.php ใน .gitignore)
 */

// ===== ฐานข้อมูล (MySQL) =====
define('DB_HOST', 'localhost');
define('DB_NAME', 'active_dee_consult');
define('DB_USER', 'your_db_username');
define('DB_PASS', 'your_db_password');

// ===== SMTP สำหรับส่งอีเมลแจ้งเตือน =====
define('SMTP_HOST', 'smtp.gmail.com');   // หรือ SMTP ของโฮสต์คุณเอง เช่น mail.yourdomain.co.th
define('SMTP_PORT', 587);                // 587 = STARTTLS (แนะนำ), 465 = SSL โดยตรง
define('SMTP_USE_TLS', true);
define('SMTP_USER', 'youraccount@gmail.com');
define('SMTP_PASSWORD', 'your-app-password'); // ถ้าใช้ Gmail ต้องใช้ App Password ไม่ใช่รหัสผ่านบัญชีปกติ

// อีเมลที่จะให้ปรากฏเป็นผู้ส่ง และอีเมลปลายทางที่จะรับข้อความติดต่อ
define('MAIL_FROM_EMAIL', SMTP_USER);
define('MAIL_FROM_NAME', 'เว็บไซต์ Active Dee Consult');
define('CONTACT_RECEIVER_EMAIL', 'info@example.co.th');
