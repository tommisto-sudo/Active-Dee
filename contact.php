<?php
/**
 * contact.php — endpoint รับข้อมูลจากฟอร์มติดต่อในหน้าเว็บ
 *
 * ทำ 2 อย่าง:
 * 1. บันทึกข้อมูลลงตาราง `contacts` ในฐานข้อมูล (เก็บไว้เสมอ แม้อีเมลจะส่งไม่สำเร็จ)
 * 2. ส่งอีเมลแจ้งเตือนไปยังทีมงานผ่าน SMTP
 *
 * รับเฉพาะ POST + Content-Type: application/json
 * ตอบกลับเป็น JSON เสมอ: { ok: true/false, message/errors }
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

// ปรับ origin ให้ตรงกับโดเมนจริงตอน deploy (ใส่ * ไว้ก่อนสำหรับทดสอบ)
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// เบราว์เซอร์จะยิง preflight request (OPTIONS) มาก่อน POST จริง ต้องตอบรับเฉย ๆ
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

require __DIR__ . '/config.php';
require __DIR__ . '/SimpleSmtpMailer.php';

function respond(array $payload, int $statusCode = 200)
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

// ===== 1) อ่านและตรวจสอบข้อมูลที่ส่งเข้ามา =====
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!is_array($data)) {
    respond(['ok' => false, 'error' => 'รูปแบบข้อมูลไม่ถูกต้อง'], 400);
}

$name = trim((string)($data['name'] ?? ''));
$email = trim((string)($data['email'] ?? ''));
$phone = trim((string)($data['phone'] ?? ''));
$service = trim((string)($data['service'] ?? ''));
$message = trim((string)($data['message'] ?? ''));

$errors = [];
if ($name === '') {
    $errors['name'] = 'กรุณากรอกชื่อ-นามสกุล';
}
if ($email === '') {
    $errors['email'] = 'กรุณากรอกอีเมล';
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors['email'] = 'รูปแบบอีเมลไม่ถูกต้อง';
}
if ($message === '') {
    $errors['message'] = 'กรุณากรอกข้อความ';
}

if (!empty($errors)) {
    respond(['ok' => false, 'errors' => $errors], 400);
}

// ===== 2) บันทึกลงฐานข้อมูล =====
$mysqli = @mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if (!$mysqli) {
    error_log('DB connection failed: ' . mysqli_connect_error());
    respond(['ok' => false, 'error' => 'เกิดข้อผิดพลาดในการเชื่อมต่อฐานข้อมูล กรุณาลองใหม่'], 500);
}
mysqli_set_charset($mysqli, 'utf8mb4');

$ip = $_SERVER['REMOTE_ADDR'] ?? null;

$stmt = mysqli_prepare(
    $mysqli,
    'INSERT INTO contacts (name, email, phone, service, message, ip_address, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())'
);
mysqli_stmt_bind_param($stmt, 'ssssss', $name, $email, $phone, $service, $message, $ip);

if (!mysqli_stmt_execute($stmt)) {
    error_log('Insert failed: ' . mysqli_stmt_error($stmt));
    mysqli_stmt_close($stmt);
    mysqli_close($mysqli);
    respond(['ok' => false, 'error' => 'บันทึกข้อมูลไม่สำเร็จ กรุณาลองใหม่'], 500);
}
mysqli_stmt_close($stmt);
mysqli_close($mysqli);

// ===== 3) ส่งอีเมลแจ้งเตือน (ถ้าส่งไม่สำเร็จ ไม่ทำให้ request ทั้งหมด fail เพราะข้อมูลถูกบันทึกแล้ว) =====
try {
    $mailer = new SimpleSmtpMailer(SMTP_HOST, SMTP_PORT, SMTP_USER, SMTP_PASSWORD, SMTP_USE_TLS);

    $body = "มีข้อความติดต่อใหม่จากหน้าเว็บไซต์\n\n"
        . "ชื่อ: {$name}\n"
        . "อีเมล: {$email}\n"
        . "เบอร์โทร: " . ($phone !== '' ? $phone : '-') . "\n"
        . "บริการที่สนใจ: " . ($service !== '' ? $service : '-') . "\n\n"
        . "ข้อความ:\n{$message}\n\n"
        . '---' . "\n"
        . 'ส่งเมื่อ: ' . date('d/m/Y H:i');

    $mailer->send(
        MAIL_FROM_EMAIL,
        MAIL_FROM_NAME,
        CONTACT_RECEIVER_EMAIL,
        "[ติดต่อจากเว็บไซต์] ข้อความใหม่จาก {$name}",
        $body
    );
} catch (Throwable $e) {
    error_log('Send mail failed: ' . $e->getMessage());
    // ไม่ respond error ตรงนี้ เพราะข้อมูลถูกบันทึกลง DB เรียบร้อยแล้ว
}

respond(['ok' => true, 'message' => 'ส่งข้อความเรียบร้อยแล้ว ทีมงานจะติดต่อกลับโดยเร็วที่สุด']);
