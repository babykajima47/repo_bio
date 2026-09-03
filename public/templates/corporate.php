<?php
/** Template: Business Corporate (slug: corporate) — chuyên nghiệp, cấu trúc rõ ràng, tông navy/xám. */

$avatarHtml = $profile['avatarPath']
    ? '<img src="' . e($profile['avatarPath']) . '" alt="' . $profile['name'] . '" class="h-24 w-24 rounded-lg object-cover" style="border:1px solid #E2E8F0">'
    : '<div class="flex h-24 w-24 items-center justify-center rounded-lg text-3xl font-bold text-white" style="background:#1E293B">' . $profile['initial'] . '</div>';

$verifiedBadge = '<span class="inline-flex h-[16px] w-[16px] items-center justify-center rounded-full" style="background:' . e($themeColor) . '">' . icon('check', 'h-2 w-2', 'none') . '</span>';
$verifiedBadge = str_replace('stroke="currentColor" stroke-width="1.8"', 'stroke="white" stroke-width="3.5"', $verifiedBadge);

$quickButtons = '';
if (!empty($profile['hotlinePhone'])) {
    $quickButtons .= '<a href="tel:' . e(normalizePhoneVn($profile['hotlinePhone'])) . '" class="flex flex-1 items-center justify-center gap-2 rounded px-4 py-2.5 text-sm font-semibold text-white transition hover:opacity-90" style="background:#1E293B">' . icon('phone', 'h-4 w-4', 'none') . '<span>Gọi Hotline</span></a>';
}
if (!empty($profile['zaloPhone'])) {
    $quickButtons .= '<a href="' . e(zaloUrl($profile['zaloPhone'])) . '" target="_blank" rel="noopener" class="flex flex-1 items-center justify-center gap-2 rounded px-4 py-2.5 text-sm font-semibold transition hover:bg-gray-50" style="border:1px solid #CBD5E1;color:#1E293B">' . icon('messageCircle', 'h-4 w-4', 'none') . '<span>Nhắn Zalo</span></a>';
}

$linksHtml = '';
foreach ($links as $link) {
    $def   = LINK_TYPES[$link['type']] ?? LINK_TYPES['custom'];
    $ic    = icon($def['icon'], 'h-4 w-4', $def['icon'] === 'facebook' || $def['icon'] === 'youtube' ? 'currentColor' : 'none');
    $href  = e(linkHref($link['type'], $link['url']));
    $id    = (int) $link['id'];
    $label = e($link['label']);
    $linksHtml .= <<<HTML
<a href="{$href}" target="_blank" rel="noopener" data-link-id="{$id}" class="biolink-item group flex items-center gap-3 rounded bg-white px-4 transition hover:bg-gray-50" style="height:52px;border:1px solid #E2E8F0;border-left:3px solid {$def['color']}">
  <span class="flex h-7 w-7 shrink-0 items-center justify-center" style="color:{$def['color']}">{$ic}</span>
  <span class="flex-1 truncate text-sm font-medium" style="color:#1E293B">{$label}</span>
  <span class="text-slate-300">
HTML;
    $linksHtml .= icon('chevronRight', 'h-4 w-4') . '</span></a>';
}
if ($links === []) {
    $linksHtml = '<p class="text-center text-sm text-slate-400">Chưa có liên kết nào.</p>';
}

$year = date('Y');
$icCheckBig = icon('check', 'h-5 w-5', 'none');
?>
<div class="relative min-h-screen overflow-hidden bg-white">
  <svg viewBox="0 0 480 180" preserveAspectRatio="none" class="pointer-events-none absolute left-0 top-0 w-full" style="height:170px">
    <polygon points="0,180 0,110 90,60 190,105 290,45 380,95 480,70 480,180" fill="#CBD5E1" opacity="0.5"/>
    <polygon points="0,180 0,140 120,95 240,130 360,85 480,120 480,180" fill="#94A3B8" opacity="0.35"/>
  </svg>
  <div class="relative z-10 mx-auto flex w-full flex-col items-center px-5 pt-14" style="max-width:480px">
    <?= $avatarHtml ?>
    <h1 class="mt-4 flex items-center gap-1.5 text-lg font-bold" style="color:#1E293B"><?= $profile['name'] ?> <?= $verifiedBadge ?></h1>
    <p class="mt-1 text-sm font-medium" style="color:#64748B"><?= $profile['jobTitle'] ?></p>
    <div class="mt-3 h-px w-10" style="background:<?= e($themeColor) ?>"></div>
    <p class="mt-3 max-w-xs text-center text-sm leading-relaxed" style="color:#475569"><?= $profile['bio'] ?></p>

    <div class="mt-5 flex w-full gap-2">
      <?= $quickButtons ?>
    </div>

    <div class="mt-6 flex w-full flex-col gap-2">
      <?= $linksHtml ?>
    </div>

    <div class="mt-8 w-full rounded bg-white p-5" style="border:1px solid #E2E8F0">
      <h2 class="text-sm font-bold" style="color:#1E293B">Đăng ký tư vấn miễn phí</h2>
      <form id="leadForm" class="mt-4 flex flex-col gap-3">
        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
        <input required name="name" placeholder="Họ và tên *" class="rounded px-3 py-2.5 text-sm outline-none" style="border:1px solid #CBD5E1;color:#1E293B">
        <input required name="phone" placeholder="Số điện thoại *" class="rounded px-3 py-2.5 text-sm outline-none" style="border:1px solid #CBD5E1;color:#1E293B">
        <textarea name="note" placeholder="Ghi chú (không bắt buộc)" rows="2" class="rounded px-3 py-2.5 text-sm outline-none" style="border:1px solid #CBD5E1;color:#1E293B"></textarea>
        <button type="submit" id="leadSubmitBtn" class="rounded px-4 py-2.5 text-sm font-semibold text-white transition hover:opacity-90" style="background:#1E293B">Gửi thông tin</button>
      </form>
      <div id="leadSuccess" class="hidden flex-col items-center py-4 text-center">
        <span class="flex h-10 w-10 items-center justify-center rounded-full bg-emerald-50 text-emerald-600"><?= $icCheckBig ?></span>
        <p class="mt-3 text-sm font-medium" style="color:#1E293B">Cảm ơn bạn! Thông tin đã được gửi thành công.</p>
      </div>
      <p id="leadError" class="mt-2 text-center text-xs text-red-600"></p>
    </div>

    <p class="mt-8 pb-10 text-[11px]" style="color:#94A3B8">© <?= $year ?> VN BioLink Hub</p>
  </div>
</div>
