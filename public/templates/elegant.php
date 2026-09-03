<?php
/** Template: Elegant Classic (slug: elegant) — nền ngà, viền mảnh, chữ dãn rộng thanh lịch. */

$avatarHtml = $profile['avatarPath']
    ? '<img src="' . e($profile['avatarPath']) . '" alt="' . $profile['name'] . '" class="h-24 w-24 rounded-full object-cover" style="border:1px solid ' . e($themeColor) . ';padding:3px">'
    : '<div class="flex h-24 w-24 items-center justify-center rounded-full text-2xl font-semibold" style="background:#FFFDF7;border:1px solid ' . e($themeColor) . ';color:' . e($themeColor) . '">' . $profile['initial'] . '</div>';

$verifiedBadge = '<span class="inline-flex h-[16px] w-[16px] items-center justify-center rounded-full" style="background:' . e($themeColor) . '">' . icon('check', 'h-2 w-2', 'none') . '</span>';
$verifiedBadge = str_replace('stroke="currentColor" stroke-width="1.8"', 'stroke="white" stroke-width="3.5"', $verifiedBadge);

$quickButtons = '';
if (!empty($profile['hotlinePhone'])) {
    $quickButtons .= '<a href="tel:' . e(normalizePhoneVn($profile['hotlinePhone'])) . '" class="flex flex-1 items-center justify-center gap-2 rounded-sm px-4 py-2.5 text-xs font-medium uppercase tracking-wider transition hover:opacity-70" style="border:1px solid ' . e($themeColor) . ';color:' . e($themeColor) . '">' . icon('phone', 'h-3.5 w-3.5', 'none') . '<span>Hotline</span></a>';
}
if (!empty($profile['zaloPhone'])) {
    $quickButtons .= '<a href="' . e(zaloUrl($profile['zaloPhone'])) . '" target="_blank" rel="noopener" class="flex flex-1 items-center justify-center gap-2 rounded-sm px-4 py-2.5 text-xs font-medium uppercase tracking-wider text-white transition hover:opacity-90" style="background:' . e($themeColor) . '">' . icon('messageCircle', 'h-3.5 w-3.5', 'none') . '<span>Zalo</span></a>';
}

$linksHtml = '';
foreach ($links as $link) {
    $def   = LINK_TYPES[$link['type']] ?? LINK_TYPES['custom'];
    $ic    = icon($def['icon'], 'h-4 w-4', $def['icon'] === 'facebook' || $def['icon'] === 'youtube' ? 'currentColor' : 'none');
    $href  = e(linkHref($link['type'], $link['url']));
    $id    = (int) $link['id'];
    $label = e($link['label']);
    $linksHtml .= <<<HTML
<a href="{$href}" target="_blank" rel="noopener" data-link-id="{$id}" class="biolink-item group flex items-center gap-3 bg-white px-4 transition hover:opacity-80" style="height:54px;border:1px solid #E7E0D3">
  <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full" style="border:1px solid {$def['color']};color:{$def['color']}">{$ic}</span>
  <span class="flex-1 truncate text-center text-sm tracking-wide" style="color:#3F3A32">{$label}</span>
  <span style="color:#C4B99A">
HTML;
    $linksHtml .= icon('chevronRight', 'h-4 w-4') . '</span></a>';
}
if ($links === []) {
    $linksHtml = '<p class="text-center text-sm" style="color:#B5A98A">Chưa có liên kết nào.</p>';
}

$year = date('Y');
$icCheckBig = icon('check', 'h-5 w-5', 'none');
?>
<div class="relative min-h-screen overflow-hidden" style="background:radial-gradient(120% 50% at 50% 0%,<?= e($themeColor) ?>14,#FFFDF7 60%)">
  <div class="pointer-events-none absolute" style="top:28px;left:28px;width:36px;height:36px;border-top:1px solid <?= e($themeColor) ?>;border-left:1px solid <?= e($themeColor) ?>"></div>
  <div class="pointer-events-none absolute" style="top:28px;right:28px;width:36px;height:36px;border-top:1px solid <?= e($themeColor) ?>;border-right:1px solid <?= e($themeColor) ?>"></div>
  <div class="relative z-10 mx-auto flex w-full flex-col items-center px-6 pt-16" style="max-width:480px">
    <?= $avatarHtml ?>
    <h1 class="mt-5 flex items-center gap-1.5 text-lg font-medium tracking-wide" style="color:#3F3A32"><?= $profile['name'] ?> <?= $verifiedBadge ?></h1>
    <p class="mt-1 text-[11px] uppercase tracking-[0.25em]" style="color:<?= e($themeColor) ?>"><?= $profile['jobTitle'] ?></p>
    <div class="mt-4 h-px w-8" style="background:<?= e($themeColor) ?>"></div>
    <p class="mt-4 max-w-xs text-center text-sm leading-loose" style="color:#6B6355"><?= $profile['bio'] ?></p>

    <div class="mt-6 flex w-full gap-2">
      <?= $quickButtons ?>
    </div>

    <div class="mt-7 flex w-full flex-col gap-2">
      <?= $linksHtml ?>
    </div>

    <div class="mt-9 w-full bg-white p-6" style="border:1px solid #E7E0D3">
      <h2 class="text-center text-xs font-medium uppercase tracking-[0.2em]" style="color:#3F3A32">Đăng ký tư vấn miễn phí</h2>
      <form id="leadForm" class="mt-5 flex flex-col gap-3">
        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
        <input required name="name" placeholder="Họ và tên *" class="rounded-sm px-3 py-2.5 text-sm outline-none" style="border:1px solid #E7E0D3;color:#3F3A32">
        <input required name="phone" placeholder="Số điện thoại *" class="rounded-sm px-3 py-2.5 text-sm outline-none" style="border:1px solid #E7E0D3;color:#3F3A32">
        <textarea name="note" placeholder="Ghi chú (không bắt buộc)" rows="2" class="rounded-sm px-3 py-2.5 text-sm outline-none" style="border:1px solid #E7E0D3;color:#3F3A32"></textarea>
        <button type="submit" id="leadSubmitBtn" class="mt-1 rounded-sm px-4 py-2.5 text-xs font-medium uppercase tracking-widest text-white transition hover:opacity-90" style="background:<?= e($themeColor) ?>">Gửi thông tin</button>
      </form>
      <div id="leadSuccess" class="hidden flex-col items-center py-4 text-center">
        <span class="flex h-10 w-10 items-center justify-center rounded-full bg-emerald-50 text-emerald-600"><?= $icCheckBig ?></span>
        <p class="mt-3 text-sm" style="color:#3F3A32">Cảm ơn bạn! Thông tin đã được gửi thành công.</p>
      </div>
      <p id="leadError" class="mt-2 text-center text-xs text-red-600"></p>
    </div>

    <p class="mt-9 pb-12 text-[10px] uppercase tracking-widest" style="color:#B5A98A">© <?= $year ?> VN BioLink Hub</p>
  </div>
</div>
