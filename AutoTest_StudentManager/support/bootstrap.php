<?php
// Nạp mã nguồn backend và bộ test KTPM mà không phải chép file vào backend/.
$backend = getenv('KTPM_BACKEND') ?: __DIR__ . '/../../backend';
$loader = require $backend . '/vendor/autoload.php';
$loader->addPsr4('Tests\\Feature\\KTPM\\', __DIR__ . '/../backend/tests/Feature/KTPM/');
