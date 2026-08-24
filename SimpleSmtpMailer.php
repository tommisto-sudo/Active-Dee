<?php
/**
 * SimpleSmtpMailer — SMTP client เล็ก ๆ เขียนด้วย PHP ล้วน ไม่พึ่ง library ภายนอก
 * รองรับ STARTTLS + AUTH LOGIN ใช้ได้กับ Gmail / Office365 / SMTP ทั่วไปที่ต้อง auth
 *
 * หมายเหตุ: ถ้าต้องการความเสถียรสูงสุดสำหรับ production แนะนำให้ติดตั้ง PHPMailer
 * ผ่าน Composer แทน (composer require phpmailer/phpmailer) แล้วสลับไปใช้แทนคลาสนี้ได้
 * โครงสร้างการเรียกใช้ (send()) ถูกออกแบบให้สลับไป-มาได้ง่าย
 */

class SimpleSmtpMailer
{
    private string $host;
    private int $port;
    private string $username;
    private string $password;
    private bool $useTls;
    private $socket;
    private array $debugLog = [];

    public function __construct(string $host, int $port, string $username, string $password, bool $useTls = true)
    {
        $this->host = $host;
        $this->port = $port;
        $this->username = $username;
        $this->password = $password;
        $this->useTls = $useTls;
    }

    public function getDebugLog(): array
    {
        return $this->debugLog;
    }

    private function log(string $direction, string $line): void
    {
        $this->debugLog[] = "$direction $line";
    }

    private function readResponse(): string
    {
        $data = '';
        while ($str = fgets($this->socket, 515)) {
            $data .= $str;
            // บรรทัดสุดท้ายของ response จะขึ้นต้นด้วยรหัส 3 หลักตามด้วยช่องว่าง (ไม่ใช่ขีดกลาง)
            if (substr($str, 3, 1) === ' ') {
                break;
            }
        }
        $this->log('S:', trim($data));
        return $data;
    }

    private function sendCommand(string $command, bool $hideInLog = false): string
    {
        $this->log('C:', $hideInLog ? '[hidden]' : $command);
        fwrite($this->socket, $command . "\r\n");
        return $this->readResponse();
    }

    private function checkCode(string $response, string $expectedCode): void
    {
        $code = substr($response, 0, 3);
        if ($code !== $expectedCode) {
            throw new Exception("SMTP error: expected $expectedCode, got: " . trim($response));
        }
    }

    /**
     * ส่งอีเมล
     *
     * @param string $fromEmail
     * @param string $fromName
     * @param string $toEmail
     * @param string $subject
     * @param string $body
     * @throws Exception
     */
    public function send(string $fromEmail, string $fromName, string $toEmail, string $subject, string $body): void
    {
        $this->socket = fsockopen($this->host, $this->port, $errno, $errstr, 15);
        if (!$this->socket) {
            throw new Exception("เชื่อมต่อ SMTP server ไม่ได้: $errstr ($errno)");
        }
        stream_set_timeout($this->socket, 15);

        $this->checkCode($this->readResponse(), '220');

        $this->checkCode($this->sendCommand('EHLO ' . gethostname()), '250');

        if ($this->useTls) {
            $this->checkCode($this->sendCommand('STARTTLS'), '220');
            if (!stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new Exception('เปิดใช้งาน TLS ไม่สำเร็จ');
            }
            // ต้อง EHLO ใหม่อีกครั้งหลังเปิด TLS
            $this->checkCode($this->sendCommand('EHLO ' . gethostname()), '250');
        }

        $this->checkCode($this->sendCommand('AUTH LOGIN'), '334');
        $this->checkCode($this->sendCommand(base64_encode($this->username), true), '334');
        $this->checkCode($this->sendCommand(base64_encode($this->password), true), '235');

        $this->checkCode($this->sendCommand("MAIL FROM:<$fromEmail>"), '250');
        $this->checkCode($this->sendCommand("RCPT TO:<$toEmail>"), '250');
        $this->checkCode($this->sendCommand('DATA'), '354');

        $headers = [];
        $headers[] = 'From: ' . $this->encodeHeader($fromName) . " <$fromEmail>";
        $headers[] = "To: <$toEmail>";
        $headers[] = 'Subject: ' . $this->encodeHeader($subject);
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-Type: text/plain; charset=UTF-8';
        $headers[] = 'Content-Transfer-Encoding: base64';
        $headers[] = 'Date: ' . date('r');

        // เข้ารหัสเนื้อหาเป็น base64 กันปัญหาภาษาไทยเพี้ยนระหว่างส่ง
        $encodedBody = chunk_split(base64_encode($body));

        $message = implode("\r\n", $headers) . "\r\n\r\n" . $encodedBody . "\r\n.";
        $this->checkCode($this->sendCommand($message), '250');

        $this->sendCommand('QUIT');
        fclose($this->socket);
    }

    private function encodeHeader(string $text): string
    {
        // เข้ารหัสหัวอีเมลภาษาไทยตามมาตรฐาน MIME (=?UTF-8?B?...?=)
        return '=?UTF-8?B?' . base64_encode($text) . '?=';
    }
}
