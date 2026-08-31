<?php
/**
 * database/init.php — CLI script chạy 1 lần mỗi lần container khởi động.
 *
 * Việc này KHÔNG lặp retry kết nối MySQL — docker-entrypoint.sh đã chờ MySQL
 * sẵn sàng trước khi gọi script này. Ở đây chỉ còn 2 việc:
 *   1. Import schema.sql (idempotent nhờ CREATE TABLE IF NOT EXISTS).
 *   2. Seed/update tài khoản admin duy nhất từ ADMIN_EMAIL + ADMIN_PASSWORD,
 *      để mỗi lần deploy lại admin luôn khớp với biến môi trường hiện tại
 *      (không để lại tài khoản admin cũ lộ mật khẩu cũ nếu operator đổi env).
 *
 * Usage: php database/init.php
 */

declare(strict_types=1);

function envOrFail(string $key): string
{
    $value = getenv($key);
    if ($value === false || $value === '') {
        fwrite(STDERR, "[init] Thiếu biến môi trường bắt buộc: {$key}\n");
        exit(1);
    }
    return $value;
}

$dbHost = getenv('DB_HOST') ?: '127.0.0.1';
$dbPort = getenv('DB_PORT') ?: '3306';
$dbName = envOrFail('DB_DATABASE');
$dbUser = envOrFail('DB_USERNAME');
$dbPass = getenv('DB_PASSWORD') ?: '';

$adminEmail    = envOrFail('ADMIN_EMAIL');
$adminPassword = envOrFail('ADMIN_PASSWORD');

$dsn = "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4";

try {
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE                  => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE       => PDO::FETCH_ASSOC,
        PDO::MYSQL_ATTR_MULTI_STATEMENTS   => true,
    ]);
} catch (PDOException $e) {
    fwrite(STDERR, '[init] Không kết nối được MySQL: ' . $e->getMessage() . "\n");
    exit(1);
}

echo "[init] Đang import schema.sql...\n";
$schema = file_get_contents(__DIR__ . '/schema.sql');
if ($schema === false) {
    fwrite(STDERR, "[init] Không đọc được database/schema.sql\n");
    exit(1);
}

try {
    $pdo->exec($schema);
} catch (PDOException $e) {
    fwrite(STDERR, '[init] Lỗi khi import schema: ' . $e->getMessage() . "\n");
    exit(1);
}
echo "[init] Schema OK.\n";

echo "[init] Đồng bộ tài khoản quản trị từ ADMIN_EMAIL/ADMIN_PASSWORD...\n";

$owner = $pdo->query('SELECT id FROM users ORDER BY id ASC LIMIT 1')->fetch();
$passwordHash = password_hash($adminPassword, PASSWORD_BCRYPT);

if ($owner === false) {
    $stmt = $pdo->prepare(
        'INSERT INTO users (email, password_hash, display_name, bio_text, theme_color)
         VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $adminEmail,
        $passwordHash,
        'Chủ trang',
        'Chào mừng bạn đến với trang giới thiệu của tôi!',
        '#4f46e5',
    ]);
    echo "[init] Đã tạo tài khoản quản trị đầu tiên: {$adminEmail}\n";
} else {
    $stmt = $pdo->prepare('UPDATE users SET email = ?, password_hash = ? WHERE id = ?');
    $stmt->execute([$adminEmail, $passwordHash, $owner['id']]);
    echo "[init] Đã đồng bộ tài khoản quản trị (id={$owner['id']}): {$adminEmail}\n";
}

echo "[init] Hoàn tất.\n";
