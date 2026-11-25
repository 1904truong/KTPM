<?php
// Dùng cho GitHub Actions: đếm số lần mẫu xuất hiện trong log và báo kết quả.
// Cách gọi: php ci-check.php "<nhãn>" <file log> "<regex>" <số lần tối thiểu>
[, $label, $file, $pattern, $min] = $argv;
$text = is_file($file) ? preg_replace('/\e\[[0-9;]*m/', '', file_get_contents($file)) : '';
$count = preg_match_all('~' . $pattern . '~u', $text);
$summary = [];
if (preg_match_all('~^.*(Tests: \d+.*|Tổng: \d+.*|OK \(.*)$~mu', $text, $m)) {
    $summary = array_map('trim', $m[1]);
}
$ok = $count >= (int) $min;
$detail = "tìm thấy $count/$min | " . implode(' ; ', array_slice($summary, -4));
if (!$ok) {
    $lines = array_values(array_filter(array_map('trim', explode("\n", $text))));
    $detail .= ' | cuối log: ' . implode(' / ', array_slice($lines, -12));
}
echo $text, PHP_EOL;
echo ($ok ? '::notice' : '::error') . " title=$label::" . str_replace(['%', "\r", "\n"], ['%25', '', '%0A'], $detail) . PHP_EOL;
exit($ok ? 0 : 1);
