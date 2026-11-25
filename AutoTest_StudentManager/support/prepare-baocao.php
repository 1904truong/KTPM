<?php
// Chuẩn bị bản mã trước khi sửa lỗi (commit 91bd384) vào .tmp/baocao - chỉ làm lần đầu.
$here = dirname(__DIR__);
$old = $here . '/.tmp/baocao';
if (is_file("$old/.ready")) {
    exit(0);
}
$repo = dirname($here);
$current = "$repo/backend";

function fail(string $msg): void
{
    fwrite(STDERR, "LỖI: $msg\n");
    exit(1);
}

function run(string $cmd): void
{
    passthru($cmd, $code);
    if ($code !== 0) {
        fail("lệnh thất bại: $cmd");
    }
}

if (!is_file("$current/vendor/autoload.php")) {
    fail('chưa có backend/vendor. Hãy chạy composer install trước.');
}
echo "Đang chuẩn bị bản 91bd384 (chỉ lần đầu, có thể mất vài phút)...\n";
if (!is_dir($old) && !mkdir($old, 0777, true)) {
    fail("không tạo được thư mục $old");
}
$tar = "$old/backend.tar";
run('git -C ' . escapeshellarg($repo) . ' archive -o ' . escapeshellarg($tar) . ' 91bd384 backend');
run('tar -xf ' . escapeshellarg($tar) . ' -C ' . escapeshellarg($old));
unlink($tar);

$target = "$old/backend";
$files = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator("$current/vendor", FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);
foreach ($files as $file) {
    $dest = "$target/vendor/" . $files->getSubPathname();
    if ($file->isDir()) {
        is_dir($dest) || mkdir($dest, 0777, true);
    } else {
        is_dir(dirname($dest)) || mkdir(dirname($dest), 0777, true);
        copy($file->getPathname(), $dest) || fail("không chép được $dest");
    }
}
foreach (['storage/framework/views', 'storage/logs', 'bootstrap/cache'] as $dir) {
    is_dir("$target/$dir") || mkdir("$target/$dir", 0777, true);
}
file_put_contents("$target/.env", 'APP_KEY=base64:' . base64_encode(random_bytes(32)) . "\n");
file_put_contents("$old/.ready", '');
