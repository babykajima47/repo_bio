# ARCH — VN BioLink Hub

## Stack

- **Backend**: PHP 8.2 thuần (không framework), chạy trên Apache
  (`php:8.2-apache`), extension `pdo_mysql`.
- **Database**: MySQL 8.0 (external service — image app không đóng gói DB).
- **Frontend**: HTML render phía server (PHP), TailwindCSS qua CDN, Vanilla JS
  cho gọi AJAX (`/api/click`, `/api/lead`).
- **Đóng gói**: 1 Dockerfile duy nhất, entrypoint tự migrate + seed admin.

## Sơ đồ thành phần

```
Browser ──HTTP──> Apache (php:8.2-apache) ──PDO──> MySQL 8.0
                     │
                     └── public/index.php (router + controller + view, 1 file)
                              │
                     ┌────────┼─────────┐
                  users     links      leads
```

## Cấu trúc thư mục

```
public/index.php        Front controller duy nhất: router, controller, API, toàn bộ HTML view
public/.htaccess         mod_rewrite: mọi request (trừ file tĩnh có thật) -> index.php
public/uploads/          Ảnh đại diện admin upload (mount volume khi deploy)
database/schema.sql      3 bảng: users (kiêm hồ sơ chủ trang), links, leads — utf8mb4
database/init.php        CLI: import schema (idempotent) + seed/update tài khoản admin
Dockerfile               Build image runtime, set DocumentRoot -> public/
docker-entrypoint.sh     Chờ MySQL sẵn sàng (qua PDO) -> chạy init.php -> apache2-foreground
docker-compose.yml       Tiện ích dev/test local (app + MySQL 8.0)
```

## Mô hình dữ liệu

- `users`: 1 dòng duy nhất = chủ trang (single-tenant). Vừa là tài khoản đăng
  nhập vừa là hồ sơ hiển thị công khai (display_name, bio_text, avatar_path,
  theme_color, hotline_phone, zalo_phone).
- `links`: liên kết mạng xã hội/gian hàng, có `type` (enum), `position` (thứ
  tự hiển thị), `clicks` (bộ đếm tăng dần qua `/api/click`), `is_active`.
- `leads`: thông tin khách để lại qua form tư vấn công khai.

## Luồng khởi động (entrypoint)

1. `docker-entrypoint.sh` retry kết nối MySQL bằng PDO của chính PHP (không
   dùng CLI `mysqladmin` — tránh lệch hành vi TLS giữa MariaDB client và
   mysqlnd khi MySQL 8 dùng self-signed cert mặc định).
2. Chạy `database/init.php`: import `schema.sql` (idempotent nhờ
   `CREATE TABLE IF NOT EXISTS`), sau đó đồng bộ tài khoản admin duy nhất từ
   `ADMIN_EMAIL`/`ADMIN_PASSWORD` (tạo mới nếu chưa có, cập nhật nếu đã có).
3. `exec apache2-foreground`.

## Bảo mật

- Toàn bộ output động đều qua `e()` (htmlspecialchars) trước khi chèn HTML —
  chống XSS, đã test với payload `<script>` qua form lead.
- CSRF token theo session cho mọi POST thay đổi dữ liệu (login, profile,
  link CRUD, lead).
- Mật khẩu băm bằng `password_hash()` (bcrypt), xác thực qua `password_verify()`.
- Tất cả câu SQL dùng PDO prepared statement (không nối chuỗi SQL).
- Upload avatar giới hạn 2MB, kiểm MIME thật qua `finfo` (không tin đuôi file).

## Điểm cần lưu ý khi vận hành

- App stateless hoàn toàn (trừ `public/uploads/`) — dữ liệu chính nằm ở MySQL
  ngoài container, cho phép nâng cấp/rollback image tự do (xem README mục [5]).
- `/health` phản ánh đúng kết nối MySQL thật (`{"status":"up","database":"connected"}`
  hoặc `503 {"status":"down",...}`), không phải giá trị tĩnh.
