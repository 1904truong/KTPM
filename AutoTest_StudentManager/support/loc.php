<?php
// Lọc kết quả chạy test cho gọn (dùng trên Windows, nơi không có grep/perl).
//   gon     : bỏ đường dẫn file và phần giới thiệu của PHPUnit
//   api-loi : chỉ giữ test API bị Fail, đánh số thứ tự
//   ui-loi  : chỉ giữ 9 test giao diện được ghi Fail trong Excel
$mode = $argv[1] ?? 'gon';
$n = 0;
$failed = false;
while (($line = fgets(STDIN)) !== false) {
    $line = rtrim($line, "\r\n");
    $plain = preg_replace('/\e\[[0-9;]*m/', '', $line);
    if ($mode === 'gon') {
        if ($plain === '' || preg_match('/(vendor|Users).*\.php|^PHPUnit|^Runtime|^Configuration/', $plain)) {
            continue;
        }
        echo $line, PHP_EOL;
    } elseif ($mode === 'api-loi') {
        if (preg_match('/^ (✔|✘) (.*)$/u', $plain, $m)) {
            $failed = $m[1] === '✘';
            if ($failed) {
                echo PHP_EOL, "\e[31m", ++$n, '. ✘ ', $m[2], "\e[0m", PHP_EOL;
            }
        } elseif ($failed && preg_match('/├ (?=\S)(.*(Expected|Failed|empty|matches).*)$/u', $plain, $m)) {
            echo '     ', $m[1], PHP_EOL;
        } elseif (str_starts_with($plain, 'Tests: ')) {
            echo PHP_EOL, $plain, PHP_EOL;
        }
    } elseif (preg_match('/^(✔|✘) (QLHS_UI_(05|11|12|19|24)|QLND_UI_(16|17)|TTHP_UI_(03|08)) /u', $plain)) {
        echo $line, PHP_EOL;
    }
}
