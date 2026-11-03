<?php
declare(strict_types=1);
session_start();

$host = getenv('DB_HOST') ?: 'localhost';
$db   = getenv('DB_NAME') ?: 'raporku';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: ''; // Default XAMPP; ganti sesuai kredensial database hosting.

// Cloudflare Turnstile: isi setelah membuat widget di https://dash.cloudflare.com/
// Jangan pernah membagikan SECRET KEY atau mengunggahnya ke repositori publik.
$turnstileSiteKey = getenv('TURNSTILE_SITE_KEY') ?: '';
$turnstileSecretKey = getenv('TURNSTILE_SECRET_KEY') ?: '';

try {
    $pdo = new PDO(
        "mysql:host={$host};dbname={$db};charset=utf8mb4",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    http_response_code(500);
    exit('Koneksi database gagal. Pastikan MySQL aktif dan database raporku sudah dibuat. Detail: ' .
        htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
}

function e($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
function redirect(string $url = 'index.php'): never {
    header("Location: {$url}");
    exit;
}
function is_admin(): bool {
    return isset($_SESSION['user']) && $_SESSION['user']['role'] === 'admin';
}
function require_login(): void {
    if (!isset($_SESSION['user'])) redirect('index.php');
}
function require_admin(): void {
    require_login();
    if (!is_admin()) {
        http_response_code(403);
        exit('Akses ditolak. Halaman ini hanya untuk administrator.');
    }
}
