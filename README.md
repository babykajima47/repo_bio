# VN BioLink Hub

Hệ thống tạo trang Bio Link giới thiệu bản thân, liên kết mạng xã hội (TikTok,
Shopee, Website, Facebook...) và thu thập khách hàng tiềm năng (lead) — nút Gọi
Hotline / Nhắn tin Zalo, đếm lượt click từng liên kết, quản trị qua dashboard
riêng. PHP 8.2 thuần (không framework) + MySQL 8.0 + TailwindCSS (CDN).

Repo: `github.com/matbaogit/vn-biolink-hub`

---

## [1] Yêu cầu trước khi chạy

- Docker Engine ≥ 20.10.
- Runtime bên trong image: **PHP 8.2** (Apache mod_php) + extension `pdo_mysql`.
- Cần **MySQL 8.0** chạy sẵn (image chỉ đóng gói app — single container, không
  bundle MySQL) — tự chạy 1 container MySQL cạnh bên cho local, hoặc trỏ vào
  MySQL do Vibe Host tự cấp qua `envMapping` khi deploy thật.

## [2] Lệnh dựng & chạy

**Cách A — copy-paste chạy ngay bằng docker run thuần (test local, không cần docker-compose):**

```bash
docker network create biolink-net

docker run -d --name biolink-mysql --network biolink-net \
  -e MYSQL_ROOT_PASSWORD=root_secret \
  -e MYSQL_DATABASE=vn_biolink_hub \
  -e MYSQL_USER=biolink \
  -e MYSQL_PASSWORD=biolink_secret \
  mysql:8.0

docker build -t vn-biolink-hub .

docker run -d --name vn-biolink-hub --network biolink-net \
  -p 8080:80 \
  -e APP_ENV=local \
  -e ADMIN_EMAIL=admin@example.com \
  -e ADMIN_PASSWORD=ChangeMe123! \
  -e DB_HOST=biolink-mysql \
  -e DB_PORT=3306 \
  -e DB_DATABASE=vn_biolink_hub \
  -e DB_USERNAME=biolink \
  -e DB_PASSWORD=biolink_secret \
  vn-biolink-hub

# App:   http://localhost:8080
# Admin: http://localhost:8080/login  (admin@example.com / ChangeMe123!)
```

**Cách B — tự build image + tự có sẵn MySQL 8.0 (mô phỏng đúng cách Vibe Host chạy):**

```bash
docker build -t vn-biolink-hub .

docker run -d --name vn-biolink-hub \
  -p 8080:80 \
  -e APP_ENV=production \
  -e ADMIN_EMAIL=admin@shop-cua-ban.vn \
  -e ADMIN_PASSWORD="MatKhauManh123!" \
  -e DB_HOST=<host-mysql-cua-ban> \
  -e DB_PORT=3306 \
  -e DB_DATABASE=vn_biolink_hub \
  -e DB_USERNAME=biolink \
  -e DB_PASSWORD=<mat-khau-mysql> \
  vn-biolink-hub
```

> Trên Vibe Host: `ADMIN_PASSWORD` do nền tảng **tự sinh ngẫu nhiên** khi
> deploy (`generated: true`); các biến `DB_*` được nền tảng tự tiêm theo
> `envMapping` sau khi provisioning MySQL 8.0 — không cần tự nhập tay.

## [3] Dấu hiệu đã chạy thành công

- `GET /health` → HTTP **200**, body:
  ```json
  {"status":"up","database":"connected"}
  ```
  Nếu MySQL chưa kết nối được, endpoint trả **503** với `{"status":"down","database":"disconnected"}`.
- Truy cập `http://localhost:8080/` → hiển thị trang bio-link (avatar, tên,
  nút Hotline/Zalo, danh sách liên kết, form nhận tư vấn).
- Log container in ra:
  ```
  [entrypoint] MySQL đã sẵn sàng.
  [init] Schema OK.
  [init] Đã tạo tài khoản quản trị đầu tiên: admin@shop-cua-ban.vn
  ```

## [4] Tài khoản đầu tiên

- Trang quản trị: `http://localhost:8080/login`
- Đăng nhập bằng đúng `ADMIN_EMAIL` / `ADMIN_PASSWORD` đã set lúc deploy.
- Cơ chế: `docker-entrypoint.sh` chờ MySQL sẵn sàng → gọi `database/init.php`,
  script này **luôn đồng bộ** (tạo mới nếu chưa có, hoặc cập nhật nếu đã có)
  tài khoản quản trị duy nhất khớp với 2 biến môi trường hiện tại — mỗi lần
  container khởi động lại. Vì vậy đổi `ADMIN_PASSWORD` rồi redeploy là cách
  hợp lệ để đổi mật khẩu admin.

## [5] Nâng cấp & lùi bản (bảo toàn dữ liệu MySQL)

- Toàn bộ dữ liệu (profile, liên kết, lượt click, lead) nằm trong **MySQL bên
  ngoài container ứng dụng** — image app hoàn toàn stateless (chỉ có
  `public/uploads/` cho ảnh đại diện cần mount volume riêng).
- **Nâng cấp bản mới:** build/pull image tag mới → `docker stop/rm` container
  ứng dụng cũ → chạy container mới **trỏ cùng MySQL/volume uploads**. Schema
  dùng `CREATE TABLE IF NOT EXISTS` nên tự động migrate an toàn.
- **Lùi bản (rollback):** chạy lại image tag cũ hơn, trỏ cùng MySQL — dữ liệu
  không đổi vì nằm ngoài image ứng dụng.
- **Trước khi nâng cấp lớn**, nên sao lưu MySQL:
  ```bash
  docker exec <mysql-container> mysqldump -u root -p vn_biolink_hub > backup.sql
  ```

## [6] Lỗi thường gặp

1. **Container app thoát ngay với log "MySQL không sẵn sàng sau ...s"**
   MySQL khởi động chậm hơn app (rất hay gặp khi cả 2 cùng start lần đầu — lần
   đầu MySQL còn phải `initialize datadir`, mất 10-20s trước khi nhận kết nối).
   `docker-entrypoint.sh` đã tự retry kết nối tối đa 30 lần / 2s = 60s bằng
   chính PDO của PHP (không dùng CLI `mysqladmin ping` — bản thân template
   này lúc build thử nghiệm với MySQL 8.0 đã dính đúng lỗi
   `TLS/SSL error: self-signed certificate in certificate chain` khi gọi
   `mysqladmin ping`, do gói `default-mysql-client` trên Debian là MariaDB
   client verify TLS khác cách với `mysqlnd` mà PHP dùng — dùng PDO để chờ vừa
   né lỗi này vừa test đúng đường mà app thật sự sẽ kết nối). Nếu MySQL của
   bạn khởi động lâu hơn 60s, tăng `MAX_TRIES` trong `docker-entrypoint.sh`.

2. **Truy cập bất kỳ trang nào cũng ra lỗi 404, kể cả `/`**
   Thường do `mod_rewrite` chưa bật hoặc `.htaccess` bị bỏ qua
   (`AllowOverride None`). Dockerfile đã chủ động `a2enmod rewrite` và set
   `AllowOverride All` cho `/var/www/html/public` — nếu bạn tự sửa Dockerfile,
   nhớ giữ 2 dòng này, nếu không toàn bộ route ngoài `/` sẽ trả 404 dù code
   đúng.

3. **`/health` trả `{"status":"down","database":"disconnected"}` dù MySQL đang chạy**
   Kiểm tra lại 5 biến `DB_HOST/DB_PORT/DB_DATABASE/DB_USERNAME/DB_PASSWORD`
   có khớp với MySQL thật không, và MySQL có cho phép user đó kết nối từ IP
   container app không (`GRANT ... TO 'user'@'%'`, không chỉ `'localhost'`).

4. **Gửi lead báo lỗi "Phiên làm việc hết hạn" (419)**
   CSRF token trong session không khớp — do tải trang từ cache cũ sau khi
   container restart, hoặc cookie bị chặn. Tải lại trang (F5) rồi gửi lại.

---

## Cấu trúc thư mục

```
public/index.php        Router + Controller + API + toàn bộ giao diện HTML (1 file, không framework)
public/.htaccess        Rewrite toàn bộ request về index.php (trừ file tĩnh có thật)
public/uploads/         Ảnh đại diện admin upload (volume riêng khi deploy)
database/schema.sql     Bảng users (kiêm hồ sơ chủ trang), links, leads — utf8mb4
database/init.php       CLI: import schema + seed/update admin từ ADMIN_EMAIL/ADMIN_PASSWORD
Dockerfile              php:8.2-apache + pdo_mysql, DocumentRoot -> public/
docker-entrypoint.sh    Retry chờ MySQL sẵn sàng -> chạy init.php -> apache2-foreground
```

Lưu ý: image chỉ đóng gói app (single container theo đúng chuẩn Vibe Host) —
không có `docker-compose.yml` trong repo. MySQL luôn là service riêng, trỏ vào
qua 5 biến `DB_*` (xem mục [2] Cách A để tự chạy 1 container MySQL cạnh bên
lúc test local).
