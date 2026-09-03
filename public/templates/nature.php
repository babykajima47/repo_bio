<?php
/** Template: Nature Green (slug: nature) — nền kem/xanh nhẹ, bo tròn hữu cơ, tông organic. */

$avatarHtml = $profile['avatarPath']
    ? '<img src="' . e($profile['avatarPath']) . '" alt="' . $profile['name'] . '" class="h-24 w-24 object-cover" style="border-radius:42% 58% 63% 37% / 41% 44% 56% 59%;border:3px solid #ECFDF5">'
    : '<div class="flex h-24 w-24 items-center justify-center text-3xl font-bold text-white" style="border-radius:42% 58% 63% 37% / 41% 44% 56% 59%;background:' . e($themeColor) . '">' . $profile['initial'] . '</div>';

$verifiedBadge = '<span class="inline-flex h-[18px] w-[18px] items-center justify-center rounded-full bg-emerald-500 align-middle">' . icon('check', 'h-2.5 w-2.5', 'none') . '</span>';
$verifiedBadge = str_replace('stroke="currentColor" stroke-width="1.8"', 'stroke="white" stroke-width="3"', $verifiedBadge);

$quickButtons = '';
if (!empty($profile['hotlinePhone'])) {
    $quickButtons .= '<a href="tel:' . e(normalizePhoneVn($profile['hotlinePhone'])) . '" class="flex flex-1 items-center justify-center gap-2 rounded-2xl px-4 py-2.5 text-sm font-semibold transition hover:opacity-90" style="background:#DCFCE7;color:#166534">' . icon('phone', 'h-4 w-4', 'none') . '<span>Gọi Hotline</span></a>';
}
if (!empty($profile['zaloPhone'])) {
    $quickButtons .= '<a href="' . e(zaloUrl($profile['zaloPhone'])) . '" target="_blank" rel="noopener" class="flex flex-1 items-center justify-center gap-2 rounded-2xl px-4 py-2.5 text-sm font-semibold text-white transition hover:opacity-90" style="background:' . e($themeColor) . '">' . icon('messageCircle', 'h-4 w-4', 'none') . '<span>Nhắn Zalo</span></a>';
}

$linksHtml = '';
foreach ($links as $link) {
    $def   = LINK_TYPES[$link['type']] ?? LINK_TYPES['custom'];
    $ic    = icon($def['icon'], 'h-4 w-4', $def['icon'] === 'facebook' || $def['icon'] === 'youtube' ? 'currentColor' : 'none');
    $href  = e(linkHref($link['type'], $link['url']));
    $id    = (int) $link['id'];
    $label = e($link['label']);
    $linksHtml .= <<<HTML
<a href="{$href}" target="_blank" rel="noopener" data-link-id="{$id}" class="biolink-item group flex items-center gap-3 rounded-2xl bg-white px-4 transition hover:-translate-y-0.5" style="height:56px;border:1px solid #D1FAE5">
  <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full" style="background:{$def['color']}1a;color:{$def['color']}">{$ic}</span>
  <span class="flex-1 truncate text-sm font-medium" style="color:#14532D">{$label}</span>
  <span class="text-emerald-300 transition group-hover:translate-x-0.5">
HTML;
    $linksHtml .= icon('chevronRight', 'h-4 w-4') . '</span></a>';
}
if ($links === []) {
    $linksHtml = '<p class="text-center text-sm text-emerald-700/60">Chưa có liên kết nào.</p>';
}

$year = date('Y');
$icCheckBig = icon('check', 'h-5 w-5', 'none');
?>
<div class="min-h-screen" style="background:linear-gradient(180deg,#ECFDF5,#F0FDF4 40%,#FFFFF8)">
  <div class="mx-auto flex w-full flex-col items-center px-5 pt-14" style="max-width:480px">
    <?= $avatarHtml ?>
    <h1 class="mt-4 flex items-center gap-1.5 text-lg font-bold" style="color:#14532D"><?= $profile['name'] ?> <?= $verifiedBadge ?></h1>
    <p class="mt-1 text-sm font-medium" style="color:<?= e($themeColor) ?>"><?= $profile['jobTitle'] ?></p>
    <p class="mt-2 max-w-xs text-center text-sm leading-relaxed" style="color:#3F6212"><?= $profile['bio'] ?></p>

    <div class="mt-5 flex w-full gap-2">
      <?= $quickButtons ?>
    </div>

    <div class="mt-6 flex w-full flex-col gap-2.5">
      <?= $linksHtml ?>
    </div>

    <div class="mt-8 w-full rounded-2xl bg-white p-5" style="border:1px solid #D1FAE5">
      <h2 class="text-sm font-bold" style="color:#14532D">Đăng ký tư vấn miễn phí</h2>
      <form id="leadForm" class="mt-4 flex flex-col gap-3">
        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
        <input required name="name" placeholder="Họ và tên *" class="rounded-xl px-3 py-2.5 text-sm outline-none" style="border:1px solid #D1FAE5;color:#14532D">
        <input required name="phone" placeholder="Số điện thoại *" class="rounded-xl px-3 py-2.5 text-sm outline-none" style="border:1px solid #D1FAE5;color:#14532D">
        <textarea name="note" placeholder="Ghi chú (không bắt buộc)" rows="2" class="rounded-xl px-3 py-2.5 text-sm outline-none" style="border:1px solid #D1FAE5;color:#14532D"></textarea>
        <button type="submit" id="leadSubmitBtn" class="rounded-xl px-4 py-2.5 text-sm font-semibold text-white transition hover:opacity-90" style="background:<?= e($themeColor) ?>">Gửi thông tin</button>
      </form>
      <div id="leadSuccess" class="hidden flex-col items-center py-4 text-center">
        <span class="flex h-10 w-10 items-center justify-center rounded-full bg-emerald-50 text-emerald-600"><?= $icCheckBig ?></span>
        <p class="mt-3 text-sm font-medium" style="color:#14532D">Cảm ơn bạn! Thông tin đã được gửi thành công.</p>
      </div>
      <p id="leadError" class="mt-2 text-center text-xs text-red-600"></p>
    </div>

    <p class="mt-8 pb-10 text-[11px]" style="color:#4D7C0F">© <?= $year ?> VN BioLink Hub</p>
  </div>
</div>
