<?php
// news-from-ai/src/Logger.php

namespace NewsFromAI;

class Logger {
    private static string $logFile = '../logs/app.log'; // 默认日志文件路径
    private static string $logLevel = 'DEBUG'; // 默认日志级别
    private static bool $isInitialized = false;

    private const LOG_LEVELS = [
        'DEBUG'    => 0,
        'INFO'     => 1,
        'WARNING'  => 2,
        'ERROR'    => 3,
        'CRITICAL' => 4,
    ];

    /**
     * 初始化Logger配置
     * @param string $filePath 日志文件路径
     * @param string $level 日志级别 (DEBUG, INFO, WARNING, ERROR, CRITICAL)
     */
    public static function init(string $filePath, string $level = 'DEBUG'): void {
        self::$logFile = $filePath;
        $level = strtoupper($level);
        if (isset(self::LOG_LEVELS[$level])) {
            self::$logLevel = $level;
        } else {
            self::$logLevel = 'DEBUG'; // 默认为DEBUG
            self::warning("无效的日志级别配置: {$level}，已重置为 DEBUG。");
        }
        self::$isInitialized = true;

        // 尝试创建日志目录（如果不存在）
        $logDir = dirname(self::$logFile);
        if (!is_dir($logDir)) {
            if (!mkdir($logDir, 0775, true) && !is_dir($logDir)) {
                // 如果无法创建目录，记录错误到PHP错误日志
                error_log("Logger Error: Failed to create log directory: {$logDir}");
                self::$isInitialized = false; // 标记为未成功初始化
            }
        }
    }

    private static function log(string $level, string $message, array $context = []): void {
        if (!self::$isInitialized) {
            // 如果未初始化或初始化失败，尝试记录到PHP错误日志
            error_log("Logger not initialized. Message ({$level}): {$message} " . (!empty($context) ? json_encode($context) : ''));
            return;
        }

        if (self::LOG_LEVELS[$level] >= self::LOG_LEVELS[self::$logLevel]) {
            $timestamp = date('Y-m-d H:i:s');
            $logEntry = "[{$timestamp}] [{$level}] {$message}";
            if (!empty($context)) {
                $logEntry .= " " . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
            $logEntry .= PHP_EOL;

            // 使用 FILE_APPEND 来追加内容， LOCK_EX 来防止并发写入问题
            if (file_put_contents(self::$logFile, $logEntry, FILE_APPEND | LOCK_EX) === false) {
                // 如果写入失败，记录到PHP错误日志
                error_log("Logger Error: Failed to write to log file: " . self::$logFile);
            }
        }
    }

    public static function debug(string $message, array $context = []): void {
        self::log('DEBUG', $message, $context);
    }

    public static function info(string $message, array $context = []): void {
        self::log('INFO', $message, $context);
    }

    public static function warning(string $message, array $context = []): void {
        self::log('WARNING', $message, $context);
    }

    public static function error(string $message, array $context = []): void {
        self::log('ERROR', $message, $context);
    }

    public static function critical(string $message, array $context = []): void {
        self::log('CRITICAL', $message, $context);
    }

    /**
     * 检查Logger是否已初始化
     * @return bool
     */
    public static function isInitialized(): bool {
        return self::$isInitialized;
    }

    /**
     * 获取当前日志文件路径
     * @return string
     */
    public static function getLogFile(): string {
        return self::$logFile;
    }
}
?>
