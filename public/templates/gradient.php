<?php
/** Template: Gradient Modern (slug: gradient) — nền gradient theo Theme Color, card kính mờ (glass). */

$avatarHtml = $profile['avatarPath']
    ? '<img src="' . e($profile['avatarPath']) . '" alt="' . $profile['name'] . '" class="h-24 w-24 rounded-full object-cover" style="border:3px solid rgba(255,255,255,.8)">'
    : '<div class="flex h-24 w-24 items-center justify-center rounded-full text-3xl font-bold" style="background:rgba(255,255,255,.9);color:' . e($themeColor) . '">' . $profile['initial'] . '</div>';

$verifiedBadge = '<span class="inline-flex h-[18px] w-[18px] items-center justify-center rounded-full bg-white align-middle">' . icon('check', 'h-2.5 w-2.5', 'none') . '</span>';
$verifiedBadge = str_replace('stroke="currentColor" stroke-width="1.8"', 'stroke="' . e($themeColor) . '" stroke-width="3.5"', $verifiedBadge);

$quickButtons = '';
if (!empty($profile['hotlinePhone'])) {
    $quickButtons .= '<a href="tel:' . e(normalizePhoneVn($profile['hotlinePhone'])) . '" class="flex flex-1 items-center justify-center gap-2 rounded-full px-4 py-2.5 text-sm font-semibold transition hover:opacity-90" style="background:rgba(255,255,255,.92);color:' . e($themeColor) . '">' . icon('phone', 'h-4 w-4', 'none') . '<span>Gọi Hotline</span></a>';
}
if (!empty($profile['zaloPhone'])) {
    $quickButtons .= '<a href="' . e(zaloUrl($profile['zaloPhone'])) . '" target="_blank" rel="noopener" class="flex flex-1 items-center justify-center gap-2 rounded-full px-4 py-2.5 text-sm font-semibold text-white transition hover:opacity-90" style="background:rgba(255,255,255,.22);border:1.5px solid rgba(255,255,255,.7)">' . icon('messageCircle', 'h-4 w-4', 'none') . '<span>Nhắn Zalo</span></a>';
}

$linksHtml = '';
foreach ($links as $link) {
    $def   = LINK_TYPES[$link['type']] ?? LINK_TYPES['custom'];
    $ic    = icon($def['icon'], 'h-4 w-4', $def['icon'] === 'facebook' || $def['icon'] === 'youtube' ? 'currentColor' : 'none');
    $href  = e(linkHref($link['type'], $link['url']));
    $id    = (int) $link['id'];
    $label = e($link['label']);
    $linksHtml .= <<<HTML
<a href="{$href}" target="_blank" rel="noopener" data-link-id="{$id}" class="biolink-item group flex items-center gap-3 rounded-2xl px-4 transition hover:-translate-y-0.5" style="height:56px;background:rgba(255,255,255,.85);backdrop-filter:blur(6px)">
  <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full" style="background:{$def['color']}1a;color:{$def['color']}">{$ic}</span>
  <span class="flex-1 truncate text-sm font-semibold" style="color:#111827">{$label}</span>
  <span class="text-slate-400 transition group-hover:translate-x-0.5">
HTML;
    $linksHtml .= icon('chevronRight', 'h-4 w-4') . '</span></a>';
}
if ($links === []) {
    $linksHtml = '<p class="text-center text-sm text-white/70">Chưa có liên kết nào.</p>';
}

$year = date('Y');
$icCheckBig = icon('check', 'h-5 w-5', 'none');
?>
<div class="relative min-h-screen overflow-hidden" style="background:linear-gradient(160deg,#FF6B9D,<?= e($themeColor) ?> 45%,#7C3AED 85%)">
  <div class="pointer-events-none absolute inset-0 overflow-hidden">
    <div class="absolute rounded-full" style="width:300px;height:300px;top:-100px;right:-80px;background:#FBBF24;opacity:.25;filter:blur(50px)"></div>
    <div class="absolute rounded-full" style="width:260px;height:260px;bottom:20px;left:-90px;background:#38BDF8;opacity:.25;filter:blur(50px)"></div>
  </div>
  <div class="relative z-10 mx-auto flex w-full flex-col items-center px-5 pt-14" style="max-width:480px">
    <?= $avatarHtml ?>
    <h1 class="mt-4 flex items-center gap-1.5 text-lg font-bold text-white"><?= $profile['name'] ?> <?= $verifiedBadge ?></h1>
    <p class="mt-1 text-sm font-medium text-white/90"><?= $profile['jobTitle'] ?></p>
    <p class="mt-2 max-w-xs text-center text-sm leading-relaxed text-white/75"><?= $profile['bio'] ?></p>

    <div class="mt-5 flex w-full gap-2">
      <?= $quickButtons ?>
    </div>

    <div class="mt-6 flex w-full flex-col gap-2.5">
      <?= $linksHtml ?>
    </div>

    <div class="mt-8 w-full rounded-2xl p-5" style="background:rgba(255,255,255,.92)">
      <h2 class="text-sm font-bold" style="color:#111827">Đăng ký tư vấn miễn phí</h2>
      <form id="leadForm" class="mt-4 flex flex-col gap-3">
        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
        <input required name="name" placeholder="Họ và tên *" class="rounded-xl border border-gray-200 px-3 py-2.5 text-sm outline-none" style="color:#111827">
        <input required name="phone" placeholder="Số điện thoại *" class="rounded-xl border border-gray-200 px-3 py-2.5 text-sm outline-none" style="color:#111827">
        <textarea name="note" placeholder="Ghi chú (không bắt buộc)" rows="2" class="rounded-xl border border-gray-200 px-3 py-2.5 text-sm outline-none" style="color:#111827"></textarea>
        <button type="submit" id="leadSubmitBtn" class="rounded-xl px-4 py-2.5 text-sm font-semibold text-white transition hover:opacity-90" style="background:<?= e($themeColor) ?>">Gửi thông tin</button>
      </form>
      <div id="leadSuccess" class="hidden flex-col items-center py-4 text-center">
        <span class="flex h-10 w-10 items-center justify-center rounded-full bg-emerald-50 text-emerald-600"><?= $icCheckBig ?></span>
        <p class="mt-3 text-sm font-medium" style="color:#111827">Cảm ơn bạn! Thông tin đã được gửi thành công.</p>
      </div>
      <p id="leadError" class="mt-2 text-center text-xs text-red-600"></p>
    </div>

    <p class="mt-8 pb-10 text-[11px] text-white/60">© <?= $year ?> VN BioLink Hub</p>
  </div>
</div>
