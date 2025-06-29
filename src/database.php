<?php
require_once __DIR__ . '/logger.php';
$config = require __DIR__ . '/../config.php';
$dbConfig = $config['db'];

try {
    $dsn = sprintf(
        'mysql:host=%s;dbname=%s;charset=%s',
        $dbConfig['host'],
        $dbConfig['dbname'],
        $dbConfig['charset']
    );
    $pdo = new PDO(
        $dsn,
        $dbConfig['user'],
        $dbConfig['pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    log_message('数据库连接失败: ' . $e->getMessage());
    die('数据库连接失败');
}
