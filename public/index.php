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

$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';

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

const LINK_TYPES = [
    'tiktok'    => ['label' => 'TikTok',            'icon' => '🎵'],
    'shopee'    => ['label' => 'Shopee',            'icon' => '🛒'],
    'website'   => ['label' => 'Website',           'icon' => '🌐'],
    'facebook'  => ['label' => 'Facebook',          'icon' => '📘'],
    'instagram' => ['label' => 'Instagram',         'icon' => '📷'],
    'youtube'   => ['label' => 'YouTube',           'icon' => '▶️'],
    'custom'    => ['label' => 'Liên kết khác',     'icon' => '🔗'],
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

function jsonResponse(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function redirect(string $to): never
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
    if (str_starts_with($digits, '84')) {
        $digits = '0' . substr($digits, 2);
    }
    return $digits;
}

function zaloUrl(string $phone): string
{
    $digits = normalizePhoneVn($phone);
    $intl   = str_starts_with($digits, '0') ? '84' . substr($digits, 1) : $digits;
    return 'https://zalo.me/' . $intl;
}

function linkHref(string $type, string $url): string
{
    if ($type === 'custom' || in_array($type, array_keys(LINK_TYPES), true)) {
        return preg_match('#^https?://#i', $url) ? $url : 'https://' . ltrim($url, '/');
    }
    return $url;
}

// ==========================================================================
// LAYOUT
// ==========================================================================

function layoutPublic(string $title, string $bodyHtml): string
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
<body class="bg-gray-100 min-h-screen font-sans antialiased">
{$bodyHtml}
</body>
</html>
HTML;
}

function layoutAdmin(string $title, string $active, string $bodyHtml, array $user): string
{
    $nav = [
        'profile' => ['/admin?tab=profile', '🎨 Hồ sơ & Giao diện'],
        'links'   => ['/admin?tab=links', '🔗 Quản lý liên kết'],
        'leads'   => ['/admin?tab=leads', '📩 Khách hàng (Leads)'],
    ];

    $navHtml = '';
    foreach ($nav as $key => [$href, $label]) {
        $cls = $key === $active
            ? 'bg-gray-900 text-white'
            : 'text-gray-600 hover:bg-gray-100';
        $navHtml .= '<a href="' . e($href) . '" class="rounded-lg px-3 py-1.5 text-sm font-medium transition ' . $cls . '">' . e($label) . '</a>';
    }

    $email = e($user['email']);

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
<header class="border-b border-gray-200 bg-white">
  <div class="mx-auto flex max-w-5xl flex-wrap items-center justify-between gap-3 px-4 py-3">
    <a href="/admin" class="text-base font-bold text-gray-900">🔗 VN BioLink Hub</a>
    <nav class="flex flex-wrap gap-1 text-sm items-center">
      {$navHtml}
      <a href="/" target="_blank" class="rounded-lg px-3 py-1.5 font-medium text-gray-600 hover:bg-gray-100">👁 Xem trang</a>
      <span class="px-2 text-xs text-gray-400">{$email}</span>
      <a href="/logout" class="rounded-lg px-3 py-1.5 font-medium text-red-600 hover:bg-red-50">Đăng xuất</a>
    </nav>
  </div>
</header>
<main class="mx-auto max-w-3xl px-4 py-6">
{$bodyHtml}
</main>
</body>
</html>
HTML;
}

// ==========================================================================
// VIEWS
// ==========================================================================

function viewBio(array $user, array $links): string
{
    $name  = e($user['display_name']);
    $bio   = nl2br(e($user['bio_text'] ?? ''));
    $color = preg_match('/^#[0-9a-fA-F]{6}$/', (string) $user['theme_color']) ? $user['theme_color'] : '#4f46e5';
    $avatar = $user['avatar_path'] ?? null;

    $avatarHtml = $avatar
        ? '<img src="' . e($avatar) . '" alt="' . $name . '" class="h-24 w-24 rounded-full object-cover ring-4 ring-white shadow-xl mx-auto">'
        : '<div class="mx-auto flex h-24 w-24 items-center justify-center rounded-full text-3xl font-bold text-white ring-4 ring-white shadow-xl" style="background:' . e($color) . '">' . e(mb_substr($user['display_name'] ?: 'B', 0, 1)) . '</div>';

    $quickButtons = '';
    if (!empty($user['hotline_phone'])) {
        $quickButtons .= '<a href="tel:' . e(normalizePhoneVn($user['hotline_phone'])) . '" class="flex items-center justify-center gap-2 rounded-xl px-4 py-3 text-sm font-semibold text-white shadow" style="background:' . e($color) . '">📞 Gọi Hotline</a>';
    }
    if (!empty($user['zalo_phone'])) {
        $quickButtons .= '<a href="' . e(zaloUrl($user['zalo_phone'])) . '" target="_blank" rel="noopener" class="flex items-center justify-center gap-2 rounded-xl border-2 px-4 py-3 text-sm font-semibold" style="border-color:' . e($color) . ';color:' . e($color) . '">💬 Nhắn tin Zalo</a>';
    }

    $linksHtml = '';
    foreach ($links as $link) {
        $icon  = LINK_TYPES[$link['type']]['icon'] ?? '🔗';
        $href  = e(linkHref($link['type'], $link['url']));
        $id    = (int) $link['id'];
        $label = e($link['label']);
        $linksHtml .= <<<HTML
<a href="{$href}" target="_blank" rel="noopener" data-link-id="{$id}" class="biolink-item flex items-center justify-center gap-2 rounded-xl bg-white px-4 py-3 text-sm font-semibold text-gray-800 shadow transition hover:-translate-y-0.5 hover:shadow-md">
  <span>{$icon}</span><span>{$label}</span>
</a>
HTML;
    }
    if ($links === []) {
        $linksHtml = '<p class="text-center text-sm text-gray-400">Chưa có liên kết nào.</p>';
    }

    $csrf = csrfToken();

    $body = <<<HTML
<div class="mx-auto flex min-h-screen w-full max-w-md flex-col items-center px-5 py-10">
  {$avatarHtml}
  <h1 class="mt-4 text-xl font-bold text-gray-900">{$name}</h1>
  <p class="mt-1 max-w-xs text-center text-sm text-gray-500">{$bio}</p>

  <div class="mt-5 grid w-full gap-2 grid-cols-1 sm:grid-cols-2">
    {$quickButtons}
  </div>

  <div class="mt-6 flex w-full flex-col gap-3">
    {$linksHtml}
  </div>

  <div class="mt-8 w-full rounded-2xl bg-white p-5 shadow-xl">
    <h2 class="text-base font-bold text-gray-900">📝 Để lại thông tin nhận tư vấn</h2>
    <form id="leadForm" class="mt-4 flex flex-col gap-3">
      <input required name="name" placeholder="Họ và tên" class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:border-gray-900">
      <input required name="phone" placeholder="Số điện thoại" class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:border-gray-900">
      <textarea name="note" placeholder="Ghi chú (không bắt buộc)" rows="2" class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:border-gray-900"></textarea>
      <button type="submit" class="rounded-lg px-4 py-2.5 text-sm font-semibold text-white" style="background:{$color}">Gửi thông tin</button>
      <p id="leadMsg" class="text-center text-xs"></p>
    </form>
  </div>

  <p class="mt-8 text-[11px] text-gray-400">Được tạo bởi VN BioLink Hub</p>
</div>

<script>
const CSRF = "{$csrf}";

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
        csrf: CSRF,
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

    return layoutPublic($name . ' — VN BioLink Hub', $body);
}

function viewLogin(?string $error): string
{
    $csrf = csrfToken();
    $errorHtml = $error ? '<div class="mt-4 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">' . e($error) . '</div>' : '';

    $body = <<<HTML
<div class="flex min-h-screen items-center justify-center px-4">
  <div class="w-full max-w-sm rounded-2xl bg-white p-6 shadow-lg">
    <h1 class="text-lg font-bold text-gray-900">🔗 VN BioLink Hub</h1>
    <p class="mt-1 text-sm text-gray-500">Đăng nhập quản trị.</p>
    {$errorHtml}
    <form method="post" action="/login" class="mt-5 flex flex-col gap-3">
      <input type="hidden" name="csrf" value="{$csrf}">
      <div>
        <label class="text-xs font-medium text-gray-600">Email</label>
        <input required type="email" name="email" autocomplete="username" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:border-gray-900">
      </div>
      <div>
        <label class="text-xs font-medium text-gray-600">Mật khẩu</label>
        <input required type="password" name="password" autocomplete="current-password" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:border-gray-900">
      </div>
      <button type="submit" class="mt-2 rounded-lg bg-gray-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-black">Đăng nhập</button>
    </form>
  </div>
</div>
HTML;

    return layoutPublic('Đăng nhập — VN BioLink Hub', $body);
}

function viewAdminProfile(array $user, bool $saved): string
{
    $csrf = csrfToken();
    $savedHtml = $saved ? '<div class="mb-4 rounded-lg bg-green-50 px-3 py-2 text-sm text-green-700">Đã lưu thay đổi.</div>' : '';
    $avatarPreview = !empty($user['avatar_path'])
        ? '<img src="' . e($user['avatar_path']) . '" class="mt-2 h-16 w-16 rounded-full object-cover">'
        : '';

    $displayName  = e($user['display_name']);
    $bioText      = e($user['bio_text']);
    $hotlinePhone = e($user['hotline_phone']);
    $zaloPhone    = e($user['zalo_phone']);
    $themeColor   = e($user['theme_color']);

    $body = <<<HTML
{$savedHtml}
<form method="post" action="/admin/profile" enctype="multipart/form-data" class="flex flex-col gap-5 rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-100">
  <input type="hidden" name="csrf" value="{$csrf}">
  <div>
    <label class="text-xs font-medium text-gray-600">Họ tên hiển thị</label>
    <input name="display_name" value="{$displayName}" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:border-gray-900">
  </div>
  <div>
    <label class="text-xs font-medium text-gray-600">Giới thiệu ngắn</label>
    <textarea name="bio_text" rows="3" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:border-gray-900">{$bioText}</textarea>
  </div>
  <div>
    <label class="text-xs font-medium text-gray-600">Ảnh đại diện</label>
    {$avatarPreview}
    <input type="file" name="avatar" accept="image/png,image/jpeg,image/webp" class="mt-1 w-full text-sm">
    <p class="mt-1 text-xs text-gray-400">JPG/PNG/WEBP, tối đa 2MB.</p>
  </div>
  <div class="grid grid-cols-2 gap-3">
    <div>
      <label class="text-xs font-medium text-gray-600">Số điện thoại Hotline</label>
      <input name="hotline_phone" value="{$hotlinePhone}" placeholder="09xxxxxxxx" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:border-gray-900">
    </div>
    <div>
      <label class="text-xs font-medium text-gray-600">Số điện thoại Zalo</label>
      <input name="zalo_phone" value="{$zaloPhone}" placeholder="09xxxxxxxx" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:border-gray-900">
    </div>
  </div>
  <div>
    <label class="text-xs font-medium text-gray-600">Màu chủ đạo</label>
    <input type="color" name="theme_color" value="{$themeColor}" class="mt-1 h-10 w-20 rounded-lg border border-gray-300">
  </div>
  <button type="submit" class="rounded-lg bg-gray-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-black">Lưu thay đổi</button>
</form>
HTML;

    return layoutAdmin('Hồ sơ & Giao diện — Admin', 'profile', $body, $user);
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
    foreach ($links as $link) {
        $icon = LINK_TYPES[$link['type']]['icon'] ?? '🔗';
        $typeOptions = '';
        foreach (LINK_TYPES as $key => $def) {
            $sel = $link['type'] === $key ? 'selected' : '';
            $typeOptions .= '<option value="' . e($key) . '" ' . $sel . '>' . e($def['label']) . '</option>';
        }
        $activeLabel = $link['is_active'] ? 'Đang hiện' : 'Đang ẩn';
        $activeClass = $link['is_active'] ? 'bg-green-50 text-green-700' : 'bg-gray-100 text-gray-500';
        $linkId    = (int) $link['id'];
        $linkLabel = e($link['label']);
        $linkUrl   = e($link['url']);
        $linkClicks = (int) $link['clicks'];

        $rows .= <<<HTML
<div class="flex flex-wrap items-center gap-2 rounded-xl bg-white p-3 shadow-sm ring-1 ring-gray-100">
  <span class="text-lg">{$icon}</span>
  <form method="post" action="/admin/links/{$linkId}/update" class="flex flex-1 flex-wrap items-center gap-2">
    <input type="hidden" name="csrf" value="{$csrf}">
    <select name="type" class="rounded-lg border border-gray-300 px-2 py-1.5 text-xs">{$typeOptions}</select>
    <input name="label" value="{$linkLabel}" class="min-w-[100px] flex-1 rounded-lg border border-gray-300 px-2 py-1.5 text-xs">
    <input name="url" value="{$linkUrl}" class="min-w-[140px] flex-1 rounded-lg border border-gray-300 px-2 py-1.5 text-xs">
    <button type="submit" class="rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-indigo-700">Lưu</button>
  </form>
  <span class="rounded-lg bg-sky-50 px-2 py-1.5 text-xs font-semibold text-sky-700">{$linkClicks} click</span>
  <form method="post" action="/admin/links/{$linkId}/toggle">
    <input type="hidden" name="csrf" value="{$csrf}">
    <button class="rounded-lg px-2 py-1.5 text-xs font-semibold {$activeClass}">{$activeLabel}</button>
  </form>
  <form method="post" action="/admin/links/{$linkId}/delete" onsubmit="return confirm('Xoá liên kết này?');">
    <input type="hidden" name="csrf" value="{$csrf}">
    <button class="rounded-lg px-2 py-1.5 text-xs font-semibold text-red-600 hover:bg-red-50">Xoá</button>
  </form>
</div>
HTML;
    }
    if ($links === []) {
        $rows = '<p class="text-center text-sm text-gray-400">Chưa có liên kết nào, thêm ở form phía trên.</p>';
    }

    $body = <<<HTML
{$errorHtml}
<div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-100">
  <h2 class="text-sm font-bold text-gray-900">Thêm liên kết mới</h2>
  <form method="post" action="/admin/links" class="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-4">
    <input type="hidden" name="csrf" value="{$csrf}">
    <select name="type" class="rounded-lg border border-gray-300 px-2 py-2 text-sm">{$options}</select>
    <input name="label" placeholder="Tên liên kết (VD: Shop trên Shopee)" class="rounded-lg border border-gray-300 px-3 py-2 text-sm">
    <input name="url" placeholder="https://..." class="rounded-lg border border-gray-300 px-3 py-2 text-sm">
    <button type="submit" class="rounded-lg bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-black">+ Thêm</button>
  </form>
</div>
<div class="mt-4 flex flex-col gap-2">
  {$rows}
</div>
HTML;

    return layoutAdmin('Quản lý liên kết — Admin', 'links', $body, $user);
}

function viewAdminLeads(array $leads, array $user): string
{
    $csrf = csrfToken();
    $rows = '';
    foreach ($leads as $lead) {
        $leadId    = (int) $lead['id'];
        $leadName  = e($lead['name']);
        $leadPhone = e($lead['phone']);
        $leadNote  = e($lead['note']);
        $leadCreatedAt = e($lead['created_at']);

        $rows .= <<<HTML
<tr class="border-t border-gray-100">
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
    }
    if ($leads === []) {
        $rows = '<tr><td colspan="5" class="px-4 py-6 text-center text-gray-400">Chưa có lead nào.</td></tr>';
    }

    $body = <<<HTML
<div class="overflow-x-auto rounded-xl bg-white shadow-sm ring-1 ring-gray-100">
  <table class="w-full text-left text-sm">
    <thead class="bg-gray-50 text-xs text-gray-500">
      <tr>
        <th class="px-4 py-3 font-medium">Tên</th>
        <th class="px-4 py-3 font-medium">SĐT</th>
        <th class="px-4 py-3 font-medium">Ghi chú</th>
        <th class="px-4 py-3 font-medium">Thời gian</th>
        <th class="px-4 py-3 font-medium"></th>
      </tr>
    </thead>
    <tbody>{$rows}</tbody>
  </table>
</div>
HTML;

    return layoutAdmin('Khách hàng — Admin', 'leads', $body, $user);
}

// ==========================================================================
// CONTROLLERS
// ==========================================================================

function ctrlHealth(): never
{
    try {
        db()->query('SELECT 1');
        jsonResponse(['status' => 'up', 'database' => 'connected']);
    } catch (Throwable $e) {
        jsonResponse(['status' => 'down', 'database' => 'disconnected'], 503);
    }
}

function ctrlBio(): never
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

function ctrlApiClick(): never
{
    $input = json_decode(file_get_contents('php://input') ?: '[]', true);
    $id = (int) ($input['id'] ?? 0);
    if ($id <= 0) {
        jsonResponse(['ok' => false, 'error' => 'invalid_id'], 422);
    }
    $stmt = db()->prepare('UPDATE links SET clicks = clicks + 1 WHERE id = ?');
    $stmt->execute([$id]);
    jsonResponse(['ok' => true]);
}

function ctrlApiLead(): never
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

function ctrlLoginShow(): never
{
    if (currentUser()) {
        redirect('/admin');
    }
    echo viewLogin($_GET['error'] ?? null);
    exit;
}

function ctrlLoginSubmit(): never
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
    redirect('/admin');
}

function ctrlLogout(): never
{
    $_SESSION = [];
    session_destroy();
    redirect('/login');
}

function ctrlAdminDashboard(): never
{
    $user = requireLogin();
    $tab  = $_GET['tab'] ?? 'profile';

    if ($tab === 'links') {
        $stmt = db()->prepare('SELECT * FROM links WHERE user_id = ? ORDER BY position ASC, id ASC');
        $stmt->execute([$user['id']]);
        echo viewAdminLinks($stmt->fetchAll(), $user, $_GET['error'] ?? null);
        exit;
    }

    if ($tab === 'leads') {
        $leads = db()->query('SELECT * FROM leads ORDER BY id DESC')->fetchAll();
        echo viewAdminLeads($leads, $user);
        exit;
    }

    echo viewAdminProfile($user, isset($_GET['saved']));
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

function ctrlAdminProfileSave(): never
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

    $themeColor = $_POST['theme_color'] ?? '#4f46e5';
    if (!preg_match('/^#[0-9a-fA-F]{6}$/', (string) $themeColor)) {
        $themeColor = '#4f46e5';
    }

    $sql = 'UPDATE users SET display_name = ?, bio_text = ?, hotline_phone = ?, zalo_phone = ?, theme_color = ?';
    $params = [
        trim((string) ($_POST['display_name'] ?? '')),
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

function ctrlAdminLinkCreate(): never
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

function ctrlAdminLinkUpdate(int $id): never
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

function ctrlAdminLinkToggle(int $id): never
{
    requireLogin();
    if (!csrfCheck($_POST['csrf'] ?? null)) {
        redirect('/admin?tab=links');
    }
    db()->prepare('UPDATE links SET is_active = 1 - is_active WHERE id = ?')->execute([$id]);
    redirect('/admin?tab=links');
}

function ctrlAdminLinkDelete(int $id): never
{
    requireLogin();
    if (!csrfCheck($_POST['csrf'] ?? null)) {
        redirect('/admin?tab=links');
    }
    db()->prepare('DELETE FROM links WHERE id = ?')->execute([$id]);
    redirect('/admin?tab=links');
}

function ctrlAdminLeadDelete(int $id): never
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
