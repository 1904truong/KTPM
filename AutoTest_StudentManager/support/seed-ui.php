<?php
// Tạo lại CSDL dùng riêng cho test giao diện.
require __DIR__ . '/../../backend/vendor/autoload.php';
$app = require __DIR__ . '/../../backend/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Chốt an toàn: migrate:fresh xóa sạch dữ liệu nên chỉ cho chạy trên file ui.sqlite.
$conn = config('database.default');
$db = (string) config("database.connections.$conn.database");
if ($conn !== 'sqlite' || basename($db) !== 'ui.sqlite') {
    fwrite(STDERR, "DỪNG: CSDL đang là [$conn] $db, không phải ui.sqlite.\n");
    exit(1);
}

Illuminate\Support\Facades\Artisan::call('migrate:fresh', ['--force' => true]);
require __DIR__ . '/UiTestSeeder.php';
(new Database\Seeders\UiTestSeeder)->setContainer($app)->__invoke();
Illuminate\Support\Facades\Artisan::call('cache:clear');
echo "Đã tạo dữ liệu mẫu.\n";
