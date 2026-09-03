<?php

declare(strict_types=1);

/**
 * VN BioLink Hub — front controller duy nhất.
 * Router + Controller + API + Giao diện HTML nằm trong 1 file (PHP thuần,
 * không framework, đúng chuẩn đóng gói Template Vibe Host).
 */

// ==========================================================================
// BOOTSTRAP
// ==========================================================================

// Hỗ trợ hosting FTP thuần (không set được biến môi trường kiểu Docker/Vibe
// Host): nếu có config.php cạnh index.php, nó tự putenv() các biến DB_* —
// phần còn lại của app không đổi gì (vẫn đọc qua getenv() như bình thường).
if (file_exists(__DIR__ . '/config.php')) {
    require __DIR__ . '/config.php';
}

$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';

ini_set('session.gc_maxlifetime', (string) (30 * 86400));

session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'secure'   => $isHttps,
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');

$APP_ENV = getenv('APP_ENV') ?: 'production';
error_reporting(E_ALL);
ini_set('display_errors', $APP_ENV === 'production' ? '0' : '1');

const UPLOAD_DIR   = __DIR__ . '/uploads';
const UPLOAD_WEB   = '/uploads';
const MAX_AVATAR_BYTES = 2 * 1024 * 1024;
const ALLOWED_AVATAR_MIME = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

// color: dùng cho chip icon trên bio page + thống kê. icon: key tra trong ICONS.
const LINK_TYPES = [
    'tiktok'    => ['label' => 'TikTok',          'icon' => 'music',        'color' => '#000000'],
    'shopee'    => ['label' => 'Shopee',          'icon' => 'shoppingBag',  'color' => '#EE4D2D'],
    'website'   => ['label' => 'Website cá nhân', 'icon' => 'globe',        'color' => '#0EA5E9'],
    'facebook'  => ['label' => 'Facebook',        'icon' => 'facebook',     'color' => '#1877F2'],
    'instagram' => ['label' => 'Instagram',       'icon' => 'instagram',    'color' => '#E4405F'],
    'youtube'   => ['label' => 'YouTube',         'icon' => 'youtube',      'color' => '#FF0000'],
    'custom'    => ['label' => 'Liên kết khác',   'icon' => 'link',         'color' => '#6B7280'],
];

const LEAD_STATUS = [
    'new'       => ['label' => 'Mới',          'class' => 'bg-indigo-50 text-indigo-600'],
    'contacted' => ['label' => 'Đã liên hệ',   'class' => 'bg-amber-50 text-amber-700'],
    'resolved'  => ['label' => 'Đã xử lý',     'class' => 'bg-emerald-50 text-emerald-700'],
];

// ==========================================================================
// ICONS — SVG line icon, stroke 1.8, không dùng emoji/font icon ngoài.
// ==========================================================================

const ICONS = [
    'grid'         => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
    'user'         => '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
    'link'         => '<path d="M10 13a5 5 0 0 0 7.07 0l1.41-1.41a5 5 0 0 0-7.07-7.07L10 6"/><path d="M14 11a5 5 0 0 0-7.07 0l-1.41 1.41a5 5 0 0 0 7.07 7.07L14 18"/>',
    'users'        => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
    'barChart'     => '<line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/>',
    'sliders'      => '<line x1="4" y1="21" x2="4" y2="14"/><line x1="4" y1="10" x2="4" y2="3"/><line x1="12" y1="21" x2="12" y2="12"/><line x1="12" y1="8" x2="12" y2="3"/><line x1="20" y1="21" x2="20" y2="16"/><line x1="20" y1="12" x2="20" y2="3"/><line x1="1" y1="14" x2="7" y2="14"/><line x1="9" y1="8" x2="15" y2="8"/><line x1="17" y1="16" x2="23" y2="16"/>',
    'eye'          => '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>',
    'eyeOff'       => '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/>',
    'logOut'       => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>',
    'search'       => '<circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>',
    'bell'         => '<path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/>',
    'chevronDown'  => '<polyline points="6 9 12 15 18 9"/>',
    'chevronRight' => '<polyline points="9 18 15 12 9 6"/>',
    'plus'         => '<line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>',
    'pencil'       => '<path d="M17 3a2.83 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"/>',
    'trash'        => '<polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/>',
    'grip'         => '<circle cx="9" cy="6" r="1.2"/><circle cx="9" cy="12" r="1.2"/><circle cx="9" cy="18" r="1.2"/><circle cx="15" cy="6" r="1.2"/><circle cx="15" cy="12" r="1.2"/><circle cx="15" cy="18" r="1.2"/>',
    'check'        => '<polyline points="20 6 9 17 4 12"/>',
    'x'            => '<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>',
    'camera'       => '<path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/>',
    'phone'        => '<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.91.34 1.85.57 2.81.7A2 2 0 0 1 22 16.92z"/>',
    'messageCircle'=> '<path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/>',
    'externalLink' => '<path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/>',
    'download'     => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>',
    'filter'       => '<polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/>',
    'calendar'     => '<rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>',
    'alertTriangle'=> '<path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>',
    'image'        => '<rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/>',
    'arrowLeft'    => '<line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/>',
    'lock'         => '<rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
    'info'         => '<circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/>',
    'inbox'        => '<polyline points="22 12 16 12 14 15 10 15 8 12 2 12"/><path d="M5.45 5.11L2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/>',
    'monitor'      => '<rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/>',
    'smartphone'   => '<rect x="5" y="2" width="14" height="20" rx="2"/><line x1="12" y1="18" x2="12.01" y2="18"/>',
    'globe'        => '<circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/>',
    'shoppingBag'  => '<path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/>',
    'music'        => '<path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/>',
    'facebook'     => '<path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/>',
    'instagram'    => '<rect x="2" y="2" width="20" height="20" rx="5"/><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"/><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"/>',
    'youtube'      => '<path d="M22.54 6.42a2.78 2.78 0 0 0-1.94-2C18.88 4 12 4 12 4s-6.88 0-8.6.46a2.78 2.78 0 0 0-1.94 2A29 29 0 0 0 1 11.75a29 29 0 0 0 .46 5.33A2.78 2.78 0 0 0 3.4 19c1.72.46 8.6.46 8.6.46s6.88 0 8.6-.46a2.78 2.78 0 0 0 1.94-2 29 29 0 0 0 .46-5.25 29 29 0 0 0-.46-5.33z"/><polygon points="9.75 15.02 15.5 11.75 9.75 8.48 9.75 15.02"/>',
    'mail'         => '<path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22 6 12 13 2 6"/>',
    'menu'         => '<line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/>',
];

function icon(string $name, string $class = 'h-5 w-5', string $fill = 'none'): string
{
    $p = ICONS[$name] ?? ICONS['info'];
    $strokeAttr = $fill === 'none' ? ' stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"' : '';
    return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="' . $fill . '"' . $strokeAttr . ' class="' . $class . '">' . $p . '</svg>';
}

// ==========================================================================
// DATABASE
// ==========================================================================

function db(): PDO
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }
    $host = getenv('DB_HOST') ?: '127.0.0.1';
    $port = getenv('DB_PORT') ?: '3306';
    $name = getenv('DB_DATABASE') ?: '';
    $user = getenv('DB_USERNAME') ?: '';
    $pass = getenv('DB_PASSWORD') ?: '';

    $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
    return $pdo;
}

// ==========================================================================
// HELPERS
// ==========================================================================

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function jsonResponse(array $data, int $status = 200)
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function redirect(string $to)
{
    header('Location: ' . $to, true, 302);
    exit;
}

function csrfToken(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrfCheck($token): bool
{
    return is_string($token) && !empty($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $token);
}

function currentUser(): ?array
{
    if (empty($_SESSION['admin_id'])) {
        return null;
    }
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $stmt = db()->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$_SESSION['admin_id']]);
    $cache = $stmt->fetch() ?: null;
    return $cache;
}

function requireLogin(): array
{
    $user = currentUser();
    if (!$user) {
        redirect('/login');
    }
    return $user;
}

function siteOwner(): ?array
{
    $row = db()->query('SELECT * FROM users ORDER BY id ASC LIMIT 1')->fetch();
    return $row ?: null;
}

function normalizePhoneVn(string $phone): string
{
    $digits = preg_replace('/\D+/', '', $phone) ?? '';
    if (substr($digits, 0, 2) === '84') {
        $digits = '0' . substr($digits, 2);
    }
    return $digits;
}

function zaloUrl(string $phone): string
{
    $digits = normalizePhoneVn($phone);
    $intl   = substr($digits, 0, 1) === '0' ? '84' . substr($digits, 1) : $digits;
    return 'https://zalo.me/' . $intl;
}

function linkHref(string $type, string $url): string
{
    if (preg_match('#^https?://#i', $url) || $type === 'custom') {
        return preg_match('#^https?://#i', $url) ? $url : 'https://' . ltrim($url, '/');
    }
    return 'https://' . ltrim($url, '/');
}

function validThemeColor($value, string $fallback = '#5B4CF6'): string
{
    return (is_string($value) && preg_match('/^#[0-9a-fA-F]{6}$/', $value)) ? $value : $fallback;
}

function pctChange(int $current, int $previous): array
{
    if ($previous === 0) {
        return ['label' => ($current > 0 ? '+100%' : '0%'), 'positive' => $current >= 0];
    }
    $pct = (($current - $previous) / $previous) * 100;
    $sign = $pct >= 0 ? '+' : '';
    return ['label' => $sign . number_format($pct, 1) . '%', 'positive' => $pct >= 0];
}

function fmtDateTime(?string $dt): string
{
    if (!$dt) {
        return '';
    }
    $ts = strtotime($dt);
    return $ts ? date('d/m/Y H:i', $ts) : $dt;
}

/** Suy ra "Desktop/Mobile/Tablet" + tên trình duyệt từ User-Agent — chỉ mang tính tương đối, không cần thư viện ngoài. */
function parseUserAgent(?string $ua): array
{
    $ua = (string) $ua;
    if ($ua === '') {
        return ['device' => 'Không rõ', 'browser' => 'Không rõ'];
    }
    if (preg_match('/tablet|ipad/i', $ua)) {
        $device = 'Tablet';
    } elseif (preg_match('/mobile|android|iphone/i', $ua)) {
        $device = 'Mobile';
    } else {
        $device = 'Desktop';
    }
    if (stripos($ua, 'Edg/') !== false) {
        $browser = 'Edge';
    } elseif (stripos($ua, 'Chrome/') !== false && stripos($ua, 'Chromium') === false) {
        $browser = 'Chrome';
    } elseif (stripos($ua, 'Firefox/') !== false) {
        $browser = 'Firefox';
    } elseif (stripos($ua, 'Safari/') !== false) {
        $browser = 'Safari';
    } else {
        $browser = 'Khác';
    }
    return ['device' => $device, 'browser' => $browser];
}

function clientIp(): string
{
    $fwd = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
    if ($fwd !== '') {
        $parts = explode(',', $fwd);
        return trim($parts[0]);
    }
    return $_SERVER['REMOTE_ADDR'] ?? '';
}

/** Sparkline SVG polyline nhỏ (24x40) từ 1 mảng số — dùng cho KPI card, dữ liệu thật. */
function sparkline(array $values, string $color): string
{
    $max = max($values) ?: 1;
    $n = count($values);
    if ($n < 2) {
        return '';
    }
    $w = 64;
    $h = 24;
    $step = $w / ($n - 1);
    $pts = [];
    foreach (array_values($values) as $i => $v) {
        $x = round($i * $step, 1);
        $y = round($h - ($v / $max) * ($h - 3) - 1.5, 1);
        $pts[] = "{$x},{$y}";
    }
    $points = implode(' ', $pts);
    return '<svg viewBox="0 0 ' . $w . ' ' . $h . '" class="h-6 w-16 overflow-visible"><polyline points="' . $points . '" fill="none" stroke="' . e($color) . '" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>';
}

// ==========================================================================
// LAYOUT
// ==========================================================================

function headFonts(): string
{
    return <<<HTML
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>
<script>tailwind.config = { theme: { extend: { fontFamily: { sans: ['Inter','system-ui','sans-serif'] } } } }</script>
<style>body{font-family:'Inter',system-ui,sans-serif}</style>
HTML;
}

function layoutPublic(string $title, string $bodyHtml, string $bgStyle = 'background:#F7F8FA'): string
{
    $fonts = headFonts();
    return <<<HTML
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{$title}</title>
{$fonts}
</head>
<body class="min-h-screen antialiased" style="{$bgStyle}">
{$bodyHtml}
</body>
</html>
HTML;
}

function toastAndModalScript(): string
{
    return <<<'JS'
function showToast(message, type) {
  type = type || 'success';
  var wrap = document.getElementById('toastWrap');
  if (!wrap) { return; }
  var colors = { success: '#16A34A', error: '#DC2626', info: '#5B4CF6' };
  var el = document.createElement('div');
  el.className = 'flex items-center gap-2 rounded-lg bg-[#111827] text-white text-sm px-4 py-3 shadow-lg';
  el.style.borderLeft = '3px solid ' + (colors[type] || colors.success);
  el.textContent = message;
  wrap.appendChild(el);
  setTimeout(function () { el.style.opacity = '0'; el.style.transition = 'opacity .3s'; }, 2500);
  setTimeout(function () { el.remove(); }, 2900);
}

function openConfirm(opts) {
  var modal = document.getElementById('confirmModal');
  document.getElementById('confirmTitle').textContent = opts.title;
  document.getElementById('confirmBody').textContent = opts.body;
  var okBtn = document.getElementById('confirmOkBtn');
  okBtn.textContent = opts.okLabel || 'Xoá';
  okBtn.className = 'rounded-lg px-4 py-2 text-sm font-semibold text-white ' + (opts.danger === false ? 'bg-[#5B4CF6] hover:opacity-90' : 'bg-[#DC2626] hover:opacity-90');
  var newOk = okBtn.cloneNode(true);
  okBtn.parentNode.replaceChild(newOk, okBtn);
  newOk.addEventListener('click', function () {
    modal.classList.add('hidden');
    modal.classList.remove('flex');
    opts.onConfirm();
  });
  modal.classList.remove('hidden');
  modal.classList.add('flex');
}
document.addEventListener('DOMContentLoaded', function () {
  var cancelBtn = document.getElementById('confirmCancelBtn');
  if (cancelBtn) {
    cancelBtn.addEventListener('click', function () {
      var modal = document.getElementById('confirmModal');
      modal.classList.add('hidden');
      modal.classList.remove('flex');
    });
  }
  var menuBtn = document.getElementById('mobileMenuBtn');
  var sidebar = document.getElementById('sidebar');
  var overlay = document.getElementById('sidebarOverlay');
  if (menuBtn && sidebar && overlay) {
    menuBtn.addEventListener('click', function () {
      sidebar.classList.remove('-translate-x-full');
      overlay.classList.remove('hidden');
    });
    overlay.addEventListener('click', function () {
      sidebar.classList.add('-translate-x-full');
      overlay.classList.add('hidden');
    });
  }
  var acctBtn = document.getElementById('acctMenuBtn');
  var acctMenu = document.getElementById('acctMenu');
  if (acctBtn && acctMenu) {
    acctBtn.addEventListener('click', function (ev) {
      ev.stopPropagation();
      acctMenu.classList.toggle('hidden');
    });
    document.addEventListener('click', function () { acctMenu.classList.add('hidden'); });
  }
});
JS;
}

function sharedModalsHtml(): string
{
    return <<<HTML
<div id="toastWrap" class="fixed bottom-4 right-4 z-[70] flex flex-col gap-2"></div>
<div id="confirmModal" class="fixed inset-0 z-[60] hidden items-center justify-center bg-black/40 px-4">
  <div class="w-full max-w-sm rounded-xl bg-white p-5" style="border:1px solid #E5E7EB">
    <h3 id="confirmTitle" class="text-sm font-bold" style="color:#111827"></h3>
    <p id="confirmBody" class="mt-2 text-sm" style="color:#64748B"></p>
    <div class="mt-5 flex justify-end gap-2">
      <button id="confirmCancelBtn" type="button" class="rounded-lg px-4 py-2 text-sm font-medium hover:bg-gray-100" style="color:#111827">Huỷ</button>
      <button id="confirmOkBtn" type="button" class="rounded-lg bg-[#DC2626] px-4 py-2 text-sm font-semibold text-white hover:opacity-90">Xoá</button>
    </div>
  </div>
</div>
HTML;
}

function layoutAdmin(string $title, string $active, string $bodyHtml, array $user): string
{
    $nav = [
        'dashboard'  => ['/admin', 'grid', 'Tổng quan'],
        'profile'    => ['/admin/profile', 'user', 'Hồ sơ cá nhân'],
        'links'      => ['/admin/links', 'link', 'Liên kết'],
        'leads'      => ['/admin/leads', 'users', 'Khách hàng (Leads)'],
        'statistics' => ['/admin/statistics', 'barChart', 'Thống kê'],
        'settings'   => ['/admin/settings', 'sliders', 'Cài đặt'],
    ];

    $navHtml = '';
    foreach ($nav as $key => [$href, $ic, $label]) {
        $isActive = $key === $active;
        $cls = $isActive ? 'text-white' : 'text-slate-400 hover:bg-white/5 hover:text-slate-200';
        $bg  = $isActive ? 'background:#272E41' : '';
        $navHtml .= '<a href="' . e($href) . '" style="' . $bg . '" class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition ' . $cls . '">' . icon($ic, 'h-[18px] w-[18px]') . '<span>' . e($label) . '</span></a>';
    }

    $email       = e($user['email']);
    $displayName = e($user['display_name']);
    $avatarPath  = $user['avatar_path'] ?? null;
    $initial     = e(mb_substr($user['display_name'] ?: 'A', 0, 1));

    $avatarSmall = $avatarPath
        ? '<img src="' . e($avatarPath) . '" class="h-9 w-9 rounded-full object-cover">'
        : '<span class="flex h-9 w-9 items-center justify-center rounded-full bg-[#5B4CF6] text-sm font-bold text-white">' . $initial . '</span>';

    $fonts = headFonts();
    $script = toastAndModalScript();
    $modals = sharedModalsHtml();

    $icLink     = icon('link', 'h-[18px] w-[18px] text-white');
    $icEye      = icon('eye', 'h-[18px] w-[18px]');
    $icLogout   = icon('logOut', 'h-[18px] w-[18px]');
    $icMenu     = icon('menu', 'h-5 w-5');
    $icSearch   = icon('search', 'h-4 w-4', 'none');
    $icBell     = icon('bell', 'h-5 w-5');
    $icChevron  = icon('chevronDown', 'h-4 w-4');
    $csrf       = e(csrfToken());

    return <<<HTML
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{$title}</title>
{$fonts}
</head>
<body class="min-h-screen antialiased" data-csrf="{$csrf}" style="background:#F7F8FA;color:#111827">
<div class="flex min-h-screen">

  <div id="sidebarOverlay" class="fixed inset-0 z-30 hidden bg-black/40 lg:hidden"></div>

  <aside id="sidebar" class="fixed inset-y-0 left-0 z-40 flex w-64 -translate-x-full flex-col px-3 py-5 transition-transform lg:static lg:translate-x-0" style="background:#0F172A">
    <a href="/admin" class="flex items-center gap-2 px-2 pb-6 text-[15px] font-bold text-white">
      <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-[#5B4CF6]">{$icLink}</span>
      VN BioLink Hub
    </a>
    <nav class="flex flex-1 flex-col gap-1">
      {$navHtml}
    </nav>
    <div class="mt-auto flex flex-col gap-1 border-t pt-3" style="border-color:rgba(255,255,255,.08)">
      <a href="/" target="_blank" class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium text-slate-400 hover:bg-white/5 hover:text-slate-200">
        {$icEye}<span>Xem trang public</span>
      </a>
      <div class="flex items-center gap-2 px-3 py-2">
        {$avatarSmall}
        <div class="min-w-0">
          <p class="truncate text-sm font-medium text-white">{$displayName}</p>
          <p class="truncate text-xs text-slate-500">{$email}</p>
        </div>
      </div>
      <a href="/logout" class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium text-red-400 hover:bg-red-500/10">
        {$icLogout}<span>Đăng xuất</span>
      </a>
    </div>
  </aside>

  <div class="flex min-w-0 flex-1 flex-col">
    <header class="flex h-14 shrink-0 items-center justify-between gap-3 border-b bg-white px-4 sm:px-6" style="border-color:#E5E7EB">
      <button id="mobileMenuBtn" type="button" class="rounded-lg p-1.5 hover:bg-gray-100 lg:hidden" style="color:#111827">{$icMenu}</button>
      <div class="hidden flex-1 max-w-xs items-center gap-2 rounded-lg px-3 py-1.5 sm:flex" style="background:#F7F8FA;border:1px solid #E5E7EB">
        {$icSearch}<input type="text" placeholder="Tìm kiếm..." class="w-full bg-transparent text-sm outline-none" style="color:#111827">
      </div>
      <div class="ml-auto flex items-center gap-3">
        <button type="button" class="rounded-lg p-1.5 hover:bg-gray-100" style="color:#64748B">{$icBell}</button>
        <div class="relative">
          <button id="acctMenuBtn" type="button" class="flex items-center gap-2">
            {$avatarSmall}
            <span class="hidden text-sm font-medium sm:inline" style="color:#111827">{$displayName}</span>
            {$icChevron}
          </button>
          <div id="acctMenu" class="absolute right-0 z-20 mt-2 hidden w-44 rounded-lg bg-white py-1 shadow-lg" style="border:1px solid #E5E7EB">
            <a href="/admin/profile" class="block px-3 py-2 text-sm hover:bg-gray-50" style="color:#111827">Hồ sơ cá nhân</a>
            <a href="/admin/settings" class="block px-3 py-2 text-sm hover:bg-gray-50" style="color:#111827">Cài đặt</a>
            <a href="/logout" class="block px-3 py-2 text-sm text-red-600 hover:bg-red-50">Đăng xuất</a>
          </div>
        </div>
      </div>
    </header>
    <main class="mx-auto w-full max-w-[1400px] flex-1 px-4 py-6 sm:px-6">
      {$bodyHtml}
    </main>
  </div>
</div>
{$modals}
<script>{$script}</script>
</body>
</html>
HTML;
}

function pageHeader(string $title, string $subtitle, string $actionHtml = ''): string
{
    return <<<HTML
<div class="mb-6 flex flex-wrap items-start justify-between gap-3">
  <div>
    <h1 class="text-xl font-bold" style="color:#111827">{$title}</h1>
    <p class="mt-1 text-sm" style="color:#64748B">{$subtitle}</p>
  </div>
  <div class="flex items-center gap-2">{$actionHtml}</div>
</div>
HTML;
}

function emptyState(string $iconName, string $title, string $body, string $ctaHtml = ''): string
{
    $ic = icon($iconName, 'h-10 w-10', 'none');
    return <<<HTML
<div class="flex flex-col items-center justify-center rounded-xl py-16 text-center" style="border:1px dashed #E5E7EB">
  <div style="color:#94A3B8">{$ic}</div>
  <p class="mt-4 text-sm font-semibold" style="color:#111827">{$title}</p>
  <p class="mt-1 max-w-xs text-sm" style="color:#64748B">{$body}</p>
  {$ctaHtml}
</div>
HTML;
}

function statusBadge(string $status): string
{
    $def = LEAD_STATUS[$status] ?? LEAD_STATUS['new'];
    return '<span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium ' . $def['class'] . '">' . e($def['label']) . '</span>';
}

// ==========================================================================
// VIEW: PUBLIC BIO PAGE
// ==========================================================================

function viewBio(array $user, array $links): string
{
    $name     = e($user['display_name']);
    $jobTitle = e($user['job_title'] ?? '');
    $bio      = nl2br(e($user['bio_text'] ?? ''));
    $color    = validThemeColor($user['theme_color']);
    $avatar   = $user['avatar_path'] ?? null;

    $avatarHtml = $avatar
        ? '<img src="' . e($avatar) . '" alt="' . $name . '" class="h-24 w-24 rounded-full object-cover" style="border:3px solid white;box-shadow:0 0 0 1px #E5E7EB">'
        : '<div class="flex h-24 w-24 items-center justify-center rounded-full text-3xl font-bold text-white" style="background:' . e($color) . '">' . e(mb_substr($user['display_name'] ?: 'B', 0, 1)) . '</div>';

    $verifiedBadge = '<span class="inline-flex h-[18px] w-[18px] items-center justify-center rounded-full bg-sky-500 align-middle">' . icon('check', 'h-2.5 w-2.5', 'none') . '</span>';
    $verifiedBadge = str_replace('stroke="currentColor" stroke-width="1.8"', 'stroke="white" stroke-width="3"', $verifiedBadge);

    $quickButtons = '';
    if (!empty($user['hotline_phone'])) {
        $quickButtons .= '<a href="tel:' . e(normalizePhoneVn($user['hotline_phone'])) . '" class="flex flex-1 items-center justify-center gap-2 rounded-lg px-4 py-2.5 text-sm font-semibold text-white transition hover:opacity-90" style="background:#16A34A">' . icon('phone', 'h-4 w-4', 'none') . '<span>Gọi Hotline</span></a>';
    }
    if (!empty($user['zalo_phone'])) {
        $quickButtons .= '<a href="' . e(zaloUrl($user['zalo_phone'])) . '" target="_blank" rel="noopener" class="flex flex-1 items-center justify-center gap-2 rounded-lg px-4 py-2.5 text-sm font-semibold text-white transition hover:opacity-90" style="background:#0068FF">' . icon('messageCircle', 'h-4 w-4', 'none') . '<span>Nhắn Zalo</span></a>';
    }

    $linksHtml = '';
    foreach ($links as $link) {
        $def   = LINK_TYPES[$link['type']] ?? LINK_TYPES['custom'];
        $ic    = icon($def['icon'], 'h-4 w-4', $def['icon'] === 'facebook' || $def['icon'] === 'youtube' ? 'currentColor' : 'none');
        $href  = e(linkHref($link['type'], $link['url']));
        $id    = (int) $link['id'];
        $label = e($link['label']);
        $linksHtml .= <<<HTML
<a href="{$href}" target="_blank" rel="noopener" data-link-id="{$id}" class="biolink-item group flex items-center gap-3 rounded-lg bg-white px-4 transition hover:-translate-y-0.5" style="height:54px;border:1px solid #E5E7EB">
  <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full" style="background:{$def['color']}1a;color:{$def['color']}">{$ic}</span>
  <span class="flex-1 truncate text-sm font-medium" style="color:#111827">{$label}</span>
  <span class="text-slate-300 transition group-hover:translate-x-0.5 group-hover:text-slate-500">
HTML;
        $linksHtml .= icon('chevronRight', 'h-4 w-4') . '</span></a>';
    }
    if ($links === []) {
        $linksHtml = '<p class="text-center text-sm" style="color:#94A3B8">Chưa có liên kết nào.</p>';
    }

    $csrf = csrfToken();
    $year = date('Y');
    $tint = $color . '0d';
    $icCheckBig = icon('check', 'h-5 w-5', 'none');

    $body = <<<HTML
<div class="min-h-screen" style="background:linear-gradient(180deg,{$tint},#F7F8FA 320px)">
  <div class="mx-auto flex w-full flex-col items-center px-5 pt-14" style="max-width:480px">
    {$avatarHtml}
    <h1 class="mt-4 flex items-center gap-1.5 text-lg font-bold" style="color:#111827">{$name} {$verifiedBadge}</h1>
    <p class="mt-1 text-sm font-medium" style="color:{$color}">{$jobTitle}</p>
    <p class="mt-2 max-w-xs text-center text-sm leading-relaxed" style="color:#64748B">{$bio}</p>

    <div class="mt-5 flex w-full gap-2">
      {$quickButtons}
    </div>

    <div class="mt-6 flex w-full flex-col gap-2.5">
      {$linksHtml}
    </div>

    <div class="mt-8 w-full rounded-xl bg-white p-5" style="border:1px solid #E5E7EB">
      <h2 class="text-sm font-bold" style="color:#111827">Đăng ký tư vấn miễn phí</h2>
      <form id="leadForm" class="mt-4 flex flex-col gap-3">
        <input type="hidden" name="csrf" value="{$csrf}">
        <input required name="name" placeholder="Họ và tên *" class="rounded-lg px-3 py-2.5 text-sm outline-none focus:border-[#5B4CF6]" style="border:1px solid #E5E7EB;color:#111827">
        <input required name="phone" placeholder="Số điện thoại *" class="rounded-lg px-3 py-2.5 text-sm outline-none focus:border-[#5B4CF6]" style="border:1px solid #E5E7EB;color:#111827">
        <textarea name="note" placeholder="Ghi chú (không bắt buộc)" rows="2" class="rounded-lg px-3 py-2.5 text-sm outline-none focus:border-[#5B4CF6]" style="border:1px solid #E5E7EB;color:#111827"></textarea>
        <button type="submit" id="leadSubmitBtn" class="rounded-lg px-4 py-2.5 text-sm font-semibold text-white transition hover:opacity-90" style="background:{$color}">Gửi thông tin</button>
      </form>
      <div id="leadSuccess" class="hidden flex-col items-center py-4 text-center">
        <span class="flex h-10 w-10 items-center justify-center rounded-full bg-emerald-50 text-emerald-600">{$icCheckBig}</span>
        <p class="mt-3 text-sm font-medium" style="color:#111827">Cảm ơn bạn! Thông tin đã được gửi thành công.</p>
      </div>
      <p id="leadError" class="mt-2 text-center text-xs text-red-600"></p>
    </div>

    <p class="mt-8 pb-10 text-[11px]" style="color:#94A3B8">© {$year} VN BioLink Hub</p>
  </div>
</div>

<script>
document.querySelectorAll('.biolink-item').forEach(function (el) {
  el.addEventListener('click', function () {
    var id = el.getAttribute('data-link-id');
    fetch('/api/click', { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify({id:id}), keepalive:true }).catch(function(){});
  });
});
var leadForm = document.getElementById('leadForm');
leadForm.addEventListener('submit', async function (ev) {
  ev.preventDefault();
  var form = ev.currentTarget;
  var btn = document.getElementById('leadSubmitBtn');
  var errEl = document.getElementById('leadError');
  btn.disabled = true;
  btn.textContent = 'Đang gửi...';
  errEl.textContent = '';
  try {
    var res = await fetch('/api/lead', {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify({ name: form.name.value, phone: form.phone.value, note: form.note.value, csrf: form.csrf.value }),
    });
    var data = await res.json();
    if (data.ok) {
      form.classList.add('hidden');
      document.getElementById('leadSuccess').classList.remove('hidden');
      document.getElementById('leadSuccess').classList.add('flex');
    } else {
      errEl.textContent = data.error || 'Có lỗi xảy ra, vui lòng thử lại.';
      btn.disabled = false;
      btn.textContent = 'Gửi thông tin';
    }
  } catch (e) {
    errEl.textContent = 'Không kết nối được máy chủ.';
    btn.disabled = false;
    btn.textContent = 'Gửi thông tin';
  }
});
</script>
HTML;

    return layoutPublic($name . ' — VN BioLink Hub', $body);
}

// ==========================================================================
// VIEW: LOGIN
// ==========================================================================

function viewLogin(?string $error): string
{
    $csrf = csrfToken();
    $errorHtml = $error ? '<div class="mt-4 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">' . e($error) . '</div>' : '';
    $year = date('Y');
    $icLink = icon('link', 'h-6 w-6 text-white', 'none');
    $icEyeOff = icon('eyeOff', 'h-4 w-4');
    $icEye2 = icon('eye', 'h-4 w-4');

    $body = <<<HTML
<div class="flex min-h-screen items-center justify-center px-4">
  <div class="w-full max-w-sm rounded-xl bg-white p-8" style="border:1px solid #E5E7EB">
    <div class="flex flex-col items-center text-center">
      <span class="flex h-12 w-12 items-center justify-center rounded-xl" style="background:#5B4CF6">{$icLink}</span>
      <h1 class="mt-3 text-lg font-bold" style="color:#111827">VN BioLink Hub</h1>
      <p class="mt-1 text-sm" style="color:#64748B">Đăng nhập để tiếp tục quản trị hệ thống</p>
    </div>
    {$errorHtml}
    <form method="post" action="/login" class="mt-6 flex flex-col gap-4">
      <input type="hidden" name="csrf" value="{$csrf}">
      <div>
        <label class="text-xs font-medium" style="color:#64748B">Email</label>
        <input required type="email" name="email" autocomplete="username" placeholder="Nhập email của bạn"
               class="mt-1 w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-[#5B4CF6]" style="border:1px solid #E5E7EB;color:#111827">
      </div>
      <div>
        <label class="text-xs font-medium" style="color:#64748B">Mật khẩu</label>
        <div class="relative mt-1">
          <input required type="password" id="passwordInput" name="password" autocomplete="current-password" placeholder="Nhập mật khẩu"
                 class="w-full rounded-lg px-3 py-2.5 pr-10 text-sm outline-none focus:border-[#5B4CF6]" style="border:1px solid #E5E7EB;color:#111827">
          <button type="button" id="togglePassword" class="absolute right-3 top-1/2 -translate-y-1/2" style="color:#94A3B8">
            <span id="eyeOnIcon">{$icEye2}</span>
            <span id="eyeOffIcon" class="hidden">{$icEyeOff}</span>
          </button>
        </div>
      </div>
      <label class="flex items-center gap-2 text-sm" style="color:#64748B">
        <input type="checkbox" name="remember" value="1" class="rounded" style="border-color:#E5E7EB">
        Ghi nhớ đăng nhập
      </label>
      <button type="submit" class="mt-1 rounded-lg px-4 py-2.5 text-sm font-semibold text-white transition hover:opacity-90" style="background:#5B4CF6">Đăng nhập</button>
    </form>
    <p class="mt-6 text-center text-xs" style="color:#94A3B8">© {$year} VN BioLink Hub</p>
  </div>
</div>
<script>
document.getElementById('togglePassword').addEventListener('click', function () {
  var input = document.getElementById('passwordInput');
  var isHidden = input.type === 'password';
  input.type = isHidden ? 'text' : 'password';
  document.getElementById('eyeOnIcon').classList.toggle('hidden', isHidden);
  document.getElementById('eyeOffIcon').classList.toggle('hidden', !isHidden);
});
</script>
HTML;

    return layoutPublic('Đăng nhập — VN BioLink Hub', $body);
}

// ==========================================================================
// VIEW: DASHBOARD (Tổng quan)
// ==========================================================================

function kpiCard(string $iconName, string $label, string $value, array $sparkValues, ?array $change = null, ?string $subtext = null): string
{
    $ic = icon($iconName, 'h-4 w-4', 'none');
    $spark = sparkline($sparkValues, '#5B4CF6');
    if ($subtext !== null) {
        $sub = '<p class="mt-1 text-xs" style="color:#64748B">' . e($subtext) . '</p>';
    } else {
        $color = ($change['positive'] ?? true) ? '#16A34A' : '#DC2626';
        $sub = '<p class="mt-1 text-xs font-medium" style="color:' . $color . '">' . e($change['label'] ?? '') . ' so với kỳ trước</p>';
    }
    return <<<HTML
<div class="rounded-xl bg-white p-4" style="border:1px solid #E5E7EB">
  <div class="flex items-start justify-between">
    <span class="flex h-8 w-8 items-center justify-center rounded-lg" style="background:#5B4CF60d;color:#5B4CF6">{$ic}</span>
    {$spark}
  </div>
  <p class="mt-3 text-2xl font-bold" style="color:#111827">{$value}</p>
  <p class="text-xs" style="color:#64748B">{$label}</p>
  {$sub}
</div>
HTML;
}

function viewDashboard(array $user, array $stats): string
{
    $cards = kpiCard('barChart', 'Tổng lượt click', number_format($stats['totalClicks']), $stats['sparkClicks'], $stats['clicksChange'])
        . kpiCard('users', 'Leads', number_format($stats['totalLeads']), $stats['sparkLeads'], $stats['leadsChange'])
        . kpiCard('link', 'Liên kết', number_format($stats['totalLinksAll']), $stats['sparkLinks'], null, $stats['totalLinksActive'] . ' đang hoạt động')
        . kpiCard('grid', 'Click hôm nay', number_format($stats['clicksToday']), $stats['sparkToday'], $stats['todayChange']);

    $leadsHtml = '';
    foreach ($stats['leadsRecent'] as $lead) {
        $initial = e(mb_substr($lead['name'], 0, 1));
        $leadsHtml .= <<<HTML
<a href="/admin/leads/{$lead['id']}" class="flex items-center gap-3 rounded-lg px-2 py-2 hover:bg-gray-50">
  <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-xs font-bold text-white" style="background:#5B4CF6">{$initial}</span>
  <div class="min-w-0 flex-1">
    <p class="truncate text-sm font-medium" style="color:#111827">{$lead['name']}</p>
    <p class="text-xs" style="color:#64748B">{$lead['phone']}</p>
  </div>
  <span class="shrink-0 text-xs" style="color:#94A3B8">{$lead['time']}</span>
</a>
HTML;
    }
    if ($stats['leadsRecent'] === []) {
        $leadsHtml = '<p class="px-2 py-6 text-center text-sm" style="color:#94A3B8">Chưa có lead nào.</p>';
    }

    $perfRows = '';
    foreach ($stats['linkPerf'] as $lp) {
        $perfRows .= <<<HTML
<tr class="border-t" style="border-color:#F1F5F9">
  <td class="py-2.5 pr-3 text-sm font-medium" style="color:#111827">{$lp['label']}</td>
  <td class="hidden py-2.5 pr-3 text-sm sm:table-cell" style="color:#64748B">{$lp['url']}</td>
  <td class="py-2.5 pr-3 text-right text-sm" style="color:#111827">{$lp['clicks']}</td>
  <td class="w-32 py-2.5">
    <div class="flex items-center gap-2">
      <div class="h-1 flex-1 overflow-hidden rounded-full" style="background:#F1F5F9">
        <div class="h-1 rounded-full" style="width:{$lp['pct']}%;background:#5B4CF6"></div>
      </div>
      <span class="w-9 text-right text-xs" style="color:#64748B">{$lp['pct']}%</span>
    </div>
  </td>
</tr>
HTML;
    }
    if ($stats['linkPerf'] === []) {
        $perfRows = '<tr><td colspan="4" class="py-8 text-center text-sm" style="color:#94A3B8">Chưa có liên kết nào.</td></tr>';
    }

    $chartLabelsJson = json_encode($stats['chart90Labels'], JSON_UNESCAPED_UNICODE);
    $chartDataJson   = json_encode($stats['chart90Data']);

    $icPlus = icon('plus', 'h-4 w-4', 'none');
    $icUser = icon('user', 'h-4 w-4', 'none');
    $icUsers = icon('users', 'h-4 w-4', 'none');
    $icEye = icon('eye', 'h-4 w-4', 'none');

    $actionHtml = '<a href="/" target="_blank" class="hidden items-center gap-1.5 rounded-lg px-3 py-2 text-sm font-medium sm:inline-flex" style="border:1px solid #E5E7EB;color:#111827">' . $icEye . '<span>Xem trang public</span></a>';

    $body = <<<HTML
<div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
  {$cards}
</div>

<div class="mt-5 grid grid-cols-1 gap-4 xl:grid-cols-[1fr_340px]">
  <div class="rounded-xl bg-white p-5" style="border:1px solid #E5E7EB">
    <div class="flex items-center justify-between">
      <h2 class="text-sm font-bold" style="color:#111827">Lượt click</h2>
      <div class="flex rounded-lg p-0.5 text-xs" style="background:#F7F8FA">
        <button type="button" class="rangeBtn rounded-md px-2.5 py-1 font-medium" data-range="7" style="background:white;color:#111827;box-shadow:0 1px 2px rgba(0,0,0,.06)">7 ngày</button>
        <button type="button" class="rangeBtn rounded-md px-2.5 py-1 font-medium" data-range="30" style="color:#64748B">30 ngày</button>
        <button type="button" class="rangeBtn rounded-md px-2.5 py-1 font-medium" data-range="90" style="color:#64748B">90 ngày</button>
      </div>
    </div>
    <div class="mt-4" style="height:260px"><canvas id="clicksChart"></canvas></div>
  </div>
  <div class="rounded-xl bg-white p-5" style="border:1px solid #E5E7EB">
    <div class="flex items-center justify-between">
      <h2 class="text-sm font-bold" style="color:#111827">Leads mới nhất</h2>
      <a href="/admin/leads" class="text-xs font-medium" style="color:#5B4CF6">Xem tất cả →</a>
    </div>
    <div class="mt-3 flex flex-col">{$leadsHtml}</div>
  </div>
</div>

<div class="mt-5 rounded-xl bg-white p-5" style="border:1px solid #E5E7EB">
  <h2 class="text-sm font-bold" style="color:#111827">Hiệu quả liên kết</h2>
  <div class="mt-3 overflow-x-auto">
    <table class="w-full">
      <thead>
        <tr class="text-left text-xs" style="color:#94A3B8">
          <th class="pb-2 pr-3 font-medium">Liên kết</th>
          <th class="hidden pb-2 pr-3 font-medium sm:table-cell">URL</th>
          <th class="pb-2 pr-3 text-right font-medium">Click</th>
          <th class="pb-2 font-medium">Tỷ lệ</th>
        </tr>
      </thead>
      <tbody>{$perfRows}</tbody>
    </table>
  </div>
</div>

<div class="mt-5 flex flex-wrap gap-2">
  <a href="/admin/links/create" class="inline-flex items-center gap-1.5 rounded-lg px-3 py-2 text-sm font-medium" style="border:1px solid #E5E7EB;color:#111827">{$icPlus}Thêm liên kết</a>
  <a href="/admin/profile" class="inline-flex items-center gap-1.5 rounded-lg px-3 py-2 text-sm font-medium" style="border:1px solid #E5E7EB;color:#111827">{$icUser}Chỉnh sửa hồ sơ</a>
  <a href="/admin/leads" class="inline-flex items-center gap-1.5 rounded-lg px-3 py-2 text-sm font-medium" style="border:1px solid #E5E7EB;color:#111827">{$icUsers}Xem Leads</a>
  <a href="/" target="_blank" class="inline-flex items-center gap-1.5 rounded-lg px-3 py-2 text-sm font-medium" style="border:1px solid #E5E7EB;color:#111827">{$icEye}Xem trang public</a>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script>
var ALL_LABELS = {$chartLabelsJson};
var ALL_DATA = {$chartDataJson};
function slice(n) { return { labels: ALL_LABELS.slice(-n), data: ALL_DATA.slice(-n) }; }
var d7 = slice(7);
var chart = new Chart(document.getElementById('clicksChart').getContext('2d'), {
  type: 'line',
  data: { labels: d7.labels, datasets: [{ label: 'Lượt click', data: d7.data, borderColor: '#5B4CF6', backgroundColor: 'rgba(91,76,246,0.08)', tension: 0.3, fill: true, pointRadius: 2 }] },
  options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } }, x: { grid: { display: false } } } },
});
document.querySelectorAll('.rangeBtn').forEach(function (btn) {
  btn.addEventListener('click', function () {
    document.querySelectorAll('.rangeBtn').forEach(function (b) { b.style.background = 'transparent'; b.style.color = '#64748B'; b.style.boxShadow = 'none'; });
    btn.style.background = 'white'; btn.style.color = '#111827'; btn.style.boxShadow = '0 1px 2px rgba(0,0,0,.06)';
    var d = slice(parseInt(btn.dataset.range, 10));
    chart.data.labels = d.labels;
    chart.data.datasets[0].data = d.data;
    chart.update();
  });
});
</script>
HTML;

    $header = pageHeader('Tổng quan', 'Theo dõi hiệu quả trang Bio Link và khách hàng tiềm năng.', $actionHtml);
    return layoutAdmin('Tổng quan — Admin', 'dashboard', $header . $body, $user);
}

// ==========================================================================
// VIEW: HỒ SƠ CÁ NHÂN (với live preview)
// ==========================================================================

function viewProfile(array $user, bool $saved): string
{
    $csrf = csrfToken();
    $savedHtml = $saved ? '<div class="mb-4 rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-700">Đã lưu thay đổi.</div>' : '';

    $displayName  = e($user['display_name']);
    $jobTitle     = e($user['job_title'] ?? '');
    $email        = e($user['email']);
    $bioText      = e($user['bio_text']);
    $hotlinePhone = e($user['hotline_phone']);
    $zaloPhone    = e($user['zalo_phone']);
    $themeColor   = e(validThemeColor($user['theme_color']));
    $avatarPath   = $user['avatar_path'] ?? null;
    $initial      = e(mb_substr($user['display_name'] ?: 'A', 0, 1));

    $icCamera = icon('camera', 'h-4 w-4', 'none');

    $avatarPreviewImg = $avatarPath
        ? '<img id="avatarPreviewImg" src="' . e($avatarPath) . '" class="h-full w-full object-cover">'
        : '<span id="avatarPreviewInitial" class="text-3xl font-bold text-white">' . $initial . '</span>';

    $body = <<<HTML
{$savedHtml}
<div class="grid grid-cols-1 gap-5 lg:grid-cols-[280px_1fr]">

  <div class="flex flex-col gap-5">
    <div class="rounded-xl bg-white p-5 text-center" style="border:1px solid #E5E7EB">
      <div id="avatarPreviewWrap" class="mx-auto flex items-center justify-center overflow-hidden rounded-full" style="width:120px;height:120px;background:{$themeColor}">
        {$avatarPreviewImg}
      </div>
      <label for="avatarInput" class="mt-4 inline-flex cursor-pointer items-center gap-1.5 rounded-lg px-3 py-2 text-sm font-medium" style="border:1px solid #E5E7EB;color:#111827">
        {$icCamera}<span>Đổi ảnh</span>
      </label>
      <input id="avatarInput" type="file" name="avatar" accept="image/png,image/jpeg,image/webp" class="hidden" form="profileForm">
      <p class="mt-2 text-xs" style="color:#94A3B8">JPG, PNG · tối đa 2MB</p>
    </div>

    <div class="rounded-xl p-4" style="background:#F7F8FA;border:1px solid #E5E7EB">
      <p class="mb-3 text-xs font-semibold uppercase tracking-wide" style="color:#94A3B8">Xem trước Bio Link</p>
      <div class="rounded-lg bg-white p-4 text-center" style="border:1px solid #E5E7EB">
        <div id="pvAvatarWrap" class="mx-auto flex h-14 w-14 items-center justify-center overflow-hidden rounded-full" style="background:{$themeColor}">
          <span id="pvAvatarInitial" class="text-lg font-bold text-white">{$initial}</span>
          <img id="pvAvatarImg" class="hidden h-full w-full object-cover" src="">
        </div>
        <p id="pvName" class="mt-2 text-sm font-bold" style="color:#111827">{$displayName}</p>
        <p id="pvJobTitle" class="text-xs font-medium" style="color:{$themeColor}">{$jobTitle}</p>
        <p id="pvBio" class="mt-1 text-[11px] leading-relaxed" style="color:#64748B">{$bioText}</p>
      </div>
    </div>
  </div>

  <form id="profileForm" method="post" action="/admin/profile" enctype="multipart/form-data" class="flex flex-col gap-4 rounded-xl bg-white p-5" style="border:1px solid #E5E7EB">
    <input type="hidden" name="csrf" value="{$csrf}">

    <div>
      <label class="text-xs font-medium" style="color:#64748B">Họ và tên *</label>
      <input required id="fDisplayName" name="display_name" value="{$displayName}" class="mt-1 w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-[#5B4CF6]" style="border:1px solid #E5E7EB;color:#111827">
    </div>

    <div>
      <label class="text-xs font-medium" style="color:#64748B">Chức danh</label>
      <input id="fJobTitle" name="job_title" value="{$jobTitle}" placeholder="VD: Chuyên viên Marketing" class="mt-1 w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-[#5B4CF6]" style="border:1px solid #E5E7EB;color:#111827">
    </div>

    <div>
      <label class="text-xs font-medium" style="color:#64748B">Email</label>
      <input value="{$email}" disabled class="mt-1 w-full rounded-lg px-3 py-2.5 text-sm" style="border:1px solid #E5E7EB;color:#94A3B8;background:#F7F8FA">
    </div>

    <div>
      <label class="text-xs font-medium" style="color:#64748B">Giới thiệu</label>
      <textarea id="fBio" name="bio_text" rows="3" class="mt-1 w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-[#5B4CF6]" style="border:1px solid #E5E7EB;color:#111827">{$bioText}</textarea>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
      <div>
        <label class="text-xs font-medium" style="color:#64748B">Số điện thoại</label>
        <input name="hotline_phone" value="{$hotlinePhone}" placeholder="09xxxxxxxx" class="mt-1 w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-[#5B4CF6]" style="border:1px solid #E5E7EB;color:#111827">
      </div>
      <div>
        <label class="text-xs font-medium" style="color:#64748B">Zalo</label>
        <input name="zalo_phone" value="{$zaloPhone}" placeholder="09xxxxxxxx" class="mt-1 w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-[#5B4CF6]" style="border:1px solid #E5E7EB;color:#111827">
      </div>
    </div>

    <div>
      <label class="text-xs font-medium" style="color:#64748B">Theme Color</label>
      <div class="mt-1 flex items-center gap-2">
        <input id="fColor" type="color" name="theme_color" value="{$themeColor}" class="h-9 w-14 cursor-pointer rounded-lg" style="border:1px solid #E5E7EB">
        <input id="fColorText" type="text" value="{$themeColor}" class="w-28 rounded-lg px-3 py-2 text-sm font-mono outline-none focus:border-[#5B4CF6]" style="border:1px solid #E5E7EB;color:#111827">
      </div>
    </div>

    <button type="submit" class="mt-2 self-start rounded-lg px-5 py-2.5 text-sm font-semibold text-white transition hover:opacity-90" style="background:#5B4CF6">Lưu thay đổi</button>
  </form>
</div>

<script>
document.getElementById('fDisplayName').addEventListener('input', function () {
  document.getElementById('pvName').textContent = this.value || 'Chưa có tên';
});
document.getElementById('fJobTitle').addEventListener('input', function () {
  document.getElementById('pvJobTitle').textContent = this.value;
});
document.getElementById('fBio').addEventListener('input', function () {
  document.getElementById('pvBio').textContent = this.value;
});
function applyColor(hex) {
  document.getElementById('avatarPreviewWrap').style.background = hex;
  document.getElementById('pvAvatarWrap').style.background = hex;
  document.getElementById('pvJobTitle').style.color = hex;
}
document.getElementById('fColor').addEventListener('input', function () {
  document.getElementById('fColorText').value = this.value;
  applyColor(this.value);
});
document.getElementById('fColorText').addEventListener('input', function () {
  if (/^#[0-9a-fA-F]{6}\$/.test(this.value)) {
    document.getElementById('fColor').value = this.value;
    applyColor(this.value);
  }
});
document.getElementById('avatarInput').addEventListener('change', function () {
  var label = document.querySelector('label[for="avatarInput"] span:last-child');
  if (this.files && this.files[0]) {
    label.textContent = this.files[0].name;
    var reader = new FileReader();
    reader.onload = function (e) {
      var wrap = document.getElementById('avatarPreviewWrap');
      wrap.innerHTML = '<img src="' + e.target.result + '" class="h-full w-full object-cover">';
      var pvImg = document.getElementById('pvAvatarImg');
      var pvInit = document.getElementById('pvAvatarInitial');
      pvImg.src = e.target.result;
      pvImg.classList.remove('hidden');
      pvInit.classList.add('hidden');
    };
    reader.readAsDataURL(this.files[0]);
  }
});
</script>
HTML;

    $header = pageHeader('Hồ sơ cá nhân', 'Cập nhật thông tin hiển thị trên trang Bio Link của bạn.');
    return layoutAdmin('Hồ sơ cá nhân — Admin', 'profile', $header . $body, $user);
}

// ==========================================================================
// VIEW: LIÊN KẾT (list kéo-thả + search + filter)
// ==========================================================================

function viewLinksList(array $links, array $user): string
{
    $csrf = csrfToken();
    $icPlus = icon('plus', 'h-4 w-4', 'none');
    $icGrip = icon('grip', 'h-4 w-4', 'none');
    $icPencil = icon('pencil', 'h-3.5 w-3.5', 'none');
    $icTrash = icon('trash', 'h-3.5 w-3.5', 'none');

    $rows = '';
    foreach ($links as $link) {
        $def = LINK_TYPES[$link['type']] ?? LINK_TYPES['custom'];
        $ic  = icon($def['icon'], 'h-4 w-4', in_array($def['icon'], ['facebook', 'youtube', 'instagram'], true) ? 'currentColor' : 'none');
        $id  = (int) $link['id'];
        $label = e($link['label']);
        $url   = e($link['url']);
        $clicks = (int) $link['clicks'];
        $active = (int) $link['is_active'] === 1;
        $activeAttr = $active ? '1' : '0';
        $toggleBg   = $active ? '#5B4CF6' : '#E5E7EB';
        $toggleLeft = $active ? '18px' : '2px';
        $itemColor  = $def['color'];
        $rows .= <<<HTML
<div class="linkItem group flex items-center gap-3 rounded-lg bg-white px-3 py-3 transition hover:-translate-y-px" draggable="true" data-id="{$id}" data-active="{$activeAttr}" data-label="{$label}" style="border:1px solid #E5E7EB">
  <span class="cursor-grab shrink-0" style="color:#CBD5E1">{$icGrip}</span>
  <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full" style="background:{$itemColor}1a;color:{$itemColor}">{$ic}</span>
  <div class="min-w-0 flex-1">
    <p class="truncate text-sm font-medium" style="color:#111827">{$label}</p>
    <p class="truncate text-xs" style="color:#94A3B8">{$url}</p>
  </div>
  <span class="hidden shrink-0 text-xs sm:inline" style="color:#64748B">{$clicks} lượt click</span>
  <button type="button" class="toggleBtn relative h-5 w-9 shrink-0 rounded-full transition" data-id="{$id}" style="background:{$toggleBg}">
    <span class="absolute top-0.5 h-4 w-4 rounded-full bg-white transition" style="left:{$toggleLeft}"></span>
  </button>
  <a href="/admin/links/{$id}/edit" class="rounded-lg p-1.5 hover:bg-gray-100" style="color:#64748B">{$icPencil}</a>
  <button type="button" class="deleteLinkBtn rounded-lg p-1.5 hover:bg-red-50" style="color:#DC2626" data-id="{$id}" data-label="{$label}">{$icTrash}</button>
</div>
HTML;
    }

    $listHtml = $rows !== '' ? $rows : '';
    $emptyHtml = emptyState('link', 'Chưa có liên kết nào', 'Thêm liên kết đầu tiên để bắt đầu xây dựng trang Bio Link của bạn.',
        '<a href="/admin/links/create" class="mt-4 inline-flex items-center gap-1.5 rounded-lg px-4 py-2 text-sm font-semibold text-white" style="background:#5B4CF6">' . $icPlus . 'Thêm liên kết</a>');

    $icSearch = icon('search', 'h-4 w-4', 'none');
    $toolbar = <<<HTML
<div class="mb-4 flex flex-wrap items-center gap-3">
  <div class="flex flex-1 items-center gap-2 rounded-lg px-3 py-2" style="border:1px solid #E5E7EB;min-width:220px;color:#94A3B8">
    {$icSearch}<input id="linkSearch" type="text" placeholder="Tìm liên kết..." class="w-full bg-transparent text-sm outline-none" style="color:#111827">
  </div>
  <div class="flex rounded-lg p-0.5 text-xs" style="background:#F7F8FA">
    <button type="button" class="filterBtn rounded-md px-3 py-1.5 font-medium" data-filter="all" style="background:white;color:#111827;box-shadow:0 1px 2px rgba(0,0,0,.06)">Tất cả</button>
    <button type="button" class="filterBtn rounded-md px-3 py-1.5 font-medium" data-filter="on" style="color:#64748B">Đang hiển thị</button>
    <button type="button" class="filterBtn rounded-md px-3 py-1.5 font-medium" data-filter="off" style="color:#64748B">Đang ẩn</button>
  </div>
</div>
HTML;

    $listWrap = '<div id="linkList" class="flex flex-col gap-2">' . $listHtml . '</div>';
    if ($links === []) {
        $listWrap = $emptyHtml;
    }

    $script = <<<'JS'
function linksCsrf() { return document.body.dataset.csrf; }

document.querySelectorAll('.toggleBtn').forEach(function (btn) {
  btn.addEventListener('click', function () {
    var id = btn.dataset.id;
    fetch('/admin/links/' + id + '/toggle', { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify({csrf: linksCsrf()}) })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (data.ok) {
          var on = data.active;
          btn.style.background = on ? '#5B4CF6' : '#E5E7EB';
          btn.querySelector('span').style.left = on ? '18px' : '2px';
          btn.closest('.linkItem').dataset.active = on ? '1' : '0';
          showToast(on ? 'Đã hiện liên kết' : 'Đã ẩn liên kết');
        }
      });
  });
});

document.querySelectorAll('.deleteLinkBtn').forEach(function (btn) {
  btn.addEventListener('click', function () {
    var id = btn.dataset.id;
    var label = btn.dataset.label;
    openConfirm({
      title: 'Xoá liên kết?',
      body: 'Bạn có chắc muốn xoá "' + label + '"? Dữ liệu lượt click liên quan cũng sẽ bị ảnh hưởng.',
      okLabel: 'Xoá',
      onConfirm: function () {
        fetch('/admin/links/' + id + '/delete', { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify({csrf: linksCsrf()}) })
          .then(function (r) { return r.json(); })
          .then(function (data) {
            if (data.ok) {
              btn.closest('.linkItem').remove();
              showToast('Đã xoá liên kết');
            }
          });
      },
    });
  });
});

var searchInput = document.getElementById('linkSearch');
var activeFilter = 'all';
function applyFilters() {
  var q = (searchInput.value || '').toLowerCase();
  document.querySelectorAll('.linkItem').forEach(function (item) {
    var matchesSearch = item.dataset.label.toLowerCase().indexOf(q) !== -1;
    var matchesFilter = activeFilter === 'all' || (activeFilter === 'on' && item.dataset.active === '1') || (activeFilter === 'off' && item.dataset.active === '0');
    item.style.display = (matchesSearch && matchesFilter) ? 'flex' : 'none';
  });
}
if (searchInput) { searchInput.addEventListener('input', applyFilters); }
document.querySelectorAll('.filterBtn').forEach(function (btn) {
  btn.addEventListener('click', function () {
    document.querySelectorAll('.filterBtn').forEach(function (b) { b.style.background = 'transparent'; b.style.color = '#64748B'; b.style.boxShadow = 'none'; });
    btn.style.background = 'white'; btn.style.color = '#111827'; btn.style.boxShadow = '0 1px 2px rgba(0,0,0,.06)';
    activeFilter = btn.dataset.filter;
    applyFilters();
  });
});

var dragEl = null;
function getDragAfterElement(container, y) {
  var els = Array.prototype.slice.call(container.querySelectorAll('.linkItem:not(.dragging)'));
  return els.reduce(function (closest, child) {
    var box = child.getBoundingClientRect();
    var offset = y - box.top - box.height / 2;
    if (offset < 0 && offset > closest.offset) { return { offset: offset, element: child }; }
    return closest;
  }, { offset: Number.NEGATIVE_INFINITY }).element;
}
document.querySelectorAll('.linkItem').forEach(function (item) {
  item.addEventListener('dragstart', function () { dragEl = item; item.classList.add('dragging'); });
  item.addEventListener('dragend', function () {
    item.classList.remove('dragging');
    var ids = Array.prototype.map.call(document.querySelectorAll('.linkItem'), function (el) { return el.dataset.id; });
    fetch('/admin/links/reorder', { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify({csrf: linksCsrf(), order: ids}) })
      .then(function (r) { return r.json(); })
      .then(function (data) { if (data.ok) { showToast('Đã lưu thứ tự mới'); } });
  });
});
var listEl = document.getElementById('linkList');
if (listEl) {
  listEl.addEventListener('dragover', function (e) {
    e.preventDefault();
    var after = getDragAfterElement(listEl, e.clientY);
    if (!dragEl) { return; }
    if (after == null) { listEl.appendChild(dragEl); } else { listEl.insertBefore(dragEl, after); }
  });
}
JS;

    $bodyHtml = $toolbar . $listWrap . '<script>' . $script . '</script>';

    $header = pageHeader('Liên kết', 'Quản lý các liên kết hiển thị trên trang Bio Link.',
        '<a href="/admin/links/create" class="inline-flex items-center gap-1.5 rounded-lg px-4 py-2 text-sm font-semibold text-white" style="background:#5B4CF6">' . $icPlus . 'Thêm liên kết</a>');

    return layoutAdmin('Liên kết — Admin', 'links', $header . $bodyHtml, $user);
}

// ==========================================================================
// VIEW: THÊM / SỬA LIÊN KẾT (form + preview realtime)
// ==========================================================================

function viewLinkForm(?array $link, array $user, ?string $error): string
{
    $csrf = csrfToken();
    $isEdit = $link !== null;
    $type   = $isEdit ? $link['type'] : 'tiktok';
    $label  = $isEdit ? e($link['label']) : '';
    $url    = $isEdit ? e($link['url']) : '';
    $color  = $isEdit ? e($link['color'] ?: LINK_TYPES[$type]['color']) : e(LINK_TYPES[$type]['color']);
    $openNewTab = $isEdit ? (int) $link['open_new_tab'] === 1 : true;
    $active = $isEdit ? (int) $link['is_active'] === 1 : true;

    $options = '';
    foreach (LINK_TYPES as $key => $def) {
        $sel = $key === $type ? 'selected' : '';
        $options .= '<option value="' . e($key) . '" data-color="' . e($def['color']) . '" ' . $sel . '>' . e($def['label']) . '</option>';
    }

    $errorHtml = $error ? '<div class="mb-4 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">' . e($error) . '</div>' : '';
    $action = $isEdit ? '/admin/links/' . (int) $link['id'] . '/update' : '/admin/links';
    $icArrowLeft = icon('arrowLeft', 'h-4 w-4', 'none');
    $icPreviewIcon = icon(LINK_TYPES[$type]['icon'], 'h-4 w-4', in_array(LINK_TYPES[$type]['icon'], ['facebook', 'youtube', 'instagram'], true) ? 'currentColor' : 'none');
    $icChevronRight = icon('chevronRight', 'h-4 w-4');

    $iconsJsonMap = [];
    foreach (LINK_TYPES as $key => $def) {
        $iconsJsonMap[$key] = ICONS[$def['icon']] ?? '';
    }
    $iconsJson = json_encode($iconsJsonMap, JSON_UNESCAPED_SLASHES);

    $selNewTab  = $openNewTab ? 'selected' : '';
    $selSameTab = !$openNewTab ? 'selected' : '';
    $activeValue = $active ? '1' : '0';
    $activeBg    = $active ? '#5B4CF6' : '#E5E7EB';
    $activeLeft  = $active ? '18px' : '2px';
    $labelPreview = $label !== '' ? $label : 'Tiêu đề liên kết';

    $body = <<<HTML
{$errorHtml}
<div class="grid grid-cols-1 gap-5 lg:grid-cols-[1fr_320px]">

  <form method="post" action="{$action}" class="flex flex-col gap-4 rounded-xl bg-white p-5" style="border:1px solid #E5E7EB">
    <input type="hidden" name="csrf" value="{$csrf}">

    <div>
      <label class="text-xs font-medium" style="color:#64748B">Tiêu đề *</label>
      <input required id="fLabel" name="label" value="{$label}" placeholder="VD: TikTok" class="mt-1 w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-[#5B4CF6]" style="border:1px solid #E5E7EB;color:#111827">
    </div>

    <div>
      <label class="text-xs font-medium" style="color:#64748B">URL *</label>
      <input required id="fUrl" name="url" value="{$url}" placeholder="https://www.tiktok.com/@username" class="mt-1 w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-[#5B4CF6]" style="border:1px solid #E5E7EB;color:#111827">
    </div>

    <div>
      <label class="text-xs font-medium" style="color:#64748B">Icon</label>
      <select id="fType" name="type" class="mt-1 w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-[#5B4CF6]" style="border:1px solid #E5E7EB;color:#111827">{$options}</select>
    </div>

    <div>
      <label class="text-xs font-medium" style="color:#64748B">Màu sắc</label>
      <div class="mt-1 flex items-center gap-2">
        <input id="fColor" type="color" name="color" value="{$color}" class="h-9 w-14 cursor-pointer rounded-lg" style="border:1px solid #E5E7EB">
        <input id="fColorText" type="text" value="{$color}" class="w-28 rounded-lg px-3 py-2 text-sm font-mono outline-none focus:border-[#5B4CF6]" style="border:1px solid #E5E7EB;color:#111827">
      </div>
    </div>

    <div class="grid grid-cols-2 gap-4">
      <div>
        <label class="text-xs font-medium" style="color:#64748B">Mở liên kết</label>
        <select id="fOpenTab" name="open_new_tab" class="mt-1 w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-[#5B4CF6]" style="border:1px solid #E5E7EB;color:#111827">
          <option value="1" {$selNewTab}>Tab mới</option>
          <option value="0" {$selSameTab}>Cùng tab</option>
        </select>
      </div>
      <div>
        <label class="text-xs font-medium" style="color:#64748B">Hiển thị</label>
        <div class="mt-1 flex h-[42px] items-center">
          <button type="button" id="fActiveToggle" class="relative h-5 w-9 rounded-full transition" data-value="{$activeValue}" style="background:{$activeBg}">
            <span class="absolute top-0.5 h-4 w-4 rounded-full bg-white transition" style="left:{$activeLeft}"></span>
          </button>
          <input type="hidden" name="is_active" id="fActiveInput" value="{$activeValue}">
        </div>
      </div>
    </div>

    <div class="mt-2 flex gap-2">
      <a href="/admin/links" class="inline-flex items-center gap-1.5 rounded-lg px-4 py-2.5 text-sm font-medium" style="border:1px solid #E5E7EB;color:#111827">{$icArrowLeft}Quay lại</a>
      <button type="submit" class="rounded-lg px-5 py-2.5 text-sm font-semibold text-white transition hover:opacity-90" style="background:#5B4CF6">Lưu liên kết</button>
    </div>
  </form>

  <div class="rounded-xl p-4" style="background:#F7F8FA;border:1px solid #E5E7EB">
    <p class="mb-3 text-xs font-semibold uppercase tracking-wide" style="color:#94A3B8">Xem trước</p>
    <div id="pvItem" class="flex items-center gap-3 rounded-lg bg-white px-4" style="height:54px;border:1px solid #E5E7EB">
      <span id="pvIconWrap" class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full" style="background:{$color}1a;color:{$color}">{$icPreviewIcon}</span>
      <span id="pvLabel" class="flex-1 truncate text-sm font-medium" style="color:#111827">{$labelPreview}</span>
      <span style="color:#CBD5E1">{$icChevronRight}</span>
    </div>
  </div>
</div>

<script>
var ICON_PATHS = {$iconsJson};
var pvLabel = document.getElementById('pvLabel');
var pvIconWrap = document.getElementById('pvIconWrap');
document.getElementById('fLabel').addEventListener('input', function () {
  pvLabel.textContent = this.value || 'Tiêu đề liên kết';
});
function applyPvColor(hex) {
  pvIconWrap.style.background = hex + '1a';
  pvIconWrap.style.color = hex;
}
document.getElementById('fColor').addEventListener('input', function () {
  document.getElementById('fColorText').value = this.value;
  applyPvColor(this.value);
});
document.getElementById('fColorText').addEventListener('input', function () {
  if (/^#[0-9a-fA-F]{6}\$/.test(this.value)) { document.getElementById('fColor').value = this.value; applyPvColor(this.value); }
});
document.getElementById('fType').addEventListener('change', function () {
  var opt = this.options[this.selectedIndex];
  var color = opt.getAttribute('data-color');
  document.getElementById('fColor').value = color;
  document.getElementById('fColorText').value = color;
  applyPvColor(color);
  var svgPath = ICON_PATHS[this.value] || '';
  pvIconWrap.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4">' + svgPath + '</svg>';
});
document.getElementById('fActiveToggle').addEventListener('click', function () {
  var on = this.dataset.value === '1';
  var next = !on;
  this.dataset.value = next ? '1' : '0';
  this.style.background = next ? '#5B4CF6' : '#E5E7EB';
  this.querySelector('span').style.left = next ? '18px' : '2px';
  document.getElementById('fActiveInput').value = next ? '1' : '0';
});
</script>
HTML;

    $titleTxt = $isEdit ? 'Sửa liên kết' : 'Thêm liên kết';
    $header = pageHeader($titleTxt, 'Mỗi liên kết hiển thị trên trang Bio Link của bạn.');
    return layoutAdmin($titleTxt . ' — Admin', 'links', $header . $body, $user);
}

// ==========================================================================
// VIEW: KHÁCH HÀNG (LEADS)
// ==========================================================================

function viewLeadsList(array $leads, array $user): string
{
    $icSearch = icon('search', 'h-4 w-4', 'none');
    $icDownload = icon('download', 'h-4 w-4', 'none');

    $rowsDesktop = '';
    $cardsMobile = '';
    foreach ($leads as $lead) {
        $id    = (int) $lead['id'];
        $name  = e($lead['name']);
        $phone = e($lead['phone']);
        $note  = e($lead['note'] ?? '');
        $time  = fmtDateTime($lead['created_at']);
        $status = $lead['status'] ?: 'new';
        $badge = statusBadge($status);
        $csvNote = str_replace(["\n", "\r"], ' ', $lead['note'] ?? '');

        $rowsDesktop .= <<<HTML
<tr class="leadRow border-t" style="border-color:#F1F5F9" data-name="{$name}" data-phone="{$phone}" data-created="{$lead['created_at']}">
  <td class="py-3 pr-3 text-sm font-medium" style="color:#111827">{$name}</td>
  <td class="py-3 pr-3 text-sm" style="color:#64748B">{$phone}</td>
  <td class="hidden max-w-xs truncate py-3 pr-3 text-sm md:table-cell" style="color:#64748B">{$note}</td>
  <td class="py-3 pr-3 text-sm" style="color:#94A3B8">{$time}</td>
  <td class="py-3 pr-3">{$badge}</td>
  <td class="py-3"><a href="/admin/leads/{$id}" class="text-sm font-medium" style="color:#5B4CF6">Xem</a></td>
</tr>
HTML;

        $cardsMobile .= <<<HTML
<a href="/admin/leads/{$id}" class="leadCard block rounded-lg bg-white p-4" data-name="{$name}" data-phone="{$phone}" data-created="{$lead['created_at']}" style="border:1px solid #E5E7EB">
  <div class="flex items-center justify-between">
    <p class="text-sm font-semibold" style="color:#111827">{$name}</p>
    {$badge}
  </div>
  <p class="mt-1 text-sm" style="color:#64748B">{$phone}</p>
  <p class="mt-1 truncate text-xs" style="color:#94A3B8">{$note}</p>
  <p class="mt-2 text-xs" style="color:#94A3B8">{$time}</p>
</a>
HTML;
    }

    $csvRowsJs = [];
    foreach ($leads as $lead) {
        $csvRowsJs[] = [$lead['name'], $lead['phone'], str_replace(["\n", "\r"], ' ', (string) ($lead['note'] ?? '')), fmtDateTime($lead['created_at']), LEAD_STATUS[$lead['status'] ?: 'new']['label']];
    }
    $csvJson = json_encode($csvRowsJs, JSON_UNESCAPED_UNICODE);

    $emptyHtml = emptyState('inbox', 'Chưa có khách hàng nào.', 'Lead mới sẽ xuất hiện tại đây khi khách gửi thông tin.',
        '<a href="/" target="_blank" class="mt-4 inline-flex items-center gap-1.5 rounded-lg px-4 py-2 text-sm font-semibold text-white" style="background:#5B4CF6">Xem trang Bio Link</a>');

    $tableHtml = <<<HTML
<div class="hidden overflow-x-auto rounded-xl bg-white sm:block" style="border:1px solid #E5E7EB">
  <table class="w-full px-2">
    <thead>
      <tr class="text-left text-xs" style="color:#94A3B8">
        <th class="px-4 pt-4 pb-2 font-medium">Họ và tên</th>
        <th class="px-0 pt-4 pb-2 font-medium">Số điện thoại</th>
        <th class="hidden px-0 pt-4 pb-2 font-medium md:table-cell">Ghi chú</th>
        <th class="px-0 pt-4 pb-2 font-medium">Thời gian</th>
        <th class="px-0 pt-4 pb-2 font-medium">Trạng thái</th>
        <th class="px-4 pt-4 pb-2 font-medium"></th>
      </tr>
    </thead>
    <tbody id="leadsTbody" class="px-4">{$rowsDesktop}</tbody>
  </table>
</div>
<div id="leadsCardsWrap" class="flex flex-col gap-2 sm:hidden">{$cardsMobile}</div>
HTML;

    $listWrap = $leads !== [] ? $tableHtml : $emptyHtml;

    $toolbar = <<<HTML
<div class="mb-4 flex flex-wrap items-center gap-3">
  <div class="flex flex-1 items-center gap-2 rounded-lg px-3 py-2" style="border:1px solid #E5E7EB;min-width:220px;color:#94A3B8">
    {$icSearch}<input id="leadSearch" type="text" placeholder="Tìm theo tên, số điện thoại..." class="w-full bg-transparent text-sm outline-none" style="color:#111827">
  </div>
  <select id="leadTimeFilter" class="rounded-lg px-3 py-2 text-sm outline-none" style="border:1px solid #E5E7EB;color:#111827">
    <option value="all">Tất cả thời gian</option>
    <option value="today">Hôm nay</option>
    <option value="7">7 ngày</option>
    <option value="30">30 ngày</option>
  </select>
  <button id="exportCsvBtn" type="button" class="inline-flex items-center gap-1.5 rounded-lg px-3 py-2 text-sm font-medium" style="border:1px solid #E5E7EB;color:#111827">{$icDownload}Xuất CSV</button>
</div>
HTML;

    $script = <<<'JS'
var CSV_ROWS = __CSV_JSON__;
document.getElementById('exportCsvBtn').addEventListener('click', function () {
  var header = ['Ho va ten', 'So dien thoai', 'Ghi chu', 'Thoi gian', 'Trang thai'];
  var lines = [header].concat(CSV_ROWS);
  var csv = lines.map(function (row) {
    return row.map(function (cell) {
      var s = String(cell == null ? '' : cell).replace(/"/g, '""');
      return '"' + s + '"';
    }).join(',');
  }).join('\r\n');
  var blob = new Blob(['﻿' + csv], { type: 'text/csv;charset=utf-8;' });
  var url = URL.createObjectURL(blob);
  var a = document.createElement('a');
  a.href = url;
  a.download = 'leads.csv';
  document.body.appendChild(a);
  a.click();
  a.remove();
  URL.revokeObjectURL(url);
});

var searchEl = document.getElementById('leadSearch');
var timeEl = document.getElementById('leadTimeFilter');
function withinRange(createdStr, mode) {
  if (mode === 'all') { return true; }
  var created = new Date(createdStr.replace(' ', 'T'));
  var now = new Date();
  var diffDays = (now - created) / 86400000;
  if (mode === 'today') { return diffDays <= 1; }
  return diffDays <= parseInt(mode, 10);
}
function applyLeadFilters() {
  var q = (searchEl.value || '').toLowerCase();
  var mode = timeEl.value;
  document.querySelectorAll('.leadRow, .leadCard').forEach(function (el) {
    var matches = (el.dataset.name.toLowerCase().indexOf(q) !== -1 || el.dataset.phone.indexOf(q) !== -1) && withinRange(el.dataset.created, mode);
    el.style.display = matches ? '' : 'none';
  });
}
searchEl.addEventListener('input', applyLeadFilters);
timeEl.addEventListener('change', applyLeadFilters);
JS;
    $script = str_replace('__CSV_JSON__', $csvJson, $script);

    $bodyHtml = $toolbar . $listWrap . '<script>' . $script . '</script>';
    $header = pageHeader('Khách hàng (Leads)', 'Theo dõi những khách hàng đã để lại thông tin tư vấn.');

    return layoutAdmin('Khách hàng — Admin', 'leads', $header . $bodyHtml, $user);
}

// ==========================================================================
// VIEW: CHI TIẾT LEAD
// ==========================================================================

function viewLeadDetail(array $lead, array $user): string
{
    $csrf = csrfToken();
    $id    = (int) $lead['id'];
    $name  = e($lead['name']);
    $phone = e($lead['phone']);
    $note  = $lead['note'] ? nl2br(e($lead['note'])) : '<span style="color:#94A3B8">(Không có ghi chú)</span>';
    $time  = fmtDateTime($lead['created_at']);
    $ua    = parseUserAgent($lead['user_agent'] ?? '');
    $ip    = e($lead['ip_address'] ?: 'Không rõ');
    $source = e($lead['source_path'] ?: '/');
    $status = $lead['status'] ?: 'new';

    $deviceIcon = $ua['device'] === 'Desktop' ? icon('monitor', 'h-4 w-4', 'none') : icon('smartphone', 'h-4 w-4', 'none');
    $icArrowLeft = icon('arrowLeft', 'h-4 w-4', 'none');

    $contactedActiveStyle = $status === 'contacted' ? 'background:#5B4CF6;color:white;border-color:#5B4CF6' : 'color:#111827;border-color:#E5E7EB';
    $resolvedActiveStyle  = $status === 'resolved' ? 'background:#16A34A;color:white;border-color:#16A34A' : 'color:#111827;border-color:#E5E7EB';

    $body = <<<HTML
<div class="grid grid-cols-1 gap-5 lg:grid-cols-2">
  <div class="rounded-xl bg-white p-5" style="border:1px solid #E5E7EB">
    <p class="mb-4 text-xs font-semibold uppercase tracking-wide" style="color:#94A3B8">Thông tin khách hàng</p>
    <dl class="flex flex-col gap-4">
      <div><dt class="text-xs" style="color:#64748B">Tên</dt><dd class="mt-0.5 text-sm font-medium" style="color:#111827">{$name}</dd></div>
      <div><dt class="text-xs" style="color:#64748B">Số điện thoại</dt><dd class="mt-0.5 text-sm font-medium" style="color:#111827"><a href="tel:{$phone}" style="color:#5B4CF6">{$phone}</a></dd></div>
      <div><dt class="text-xs" style="color:#64748B">Ghi chú</dt><dd class="mt-0.5 text-sm" style="color:#111827">{$note}</dd></div>
      <div><dt class="text-xs" style="color:#64748B">Thời gian</dt><dd class="mt-0.5 text-sm" style="color:#111827">{$time}</dd></div>
    </dl>
  </div>

  <div class="rounded-xl bg-white p-5" style="border:1px solid #E5E7EB">
    <p class="mb-4 text-xs font-semibold uppercase tracking-wide" style="color:#94A3B8">Thông tin truy cập</p>
    <dl class="flex flex-col gap-4">
      <div><dt class="text-xs" style="color:#64748B">IP</dt><dd class="mt-0.5 text-sm font-medium" style="color:#111827">{$ip}</dd></div>
      <div><dt class="text-xs" style="color:#64748B">Thiết bị</dt><dd class="mt-0.5 flex items-center gap-1.5 text-sm font-medium" style="color:#111827">{$deviceIcon}{$ua['device']}</dd></div>
      <div><dt class="text-xs" style="color:#64748B">Trình duyệt</dt><dd class="mt-0.5 text-sm font-medium" style="color:#111827">{$ua['browser']}</dd></div>
      <div><dt class="text-xs" style="color:#64748B">Nguồn</dt><dd class="mt-0.5 text-sm font-medium" style="color:#111827">Trang Bio Link</dd></div>
      <div><dt class="text-xs" style="color:#64748B">URL</dt><dd class="mt-0.5 text-sm font-mono" style="color:#64748B">{$source}</dd></div>
    </dl>
  </div>
</div>

<div class="mt-5 flex flex-wrap gap-2">
  <button type="button" class="statusBtn rounded-lg px-4 py-2.5 text-sm font-semibold transition" style="border:1px solid;{$contactedActiveStyle}" data-status="contacted" data-id="{$id}">Đánh dấu đã liên hệ</button>
  <button type="button" class="statusBtn rounded-lg px-4 py-2.5 text-sm font-semibold transition" style="border:1px solid;{$resolvedActiveStyle}" data-status="resolved" data-id="{$id}">Đã xử lý</button>
  <button type="button" id="deleteLeadBtn" class="ml-auto rounded-lg px-4 py-2.5 text-sm font-semibold text-red-600 transition hover:bg-red-50" style="border:1px solid #FCA5A5">Xoá Lead</button>
</div>

<script>
document.querySelectorAll('.statusBtn').forEach(function (btn) {
  btn.addEventListener('click', function () {
    fetch('/admin/leads/{$id}/status', {
      method: 'POST', headers: {'Content-Type':'application/json'},
      body: JSON.stringify({ csrf: document.body.dataset.csrf, status: btn.dataset.status }),
    }).then(function (r) { return r.json(); }).then(function (data) {
      if (data.ok) { showToast('Đã cập nhật trạng thái'); setTimeout(function () { location.reload(); }, 500); }
    });
  });
});
document.getElementById('deleteLeadBtn').addEventListener('click', function () {
  openConfirm({
    title: 'Xoá lead?',
    body: 'Bạn có chắc muốn xoá lead "{$name}"? Hành động này không thể hoàn tác.',
    okLabel: 'Xoá',
    onConfirm: function () {
      fetch('/admin/leads/{$id}/delete', { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify({csrf: document.body.dataset.csrf}) })
        .then(function (r) { return r.json(); }).then(function (data) { if (data.ok) { window.location.href = '/admin/leads'; } });
    },
  });
});
</script>
HTML;

    $header = pageHeader('Chi tiết Lead', '', '<a href="/admin/leads" class="inline-flex items-center gap-1.5 rounded-lg px-3 py-2 text-sm font-medium" style="border:1px solid #E5E7EB;color:#111827">' . $icArrowLeft . 'Quay lại</a>');
    return layoutAdmin('Chi tiết Lead — Admin', 'leads', $header . $body, $user);
}

// ==========================================================================
// VIEW: THỐNG KÊ
// ==========================================================================

function viewStatistics(array $user, array $stats): string
{
    $donutColors = ['#5B4CF6', '#0EA5E9', '#EE4D2D', '#16A34A', '#F59E0B', '#EF4444', '#6B7280'];

    $donutLabels = [];
    $donutData = [];
    $donutColorList = [];
    foreach ($stats['perLink'] as $i => $lp) {
        $donutLabels[] = $lp['label'];
        $donutData[] = $lp['clicks'];
        $donutColorList[] = $donutColors[$i % count($donutColors)];
    }
    $donutLabelsJson = json_encode($donutLabels, JSON_UNESCAPED_UNICODE);
    $donutDataJson   = json_encode($donutData);
    $donutColorsJson = json_encode($donutColorList);
    $chartLabelsJson = json_encode($stats['chartLabels'], JSON_UNESCAPED_UNICODE);
    $chartDataJson   = json_encode($stats['chartData']);

    $tableRows = '';
    foreach ($stats['perLink'] as $i => $lp) {
        $trendColor = $lp['trendPositive'] ? '#16A34A' : '#DC2626';
        $trendSign  = $lp['trendPositive'] ? '↑' : '↓';
        $dotColor   = $donutColors[$i % count($donutColors)];
        $tableRows .= <<<HTML
<tr class="border-t" style="border-color:#F1F5F9">
  <td class="py-2.5 pr-3 text-sm font-medium" style="color:#111827"><span class="mr-2 inline-block h-2 w-2 rounded-full" style="background:{$dotColor}"></span>{$lp['label']}</td>
  <td class="py-2.5 pr-3 text-right text-sm" style="color:#111827">{$lp['clicks']}</td>
  <td class="py-2.5 pr-3 text-right text-sm" style="color:#64748B">{$lp['pct']}%</td>
  <td class="py-2.5 text-right text-sm font-medium" style="color:{$trendColor}">{$trendSign} {$lp['trendLabel']}</td>
</tr>
HTML;
    }
    if ($stats['perLink'] === []) {
        $tableRows = '<tr><td colspan="4" class="py-8 text-center text-sm" style="color:#94A3B8">Chưa có dữ liệu.</td></tr>';
    }

    $cards = kpiCard('barChart', 'Tổng lượt click', number_format($stats['totalClicks']), $stats['chartData'], null, 'Toàn thời gian')
        . kpiCard('users', 'Tổng Leads', number_format($stats['totalLeads']), $stats['chartData'], null, 'Toàn thời gian')
        . kpiCard('link', 'Số liên kết', number_format($stats['totalLinks']), $stats['chartData'], null, 'Đang hoạt động')
        . kpiCard('grid', 'Tỷ lệ chuyển đổi', $stats['conversionRate'] . '%', $stats['chartData'], null, 'Lead / Click');

    $body = <<<HTML
<div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
  {$cards}
</div>

<div class="mt-5 grid grid-cols-1 gap-4 xl:grid-cols-[1fr_320px]">
  <div class="rounded-xl bg-white p-5" style="border:1px solid #E5E7EB">
    <h2 class="text-sm font-bold" style="color:#111827">Lượt click theo thời gian</h2>
    <div class="mt-4" style="height:280px"><canvas id="statsLineChart"></canvas></div>
  </div>
  <div class="rounded-xl bg-white p-5" style="border:1px solid #E5E7EB">
    <h2 class="text-sm font-bold" style="color:#111827">Click theo từng liên kết</h2>
    <div class="mt-4" style="height:220px"><canvas id="statsDonutChart"></canvas></div>
  </div>
</div>

<div class="mt-5 rounded-xl bg-white p-5" style="border:1px solid #E5E7EB">
  <h2 class="text-sm font-bold" style="color:#111827">Hiệu quả liên kết</h2>
  <div class="mt-3 overflow-x-auto">
    <table class="w-full">
      <thead>
        <tr class="text-left text-xs" style="color:#94A3B8">
          <th class="pb-2 pr-3 font-medium">Link</th>
          <th class="pb-2 pr-3 text-right font-medium">Click</th>
          <th class="pb-2 pr-3 text-right font-medium">Tỷ trọng</th>
          <th class="pb-2 text-right font-medium">Xu hướng (7d)</th>
        </tr>
      </thead>
      <tbody>{$tableRows}</tbody>
    </table>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script>
new Chart(document.getElementById('statsLineChart').getContext('2d'), {
  type: 'line',
  data: { labels: {$chartLabelsJson}, datasets: [{ label: 'Lượt click', data: {$chartDataJson}, borderColor: '#5B4CF6', backgroundColor: 'rgba(91,76,246,0.08)', tension: 0.3, fill: true, pointRadius: 0 }] },
  options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } }, x: { grid: { display: false }, ticks: { maxTicksLimit: 10 } } } },
});
new Chart(document.getElementById('statsDonutChart').getContext('2d'), {
  type: 'doughnut',
  data: { labels: {$donutLabelsJson}, datasets: [{ data: {$donutDataJson}, backgroundColor: {$donutColorsJson}, borderWidth: 0 }] },
  options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom', labels: { boxWidth: 8, font: { size: 11 } } } } },
});
</script>
HTML;

    $header = pageHeader('Thống kê', 'Phân tích hiệu quả trang Bio Link.');
    return layoutAdmin('Thống kê — Admin', 'statistics', $header . $body, $user);
}

// ==========================================================================
// VIEW: CÀI ĐẶT
// ==========================================================================

function viewSettings(array $user, ?string $pwError, bool $pwSaved): string
{
    $csrf = csrfToken();
    $email = e($user['email']);
    $createdAt = fmtDateTime($user['created_at']);
    $icAlert = icon('alertTriangle', 'h-5 w-5', 'none');
    $icLock  = icon('lock', 'h-4 w-4', 'none');

    $pwErrorHtml = $pwError ? '<div class="mb-3 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">' . e($pwError) . '</div>' : '';
    $pwSavedHtml = $pwSaved ? '<div class="mb-3 rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-700">Đã đổi mật khẩu thành công.</div>' : '';

    $body = <<<HTML
<div class="flex flex-col gap-5">

  <section class="rounded-xl bg-white p-5" style="border:1px solid #E5E7EB">
    <h2 class="text-sm font-bold" style="color:#111827">Tài khoản</h2>
    <dl class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-3">
      <div><dt class="text-xs" style="color:#64748B">Email</dt><dd class="mt-0.5 text-sm font-medium" style="color:#111827">{$email}</dd></div>
      <div><dt class="text-xs" style="color:#64748B">Vai trò</dt><dd class="mt-0.5 text-sm font-medium" style="color:#111827">Quản trị viên</dd></div>
      <div><dt class="text-xs" style="color:#64748B">Ngày tạo</dt><dd class="mt-0.5 text-sm font-medium" style="color:#111827">{$createdAt}</dd></div>
    </dl>
  </section>

  <section class="rounded-xl bg-white p-5" style="border:1px solid #E5E7EB">
    <h2 class="text-sm font-bold" style="color:#111827">Đổi mật khẩu</h2>
    {$pwErrorHtml}{$pwSavedHtml}
    <form method="post" action="/admin/settings/password" class="mt-4 flex max-w-sm flex-col gap-3">
      <input type="hidden" name="csrf" value="{$csrf}">
      <div>
        <label class="text-xs font-medium" style="color:#64748B">Mật khẩu hiện tại</label>
        <input required type="password" name="current_password" class="mt-1 w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-[#5B4CF6]" style="border:1px solid #E5E7EB;color:#111827">
      </div>
      <div>
        <label class="text-xs font-medium" style="color:#64748B">Mật khẩu mới</label>
        <input required minlength="8" id="newPw" type="password" name="new_password" class="mt-1 w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-[#5B4CF6]" style="border:1px solid #E5E7EB;color:#111827">
      </div>
      <div>
        <label class="text-xs font-medium" style="color:#64748B">Xác nhận mật khẩu mới</label>
        <input required id="confirmPw" type="password" name="confirm_password" class="mt-1 w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-[#5B4CF6]" style="border:1px solid #E5E7EB;color:#111827">
      </div>
      <p id="pwMismatch" class="hidden text-xs text-red-600">Mật khẩu xác nhận không khớp.</p>
      <button type="submit" class="mt-1 inline-flex w-fit items-center gap-1.5 rounded-lg px-4 py-2.5 text-sm font-semibold text-white transition hover:opacity-90" style="background:#5B4CF6">{$icLock}Đổi mật khẩu</button>
    </form>
  </section>

  <section class="rounded-xl p-5" style="background:#FEF2F2;border:1px solid #FECACA">
    <div class="flex items-start gap-3">
      <span style="color:#DC2626">{$icAlert}</span>
      <div>
        <h2 class="text-sm font-bold" style="color:#991B1B">Nguy hiểm</h2>
        <p class="mt-1 text-sm" style="color:#B91C1C">Xoá tài khoản sẽ xoá toàn bộ dữ liệu (liên kết, lead, hồ sơ). Thao tác này không thể hoàn tác.</p>
        <button id="deleteAccountBtn" type="button" class="mt-3 rounded-lg px-4 py-2.5 text-sm font-semibold text-white transition hover:opacity-90" style="background:#DC2626">Xoá tài khoản</button>
      </div>
    </div>
  </section>

</div>

<script>
function checkPwMatch() {
  var mismatch = document.getElementById('newPw').value !== document.getElementById('confirmPw').value && document.getElementById('confirmPw').value !== '';
  document.getElementById('pwMismatch').classList.toggle('hidden', !mismatch);
}
document.getElementById('newPw').addEventListener('input', checkPwMatch);
document.getElementById('confirmPw').addEventListener('input', checkPwMatch);

document.getElementById('deleteAccountBtn').addEventListener('click', function () {
  openConfirm({
    title: 'Xoá tài khoản?',
    body: 'Nhập lại email "{$email}" để xác nhận xoá vĩnh viễn toàn bộ dữ liệu.',
    okLabel: 'Tôi hiểu, tiếp tục',
    onConfirm: function () {
      var typed = prompt('Nhập email "{$email}" để xác nhận:');
      if (typed === '{$email}') {
        fetch('/admin/settings/delete-account', { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify({csrf: document.body.dataset.csrf, email: typed}) })
          .then(function (r) { return r.json(); }).then(function (data) {
            if (data.ok) { window.location.href = '/login'; } else { showToast(data.error || 'Có lỗi xảy ra', 'error'); }
          });
      } else if (typed !== null) {
        showToast('Email không khớp, huỷ thao tác.', 'error');
      }
    },
  });
});
</script>
HTML;

    $header = pageHeader('Cài đặt', 'Cấu hình hệ thống và tài khoản.');
    return layoutAdmin('Cài đặt — Admin', 'settings', $header . $body, $user);
}

// ==========================================================================
// ANALYTICS (dữ liệu thật từ link_clicks/leads/links — không có số minh hoạ)
// ==========================================================================

function jsonInput(): array
{
    $raw = file_get_contents('php://input') ?: '';
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function countClicksSince(PDO $pdo, int $days): int
{
    $stmt = $pdo->prepare("SELECT COUNT(*) c FROM link_clicks WHERE created_at >= (NOW() - INTERVAL {$days} DAY)");
    $stmt->execute();
    return (int) $stmt->fetch()['c'];
}

function countClicksBetween(PDO $pdo, int $fromDays, int $toDays): int
{
    $stmt = $pdo->prepare("SELECT COUNT(*) c FROM link_clicks WHERE created_at >= (NOW() - INTERVAL {$fromDays} DAY) AND created_at < (NOW() - INTERVAL {$toDays} DAY)");
    $stmt->execute();
    return (int) $stmt->fetch()['c'];
}

function computeAnalytics(int $userId): array
{
    $pdo = db();

    $totalClicks = (int) $pdo->query('SELECT COALESCE(SUM(clicks),0) c FROM links')->fetch()['c'];
    $totalLeads  = (int) $pdo->query('SELECT COUNT(*) c FROM leads')->fetch()['c'];

    $stmt = $pdo->prepare('SELECT COUNT(*) c FROM links WHERE user_id = ? AND is_active = 1');
    $stmt->execute([$userId]);
    $totalLinksActive = (int) $stmt->fetch()['c'];

    $stmt = $pdo->prepare('SELECT COUNT(*) c FROM links WHERE user_id = ?');
    $stmt->execute([$userId]);
    $totalLinksAll = (int) $stmt->fetch()['c'];

    $stmt = $pdo->prepare('SELECT COUNT(*) c FROM links WHERE user_id = ? AND created_at >= (NOW() - INTERVAL 30 DAY)');
    $stmt->execute([$userId]);
    $linksNew = (int) $stmt->fetch()['c'];

    $clicksLast7  = countClicksSince($pdo, 7);
    $clicksPrev7  = countClicksBetween($pdo, 14, 7);
    $leadsLast7   = (int) $pdo->query("SELECT COUNT(*) c FROM leads WHERE created_at >= (NOW() - INTERVAL 7 DAY)")->fetch()['c'];
    $leadsPrev7   = (int) $pdo->query("SELECT COUNT(*) c FROM leads WHERE created_at >= (NOW() - INTERVAL 14 DAY) AND created_at < (NOW() - INTERVAL 7 DAY)")->fetch()['c'];
    $clicksToday     = (int) $pdo->query('SELECT COUNT(*) c FROM link_clicks WHERE DATE(created_at) = CURDATE()')->fetch()['c'];
    $clicksYesterday = (int) $pdo->query('SELECT COUNT(*) c FROM link_clicks WHERE DATE(created_at) = CURDATE() - INTERVAL 1 DAY')->fetch()['c'];

    $byDay90 = $pdo->query("SELECT DATE(created_at) d, COUNT(*) c FROM link_clicks WHERE created_at >= (CURDATE() - INTERVAL 89 DAY) GROUP BY d")->fetchAll(PDO::FETCH_KEY_PAIR);
    $chartLabels = [];
    $chartData   = [];
    for ($i = 89; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-{$i} day"));
        $chartLabels[] = date('d/m', strtotime($date));
        $chartData[]   = (int) ($byDay90[$date] ?? 0);
    }

    $byDayLeads7 = $pdo->query("SELECT DATE(created_at) d, COUNT(*) c FROM leads WHERE created_at >= (CURDATE() - INTERVAL 6 DAY) GROUP BY d")->fetchAll(PDO::FETCH_KEY_PAIR);
    $sparkLeads = [];
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-{$i} day"));
        $sparkLeads[] = (int) ($byDayLeads7[$date] ?? 0);
    }

    $stmtCum = $pdo->prepare('SELECT COUNT(*) c FROM links WHERE user_id = ? AND created_at <= ?');
    $sparkLinks = [];
    for ($i = 6; $i >= 0; $i--) {
        $ts = date('Y-m-d 23:59:59', strtotime("-{$i} day"));
        $stmtCum->execute([$userId, $ts]);
        $sparkLinks[] = (int) $stmtCum->fetch()['c'];
    }
    if (array_sum($sparkLinks) === 0) {
        $sparkLinks = array_fill(0, 7, 0);
    }

    $byHourToday = $pdo->query('SELECT HOUR(created_at) h, COUNT(*) c FROM link_clicks WHERE DATE(created_at) = CURDATE() GROUP BY h')->fetchAll(PDO::FETCH_KEY_PAIR);
    $sparkToday = [];
    for ($h = 0; $h < 24; $h += 3) {
        $sum = 0;
        for ($k = $h; $k < $h + 3; $k++) {
            $sum += (int) ($byHourToday[$k] ?? 0);
        }
        $sparkToday[] = $sum;
    }

    $leadsRecentRaw = $pdo->query('SELECT id, name, phone, created_at FROM leads ORDER BY id DESC LIMIT 5')->fetchAll();
    $leadsRecent = [];
    foreach ($leadsRecentRaw as $r) {
        $leadsRecent[] = [
            'id'    => (int) $r['id'],
            'name'  => e($r['name']),
            'phone' => e($r['phone']),
            'time'  => fmtDateTime($r['created_at']),
        ];
    }

    $stmt = $pdo->prepare('SELECT id, label, url, clicks FROM links WHERE user_id = ? ORDER BY clicks DESC');
    $stmt->execute([$userId]);
    $allLinks = $stmt->fetchAll();
    $totalForPct = array_sum(array_column($allLinks, 'clicks')) ?: 1;

    $linkPerf = [];
    $perLink  = [];
    $stmtLast7 = $pdo->prepare('SELECT COUNT(*) c FROM link_clicks WHERE link_id = ? AND created_at >= (NOW() - INTERVAL 7 DAY)');
    $stmtPrev7 = $pdo->prepare('SELECT COUNT(*) c FROM link_clicks WHERE link_id = ? AND created_at >= (NOW() - INTERVAL 14 DAY) AND created_at < (NOW() - INTERVAL 7 DAY)');
    foreach ($allLinks as $l) {
        $lid = (int) $l['id'];
        $pct = (int) round(((int) $l['clicks']) / $totalForPct * 100);
        $linkPerf[] = ['label' => e($l['label']), 'url' => e($l['url']), 'clicks' => (int) $l['clicks'], 'pct' => $pct];

        $stmtLast7->execute([$lid]);
        $last7 = (int) $stmtLast7->fetch()['c'];
        $stmtPrev7->execute([$lid]);
        $prev7 = (int) $stmtPrev7->fetch()['c'];
        $trend = pctChange($last7, $prev7);
        $perLink[] = ['label' => e($l['label']), 'clicks' => (int) $l['clicks'], 'pct' => $pct, 'trendLabel' => $trend['label'], 'trendPositive' => $trend['positive']];
    }

    $conversionRate = $totalClicks > 0 ? round($totalLeads / $totalClicks * 100, 2) : 0.0;

    return [
        'totalClicks'      => $totalClicks,
        'totalLeads'       => $totalLeads,
        'totalLinksActive' => $totalLinksActive,
        'totalLinksAll'    => $totalLinksAll,
        'totalLinks'       => $totalLinksActive,
        'linksNew'         => $linksNew,
        'clicksToday'      => $clicksToday,
        'clicksChange'     => pctChange($clicksLast7, $clicksPrev7),
        'leadsChange'      => pctChange($leadsLast7, $leadsPrev7),
        'todayChange'      => pctChange($clicksToday, $clicksYesterday),
        'sparkClicks'      => array_slice($chartData, -7),
        'sparkLeads'       => $sparkLeads,
        'sparkLinks'       => $sparkLinks,
        'sparkToday'       => $sparkToday,
        'chart90Labels'    => $chartLabels,
        'chart90Data'      => $chartData,
        'chartLabels'      => $chartLabels,
        'chartData'        => $chartData,
        'leadsRecent'      => $leadsRecent,
        'linkPerf'         => $linkPerf,
        'perLink'          => $perLink,
        'conversionRate'   => rtrim(rtrim(number_format($conversionRate, 2), '0'), '.'),
    ];
}

// ==========================================================================
// CONTROLLERS
// ==========================================================================

function ctrlHealth()
{
    try {
        db()->query('SELECT 1');
        jsonResponse(['status' => 'up', 'database' => 'connected']);
    } catch (Throwable $e) {
        jsonResponse(['status' => 'down', 'database' => 'disconnected'], 503);
    }
}

function ctrlBio()
{
    $user = siteOwner();
    if (!$user) {
        http_response_code(503);
        echo 'Hệ thống chưa khởi tạo xong, vui lòng thử lại sau giây lát.';
        exit;
    }
    $stmt = db()->prepare('SELECT * FROM links WHERE user_id = ? AND is_active = 1 ORDER BY position ASC, id ASC');
    $stmt->execute([$user['id']]);
    echo viewBio($user, $stmt->fetchAll());
    exit;
}

function ctrlApiClick()
{
    $input = jsonInput();
    $id = (int) ($input['id'] ?? 0);
    if ($id <= 0) {
        jsonResponse(['ok' => false, 'error' => 'invalid_id'], 422);
    }
    $pdo = db();
    $stmt = $pdo->prepare('UPDATE links SET clicks = clicks + 1 WHERE id = ?');
    $stmt->execute([$id]);
    if ($stmt->rowCount() > 0) {
        $pdo->prepare('INSERT INTO link_clicks (link_id) VALUES (?)')->execute([$id]);
    }
    jsonResponse(['ok' => true]);
}

function ctrlApiLead()
{
    $input = jsonInput();

    if (!csrfCheck($input['csrf'] ?? null)) {
        jsonResponse(['ok' => false, 'error' => 'Phiên làm việc hết hạn, vui lòng tải lại trang.'], 419);
    }

    $name  = trim((string) ($input['name'] ?? ''));
    $phone = trim((string) ($input['phone'] ?? ''));
    $note  = trim((string) ($input['note'] ?? ''));

    if ($name === '' || $phone === '') {
        jsonResponse(['ok' => false, 'error' => 'Vui lòng nhập đầy đủ Họ tên và Số điện thoại.'], 422);
    }
    if (!preg_match('/^[0-9+ ()-]{8,15}$/', $phone)) {
        jsonResponse(['ok' => false, 'error' => 'Số điện thoại không hợp lệ.'], 422);
    }

    $ip = clientIp();
    $ua = substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);

    $stmt = db()->prepare('INSERT INTO leads (name, phone, note, ip_address, user_agent, source_path) VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->execute([$name, $phone, $note !== '' ? $note : null, $ip, $ua, '/']);

    jsonResponse(['ok' => true, 'message' => 'Cảm ơn bạn! Thông tin đã được gửi thành công.']);
}

function ctrlLoginShow()
{
    if (currentUser()) {
        redirect('/admin');
    }
    echo viewLogin($_GET['error'] ?? null);
    exit;
}

function ctrlLoginSubmit()
{
    if (!csrfCheck($_POST['csrf'] ?? null)) {
        redirect('/login?error=' . urlencode('Phiên làm việc hết hạn, thử lại.'));
    }

    $email    = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    $stmt = db()->prepare('SELECT id, password_hash FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $row = $stmt->fetch();

    if (!$row || !password_verify($password, $row['password_hash'])) {
        redirect('/login?error=' . urlencode('Email hoặc mật khẩu không đúng.'));
    }

    session_regenerate_id(true);
    $_SESSION['admin_id'] = $row['id'];

    if (!empty($_POST['remember'])) {
        global $isHttps;
        setcookie(session_name(), session_id(), [
            'expires'  => time() + 30 * 86400,
            'path'     => '/',
            'secure'   => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    redirect('/admin');
}

function ctrlLogout()
{
    $_SESSION = [];
    session_destroy();
    redirect('/login');
}

function ctrlDashboard()
{
    $user = requireLogin();
    echo viewDashboard($user, computeAnalytics((int) $user['id']));
    exit;
}

function ctrlProfileShow()
{
    $user = requireLogin();
    echo viewProfile($user, isset($_GET['saved']));
    exit;
}

function handleAvatarUpload(array $file): string
{
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Tải ảnh đại diện thất bại.');
    }
    if ($file['size'] > MAX_AVATAR_BYTES) {
        throw new RuntimeException('Ảnh đại diện tối đa 2MB.');
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($file['tmp_name']);
    if (!isset(ALLOWED_AVATAR_MIME[$mime])) {
        throw new RuntimeException('Chỉ chấp nhận ảnh JPG, PNG hoặc WEBP.');
    }
    if (!is_dir(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0775, true);
    }
    $ext      = ALLOWED_AVATAR_MIME[$mime];
    $filename = 'avatar_' . bin2hex(random_bytes(8)) . '.' . $ext;
    $dest     = UPLOAD_DIR . '/' . $filename;
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        throw new RuntimeException('Không lưu được ảnh đại diện.');
    }
    return UPLOAD_WEB . '/' . $filename;
}

function ctrlProfileSave()
{
    $user = requireLogin();
    if (!csrfCheck($_POST['csrf'] ?? null)) {
        redirect('/admin/profile');
    }

    $avatarPath = null;
    if (!empty($_FILES['avatar']['name'])) {
        try {
            $avatarPath = handleAvatarUpload($_FILES['avatar']);
        } catch (RuntimeException $e) {
            redirect('/admin/profile?error=' . urlencode($e->getMessage()));
        }
    }

    $themeColor = validThemeColor($_POST['theme_color'] ?? null);

    $sql = 'UPDATE users SET display_name = ?, job_title = ?, bio_text = ?, hotline_phone = ?, zalo_phone = ?, theme_color = ?';
    $params = [
        trim((string) ($_POST['display_name'] ?? '')),
        trim((string) ($_POST['job_title'] ?? '')) ?: null,
        trim((string) ($_POST['bio_text'] ?? '')),
        trim((string) ($_POST['hotline_phone'] ?? '')) ?: null,
        trim((string) ($_POST['zalo_phone'] ?? '')) ?: null,
        $themeColor,
    ];
    if ($avatarPath !== null) {
        $sql .= ', avatar_path = ?';
        $params[] = $avatarPath;
    }
    $sql .= ' WHERE id = ?';
    $params[] = $user['id'];

    db()->prepare($sql)->execute($params);
    redirect('/admin/profile?saved=1');
}

function decorateLink(array $link): array
{
    $link['id']           = (int) $link['id'];
    $link['clicks']       = (int) $link['clicks'];
    $link['is_active']    = (int) $link['is_active'];
    $link['open_new_tab'] = (int) ($link['open_new_tab'] ?? 1);
    return $link;
}

function ctrlLinksList()
{
    $user = requireLogin();
    $stmt = db()->prepare('SELECT * FROM links WHERE user_id = ? ORDER BY position ASC, id ASC');
    $stmt->execute([$user['id']]);
    $links = array_map('decorateLink', $stmt->fetchAll());
    echo viewLinksList($links, $user);
    exit;
}

function ctrlLinkCreateShow()
{
    $user = requireLogin();
    echo viewLinkForm(null, $user, $_GET['error'] ?? null);
    exit;
}

function ctrlLinkEditShow(int $id)
{
    $user = requireLogin();
    $stmt = db()->prepare('SELECT * FROM links WHERE id = ? AND user_id = ?');
    $stmt->execute([$id, $user['id']]);
    $link = $stmt->fetch();
    if (!$link) {
        redirect('/admin/links');
    }
    echo viewLinkForm(decorateLink($link), $user, $_GET['error'] ?? null);
    exit;
}

function readLinkInput(): array
{
    $type = (string) ($_POST['type'] ?? 'custom');
    if (!array_key_exists($type, LINK_TYPES)) {
        $type = 'custom';
    }
    $label = trim((string) ($_POST['label'] ?? ''));
    $url   = trim((string) ($_POST['url'] ?? ''));
    $color = validThemeColor($_POST['color'] ?? null, LINK_TYPES[$type]['color']);
    $openNewTab = ($_POST['open_new_tab'] ?? '1') === '1' ? 1 : 0;
    $isActive   = ($_POST['is_active'] ?? '1') === '1' ? 1 : 0;

    if ($label === '') {
        $label = LINK_TYPES[$type]['label'];
    }
    if ($url === '') {
        redirect('/admin/links/create?error=' . urlencode('Vui lòng nhập URL cho liên kết.'));
    }

    return [$type, $label, $url, $color, $openNewTab, $isActive];
}

function ctrlLinkCreate()
{
    $user = requireLogin();
    if (!csrfCheck($_POST['csrf'] ?? null)) {
        redirect('/admin/links');
    }
    [$type, $label, $url, $color, $openNewTab, $isActive] = readLinkInput();

    $maxPos = (int) db()->query('SELECT COALESCE(MAX(position), -1) m FROM links')->fetch()['m'];
    $stmt = db()->prepare('INSERT INTO links (user_id, type, label, url, color, open_new_tab, is_active, position) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([$user['id'], $type, $label, $url, $color, $openNewTab, $isActive, $maxPos + 1]);

    redirect('/admin/links');
}

function ctrlLinkUpdate(int $id)
{
    $user = requireLogin();
    if (!csrfCheck($_POST['csrf'] ?? null)) {
        redirect('/admin/links');
    }
    [$type, $label, $url, $color, $openNewTab, $isActive] = readLinkInput();

    $stmt = db()->prepare('UPDATE links SET type = ?, label = ?, url = ?, color = ?, open_new_tab = ?, is_active = ? WHERE id = ? AND user_id = ?');
    $stmt->execute([$type, $label, $url, $color, $openNewTab, $isActive, $id, $user['id']]);

    redirect('/admin/links');
}

function ctrlLinkToggle(int $id)
{
    $user = requireLogin();
    $input = jsonInput();
    if (!csrfCheck($input['csrf'] ?? null)) {
        jsonResponse(['ok' => false], 419);
    }
    $stmt = db()->prepare('UPDATE links SET is_active = 1 - is_active WHERE id = ? AND user_id = ?');
    $stmt->execute([$id, $user['id']]);

    $stmt = db()->prepare('SELECT is_active FROM links WHERE id = ? AND user_id = ?');
    $stmt->execute([$id, $user['id']]);
    $row = $stmt->fetch();
    jsonResponse(['ok' => true, 'active' => $row ? (int) $row['is_active'] === 1 : false]);
}

function ctrlLinkDelete(int $id)
{
    $user = requireLogin();
    $input = jsonInput();
    if (!csrfCheck($input['csrf'] ?? null)) {
        jsonResponse(['ok' => false], 419);
    }
    db()->prepare('DELETE FROM links WHERE id = ? AND user_id = ?')->execute([$id, $user['id']]);
    jsonResponse(['ok' => true]);
}

function ctrlLinkReorder()
{
    $user = requireLogin();
    $input = jsonInput();
    if (!csrfCheck($input['csrf'] ?? null)) {
        jsonResponse(['ok' => false], 419);
    }
    $order = $input['order'] ?? [];
    if (!is_array($order)) {
        jsonResponse(['ok' => false], 422);
    }
    $stmt = db()->prepare('UPDATE links SET position = ? WHERE id = ? AND user_id = ?');
    foreach ($order as $position => $linkId) {
        $stmt->execute([(int) $position, (int) $linkId, $user['id']]);
    }
    jsonResponse(['ok' => true]);
}

function ctrlLeadsList()
{
    $user = requireLogin();
    $leads = db()->query('SELECT * FROM leads ORDER BY id DESC')->fetchAll();
    echo viewLeadsList($leads, $user);
    exit;
}

function ctrlLeadDetail(int $id)
{
    $user = requireLogin();
    $stmt = db()->prepare('SELECT * FROM leads WHERE id = ?');
    $stmt->execute([$id]);
    $lead = $stmt->fetch();
    if (!$lead) {
        redirect('/admin/leads');
    }
    echo viewLeadDetail($lead, $user);
    exit;
}

function ctrlLeadStatus(int $id)
{
    requireLogin();
    $input = jsonInput();
    if (!csrfCheck($input['csrf'] ?? null)) {
        jsonResponse(['ok' => false], 419);
    }
    $status = (string) ($input['status'] ?? '');
    if (!array_key_exists($status, LEAD_STATUS)) {
        jsonResponse(['ok' => false], 422);
    }
    db()->prepare('UPDATE leads SET status = ? WHERE id = ?')->execute([$status, $id]);
    jsonResponse(['ok' => true]);
}

function ctrlLeadDelete(int $id)
{
    requireLogin();
    $input = jsonInput();
    if (!csrfCheck($input['csrf'] ?? null)) {
        jsonResponse(['ok' => false], 419);
    }
    db()->prepare('DELETE FROM leads WHERE id = ?')->execute([$id]);
    jsonResponse(['ok' => true]);
}

function ctrlStatistics()
{
    $user = requireLogin();
    echo viewStatistics($user, computeAnalytics((int) $user['id']));
    exit;
}

function ctrlSettingsShow()
{
    $user = requireLogin();
    echo viewSettings($user, $_GET['error'] ?? null, isset($_GET['saved']));
    exit;
}

function ctrlSettingsPassword()
{
    $user = requireLogin();
    if (!csrfCheck($_POST['csrf'] ?? null)) {
        redirect('/admin/settings');
    }
    $current = (string) ($_POST['current_password'] ?? '');
    $new     = (string) ($_POST['new_password'] ?? '');
    $confirm = (string) ($_POST['confirm_password'] ?? '');

    if (!password_verify($current, $user['password_hash'])) {
        redirect('/admin/settings?error=' . urlencode('Mật khẩu hiện tại không đúng.'));
    }
    if (strlen($new) < 8) {
        redirect('/admin/settings?error=' . urlencode('Mật khẩu mới phải có ít nhất 8 ký tự.'));
    }
    if ($new !== $confirm) {
        redirect('/admin/settings?error=' . urlencode('Mật khẩu xác nhận không khớp.'));
    }

    $hash = password_hash($new, PASSWORD_BCRYPT);
    db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([$hash, $user['id']]);
    redirect('/admin/settings?saved=1');
}

function ctrlSettingsDeleteAccount()
{
    $user = requireLogin();
    $input = jsonInput();
    if (!csrfCheck($input['csrf'] ?? null)) {
        jsonResponse(['ok' => false, 'error' => 'Phiên làm việc hết hạn.'], 419);
    }
    $typedEmail = trim((string) ($input['email'] ?? ''));
    if ($typedEmail !== $user['email']) {
        jsonResponse(['ok' => false, 'error' => 'Email xác nhận không khớp.'], 422);
    }
    db()->prepare('DELETE FROM users WHERE id = ?')->execute([$user['id']]);
    $_SESSION = [];
    session_destroy();
    jsonResponse(['ok' => true]);
}

// ==========================================================================
// ROUTER
// ==========================================================================

$method = $_SERVER['REQUEST_METHOD'];
$path   = rtrim((string) parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
if ($path === '') {
    $path = '/';
}

try {
    if ($method === 'GET' && $path === '/health') {
        ctrlHealth();
    }
    if ($method === 'GET' && $path === '/') {
        ctrlBio();
    }
    if ($method === 'POST' && $path === '/api/click') {
        ctrlApiClick();
    }
    if ($method === 'POST' && $path === '/api/lead') {
        ctrlApiLead();
    }
    if ($method === 'GET' && $path === '/login') {
        ctrlLoginShow();
    }
    if ($method === 'POST' && $path === '/login') {
        ctrlLoginSubmit();
    }
    if ($path === '/logout') {
        ctrlLogout();
    }

    if ($method === 'GET' && $path === '/admin') {
        ctrlDashboard();
    }
    if ($method === 'GET' && $path === '/admin/profile') {
        ctrlProfileShow();
    }
    if ($method === 'POST' && $path === '/admin/profile') {
        ctrlProfileSave();
    }

    if ($method === 'GET' && $path === '/admin/links') {
        ctrlLinksList();
    }
    if ($method === 'GET' && $path === '/admin/links/create') {
        ctrlLinkCreateShow();
    }
    if ($method === 'POST' && $path === '/admin/links') {
        ctrlLinkCreate();
    }
    if ($method === 'POST' && $path === '/admin/links/reorder') {
        ctrlLinkReorder();
    }
    if ($method === 'GET' && preg_match('#^/admin/links/(\d+)/edit$#', $path, $m)) {
        ctrlLinkEditShow((int) $m[1]);
    }
    if ($method === 'POST' && preg_match('#^/admin/links/(\d+)/update$#', $path, $m)) {
        ctrlLinkUpdate((int) $m[1]);
    }
    if ($method === 'POST' && preg_match('#^/admin/links/(\d+)/toggle$#', $path, $m)) {
        ctrlLinkToggle((int) $m[1]);
    }
    if ($method === 'POST' && preg_match('#^/admin/links/(\d+)/delete$#', $path, $m)) {
        ctrlLinkDelete((int) $m[1]);
    }

    if ($method === 'GET' && $path === '/admin/leads') {
        ctrlLeadsList();
    }
    if ($method === 'GET' && preg_match('#^/admin/leads/(\d+)$#', $path, $m)) {
        ctrlLeadDetail((int) $m[1]);
    }
    if ($method === 'POST' && preg_match('#^/admin/leads/(\d+)/status$#', $path, $m)) {
        ctrlLeadStatus((int) $m[1]);
    }
    if ($method === 'POST' && preg_match('#^/admin/leads/(\d+)/delete$#', $path, $m)) {
        ctrlLeadDelete((int) $m[1]);
    }

    if ($method === 'GET' && $path === '/admin/statistics') {
        ctrlStatistics();
    }

    if ($method === 'GET' && $path === '/admin/settings') {
        ctrlSettingsShow();
    }
    if ($method === 'POST' && $path === '/admin/settings/password') {
        ctrlSettingsPassword();
    }
    if ($method === 'POST' && $path === '/admin/settings/delete-account') {
        ctrlSettingsDeleteAccount();
    }

    http_response_code(404);
    echo '404 Not Found';
} catch (Throwable $e) {
    http_response_code(500);
    error_log('[vn-biolink-hub] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    if ($APP_ENV !== 'production') {
        echo '<pre>' . htmlspecialchars((string) $e, ENT_QUOTES, 'UTF-8') . '</pre>';
    } else {
        echo 'Đã có lỗi xảy ra. Vui lòng thử lại sau.';
    }
}
