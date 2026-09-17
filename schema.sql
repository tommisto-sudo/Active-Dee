-- รันไฟล์นี้ครั้งเดียวเพื่อสร้างตารางเก็บข้อมูลฟอร์มติดต่อ
-- ตัวอย่าง: mysql -u root -p active_dee_consult < schema.sql

CREATE DATABASE IF NOT EXISTS active_dee_consult
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE active_dee_consult;

CREATE TABLE IF NOT EXISTS contacts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL,
    phone VARCHAR(30) DEFAULT NULL,
    service VARCHAR(120) DEFAULT NULL,
    message TEXT NOT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_contacts_created_at ON contacts (created_at);

-- ถ้าเคยสร้างตารางไว้แล้วจากเวอร์ชันก่อน ให้รันบรรทัดนี้เพื่อเพิ่มคอลัมน์ service
-- ALTER TABLE contacts ADD COLUMN service VARCHAR(120) DEFAULT NULL AFTER phone;
