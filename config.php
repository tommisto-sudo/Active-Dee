<?php
/**
 * ไฟล์ตั้งค่า — แก้ค่าตรงนี้ให้เป็นของจริงก่อนใช้งาน
 * ห้าม commit ไฟล์นี้ขึ้น git สาธารณะ (ใส่ config.php ใน .gitignore)
 */

// ===== ฐานข้อมูล (MySQL) =====
define('DB_HOST', 'activedeeconsult.com');
define('DB_NAME', 'activedee_contacts');
define('DB_USER', 'activedee_admin');
define('DB_PASS', 'Active@ee148');

// ===== SMTP สำหรับส่งอีเมลแจ้งเตือน =====
define('SMTP_HOST', 'smtp.activedeeconsult.com');   // หรือ SMTP ของโฮสต์คุณเอง เช่น mail.yourdomain.co.th
define('SMTP_PORT', 587);                // 587 = STARTTLS (แนะนำ), 465 = SSL โดยตรง
define('SMTP_USE_TLS', true);
define('SMTP_USER', 'admin@activedeeconsult.com');
define('SMTP_PASSWORD', 'Lahickty228'); // ถ้าใช้ Gmail ต้องใช้ App Password ไม่ใช่รหัสผ่านบัญชีปกติ

// อีเมลที่จะให้ปรากฏเป็นผู้ส่ง และอีเมลปลายทางที่จะรับข้อความติดต่อ
define('MAIL_FROM_EMAIL', SMTP_USER);
define('MAIL_FROM_NAME', 'เว็บไซต์ Active Dee Consult');
define('CONTACT_RECEIVER_EMAIL', 'admin@activedeeconsult.com');
