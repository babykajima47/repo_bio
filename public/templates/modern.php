<?php
/** Template: Ultra Modern (slug: modern) — mono đen/trắng/xám, chỉ 1 màu nhấn duy nhất, cạnh sắc. */

$avatarHtml = $profile['avatarPath']
    ? '<img src="' . e($profile['avatarPath']) . '" alt="' . $profile['name'] . '" class="h-24 w-24 object-cover" style="border-radius:4px">'
    : '<div class="flex h-24 w-24 items-center justify-center text-3xl font-bold text-white" style="border-radius:4px;background:#111827">' . $profile['initial'] . '</div>';

$verifiedBadge = '<span class="inline-flex h-[16px] w-[16px] items-center justify-center" style="border-radius:3px;background:' . e($themeColor) . '">' . icon('check', 'h-2 w-2', 'none') . '</span>';
$verifiedBadge = str_replace('stroke="currentColor" stroke-width="1.8"', 'stroke="white" stroke-width="3.5"', $verifiedBadge);

$quickButtons = '';
if (!empty($profile['hotlinePhone'])) {
    $quickButtons .= '<a href="tel:' . e(normalizePhoneVn($profile['hotlinePhone'])) . '" class="flex flex-1 items-center justify-center gap-2 px-4 py-2.5 text-sm font-semibold text-white transition hover:opacity-85" style="border-radius:4px;background:#111827">' . icon('phone', 'h-4 w-4', 'none') . '<span>Gọi Hotline</span></a>';
}
if (!empty($profile['zaloPhone'])) {
    $quickButtons .= '<a href="' . e(zaloUrl($profile['zaloPhone'])) . '" target="_blank" rel="noopener" class="flex flex-1 items-center justify-center gap-2 px-4 py-2.5 text-sm font-semibold text-white transition hover:opacity-90" style="border-radius:4px;background:' . e($themeColor) . '">' . icon('messageCircle', 'h-4 w-4', 'none') . '<span>Nhắn Zalo</span></a>';
}

$linksHtml = '';
foreach ($links as $link) {
    $def   = LINK_TYPES[$link['type']] ?? LINK_TYPES['custom'];
    $ic    = icon($def['icon'], 'h-4 w-4', $def['icon'] === 'facebook' || $def['icon'] === 'youtube' ? 'currentColor' : 'none');
    $href  = e(linkHref($link['type'], $link['url']));
    $id    = (int) $link['id'];
    $label = e($link['label']);
    $linksHtml .= <<<HTML
<a href="{$href}" target="_blank" rel="noopener" data-link-id="{$id}" class="biolink-item group flex items-center gap-3 bg-white px-4 transition hover:bg-gray-50" style="height:54px;border:1px solid #E5E5E5;border-radius:4px">
  <span class="flex h-8 w-8 shrink-0 items-center justify-center text-white" style="border-radius:4px;background:#111827">{$ic}</span>
  <span class="flex-1 truncate text-sm font-semibold" style="color:#111827">{$label}</span>
  <span class="text-gray-300 transition group-hover:translate-x-0.5">
HTML;
    $linksHtml .= icon('chevronRight', 'h-4 w-4') . '</span></a>';
}
if ($links === []) {
    $linksHtml = '<p class="text-center text-sm text-gray-400">Chưa có liên kết nào.</p>';
}

$year = date('Y');
$icCheckBig = icon('check', 'h-5 w-5', 'none');
?>
<div class="min-h-screen bg-white">
  <div class="mx-auto flex w-full flex-col items-center px-5 pt-14" style="max-width:480px">
    <?= $avatarHtml ?>
    <h1 class="mt-4 flex items-center gap-1.5 text-lg font-extrabold tracking-tight" style="color:#111827"><?= $profile['name'] ?> <?= $verifiedBadge ?></h1>
    <p class="mt-1 text-sm font-semibold" style="color:<?= e($themeColor) ?>"><?= $profile['jobTitle'] ?></p>
    <p class="mt-2 max-w-xs text-center text-sm leading-relaxed" style="color:#6B7280"><?= $profile['bio'] ?></p>

    <div class="mt-5 flex w-full gap-2">
      <?= $quickButtons ?>
    </div>

    <div class="mt-6 flex w-full flex-col gap-2.5">
      <?= $linksHtml ?>
    </div>

    <div class="mt-8 w-full bg-white p-5" style="border:1px solid #E5E5E5;border-radius:4px">
      <h2 class="text-sm font-extrabold" style="color:#111827">Đăng ký tư vấn miễn phí</h2>
      <form id="leadForm" class="mt-4 flex flex-col gap-3">
        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
        <input required name="name" placeholder="Họ và tên *" class="px-3 py-2.5 text-sm outline-none" style="border:1px solid #E5E5E5;border-radius:4px;color:#111827">
        <input required name="phone" placeholder="Số điện thoại *" class="px-3 py-2.5 text-sm outline-none" style="border:1px solid #E5E5E5;border-radius:4px;color:#111827">
        <textarea name="note" placeholder="Ghi chú (không bắt buộc)" rows="2" class="px-3 py-2.5 text-sm outline-none" style="border:1px solid #E5E5E5;border-radius:4px;color:#111827"></textarea>
        <button type="submit" id="leadSubmitBtn" class="px-4 py-2.5 text-sm font-bold text-white transition hover:opacity-90" style="border-radius:4px;background:#111827">Gửi thông tin</button>
      </form>
      <div id="leadSuccess" class="hidden flex-col items-center py-4 text-center">
        <span class="flex h-10 w-10 items-center justify-center rounded-full bg-emerald-50 text-emerald-600"><?= $icCheckBig ?></span>
        <p class="mt-3 text-sm font-semibold" style="color:#111827">Cảm ơn bạn! Thông tin đã được gửi thành công.</p>
      </div>
      <p id="leadError" class="mt-2 text-center text-xs text-red-600"></p>
    </div>

    <p class="mt-8 pb-10 text-[11px] font-medium text-gray-400">© <?= $year ?> VN BioLink Hub</p>
  </div>
</div>
