<?php
/**
 * rd-news.php — ดึงข่าวประชาสัมพันธ์ล่าสุดจาก RSS ของกรมสรรพากร
 * แล้วแปลงเป็น JSON ให้หน้าเว็บเรียกใช้
 *
 * มีการ cache ไว้เป็นไฟล์ 30 นาที เพื่อไม่ให้ยิงไปที่เว็บกรมสรรพากรถี่เกินไป
 * ถ้าดึงสดไม่สำเร็จ จะส่งข้อมูลที่ cache ไว้ล่าสุดกลับไปแทน (ระบุ stale = true)
 *
 * เรียกใช้: rd-news.php?limit=4
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

const RD_RSS_URL   = 'https://www.rd.go.th/publish.xml';
const CACHE_FILE   = __DIR__ . '/cache_rd_news.json';
const CACHE_SECOND = 1800; // 30 นาที

$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 4;
$limit = max(1, min($limit, 20));

$THAI_MONTHS = [
    1 => 'ม.ค.', 2 => 'ก.พ.', 3 => 'มี.ค.', 4 => 'เม.ย.', 5 => 'พ.ค.', 6 => 'มิ.ย.',
    7 => 'ก.ค.', 8 => 'ส.ค.', 9 => 'ก.ย.', 10 => 'ต.ค.', 11 => 'พ.ย.', 12 => 'ธ.ค.',
];

/** แปลง pubDate แบบ RFC822 เป็นวันที่ไทย เช่น "17 ส.ค. 2569" */
function thaiDate(string $pubDate, array $months): string
{
    $ts = strtotime($pubDate);
    if ($ts === false) {
        return '';
    }
    $d = (int)date('j', $ts);
    $m = (int)date('n', $ts);
    $y = (int)date('Y', $ts) + 543;
    return $d . ' ' . ($months[$m] ?? '') . ' ' . $y;
}

/** ตัดข้อความให้สั้นลง ไม่ให้การ์ดยาวเกินไป */
function shorten(string $text, int $len = 120): string
{
    $text = trim(preg_replace('/\s+/u', ' ', strip_tags($text)) ?? '');
    if (mb_strlen($text, 'UTF-8') <= $len) {
        return $text;
    }
    return mb_substr($text, 0, $len, 'UTF-8') . '…';
}

function readCache(): ?array
{
    if (!is_readable(CACHE_FILE)) {
        return null;
    }
    $raw = file_get_contents(CACHE_FILE);
    if ($raw === false) {
        return null;
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : null;
}

// ===== 1) ถ้ามี cache ที่ยังไม่หมดอายุ ใช้เลย =====
$cache = readCache();
if ($cache && isset($cache['fetched_at']) && (time() - (int)$cache['fetched_at'] < CACHE_SECOND)) {
    echo json_encode([
        'ok'     => true,
        'source' => 'กรมสรรพากร',
        'items'  => array_slice($cache['items'], 0, $limit),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ===== 2) ดึงสดจาก RSS =====
$items = [];
$fetchOk = false;

try {
    $ctx = stream_context_create([
        'http' => [
            'timeout' => 10,
            'header'  => "User-Agent: Mozilla/5.0\r\n",
        ],
        'ssl' => [
            'verify_peer'      => true,
            'verify_peer_name' => true,
        ],
    ]);

    $xmlRaw = @file_get_contents(RD_RSS_URL, false, $ctx);

    // เผื่อ host ปิด allow_url_fopen — ลองใช้ cURL แทน
    if ($xmlRaw === false && function_exists('curl_init')) {
        $ch = curl_init(RD_RSS_URL);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT      => 'Mozilla/5.0',
        ]);
        $xmlRaw = curl_exec($ch);
        curl_close($ch);
        if ($xmlRaw === false) {
            $xmlRaw = null;
        }
    }

    if (!empty($xmlRaw)) {
        $prev = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($xmlRaw);
        libxml_use_internal_errors($prev);

        if ($xml !== false && isset($xml->channel->item)) {
            foreach ($xml->channel->item as $item) {
                $items[] = [
                    'title'   => shorten((string)$item->title, 90),
                    'link'    => trim((string)$item->link),
                    'excerpt' => shorten((string)$item->description, 110),
                    'date'    => thaiDate((string)$item->pubDate, $THAI_MONTHS),
                ];
            }
            $fetchOk = count($items) > 0;
        }
    }
} catch (Throwable $e) {
    error_log('RD RSS fetch failed: ' . $e->getMessage());
}

// ===== 3) สำเร็จ -> เขียน cache แล้วส่งกลับ =====
if ($fetchOk) {
    @file_put_contents(CACHE_FILE, json_encode([
        'fetched_at' => time(),
        'items'      => $items,
    ], JSON_UNESCAPED_UNICODE));

    echo json_encode([
        'ok'     => true,
        'source' => 'กรมสรรพากร',
        'items'  => array_slice($items, 0, $limit),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ===== 4) ดึงไม่สำเร็จ -> ใช้ cache เก่า (ถ้ามี) =====
if ($cache && !empty($cache['items'])) {
    echo json_encode([
        'ok'     => true,
        'source' => 'กรมสรรพากร',
        'items'  => array_slice($cache['items'], 0, $limit),
        'stale'  => true,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

http_response_code(502);
echo json_encode([
    'ok'    => false,
    'error' => 'ไม่สามารถดึงข่าวจากกรมสรรพากรได้ในขณะนี้',
], JSON_UNESCAPED_UNICODE);
