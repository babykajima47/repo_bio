# PRD — VN BioLink Hub

## Vấn đề

Chủ shop, người sáng tạo nội dung và cá nhân kinh doanh online bán hàng đa
kênh (TikTok, Shopee, Facebook, Zalo...) nhưng mạng xã hội chỉ cho gắn 1 link
trong bio. Dùng dịch vụ ngoài (Linktree, Beacons...) thì tốn phí hàng tháng,
không tùy biến domain riêng, và không có cơ chế thu thập lead tư vấn.

## Giải pháp

Trang Bio Link tự host: 1 trang giới thiệu bản thân + danh sách liên kết đa
kênh + nút liên hệ nhanh (Hotline, Zalo) + form thu thập lead, có dashboard
quản trị riêng, không phụ thuộc bên thứ ba.

## Đối tượng dùng

- Chủ shop online, người bán hàng trên Shopee/TikTok Shop.
- Người sáng tạo nội dung (KOC/KOL) cần gom link mạng xã hội.
- Cá nhân kinh doanh dịch vụ cần khách để lại SĐT tư vấn.

## Tính năng (đã triển khai)

1. **Trang Bio public (`/`)**: avatar, tên, giới thiệu ngắn, màu chủ đạo tùy
   biến, nút Gọi Hotline (`tel:`), nút Nhắn Zalo (`zalo.me/...`), danh sách
   liên kết (TikTok/Shopee/Website/Facebook/Instagram/YouTube/khác).
2. **Đếm lượt click**: mỗi liên kết ghi nhận số lượt click qua `POST /api/click`,
   xem lại trong dashboard admin.
3. **Form thu thập lead**: khách để lại Tên/SĐT/Ghi chú qua `POST /api/lead`
   (AJAX), lưu vào bảng `leads`.
4. **Đăng nhập quản trị (`/login`)**: session + `password_verify`, CSRF token
   cho mọi form thay đổi dữ liệu.
5. **Dashboard quản trị (`/admin`)**:
   - Tab Hồ sơ & Giao diện: sửa tên, giới thiệu, avatar (upload), Hotline,
     Zalo, màu chủ đạo.
   - Tab Quản lý liên kết: CRUD liên kết, bật/tắt hiển thị, xem số click.
   - Tab Khách hàng (Leads): danh sách lead đã gửi, xoá lead.
6. **Health check (`/health`)**: phản ánh đúng trạng thái kết nối MySQL, dùng
   cho nền tảng Vibe Host xác định app đã sống.

## Ngoài phạm vi (chưa làm)

- Đa người dùng / đa tenant (hiện tại 1 container = 1 chủ trang duy nhất).
- Thống kê nâng cao (biểu đồ, nguồn traffic UTM).
- Thông báo lead mới qua Telegram/Webhook (có ở phiên bản chị em SQLite,
  chưa đưa vào bản MySQL này).

## Tiêu chí hoàn thành (Definition of Done)

- Build Docker sạch, chạy được bằng `docker compose up` (README mục [2]).
- `/health` trả đúng JSON theo đặc tả Vibe Host.
- Toàn bộ luồng chính (login, CRUD link, click tracking, lead capture) đã
  test end-to-end bằng HTTP thật, không phải mock.
