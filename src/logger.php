<?php
// 简易日志记录器
function log_message(string $message): void {
    $dir = __DIR__ . '/../logs';
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    $time = date('Y-m-d H:i:s');
    file_put_contents($dir . '/app.log', "[$time] $message\n", FILE_APPEND);
}
