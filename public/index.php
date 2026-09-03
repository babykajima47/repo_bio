<?php

declare(strict_types=1);

/**
 * VN BioLink Hub — front controller duy nhất.
 * Toàn bộ Router + Controller + API + Giao diện HTML nằm trong 1 file theo
 * đúng yêu cầu đóng gói Template Vibe Host (PHP thuần, không framework).
 */

// ==========================================================================
// BOOTSTRAP
// ==========================================================================

// Hỗ trợ hosting FTP thuần (không set được biến môi trường kiểu Docker/Vibe
// Host): nếu có config.php cạnh index.php, nó tự putenv() các biến DB_* —
// phần còn lại của app không đổi gì (vẫn đọc qua getenv() như bình thường).
// File này KHÔNG được commit (xem .gitignore) vì chứa thông tin kết nối thật.
if (file_exists(__DIR__ . '/config.php')) {
    require __DIR__ . '/config.php';
}

$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';

// GC lifetime dài để cookie "Ghi nhớ đăng nhập" (30 ngày) thực sự giữ được phiên
// server-side — không chỉ phụ thuộc giá trị mặc định ngắn của PHP (~24 phút).
ini_set('session.gc_maxlifetime', (string) (30 * 86400));

session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'secure'   => $isHttps,
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

// Header bảo mật cơ bản cho mọi response — chống clickjacking + MIME-sniffing.
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');

$APP_ENV = getenv('APP_ENV') ?: 'production';
error_reporting(E_ALL);
ini_set('display_errors', $APP_ENV === 'production' ? '0' : '1');

const UPLOAD_DIR   = __DIR__ . '/uploads';
const UPLOAD_WEB   = '/uploads';
const MAX_AVATAR_BYTES = 2 * 1024 * 1024; // 2MB
const ALLOWED_AVATAR_MIME = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

// icon: emoji trung tính (an toàn, không phụ thuộc font icon ngoài) — hiển thị
// trong 1 chip tròn nền màu thương hiệu để có cảm giác "logo" như mockup.
const LINK_TYPES = [
    'tiktok'    => ['label' => 'TikTok',           'icon' => '🎵', 'color' => '#000000'],
    'shopee'    => ['label' => 'Shopee',           'icon' => '🛒', 'color' => '#EE4D2D'],
    'website'   => ['label' => 'Website cá nhân',  'icon' => '🌐', 'color' => '#0EA5E9'],
    'facebook'  => ['label' => 'Facebook',         'icon' => '📘', 'color' => '#1877F2'],
    'instagram' => ['label' => 'Instagram',        'icon' => '📷', 'color' => '#E4405F'],
    'youtube'   => ['label' => 'YouTube',          'icon' => '▶️', 'color' => '#FF0000'],
    'custom'    => ['label' => 'Liên kết khác',    'icon' => '🔗', 'color' => '#6B7280'],
];

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
        PDO::ATTR_ERRMODE              => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE   => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES     => false,
    ]);

    return $pdo;
}

// ==========================================================================
// HELPERS
// ==========================================================================

function e(?string $value): string
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

function csrfCheck(?string $token): bool
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

/** Trang bio public luôn hiển thị hồ sơ của chủ trang duy nhất (id nhỏ nhất). */
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
    if ($type === 'custom' || in_array($type, array_keys(LINK_TYPES), true)) {
        return preg_match('#^https?://#i', $url) ? $url : 'https://' . ltrim($url, '/');
    }
    return $url;
}

function validThemeColor($value, string $fallback = '#6C4EF6'): string
{
    return (is_string($value) && preg_match('/^#[0-9a-fA-F]{6}$/', $value)) ? $value : $fallback;
}

/** Chip tròn nền màu thương hiệu chứa icon — dùng cho danh sách liên kết ở cả bio page và bảng quản trị. */
function linkChip(string $type, string $sizeClass = 'h-10 w-10 text-lg'): string
{
    $def = LINK_TYPES[$type] ?? LINK_TYPES['custom'];
    return '<span class="' . $sizeClass . ' inline-flex shrink-0 items-center justify-center rounded-full" style="background:' . e($def['color']) . '1a">' . $def['icon'] . '</span>';
}

/** So sánh % giữa 2 kỳ — dùng cho các chỉ số trên Tổng quan (dữ liệu thật, không phải số minh hoạ). */
function pctChange(int $current, int $previous): array
{
    if ($previous === 0) {
        $label = $current > 0 ? '+100%' : '0%';
        return ['label' => $label, 'positive' => $current >= 0];
    }
    $pct = (($current - $previous) / $previous) * 100;
    $sign = $pct >= 0 ? '+' : '';
    return ['label' => $sign . number_format($pct, 1) . '%', 'positive' => $pct >= 0];
}

// ==========================================================================
// LAYOUT
// ==========================================================================

function layoutPublic(string $title, string $bodyHtml, string $bgClass = 'bg-gray-100'): string
{
    return <<<HTML
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{$title}</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="{$bgClass} min-h-screen font-sans antialiased">
{$bodyHtml}
</body>
</html>
HTML;
}

function layoutAdmin(string $title, string $active, string $bodyHtml, array $user, string $headerHtml = ''): string
{
    $nav = [
        'dashboard' => ['/admin', '📊', 'Tổng quan'],
        'profile'   => ['/admin?tab=profile', '👤', 'Hồ sơ cá nhân'],
        'links'     => ['/admin?tab=links', '🔗', 'Liên kết'],
        'leads'     => ['/admin?tab=leads', '📩', 'Khách hàng (Leads)'],
    ];

    $navHtml = '';
    foreach ($nav as $key => [$href, $icon, $label]) {
        $cls = $key === $active
            ? 'bg-white/10 text-white'
            : 'text-slate-400 hover:bg-white/5 hover:text-slate-200';
        $navHtml .= '<a href="' . e($href) . '" class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition ' . $cls . '"><span class="text-base">' . $icon . '</span>' . e($label) . '</a>';
    }

    $email       = e($user['email']);
    $displayName = e($user['display_name']);
    $initial     = e(mb_substr($user['display_name'] ?: 'A', 0, 1));

    return <<<HTML
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{$title}</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen font-sans antialiased">
<div class="flex min-h-screen">
  <aside class="flex w-64 shrink-0 flex-col bg-[#0f172a] px-3 py-5">
    <a href="/admin" class="flex items-center gap-2 px-2 pb-6 text-base font-bold text-white">
      <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-indigo-500">🔗</span>
      VN BioLink Hub
    </a>
    <nav class="flex flex-1 flex-col gap-1">
      {$navHtml}
    </nav>
    <div class="mt-auto flex flex-col gap-2 border-t border-white/10 pt-4">
      <a href="/" target="_blank" class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium text-slate-400 hover:bg-white/5 hover:text-slate-200">
        <span class="text-base">👁</span>Xem trang public
      </a>
      <div class="flex items-center gap-2 rounded-lg px-3 py-2">
        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-indigo-500 text-sm font-bold text-white">{$initial}</span>
        <div class="min-w-0">
          <p class="truncate text-sm font-medium text-white">{$displayName}</p>
          <p class="truncate text-xs text-slate-400">{$email}</p>
        </div>
      </div>
      <a href="/logout" class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium text-red-400 hover:bg-red-500/10">
        <span class="text-base">🚪</span>Đăng xuất
      </a>
    </div>
  </aside>
  <main class="min-w-0 flex-1 px-6 py-6 sm:px-8 sm:py-8">
    {$headerHtml}
    {$bodyHtml}
  </main>
</div>
</body>
</html>
HTML;
}

// ==========================================================================
// VIEWS
// ==========================================================================

function viewBio(array $user, array $links): string
{
    $name     = e($user['display_name']);
    $jobTitle = e($user['job_title'] ?? '');
    $bio      = nl2br(e($user['bio_text'] ?? ''));
    $color    = validThemeColor($user['theme_color']);
    $avatar   = $user['avatar_path'] ?? null;

    $avatarHtml = $avatar
        ? '<img src="' . e($avatar) . '" alt="' . $name . '" class="h-24 w-24 rounded-full object-cover ring-4 ring-white/10">'
        : '<div class="flex h-24 w-24 items-center justify-center rounded-full text-3xl font-bold text-white ring-4 ring-white/10" style="background:' . e($color) . '">' . e(mb_substr($user['display_name'] ?: 'B', 0, 1)) . '</div>';

    // Heroicons "check" — path đơn giản, an toàn để tự viết tay (3 điểm tạo hình dấu tick).
    $verifiedBadge = <<<HTML
<span class="inline-flex h-5 w-5 items-center justify-center rounded-full bg-sky-500 align-middle">
  <svg viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="3" class="h-3 w-3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
</span>
HTML;

    $quickButtons = '';
    if (!empty($user['hotline_phone'])) {
        $quickButtons .= '<a href="tel:' . e(normalizePhoneVn($user['hotline_phone'])) . '" class="flex items-center justify-center gap-2 rounded-full bg-emerald-500 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-emerald-500/20 transition hover:bg-emerald-600">📞 Gọi Hotline</a>';
    }
    if (!empty($user['zalo_phone'])) {
        $quickButtons .= '<a href="' . e(zaloUrl($user['zalo_phone'])) . '" target="_blank" rel="noopener" class="flex items-center justify-center gap-2 rounded-full bg-sky-500 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-sky-500/20 transition hover:bg-sky-600">💬 Nhắn tin Zalo</a>';
    }

    $linksHtml = '';
    foreach ($links as $link) {
        $chip  = linkChip($link['type']);
        $href  = e(linkHref($link['type'], $link['url']));
        $id    = (int) $link['id'];
        $label = e($link['label']);
        $clicks = (int) $link['clicks'];
        $linksHtml .= <<<HTML
<a href="{$href}" target="_blank" rel="noopener" data-link-id="{$id}" class="biolink-item flex items-center gap-3 rounded-2xl bg-white px-4 py-3 text-sm font-semibold text-gray-800 shadow transition hover:-translate-y-0.5 hover:shadow-lg">
  {$chip}
  <span class="flex-1">{$label}</span>
  <span class="text-xs font-normal text-gray-400">{$clicks} lượt click</span>
</a>
HTML;
    }
    if ($links === []) {
        $linksHtml = '<p class="text-center text-sm text-white/50">Chưa có liên kết nào.</p>';
    }

    $csrf = csrfToken();
    $year = date('Y');

    $body = <<<HTML
<div class="min-h-screen bg-[#12122b] pb-10">
  <div class="mx-auto flex w-full max-w-md flex-col items-center px-5 pt-12">
    {$avatarHtml}
    <h1 class="mt-4 flex items-center gap-1.5 text-xl font-bold text-white">{$name} {$verifiedBadge}</h1>
    <p class="mt-0.5 text-sm font-medium text-indigo-300">{$jobTitle}</p>
    <p class="mt-2 max-w-xs text-center text-sm text-slate-400">{$bio}</p>

    <div class="mt-5 grid w-full gap-2 grid-cols-1 sm:grid-cols-2">
      {$quickButtons}
    </div>

    <div class="mt-6 w-full">
      <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">Kết nối với tôi</p>
      <div class="flex w-full flex-col gap-3">
        {$linksHtml}
      </div>
    </div>

    <div class="mt-8 w-full rounded-2xl bg-white p-5 shadow-xl">
      <h2 class="text-base font-bold text-gray-900">Đăng ký tư vấn miễn phí</h2>
      <form id="leadForm" class="mt-4 flex flex-col gap-3">
        <input type="hidden" name="csrf" value="{$csrf}">
        <div class="grid grid-cols-2 gap-3">
          <input required name="name" placeholder="Họ và tên" class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:border-gray-900">
          <input required name="phone" placeholder="Số điện thoại" class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:border-gray-900">
        </div>
        <textarea name="note" placeholder="Ghi chú (không bắt buộc)" rows="2" class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:border-gray-900"></textarea>
        <button type="submit" class="rounded-lg px-4 py-2.5 text-sm font-semibold text-white transition hover:opacity-90" style="background:{$color}">Gửi thông tin</button>
        <p id="leadMsg" class="text-center text-xs"></p>
      </form>
    </div>

    <p class="mt-8 text-[11px] text-slate-500">© {$year} VN BioLink Hub. All rights reserved.</p>
    <p class="text-[11px] text-slate-600">Powered by VN BioLink Hub</p>
  </div>
</div>

<script>
document.querySelectorAll('.biolink-item').forEach(function (el) {
  el.addEventListener('click', function () {
    const id = el.getAttribute('data-link-id');
    fetch('/api/click', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id: id }),
      keepalive: true,
    }).catch(function () {});
  });
});

document.getElementById('leadForm').addEventListener('submit', async function (ev) {
  ev.preventDefault();
  const form = ev.currentTarget;
  const btn = form.querySelector('button[type=submit]');
  const msg = document.getElementById('leadMsg');
  btn.disabled = true;
  msg.textContent = '';
  try {
    const res = await fetch('/api/lead', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        name: form.name.value,
        phone: form.phone.value,
        note: form.note.value,
        csrf: form.csrf.value,
      }),
    });
    const data = await res.json();
    if (data.ok) {
      msg.textContent = data.message;
      msg.className = 'text-center text-xs text-green-600';
      form.reset();
    } else {
      msg.textContent = data.error || 'Có lỗi xảy ra, vui lòng thử lại.';
      msg.className = 'text-center text-xs text-red-600';
    }
  } catch (e) {
    msg.textContent = 'Không kết nối được máy chủ.';
    msg.className = 'text-center text-xs text-red-600';
  } finally {
    btn.disabled = false;
  }
});
</script>
HTML;

    return layoutPublic($name . ' — VN BioLink Hub', $body, 'bg-[#12122b]');
}

function viewLogin(?string $error): string
{
    $csrf = csrfToken();
    $errorHtml = $error ? '<div class="mt-4 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">' . e($error) . '</div>' : '';
    $year = date('Y');

    $body = <<<HTML
<div class="flex min-h-screen items-center justify-center px-4">
  <div class="w-full max-w-sm rounded-2xl bg-white p-8 shadow-lg">
    <div class="flex flex-col items-center text-center">
      <span class="flex h-12 w-12 items-center justify-center rounded-xl bg-indigo-500 text-2xl">🔗</span>
      <h1 class="mt-3 text-lg font-bold text-gray-900">VN BioLink Hub</h1>
      <p class="mt-1 text-sm text-gray-500">Đăng nhập để tiếp tục quản trị hệ thống</p>
    </div>
    {$errorHtml}
    <form method="post" action="/login" class="mt-6 flex flex-col gap-4">
      <input type="hidden" name="csrf" value="{$csrf}">
      <div>
        <label class="text-xs font-medium text-gray-600">Email</label>
        <input required type="email" name="email" autocomplete="username" placeholder="Nhập email của bạn"
               class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:outline-none focus:border-indigo-500">
      </div>
      <div>
        <label class="text-xs font-medium text-gray-600">Mật khẩu</label>
        <div class="relative mt-1">
          <input required type="password" id="passwordInput" name="password" autocomplete="current-password" placeholder="Nhập mật khẩu"
                 class="w-full rounded-lg border border-gray-300 px-3 py-2.5 pr-16 text-sm focus:outline-none focus:border-indigo-500">
          <button type="button" id="togglePassword" class="absolute right-3 top-1/2 -translate-y-1/2 text-xs font-semibold text-indigo-600">Hiện</button>
        </div>
      </div>
      <label class="flex items-center gap-2 text-sm text-gray-600">
        <input type="checkbox" name="remember" value="1" class="rounded border-gray-300">
        Ghi nhớ đăng nhập
      </label>
      <button type="submit" class="mt-1 rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-indigo-700">Đăng nhập</button>
    </form>
    <p class="mt-6 text-center text-xs text-gray-400">© {$year} VN BioLink Hub</p>
  </div>
</div>
<script>
document.getElementById('togglePassword').addEventListener('click', function () {
  const input = document.getElementById('passwordInput');
  const isHidden = input.type === 'password';
  input.type = isHidden ? 'text' : 'password';
  this.textContent = isHidden ? 'Ẩn' : 'Hiện';
});
</script>
HTML;

    return layoutPublic('Đăng nhập — VN BioLink Hub', $body);
}

function statCard(string $label, string $value, ?array $change, string $accent): string
{
    $changeHtml = '';
    if ($change !== null) {
        $cls = $change['positive'] ? 'text-emerald-600' : 'text-red-500';
        $changeHtml = '<p class="mt-1 text-xs font-medium ' . $cls . '">' . e($change['label']) . ' so với trước</p>';
    }
    return <<<HTML
<div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-100">
  <p class="text-xs font-medium text-gray-500">{$label}</p>
  <p class="mt-2 text-2xl font-bold" style="color:{$accent}">{$value}</p>
  {$changeHtml}
</div>
HTML;
}

function viewAdminDashboard(array $user, array $stats): string
{
    $color = validThemeColor($user['theme_color']);

    $cards = statCard('Tổng lượt click', number_format($stats['totalClicks']), $stats['clicksChange'], $color)
        . statCard('Tổng Leads', number_format($stats['totalLeads']), $stats['leadsChange'], $color)
        . statCard('Liên kết', number_format($stats['totalLinks']), ['label' => '+' . $stats['linksNew'], 'positive' => true], $color)
        . statCard('Lượt click hôm nay', number_format($stats['clicksToday']), $stats['todayChange'], $color);

    $labelsJson = json_encode($stats['chartLabels'], JSON_UNESCAPED_UNICODE);
    $dataJson   = json_encode($stats['chartData']);

    $body = <<<HTML
<div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
  {$cards}
</div>

<div class="mt-6 rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-100">
  <h2 class="text-sm font-bold text-gray-900">Biểu đồ lượt click 7 ngày qua</h2>
  <div class="mt-4" style="height:260px">
    <canvas id="clicksChart"></canvas>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script>
new Chart(document.getElementById('clicksChart').getContext('2d'), {
  type: 'line',
  data: {
    labels: {$labelsJson},
    datasets: [{
      label: 'Lượt click',
      data: {$dataJson},
      borderColor: '{$color}',
      backgroundColor: '{$color}22',
      tension: 0.35,
      fill: true,
      pointRadius: 3,
    }],
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { display: false } },
    scales: { y: { beginAtZero: true, ticks: { precision: 0 } } },
  },
});
</script>
HTML;

    $header = '<div class="mb-6"><h1 class="text-xl font-bold text-gray-900">Tổng quan</h1><p class="text-sm text-gray-500">Chào mừng bạn quay trở lại!</p></div>';

    return layoutAdmin('Tổng quan — Admin', 'dashboard', $body, $user, $header);
}

function viewAdminProfile(array $user, bool $saved): string
{
    $csrf = csrfToken();
    $savedHtml = $saved ? '<div class="mb-4 rounded-lg bg-green-50 px-3 py-2 text-sm text-green-700">Đã lưu thay đổi.</div>' : '';

    $avatarPreview = !empty($user['avatar_path'])
        ? '<img src="' . e($user['avatar_path']) . '" class="h-16 w-16 rounded-full object-cover ring-1 ring-gray-200">'
        : '<div class="flex h-16 w-16 items-center justify-center rounded-full bg-gray-100 text-xl font-bold text-gray-400 ring-1 ring-gray-200">' . e(mb_substr($user['display_name'] ?: 'A', 0, 1)) . '</div>';

    $displayName  = e($user['display_name']);
    $jobTitle     = e($user['job_title'] ?? '');
    $bioText      = e($user['bio_text']);
    $hotlinePhone = e($user['hotline_phone']);
    $zaloPhone    = e($user['zalo_phone']);
    $themeColor   = e(validThemeColor($user['theme_color']));

    $body = <<<HTML
{$savedHtml}
<form method="post" action="/admin/profile" enctype="multipart/form-data" class="flex flex-col gap-5 rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-100">
  <input type="hidden" name="csrf" value="{$csrf}">

  <div>
    <label class="text-xs font-medium text-gray-600">Avatar</label>
    <div class="mt-2 flex items-center gap-4">
      {$avatarPreview}
      <div>
        <label for="avatarInput" class="cursor-pointer rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50">Chọn ảnh</label>
        <input id="avatarInput" type="file" name="avatar" accept="image/png,image/jpeg,image/webp" class="hidden">
        <p class="mt-1 text-[11px] text-gray-400">JPG, PNG tối đa 2MB</p>
      </div>
    </div>
  </div>

  <div>
    <label class="text-xs font-medium text-gray-600">Họ và tên</label>
    <input name="display_name" value="{$displayName}" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:border-indigo-500">
  </div>

  <div>
    <label class="text-xs font-medium text-gray-600">Chức danh</label>
    <input name="job_title" value="{$jobTitle}" placeholder="VD: Chuyên viên Marketing" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:border-indigo-500">
  </div>

  <div>
    <label class="text-xs font-medium text-gray-600">Giới thiệu</label>
    <textarea name="bio_text" rows="3" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:border-indigo-500">{$bioText}</textarea>
  </div>

  <div>
    <label class="text-xs font-medium text-gray-600">Số điện thoại</label>
    <input name="hotline_phone" value="{$hotlinePhone}" placeholder="09xxxxxxxx" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:border-indigo-500">
  </div>

  <div>
    <label class="text-xs font-medium text-gray-600">Zalo</label>
    <input name="zalo_phone" value="{$zaloPhone}" placeholder="09xxxxxxxx" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:border-indigo-500">
  </div>

  <div>
    <label class="text-xs font-medium text-gray-600">Màu chủ đạo</label>
    <div class="mt-1 flex items-center gap-2">
      <input type="color" name="theme_color" value="{$themeColor}" class="h-9 w-14 rounded-lg border border-gray-300">
      <span class="rounded-lg bg-gray-100 px-2 py-1 text-xs font-mono text-gray-600">{$themeColor}</span>
    </div>
  </div>

  <button type="submit" class="self-start rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700">Lưu thay đổi</button>
</form>

<script>
document.getElementById('avatarInput').addEventListener('change', function () {
  const label = document.querySelector('label[for="avatarInput"]');
  if (this.files && this.files[0]) {
    label.textContent = this.files[0].name;
  }
});
</script>
HTML;

    $header = '<div class="mb-6"><h1 class="text-xl font-bold text-gray-900">Hồ sơ cá nhân</h1></div>';

    return layoutAdmin('Hồ sơ cá nhân — Admin', 'profile', $body, $user, $header);
}

function viewAdminLinks(array $links, array $user, ?string $error): string
{
    $csrf = csrfToken();
    $errorHtml = $error ? '<div class="mb-4 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">' . e($error) . '</div>' : '';

    $options = '';
    foreach (LINK_TYPES as $key => $def) {
        $options .= '<option value="' . e($key) . '">' . $def['icon'] . ' ' . e($def['label']) . '</option>';
    }

    $rows = '';
    $stt = 1;
    foreach ($links as $link) {
        $chip = linkChip($link['type'], 'h-9 w-9 text-base');
        $linkId    = (int) $link['id'];
        $activeLabel = $link['is_active'] ? 'Đang hiện' : 'Đang ẩn';
        $activeClass = $link['is_active'] ? 'bg-green-50 text-green-700' : 'bg-gray-100 text-gray-500';

        $rows .= <<<HTML
<tr class="border-t border-gray-100">
  <td class="px-4 py-3 text-gray-500">{$stt}</td>
  <td class="px-4 py-3">
    <div class="flex items-center gap-2 font-medium text-gray-900">{$chip} {$link['labelSafe']}</div>
  </td>
  <td class="px-4 py-3 max-w-xs truncate text-gray-500">{$link['urlSafe']}</td>
  <td class="px-4 py-3 text-gray-600">{$link['clicks']}</td>
  <td class="px-4 py-3">
    <div class="flex items-center gap-3">
      <button type="button" class="editLinkBtn text-xs font-semibold text-indigo-600 hover:underline"
              data-id="{$linkId}" data-type="{$link['type']}" data-label="{$link['labelAttr']}" data-url="{$link['urlAttr']}">Sửa</button>
      <form method="post" action="/admin/links/{$linkId}/toggle">
        <input type="hidden" name="csrf" value="{$csrf}">
        <button class="rounded px-1.5 py-0.5 text-xs font-semibold {$activeClass}">{$activeLabel}</button>
      </form>
      <form method="post" action="/admin/links/{$linkId}/delete" onsubmit="return confirm('Xoá liên kết này?');">
        <input type="hidden" name="csrf" value="{$csrf}">
        <button class="text-xs font-semibold text-red-600 hover:underline">Xoá</button>
      </form>
    </div>
  </td>
</tr>
HTML;
        $stt++;
    }
    if ($links === []) {
        $rows = '<tr><td colspan="5" class="px-4 py-8 text-center text-gray-400">Chưa có liên kết nào, bấm "+ Thêm liên kết" để bắt đầu.</td></tr>';
    }

    $body = <<<HTML
{$errorHtml}
<div class="overflow-x-auto rounded-xl bg-white shadow-sm ring-1 ring-gray-100">
  <table class="w-full text-left text-sm">
    <thead class="bg-gray-50 text-xs text-gray-500">
      <tr>
        <th class="px-4 py-3 font-medium">STT</th>
        <th class="px-4 py-3 font-medium">Tên liên kết</th>
        <th class="px-4 py-3 font-medium">URL</th>
        <th class="px-4 py-3 font-medium">Lượt click</th>
        <th class="px-4 py-3 font-medium">Thao tác</th>
      </tr>
    </thead>
    <tbody>{$rows}</tbody>
  </table>
</div>

<!-- Modal thêm/sửa liên kết -->
<div id="linkModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 px-4">
  <div class="w-full max-w-md rounded-xl bg-white p-6 shadow-xl">
    <h3 id="linkModalTitle" class="text-sm font-bold text-gray-900">Thêm liên kết mới</h3>
    <form id="linkForm" method="post" action="/admin/links" class="mt-4 flex flex-col gap-3">
      <input type="hidden" name="csrf" value="{$csrf}">
      <div>
        <label class="text-xs font-medium text-gray-600">Loại liên kết</label>
        <select name="type" id="linkFormType" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">{$options}</select>
      </div>
      <div>
        <label class="text-xs font-medium text-gray-600">Tên liên kết</label>
        <input name="label" id="linkFormLabel" placeholder="VD: Shop trên Shopee" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
      </div>
      <div>
        <label class="text-xs font-medium text-gray-600">URL</label>
        <input name="url" id="linkFormUrl" placeholder="https://..." class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
      </div>
      <div class="mt-2 flex justify-end gap-2">
        <button type="button" id="linkModalCancel" class="rounded-lg px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-100">Huỷ</button>
        <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Lưu</button>
      </div>
    </form>
  </div>
</div>

<script>
const modal = document.getElementById('linkModal');
const form = document.getElementById('linkForm');
const title = document.getElementById('linkModalTitle');

function openModal(mode, data) {
  modal.classList.remove('hidden');
  modal.classList.add('flex');
  if (mode === 'create') {
    title.textContent = 'Thêm liên kết mới';
    form.action = '/admin/links';
    form.type.value = 'custom';
    form.label.value = '';
    form.url.value = '';
  } else {
    title.textContent = 'Sửa liên kết';
    form.action = '/admin/links/' + data.id + '/update';
    form.type.value = data.type;
    form.label.value = data.label;
    form.url.value = data.url;
  }
}

document.getElementById('addLinkBtn')?.addEventListener('click', function () { openModal('create'); });
document.getElementById('linkModalCancel').addEventListener('click', function () {
  modal.classList.add('hidden');
  modal.classList.remove('flex');
});
document.querySelectorAll('.editLinkBtn').forEach(function (btn) {
  btn.addEventListener('click', function () {
    openModal('edit', {
      id: this.dataset.id,
      type: this.dataset.type,
      label: this.dataset.label,
      url: this.dataset.url,
    });
  });
});
</script>
HTML;

    $header = <<<HTML
<div class="mb-6 flex items-center justify-between">
  <h1 class="text-xl font-bold text-gray-900">Quản lý liên kết</h1>
  <button id="addLinkBtn" type="button" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">+ Thêm liên kết</button>
</div>
HTML;

    return layoutAdmin('Quản lý liên kết — Admin', 'links', $body, $user, $header);
}

function viewAdminLeads(array $leads, array $user): string
{
    $csrf = csrfToken();
    $rows = '';
    $stt = 1;
    foreach ($leads as $lead) {
        $leadId    = (int) $lead['id'];
        $leadName  = e($lead['name']);
        $leadPhone = e($lead['phone']);
        $leadNote  = e($lead['note']);
        $leadCreatedAt = e($lead['created_at']);

        $rows .= <<<HTML
<tr class="border-t border-gray-100">
  <td class="px-4 py-3 text-gray-500">{$stt}</td>
  <td class="px-4 py-3 font-medium text-gray-900">{$leadName}</td>
  <td class="px-4 py-3"><a class="text-indigo-600 hover:underline" href="tel:{$leadPhone}">{$leadPhone}</a></td>
  <td class="px-4 py-3 text-gray-600">{$leadNote}</td>
  <td class="px-4 py-3 text-gray-400">{$leadCreatedAt}</td>
  <td class="px-4 py-3">
    <form method="post" action="/admin/leads/{$leadId}/delete" onsubmit="return confirm('Xoá lead này?');">
      <input type="hidden" name="csrf" value="{$csrf}">
      <button class="text-xs font-semibold text-red-600 hover:underline">Xoá</button>
    </form>
  </td>
</tr>
HTML;
        $stt++;
    }
    if ($leads === []) {
        $rows = '<tr><td colspan="6" class="px-4 py-8 text-center text-gray-400">Chưa có lead nào.</td></tr>';
    }

    $body = <<<HTML
<div class="overflow-x-auto rounded-xl bg-white shadow-sm ring-1 ring-gray-100">
  <table class="w-full text-left text-sm">
    <thead class="bg-gray-50 text-xs text-gray-500">
      <tr>
        <th class="px-4 py-3 font-medium">STT</th>
        <th class="px-4 py-3 font-medium">Họ và tên</th>
        <th class="px-4 py-3 font-medium">Số điện thoại</th>
        <th class="px-4 py-3 font-medium">Ghi chú</th>
        <th class="px-4 py-3 font-medium">Thời gian</th>
        <th class="px-4 py-3 font-medium"></th>
      </tr>
    </thead>
    <tbody>{$rows}</tbody>
  </table>
</div>
HTML;

    $header = '<div class="mb-6"><h1 class="text-xl font-bold text-gray-900">Khách hàng (Leads)</h1></div>';

    return layoutAdmin('Khách hàng — Admin', 'leads', $body, $user, $header);
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
    $input = json_decode(file_get_contents('php://input') ?: '[]', true);
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
    $input = json_decode(file_get_contents('php://input') ?: '[]', true);

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

    $stmt = db()->prepare('INSERT INTO leads (name, phone, note) VALUES (?, ?, ?)');
    $stmt->execute([$name, $phone, $note !== '' ? $note : null]);

    jsonResponse(['ok' => true, 'message' => 'Cảm ơn bạn! Chúng tôi sẽ liên hệ lại sớm nhất.']);
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

    // "Ghi nhớ đăng nhập" — giữ cookie session qua 30 ngày thay vì chỉ tới khi đóng trình duyệt.
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

function buildDashboardStats(int $userId): array
{
    $pdo = db();

    $totalClicks = (int) $pdo->query('SELECT COALESCE(SUM(clicks),0) c FROM links')->fetch()['c'];
    $totalLeads  = (int) $pdo->query('SELECT COUNT(*) c FROM leads')->fetch()['c'];

    $stmt = $pdo->prepare('SELECT COUNT(*) c FROM links WHERE user_id = ? AND is_active = 1');
    $stmt->execute([$userId]);
    $totalLinks = (int) $stmt->fetch()['c'];

    $stmt = $pdo->prepare('SELECT COUNT(*) c FROM links WHERE user_id = ? AND created_at >= (NOW() - INTERVAL 30 DAY)');
    $stmt->execute([$userId]);
    $linksNew = (int) $stmt->fetch()['c'];

    $clicksLast30 = (int) $pdo->query("SELECT COUNT(*) c FROM link_clicks WHERE created_at >= (NOW() - INTERVAL 30 DAY)")->fetch()['c'];
    $clicksPrev30 = (int) $pdo->query("SELECT COUNT(*) c FROM link_clicks WHERE created_at >= (NOW() - INTERVAL 60 DAY) AND created_at < (NOW() - INTERVAL 30 DAY)")->fetch()['c'];

    $leadsLast30 = (int) $pdo->query("SELECT COUNT(*) c FROM leads WHERE created_at >= (NOW() - INTERVAL 30 DAY)")->fetch()['c'];
    $leadsPrev30 = (int) $pdo->query("SELECT COUNT(*) c FROM leads WHERE created_at >= (NOW() - INTERVAL 60 DAY) AND created_at < (NOW() - INTERVAL 30 DAY)")->fetch()['c'];

    $clicksToday     = (int) $pdo->query('SELECT COUNT(*) c FROM link_clicks WHERE DATE(created_at) = CURDATE()')->fetch()['c'];
    $clicksYesterday = (int) $pdo->query('SELECT COUNT(*) c FROM link_clicks WHERE DATE(created_at) = CURDATE() - INTERVAL 1 DAY')->fetch()['c'];

    $byDay = $pdo->query(
        "SELECT DATE(created_at) d, COUNT(*) c FROM link_clicks
         WHERE created_at >= (CURDATE() - INTERVAL 6 DAY) GROUP BY d"
    )->fetchAll(PDO::FETCH_KEY_PAIR);

    $chartLabels = [];
    $chartData   = [];
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-{$i} day"));
        $chartLabels[] = date('d/m', strtotime($date));
        $chartData[]   = (int) ($byDay[$date] ?? 0);
    }

    return [
        'totalClicks'  => $totalClicks,
        'totalLeads'   => $totalLeads,
        'totalLinks'   => $totalLinks,
        'linksNew'     => $linksNew,
        'clicksToday'  => $clicksToday,
        'clicksChange' => pctChange($clicksLast30, $clicksPrev30),
        'leadsChange'  => pctChange($leadsLast30, $leadsPrev30),
        'todayChange'  => pctChange($clicksToday, $clicksYesterday),
        'chartLabels'  => $chartLabels,
        'chartData'    => $chartData,
    ];
}

function ctrlAdminDashboard()
{
    $user = requireLogin();
    $tab  = $_GET['tab'] ?? 'dashboard';

    if ($tab === 'links') {
        $stmt = db()->prepare('SELECT * FROM links WHERE user_id = ? ORDER BY position ASC, id ASC');
        $stmt->execute([$user['id']]);
        $links = $stmt->fetchAll();
        foreach ($links as &$link) {
            $link['labelSafe'] = e($link['label']);
            $link['urlSafe']   = e($link['url']);
            $link['labelAttr'] = e($link['label']);
            $link['urlAttr']   = e($link['url']);
            $link['clicks']    = (int) $link['clicks'];
        }
        unset($link);
        echo viewAdminLinks($links, $user, $_GET['error'] ?? null);
        exit;
    }

    if ($tab === 'leads') {
        $leads = db()->query('SELECT * FROM leads ORDER BY id DESC')->fetchAll();
        echo viewAdminLeads($leads, $user);
        exit;
    }

    if ($tab === 'profile') {
        echo viewAdminProfile($user, isset($_GET['saved']));
        exit;
    }

    echo viewAdminDashboard($user, buildDashboardStats((int) $user['id']));
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

function ctrlAdminProfileSave()
{
    $user = requireLogin();

    if (!csrfCheck($_POST['csrf'] ?? null)) {
        redirect('/admin?tab=profile');
    }

    $avatarPath = null;
    if (!empty($_FILES['avatar']['name'])) {
        try {
            $avatarPath = handleAvatarUpload($_FILES['avatar']);
        } catch (RuntimeException $e) {
            redirect('/admin?tab=profile&error=' . urlencode($e->getMessage()));
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
    redirect('/admin?tab=profile&saved=1');
}

function readLinkInput(): array
{
    $type  = (string) ($_POST['type'] ?? 'custom');
    if (!array_key_exists($type, LINK_TYPES)) {
        $type = 'custom';
    }
    $label = trim((string) ($_POST['label'] ?? ''));
    $url   = trim((string) ($_POST['url'] ?? ''));

    if ($label === '') {
        $label = LINK_TYPES[$type]['label'];
    }
    if ($url === '') {
        redirect('/admin?tab=links&error=' . urlencode('Vui lòng nhập URL cho liên kết.'));
    }

    return [$type, $label, $url];
}

function ctrlAdminLinkCreate()
{
    $user = requireLogin();
    if (!csrfCheck($_POST['csrf'] ?? null)) {
        redirect('/admin?tab=links');
    }
    [$type, $label, $url] = readLinkInput();

    $maxPos = (int) db()->query('SELECT COALESCE(MAX(position), -1) m FROM links')->fetch()['m'];
    $stmt = db()->prepare('INSERT INTO links (user_id, type, label, url, position) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([$user['id'], $type, $label, $url, $maxPos + 1]);

    redirect('/admin?tab=links');
}

function ctrlAdminLinkUpdate(int $id)
{
    requireLogin();
    if (!csrfCheck($_POST['csrf'] ?? null)) {
        redirect('/admin?tab=links');
    }
    [$type, $label, $url] = readLinkInput();

    $stmt = db()->prepare('UPDATE links SET type = ?, label = ?, url = ? WHERE id = ?');
    $stmt->execute([$type, $label, $url, $id]);

    redirect('/admin?tab=links');
}

function ctrlAdminLinkToggle(int $id)
{
    requireLogin();
    if (!csrfCheck($_POST['csrf'] ?? null)) {
        redirect('/admin?tab=links');
    }
    db()->prepare('UPDATE links SET is_active = 1 - is_active WHERE id = ?')->execute([$id]);
    redirect('/admin?tab=links');
}

function ctrlAdminLinkDelete(int $id)
{
    requireLogin();
    if (!csrfCheck($_POST['csrf'] ?? null)) {
        redirect('/admin?tab=links');
    }
    db()->prepare('DELETE FROM links WHERE id = ?')->execute([$id]);
    redirect('/admin?tab=links');
}

function ctrlAdminLeadDelete(int $id)
{
    requireLogin();
    if (!csrfCheck($_POST['csrf'] ?? null)) {
        redirect('/admin?tab=leads');
    }
    db()->prepare('DELETE FROM leads WHERE id = ?')->execute([$id]);
    redirect('/admin?tab=leads');
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
        ctrlAdminDashboard();
    }
    if ($method === 'POST' && $path === '/admin/profile') {
        ctrlAdminProfileSave();
    }
    if ($method === 'POST' && $path === '/admin/links') {
        ctrlAdminLinkCreate();
    }
    if ($method === 'POST' && preg_match('#^/admin/links/(\d+)/update$#', $path, $m)) {
        ctrlAdminLinkUpdate((int) $m[1]);
    }
    if ($method === 'POST' && preg_match('#^/admin/links/(\d+)/toggle$#', $path, $m)) {
        ctrlAdminLinkToggle((int) $m[1]);
    }
    if ($method === 'POST' && preg_match('#^/admin/links/(\d+)/delete$#', $path, $m)) {
        ctrlAdminLinkDelete((int) $m[1]);
    }
    if ($method === 'POST' && preg_match('#^/admin/leads/(\d+)/delete$#', $path, $m)) {
        ctrlAdminLeadDelete((int) $m[1]);
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
