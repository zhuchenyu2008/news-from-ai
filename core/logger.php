<?php
// core/logger.php

if (session_status() == PHP_SESSION_NONE) {
    // 如果代码在web环境外（如cron）运行，session_start()可能会报错或不必要
    // 但如果也可能从web调用，则需要它。为简单起见，这里先加上。
    // 更好的做法是判断 SAPI 类型。
    // if (php_sapi_name() !== 'cli') {
    //    @session_start();
    // }
}

// 确保配置文件已加载，以便获取 LOG_FILE_PATH
// 通常，核心文件会通过一个统一的入口文件加载配置，这里为了模块独立性先直接包含
// 在实际项目中，config.php 应该在 cron 脚本或 index.php 的早期被包含
if (!defined('LOG_FILE_PATH')) {
    // 尝试加载配置文件，如果它还没有被加载的话
    $configPath = __DIR__ . '/../config/config.php';
    if (file_exists($configPath)) {
        require_once $configPath;
    } else {
        // 如果配置文件不存在，提供一个默认的日志路径或者直接退出
        // 为了避免在没有配置的情况下完全失败，我们可以定义一个后备路径
        // 但这通常表示配置问题
        if (!defined('LOG_FILE_PATH')) { // 再次检查，防止 config.php 内部定义了但这里没立即认出
             // 在 cron 环境下，__DIR__ 指向 core 目录
            $fallbackLogPath = __DIR__ . '/../logs/app_fallback.log';
            define('LOG_FILE_PATH', $fallbackLogPath);
            // 可以尝试记录一条关于配置缺失的日志，但这本身也需要日志路径
            error_log("警告: config.php 未找到或未定义 LOG_FILE_PATH。日志将写入: " . $fallbackLogPath, 0);
        }
    }
}


/**
 * 记录日志信息到文件
 *
 * @param string $message 日志消息
 * @param string $level 日志级别 (INFO, WARNING, ERROR, DEBUG) - 默认为 INFO
 * @return void
 */
function write_log(string $message, string $level = 'INFO'): void {
    // 确保 LOG_FILE_PATH 已定义
    if (!defined('LOG_FILE_PATH')) {
        error_log("严重错误: LOG_FILE_PATH 未定义。无法写入日志: [{$level}] {$message}");
        return;
    }

    $logFilePath = LOG_FILE_PATH;

    // 检查日志目录是否存在，如果不存在则尝试创建
    $logDir = dirname($logFilePath);
    if (!is_dir($logDir)) {
        // 尝试创建目录，@禁止错误输出，后面检查结果
        if (!@mkdir($logDir, 0755, true) && !is_dir($logDir)) { // 第二个is_dir检查是防止并发创建问题
            error_log("严重错误: 无法创建日志目录 {$logDir}。请检查权限。");
            return;
        }
    }

    // 检查日志文件是否可写
    if ((file_exists($logFilePath) && !is_writable($logFilePath)) || (!file_exists($logFilePath) && !is_writable($logDir))) {
        error_log("严重错误: 日志文件 {$logFilePath} 或其目录不可写。请检查权限。");
        return;
    }

    $timestamp = date('Y-m-d H:i:s'); // 获取当前时间戳
    $logEntry = "[{$timestamp}] [{$level}] {$message}" . PHP_EOL; // PHP_EOL 用于跨平台的换行符

    // 将日志条目追加到日志文件
    // FILE_APPEND 确保内容是追加而不是覆盖
    // LOCK_EX 防止并发写入时文件损坏 (有一定性能开销，但对于日志是值得的)
    if (file_put_contents($logFilePath, $logEntry, FILE_APPEND | LOCK_EX) === false) {
        // 如果写入失败，尝试通过 error_log 输出到系统日志或web服务器日志
        error_log("错误: 无法写入日志文件 {$logFilePath}。消息: [{$level}] {$message}");
    }
}

/**
 * 辅助函数：记录INFO级别的日志
 * @param string $message
 * @return void
 */
function log_info(string $message): void {
    write_log($message, 'INFO');
}

/**
 * 辅助函数：记录WARNING级别的日志
 * @param string $message
 * @return void
 */
function log_warning(string $message): void {
    write_log($message, 'WARNING');
}

/**
 * 辅助函数：记录ERROR级别的日志
 * @param string $message
 * @return void
 */
function log_error(string $message): void {
    write_log($message, 'ERROR');
}

/**
 * 辅助函数：记录DEBUG级别的日志
 * @param string $message
 * @return void
 */
function log_debug(string $message): void {
    // 可以考虑添加一个配置项来控制是否记录DEBUG日志
    // if (defined('DEBUG_MODE') && DEBUG_MODE === true) {
    //     write_log($message, 'DEBUG');
    // }
    write_log($message, 'DEBUG');
}

// 用法示例:
// (确保 config.php 和 logger.php 在正确的路径并可被包含)
// require_once __DIR__ . '/../config/config.php'; // 通常在入口文件顶部
// require_once __DIR__ . '/logger.php';          // 通常在入口文件顶部

// log_info("这是一条信息日志。");
// log_warning("这是一条警告日志。");
// log_error("发生了一个错误！详情请查看相关上下文。");
// log_debug("调试信息：用户ID是 123。");

?>
