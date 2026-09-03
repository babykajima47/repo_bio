<?php
/** Template: Fashion Lifestyle (slug: fashion) — editorial, nhiều khoảng trắng, chữ mảnh dãn dòng. */

$avatarHtml = $profile['avatarPath']
    ? '<img src="' . e($profile['avatarPath']) . '" alt="' . $profile['name'] . '" class="h-28 w-28 rounded-full object-cover" style="border:1px solid #E5E5E5">'
    : '<div class="flex h-28 w-28 items-center justify-center rounded-full text-3xl font-light text-white" style="background:' . e($themeColor) . '">' . $profile['initial'] . '</div>';

$verifiedBadge = '<span class="inline-flex h-[16px] w-[16px] items-center justify-center rounded-full bg-black align-middle">' . icon('check', 'h-2 w-2', 'none') . '</span>';
$verifiedBadge = str_replace('stroke="currentColor" stroke-width="1.8"', 'stroke="white" stroke-width="3.5"', $verifiedBadge);

$quickButtons = '';
if (!empty($profile['hotlinePhone'])) {
    $quickButtons .= '<a href="tel:' . e(normalizePhoneVn($profile['hotlinePhone'])) . '" class="flex flex-1 items-center justify-center gap-2 py-2.5 text-xs font-medium uppercase tracking-widest transition hover:opacity-60" style="border-top:1px solid #111827;border-bottom:1px solid #111827;color:#111827">' . icon('phone', 'h-3.5 w-3.5', 'none') . '<span>Hotline</span></a>';
}
if (!empty($profile['zaloPhone'])) {
    $quickButtons .= '<a href="' . e(zaloUrl($profile['zaloPhone'])) . '" target="_blank" rel="noopener" class="flex flex-1 items-center justify-center gap-2 py-2.5 text-xs font-medium uppercase tracking-widest text-white transition hover:opacity-80" style="background:#111827">' . icon('messageCircle', 'h-3.5 w-3.5', 'none') . '<span>Zalo</span></a>';
}

$linksHtml = '';
foreach ($links as $link) {
    $def   = LINK_TYPES[$link['type']] ?? LINK_TYPES['custom'];
    $ic    = icon($def['icon'], 'h-4 w-4', $def['icon'] === 'facebook' || $def['icon'] === 'youtube' ? 'currentColor' : 'none');
    $href  = e(linkHref($link['type'], $link['url']));
    $id    = (int) $link['id'];
    $label = e($link['label']);
    $linksHtml .= <<<HTML
<a href="{$href}" target="_blank" rel="noopener" data-link-id="{$id}" class="biolink-item group flex items-center gap-3 px-1 py-4 transition" style="border-bottom:1px solid #E5E5E5">
  <span style="color:#111827">{$ic}</span>
  <span class="flex-1 truncate text-sm uppercase tracking-wide" style="color:#111827">{$label}</span>
  <span class="text-gray-300 transition group-hover:translate-x-1">
HTML;
    $linksHtml .= icon('chevronRight', 'h-4 w-4') . '</span></a>';
}
if ($links === []) {
    $linksHtml = '<p class="text-center text-sm text-gray-400">Chưa có liên kết nào.</p>';
}

$year = date('Y');
$icCheckBig = icon('check', 'h-5 w-5', 'none');
?>
<div class="relative min-h-screen overflow-hidden" style="background:linear-gradient(180deg,#FDF2F8,#FFFFFF 45%)">
  <div class="pointer-events-none absolute inset-0 overflow-hidden">
    <div class="absolute rounded-full" style="width:18px;height:18px;top:40px;left:12%;background:#FBCFE8"></div>
    <div class="absolute rounded-full" style="width:10px;height:10px;top:90px;left:22%;background:#F9A8D4"></div>
    <div class="absolute rounded-full" style="width:24px;height:24px;top:30px;right:15%;background:#FCE7F3"></div>
    <div class="absolute rounded-full" style="width:12px;height:12px;top:100px;right:8%;background:#F9A8D4"></div>
    <div class="absolute rounded-full" style="width:16px;height:16px;bottom:120px;left:6%;background:#FBCFE8;opacity:.8"></div>
  </div>
  <div class="relative z-10 mx-auto flex w-full flex-col items-center px-6 pt-16" style="max-width:480px">
    <?= $avatarHtml ?>
    <h1 class="mt-5 flex items-center gap-1.5 text-xl font-light tracking-wide" style="color:#111827"><?= $profile['name'] ?> <?= $verifiedBadge ?></h1>
    <p class="mt-1 text-[11px] font-medium uppercase tracking-[0.2em]" style="color:<?= e($themeColor) ?>"><?= $profile['jobTitle'] ?></p>
    <p class="mt-4 max-w-xs text-center text-sm font-light leading-loose" style="color:#525252"><?= $profile['bio'] ?></p>

    <div class="mt-6 flex w-full">
      <?= $quickButtons ?>
    </div>

    <div class="mt-8 flex w-full flex-col">
      <?= $linksHtml ?>
    </div>

    <div class="mt-10 w-full p-6" style="border:1px solid #E5E5E5">
      <h2 class="text-center text-xs font-medium uppercase tracking-[0.2em]" style="color:#111827">Đăng ký tư vấn miễn phí</h2>
      <form id="leadForm" class="mt-5 flex flex-col gap-3">
        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
        <input required name="name" placeholder="Họ và tên *" class="border-0 border-b py-2 text-sm outline-none" style="border-bottom:1px solid #D4D4D4;color:#111827">
        <input required name="phone" placeholder="Số điện thoại *" class="border-0 border-b py-2 text-sm outline-none" style="border-bottom:1px solid #D4D4D4;color:#111827">
        <textarea name="note" placeholder="Ghi chú (không bắt buộc)" rows="2" class="border-0 border-b py-2 text-sm outline-none" style="border-bottom:1px solid #D4D4D4;color:#111827"></textarea>
        <button type="submit" id="leadSubmitBtn" class="mt-2 py-3 text-xs font-medium uppercase tracking-widest text-white transition hover:opacity-80" style="background:#111827">Gửi thông tin</button>
      </form>
      <div id="leadSuccess" class="hidden flex-col items-center py-4 text-center">
        <span class="flex h-10 w-10 items-center justify-center rounded-full bg-emerald-50 text-emerald-600"><?= $icCheckBig ?></span>
        <p class="mt-3 text-sm" style="color:#111827">Cảm ơn bạn! Thông tin đã được gửi thành công.</p>
      </div>
      <p id="leadError" class="mt-2 text-center text-xs text-red-600"></p>
    </div>

    <p class="mt-10 pb-12 text-[10px] uppercase tracking-widest text-gray-400">© <?= $year ?> VN BioLink Hub</p>
  </div>
</div>
