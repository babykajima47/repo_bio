# TASKS — VN BioLink Hub

Trạng thái: **Đã hoàn thành bản đầu (iMVP)**, đã build + test end-to-end bằng
docker-compose + MySQL 8.0 thật (không mock).

## Đã xong

- [x] Thiết kế schema MySQL (`users`, `links`, `leads`) utf8mb4.
- [x] `database/init.php`: migrate schema + seed/update admin từ env.
- [x] `docker-entrypoint.sh`: chờ MySQL sẵn sàng (fix lỗi TLS `mysqladmin` →
      chuyển sang PDO của PHP), migrate, khởi động Apache.
- [x] Trang Bio public: avatar, tên, giới thiệu, theme color, nút Hotline/Zalo,
      danh sách liên kết.
- [x] `POST /api/click`: đếm lượt click theo từng liên kết.
- [x] `POST /api/lead`: lưu lead + trả JSON, có CSRF + validate SĐT.
- [x] `/login`, `/logout`: session-based auth, `password_verify`.
- [x] `/admin` dashboard 3 tab: Hồ sơ & Giao diện, Quản lý liên kết, Leads.
- [x] CRUD liên kết (thêm/sửa/bật-tắt/xoá), upload avatar (giới hạn 2MB, check MIME).
- [x] `/health` phản ánh đúng trạng thái kết nối MySQL thật.
- [x] Test end-to-end: health check, login flow, CRUD link, click tracking,
      lead capture (kèm test chống XSS với payload `<script>`), cập nhật hồ sơ.
- [x] README.md đủ 6 mục theo chuẩn Vibe Host (yc-deploy mục 8).
- [x] `vibehost.json` khai báo đủ thông số nộp nền tảng (env, database,
      healthPath, first-run admin).

## Việc tiếp theo (nếu mở rộng)

- [ ] Thông báo lead mới qua Telegram Bot / Webhook tuỳ chỉnh.
- [ ] Thống kê nâng cao: biểu đồ theo ngày, nguồn truy cập (UTM/referrer).
- [ ] Đổi mật khẩu admin qua giao diện (hiện tại chỉ đổi qua env + redeploy).
- [ ] Rate-limit cho `/api/lead` và `/login` chống spam/brute-force.
- [ ] Đa ngôn ngữ (hiện tại 100% tiếng Việt, đúng yêu cầu ban đầu).

## Ghi chú vận hành

- Đây là template 1-click deploy cho Vibe Host — không phải sản phẩm SaaS đa
  khách hàng. Mỗi lần deploy = 1 chủ trang (single-tenant theo thiết kế).
- File `docs/PRD.md`, `docs/ARCH.md`, `docs/TASKS.md` này được tạo tự động
  bằng cách scan source code hiện có (không phải viết trước khi code) — cần
  người phụ trách rà lại nếu có thay đổi tính năng sau này.
