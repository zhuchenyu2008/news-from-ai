<?php
// news-from-ai/src/bootstrap.php

// 基本设置
error_reporting(E_ALL);
ini_set('display_errors', 0); // 在生产环境中应设为0，错误记录到日志
date_default_timezone_set('Asia/Shanghai'); // 根据需要设置时区

define('PROJECT_ROOT', dirname(__DIR__)); // 项目根目录 news-from-ai/

// 引入核心类
// 注意顺序，有依赖关系的类需要先引入其依赖
require_once PROJECT_ROOT . '/src/Logger.php';
require_once PROJECT_ROOT . '/src/Config.php'; // Config依赖Logger
require_once PROJECT_ROOT . '/src/Database.php'; // Database可能也需要记录日志，但其init是静态的
require_once PROJECT_ROOT . '/src/lib/Parsedown.php'; // Parsedown库
require_once PROJECT_ROOT . '/src/AIClient.php'; // AIClient依赖Logger
require_once PROJECT_ROOT . '/src/GoogleSearch.php'; // GoogleSearch依赖Logger
require_once PROJECT_ROOT . '/src/RSSReader.php'; // RSSReader依赖Logger
require_once PROJECT_ROOT . '/src/NewsGatheringService.php'; // NewsGatheringService依赖Config, Logger, GoogleSearch, AIClient
require_once PROJECT_ROOT . '/src/NewsProcessingService.php'; // NewsProcessingService依赖Config, Logger, Database, AIClient等

use NewsFromAI\Logger;
use NewsFromAI\Config;
use NewsFromAI\Database;
use NewsFromAI\NewsProcessingService;

// 初始化日志记录器
// 日志文件路径和级别应从一个非常基础的配置或环境变量读取，或者硬编码一个默认值
// 在此阶段，我们先用一个默认值，之后Config加载后可以重新初始化
$defaultLogPath = PROJECT_ROOT . '/logs/app.log';
$logDir = dirname($defaultLogPath);
if (!is_dir($logDir)) {
    mkdir($logDir, 0775, true);
}
Logger::init($defaultLogPath, 'DEBUG'); // 默认初始化

// 加载配置文件
// config.ini 的路径是相对于项目根目录的
$configFilePath = PROJECT_ROOT . '/config/config.ini';
// $config = new Config($configFilePath, new Logger()); // Config constructor no longer needs Logger instance
$config = new Config($configFilePath);

// 根据配置文件重新初始化日志记录器 (如果配置了不同的路径或级别)
$logFileFromConfig = $config->get('logging.log_file', $defaultLogPath);
// 如果配置文件中的路径是相对的 (e.g., ../logs/app.log)，需要基于特定基准转换
// 假设它是相对于项目根目录
if (strpos($logFileFromConfig, '../') === 0) { // 简单的相对路径判断
    $logFileFromConfig = PROJECT_ROOT . '/' . str_replace('../', '', $logFileFromConfig);
} elseif ($logFileFromConfig[0] !== '/' && !preg_match('/^[a-zA-Z]:\\\\/', $logFileFromConfig)) { // 不是绝对路径 (Unix/Windows)
    $logFileFromConfig = PROJECT_ROOT . '/' . $logFileFromConfig;
}

$logDir = dirname($logFileFromConfig);
if (!is_dir($logDir)) {
    if (!mkdir($logDir, 0775, true) && !is_dir($logDir)) {
         error_log("Bootstrap: Failed to create log directory from config: {$logDir}");
    }
}
Logger::init($logFileFromConfig, $config->get('logging.log_level', 'DEBUG'));

Logger::info("Bootstrap: Application initialized.");
Logger::debug("Bootstrap: Project root: " . PROJECT_ROOT);
Logger::debug("Bootstrap: Config file path: " . $configFilePath);
Logger::debug("Bootstrap: Log file path: " . Logger::getLogFile());


// 开启全局异常处理器
set_exception_handler(function(Throwable $exception) {
    Logger::critical("Unhandled Exception: " . $exception->getMessage(), [
        'file' => $exception->getFile(),
        'line' => $exception->getLine(),
        'trace' => $exception->getTraceAsString()
    ]);
    // 在生产环境中，这里应该显示一个用户友好的错误页面
    if (ini_get('display_errors')) {
        // echo "<pre>Unhandled Exception:\n";
        // print_r($exception);
        // echo "</pre>";
        // 避免直接输出敏感信息到浏览器
         echo "An unexpected error occurred. Please try again later. Details have been logged.";
    } else {
        echo "An unexpected error occurred. Please try again later.";
    }
    exit(1);
});

set_error_handler(function($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) {
        // This error code is not included in error_reporting
        return false;
    }
    Logger::error("PHP Error: [$severity] $message", [
        'file' => $file,
        'line' => $line
    ]);
    // 不要执行 PHP 内部错误处理程序, 如果返回false，则会执行
    return true; // 返回true表示错误已处理
});


// 初始化数据库连接
$dbConfig = $config->get('database');
if ($dbConfig) {
    Database::init($dbConfig);
    // 尝试获取连接以确保配置正确
    $pdo = Database::getConnection();
    if ($pdo) {
        Logger::info("Bootstrap: Database connection successful.");
    } else {
        Logger::critical("Bootstrap: Database connection failed. Please check config.ini and database server.");
        // 可以在这里决定是否终止程序
        // die("Database connection failed. Check logs for details.");
    }
} else {
    Logger::critical("Bootstrap: Database configuration not found in config.ini.");
    // die("Database configuration missing. Check logs for details.");
}

// 实例化核心服务 (全局可用或通过依赖注入)
// $newsProcessingService = new NewsProcessingService($config, new Logger(), new Database());
// 改为使用已初始化的Logger和Database实例
$newsProcessingService = new NewsProcessingService($config, Logger::class, Database::class);


// 返回全局变量，供 index.php 使用
return [
    'config' => $config,
    'logger' => Logger::class, // 返回类名，静态调用
    'db' => Database::class,   // 返回类名，静态调用
    'newsProcessingService' => $newsProcessingService
];

?>
