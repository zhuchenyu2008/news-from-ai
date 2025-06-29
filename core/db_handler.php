<?php
// core/db_handler.php

// 配置文件和日志应由调用者（如cron脚本）确保已加载
// if (!defined('DB_HOST')) { require_once __DIR__ . '/../config/config.php'; }
// if (!function_exists('log_info')) { require_once __DIR__ . '/logger.php'; }


/**
 * @var ?PDO $pdo PDO数据库连接实例
 */
$pdo = null;

/**
 * 获取PDO数据库连接实例 (单例模式)
 * @return PDO|null PDO实例，如果连接失败则返回null
 */
function get_db_connection(): ?PDO {
    global $pdo;

    if ($pdo === null) {
        // 确保常量已定义
        if (!defined('DB_HOST') || !defined('DB_NAME') || !defined('DB_USER') || !defined('DB_PASS') || !defined('DB_CHARSET')) {
            if(function_exists('log_error')) log_error("数据库配置常量未完整定义。");
            else error_log("数据库配置常量未完整定义。");
            return null;
        }

        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // 推荐：错误报告为异常
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // 默认获取模式为关联数组
            PDO::ATTR_EMULATE_PREPARES   => false,                  // 禁用模拟预处理，使用真正的预处理语句
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
            if(function_exists('log_info')) log_info("数据库连接成功。");
        } catch (PDOException $e) {
            if(function_exists('log_error')) log_error("数据库连接失败: " . $e->getMessage());
            else error_log("数据库连接失败: " . $e->getMessage());
            $pdo = null; //确保失败时pdo仍为null
            return null;
        }
    }
    return $pdo;
}

/**
 * 检查新闻是否已存在于数据库中（基于source_url）
 * @param PDO $db PDO数据库连接
 * @param string $sourceUrl 新闻来源URL
 * @return bool 如果存在则返回true，否则返回false
 */
function news_exists(PDO $db, string $sourceUrl): bool {
    // 对source_url进行规范化，去除末尾的斜杠，以提高匹配率
    $normalizedUrl = rtrim($sourceUrl, '/');
    try {
        // 考虑到URL可能有变体（http vs https, www vs non-www），更复杂的去重可能需要更高级的URL规范化
        // 或者基于标题和域名进行模糊匹配，但这里先用精确的source_url
        $stmt = $db->prepare("SELECT COUNT(*) FROM news WHERE source_url = :source_url OR source_url = :normalized_url");
        $stmt->bindParam(':source_url', $sourceUrl, PDO::PARAM_STR);
        $stmt->bindParam(':normalized_url', $normalizedUrl, PDO::PARAM_STR);
        $stmt->execute();
        return $stmt->fetchColumn() > 0;
    } catch (PDOException $e) {
        if(function_exists('log_error')) log_error("检查新闻是否存在时出错: " . $e->getMessage() . " URL: " . $sourceUrl);
        else error_log("检查新闻是否存在时出错: " . $e->getMessage() . " URL: " . $sourceUrl);
        return false; // 出错时保守处理，可能导致重复，但优于中断
    }
}

/**
 * 将新闻数据插入数据库
 *
 * @param PDO $db PDO数据库连接
 * @param array $newsData 包含新闻数据的关联数组:
 *  'title' (string)
 *  'content_html' (string)
 *  'ai_comment' (string|null)
 *  'source_url' (string)
 *  'source_name' (string|null)
 *  'type' (string 'ai_generated' or 'rss')
 *  'raw_data' (string|null JSON encoded)
 *  'category' (string|null)
 *  'fetched_at' (string 'Y-m-d H:i:s')
 * @return int|false 插入成功则返回最后插入的ID，失败则返回false
 */
function insert_news(PDO $db, array $newsData) {
    // 默认值处理
    $newsData['title'] = $newsData['title'] ?? 'N/A';
    $newsData['content_html'] = $newsData['content_html'] ?? '<p>内容处理失败</p>';
    $newsData['ai_comment'] = $newsData['ai_comment'] ?? null;
    $newsData['source_url'] = $newsData['source_url'] ?? 'N/A';
    $newsData['source_name'] = $newsData['source_name'] ?? null;
    $newsData['type'] = $newsData['type'] ?? 'unknown';
    $newsData['raw_data'] = isset($newsData['raw_data']) ? (is_string($newsData['raw_data']) ? $newsData['raw_data'] : json_encode($newsData['raw_data'], JSON_UNESCAPED_UNICODE)) : null;
    $newsData['category'] = $newsData['category'] ?? null;
    $newsData['fetched_at'] = $newsData['fetched_at'] ?? date('Y-m-d H:i:s');


    $sql = "INSERT INTO news (title, content_html, ai_comment, source_url, source_name, type, raw_data, category, fetched_at)
            VALUES (:title, :content_html, :ai_comment, :source_url, :source_name, :type, :raw_data, :category, :fetched_at)";

    try {
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':title', $newsData['title']);
        $stmt->bindParam(':content_html', $newsData['content_html']);
        $stmt->bindParam(':ai_comment', $newsData['ai_comment']);
        $stmt->bindParam(':source_url', $newsData['source_url']);
        $stmt->bindParam(':source_name', $newsData['source_name']);
        $stmt->bindParam(':type', $newsData['type']);
        $stmt->bindParam(':raw_data', $newsData['raw_data']);
        $stmt->bindParam(':category', $newsData['category']);
        $stmt->bindParam(':fetched_at', $newsData['fetched_at']);

        $stmt->execute();
        return $db->lastInsertId();
    } catch (PDOException $e) {
        $logFunction = function_exists('log_error') ? 'log_error' : 'error_log';
        // 检查是否是唯一约束冲突 (错误码 23000, SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry)
        if ($e->getCode() == 23000) {
            $logFunction("插入新闻到数据库时发生唯一约束冲突 (可能已存在): " . $e->getMessage() . " URL: " . $newsData['source_url']);
        } else {
            $logFunction("插入新闻到数据库失败: " . $e->getMessage() . " Data: " . json_encode(['title' => $newsData['title'], 'source_url' => $newsData['source_url']], JSON_UNESCAPED_UNICODE)); // 只记录部分关键数据避免日志过长
        }
        return false;
    }
}

?>
