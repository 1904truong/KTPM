<?php
// Chờ backend (8000) và frontend (5173) sẵn sàng, tối đa 60 giây.
foreach (['127.0.0.1:8000', 'localhost:5173'] as $address) {
    $ok = false;
    for ($i = 0; $i < 60 && !$ok; $i++) {
        $socket = @stream_socket_client("tcp://$address", $errno, $error, 1);
        $ok = $socket !== false;
        $ok ? fclose($socket) : sleep(1);
    }
    if (!$ok) {
        fwrite(STDERR, "Không kết nối được $address\n");
        exit(1);
    }
}
