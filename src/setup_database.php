<?php
// news-from-ai - 数据库设置脚本

// 确保可以从项目根目录的任何地方运行此脚本
// 假设此脚本位于 src/ 目录下，config.php 位于 config/ 目录下
$configPath = __DIR__ . '/../config/config.php';

if (!file_exists($configPath)) {
    die("错误：配置文件未找到！请确保 config/config.php 存在。\n");
}

$config = require $configPath;

$dbConfig = $config['db'];

// 创建数据库连接
$mysqli = new mysqli($dbConfig['host'], $dbConfig['username'], $dbConfig['password']);

// 检查连接
if ($mysqli->connect_error) {
    die("数据库连接失败: " . $mysqli->connect_error . "\n");
}

// 尝试创建数据库 (如果不存在)
$dbName = $mysqli->real_escape_string($dbConfig['dbname']);
if (!$mysqli->query("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET {$dbConfig['charset']} COLLATE {$dbConfig['charset']}_unicode_ci")) {
    die("错误：创建数据库 '{$dbName}' 失败: " . $mysqli->error . "\n");
}
echo "数据库 '{$dbName}' 检查/创建成功。\n";

// 选择数据库
$mysqli->select_db($dbName);

// 设置字符集
if (!$mysqli->set_charset($dbConfig['charset'])) {
    printf("错误：加载字符集utf8mb4失败: %s\n", $mysqli->error);
    exit();
}

// SQL语句创建表
$sqlStatements = [
    "CREATE TABLE IF NOT EXISTS `news_articles` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `title` VARCHAR(255) NOT NULL,
        `source_url` TEXT NOT NULL,
        `source_name` VARCHAR(100) DEFAULT NULL,
        `original_content` TEXT DEFAULT NULL,
        `ai_generated_content` MEDIUMTEXT NOT NULL,
        `ai_presentation_format` VARCHAR(50) NOT NULL COMMENT 'e.g., timeline, multi_confirm, single_article',
        `published_at` DATETIME DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        `rss_feed_id` INT DEFAULT NULL,
        `search_keywords` VARCHAR(255) DEFAULT NULL,
        INDEX `idx_published_at` (`published_at`),
        INDEX `idx_rss_feed_id` (`rss_feed_id`)
        -- CONSTRAINT `fk_news_rss_feed` FOREIGN KEY (`rss_feed_id`) REFERENCES `rss_feeds`(`id`) ON DELETE SET NULL ON UPDATE CASCADE -- 稍后添加，确保rss_feeds表先创建
    ) ENGINE=InnoDB DEFAULT CHARSET={$dbConfig['charset']} COLLATE={$dbConfig['charset']}_unicode_ci;",

    "CREATE TABLE IF NOT EXISTS `rss_feeds` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `name` VARCHAR(100) NOT NULL,
        `url` VARCHAR(255) NOT NULL UNIQUE,
        `last_fetched_at` DATETIME DEFAULT NULL,
        `is_active` BOOLEAN DEFAULT TRUE,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX `idx_is_active` (`is_active`)
    ) ENGINE=InnoDB DEFAULT CHARSET={$dbConfig['charset']} COLLATE={$dbConfig['charset']}_unicode_ci;",

    "CREATE TABLE IF NOT EXISTS `ai_tasks_log` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `task_type` VARCHAR(100) NOT NULL COMMENT 'e.g., rss_summary, news_sourcing, presentation_design',
        `input_data_ref` VARCHAR(255) DEFAULT NULL COMMENT 'e.g., news_id, rss_url_or_id',
        `status` VARCHAR(20) NOT NULL COMMENT 'success, failure',
        `prompt_used` TEXT DEFAULT NULL,
        `ai_response_raw` TEXT DEFAULT NULL,
        `error_message` TEXT DEFAULT NULL,
        `execution_time_ms` INT DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX `idx_task_type_status` (`task_type`, `status`),
        INDEX `idx_created_at` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET={$dbConfig['charset']} COLLATE={$dbConfig['charset']}_unicode_ci;"
];

// 执行SQL语句
foreach ($sqlStatements as $sql) {
    if ($mysqli->query($sql) === TRUE) {
        // 从SQL语句中提取表名用于日志输出
        if (preg_match('/CREATE TABLE IF NOT EXISTS `(\w+)`/i', $sql, $matches)) {
            echo "表 `{$matches[1]}` 检查/创建成功。\n";
        } else {
            echo "一个SQL语句执行成功。\n";
        }
    } else {
        // 从SQL语句中提取表名用于日志输出
        if (preg_match('/CREATE TABLE IF NOT EXISTS `(\w+)`/i', $sql, $matches)) {
            die("错误：创建表 `{$matches[1]}` 失败: " . $mysqli->error . "\n");
        } else {
            die("错误：执行SQL语句失败: " . $mysqli->error . "\nSQL: " . $sql . "\n");
        }
    }
}

// 现在 rss_feeds 表已创建，可以尝试添加外键到 news_articles (如果之前未成功或需要单独处理)
$alterTableSQL = "ALTER TABLE `news_articles`
    ADD CONSTRAINT `fk_news_rss_feed`
    FOREIGN KEY IF NOT EXISTS (`rss_feed_id`) REFERENCES `rss_feeds`(`id`)
    ON DELETE SET NULL ON UPDATE CASCADE;";

// 检查外键是否已存在 (这是一个简化的检查，更可靠的方法是查询 information_schema)
$checkFkSQL = "SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
               WHERE TABLE_SCHEMA = '{$dbName}' AND TABLE_NAME = 'news_articles' AND CONSTRAINT_NAME = 'fk_news_rss_feed';";
$fkResult = $mysqli->query($checkFkSQL);

if ($fkResult && $fkResult->num_rows == 0) {
    if ($mysqli->query($alterTableSQL) === TRUE) {
        echo "外键 `fk_news_rss_feed` 添加到表 `news_articles` 成功。\n";
    } else {
        // 如果添加外键失败，打印错误但允许脚本继续，因为核心表已创建
        echo "警告：添加外键 `fk_news_rss_feed` 到表 `news_articles` 失败: " . $mysqli->error . "\n这可能是因为表中已存在不符合外键约束的数据，或者外键已存在但名称不同。请手动检查。\n";
    }
} else if ($fkResult) {
    echo "外键 `fk_news_rss_feed` 似乎已存在于表 `news_articles`。\n";
} else {
    echo "警告：检查外键 `fk_news_rss_feed` 是否存在时出错: " . $mysqli->error . "\n";
}


echo "数据库设置脚本执行完毕。\n";

// 关闭连接
$mysqli->close();

?>
<p>数据库设置脚本已执行。请检查上面的输出以获取详细信息。</p>
<p><b>重要提示：</b></p>
<ul>
    <li>请确保 <code>config/config.php</code> 中的数据库用户名和密码具有创建数据库和表的权限。</li>
    <li>此脚本会尝试创建数据库（如果它不存在）。</li>
    <li>此脚本会创建 <code>news_articles</code>, <code>rss_feeds</code>, 和 <code>ai_tasks_log</code> 表（如果它们不存在）。</li>
    <li>您可以从命令行运行此脚本：<code>php src/setup_database.php</code></li>
</ul>
