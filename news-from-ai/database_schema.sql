-- news-from-ai/database_schema.sql
-- 数据库表结构创建脚本

-- 确保数据库存在 (如果需要手动创建数据库，请取消注释下一行)
-- CREATE DATABASE IF NOT EXISTS news_from_ai_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- USE news_from_ai_db;

-- 新闻表 (存储AI处理后的新闻)
CREATE TABLE IF NOT EXISTS `news` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `title` VARCHAR(512) NOT NULL COMMENT '新闻标题',
    `content_html` MEDIUMTEXT COMMENT 'AI整理汇总后生成的HTML内容',
    `ai_comment` TEXT COMMENT 'AI生成的评论',
    `source_url` VARCHAR(2048) COMMENT '新闻原文链接',
    `source_name` VARCHAR(255) COMMENT '新闻来源机构名',
    `published_at` DATETIME COMMENT '新闻原始发布时间',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '记录插入数据库的时间',
    `news_type` VARCHAR(20) NOT NULL COMMENT '新闻类型 (ai_search, rss)',
    `keywords` VARCHAR(255) DEFAULT NULL COMMENT '用于AI搜索的关键词',
    `rss_feed_id` INT DEFAULT NULL COMMENT '关联的RSS源ID (仅对rss类型)',
    `raw_data_json` TEXT DEFAULT NULL COMMENT '存储原始获取数据，JSON格式，用于调试或重新处理',
    INDEX `idx_news_type` (`news_type`),
    INDEX `idx_created_at` (`created_at`),
    INDEX `idx_published_at` (`published_at`),
    FOREIGN KEY (`rss_feed_id`) REFERENCES `rss_feeds`(`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='存储处理后的新闻条目';

-- RSS源表
CREATE TABLE IF NOT EXISTS `rss_feeds` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `url` VARCHAR(2048) NOT NULL UNIQUE COMMENT 'RSS源的URL',
    `name` VARCHAR(255) DEFAULT NULL COMMENT 'RSS源的名称 (可自定义或从feed解析)',
    `description` TEXT DEFAULT NULL COMMENT 'RSS源的描述 (从feed解析)',
    `last_fetched_at` TIMESTAMP NULL DEFAULT NULL COMMENT '上次成功获取该feed的时间',
    `last_error` TEXT DEFAULT NULL COMMENT '上次获取失败时的错误信息',
    `fetch_interval_minutes` INT DEFAULT 60 COMMENT '建议的抓取间隔（分钟），实际调度由cron控制',
    `is_enabled` BOOLEAN DEFAULT TRUE COMMENT '是否启用该RSS源',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '添加时间',
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='存储用户配置的RSS订阅源';

-- 可以在这里添加一些初始的RSS源数据，如果需要的话
-- 例如:
-- INSERT INTO `rss_feeds` (`url`, `name`, `is_enabled`) VALUES
-- ('http://feeds.bbci.co.uk/zhongwen/simp/rss.xml', 'BBC News 中文', TRUE),
-- ('https://www.zhihu.com/rss', '知乎每日精选', TRUE),
-- ('https://www.solidot.org/index.rss', 'Solidot', TRUE);

-- 任务执行日志表 (可选，但推荐用于追踪cron任务)
CREATE TABLE IF NOT EXISTS `cron_job_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `job_type` VARCHAR(50) NOT NULL COMMENT '任务类型 (e.g., ai_news_fetch, rss_news_fetch)',
    `start_time` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `end_time` TIMESTAMP NULL DEFAULT NULL,
    `status` VARCHAR(20) NOT NULL COMMENT '状态 (running, success, failed)',
    `messages` TEXT COMMENT '执行过程中的消息或错误详情',
    `items_processed` INT DEFAULT 0 COMMENT '处理的项目数量'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='定时任务执行日志';

-- AI API 调用日志 (可选，用于追踪API使用情况和调试)
CREATE TABLE IF NOT EXISTS `ai_api_logs` (
    `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
    `ai_type` VARCHAR(50) NOT NULL COMMENT 'AI类型 (news_gathering, news_commenting, news_summarizing)',
    `request_timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `response_timestamp` TIMESTAMP NULL DEFAULT NULL,
    `model_used` VARCHAR(100) DEFAULT NULL,
    `prompt_tokens` INT DEFAULT NULL,
    `completion_tokens` INT DEFAULT NULL,
    `total_tokens` INT DEFAULT NULL,
    `request_payload` TEXT COMMENT '发送给API的请求体 (部分或全部，注意敏感信息)',
    `response_payload` TEXT COMMENT 'API返回的响应体 (部分或全部)',
    `status_code` INT DEFAULT NULL COMMENT 'HTTP状态码',
    `error_message` TEXT DEFAULT NULL,
    `related_news_id` INT DEFAULT NULL COMMENT '关联的新闻ID',
    INDEX `idx_ai_type` (`ai_type`),
    INDEX `idx_request_timestamp` (`request_timestamp`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='AI API调用日志';

-- 创建一个用于占位符图片的目录，如果配置文件中指向本地图片
-- 这不是SQL，而是一个文件系统操作，需要在项目设置时完成。
-- mkdir -p public/images/
-- (可以放一个 placeholder.png 图片到 public/images/ 目录下)

-- 提示:
-- 1. 在实际部署前，请务必修改 `news_from_ai_db` 为你的数据库名，并设置好用户和密码。
-- 2. `raw_data_json` 字段可以非常有用，但也会占用较多空间，根据实际需求决定是否保留或定期清理。
-- 3. `published_at` 对于新闻排序和过滤非常重要，应尽可能确保获取和存储正确。
-- 4. 考虑为 `source_url` 添加唯一索引 (如果业务逻辑要求同一篇文章只入库一次)，但这需要更复杂的查重逻辑。
--    ALTER TABLE `news` ADD UNIQUE `unique_source_url` (`source_url`(255)); -- 注意索引长度限制
--    如果添加此唯一索引，则插入前需要检查URL是否存在，或者使用 INSERT IGNORE / ON DUPLICATE KEY UPDATE。

-- 结束
-- 你可以通过 phpMyAdmin 导入此文件，或者通过命令行执行：
-- mysql -u your_username -p your_database_name < database_schema.sql
-- (请先创建数据库 news_from_ai_db)
-- 或者在PHP代码中实现一个安装程序来执行这些SQL。

ALTER TABLE `news` ADD COLUMN `feed_item_guid` VARCHAR(512) DEFAULT NULL COMMENT 'RSS项目的GUID或唯一标识符，用于防止重复处理RSS条目';
ALTER TABLE `news` ADD UNIQUE INDEX `unique_feed_item` (`rss_feed_id`, `feed_item_guid`);
-- 为 news 表的 source_url 添加索引，加速查询
ALTER TABLE `news` ADD INDEX `idx_source_url` (`source_url`(191)); -- 使用191以兼容utf8mb4的索引长度限制

-- 重新整理 news 表的索引，确保外键和常用查询字段有索引
-- (已有的索引: idx_news_type, idx_created_at, idx_published_at, unique_feed_item, idx_source_url)

-- 重新整理 rss_feeds 表的索引
-- (已有的索引: UNIQUE `url` (url(191) -- 假设已调整或创建时指定长度), `PRIMARY`)
ALTER TABLE `rss_feeds` ADD INDEX `idx_is_enabled` (`is_enabled`);
ALTER TABLE `rss_feeds` ADD INDEX `idx_last_fetched_at` (`last_fetched_at`);

-- 调整 cron_job_logs 表
ALTER TABLE `cron_job_logs` ADD INDEX `idx_job_type_status` (`job_type`, `status`);
ALTER TABLE `cron_job_logs` ADD INDEX `idx_start_time` (`start_time`);

-- 调整 ai_api_logs 表
ALTER TABLE `ai_api_logs` ADD INDEX `idx_related_news_id` (`related_news_id`);
-- (已有的索引: idx_ai_type, idx_request_timestamp)

-- 考虑为 news.title 添加 FULLTEXT 索引以支持搜索，如果需要应用内搜索功能
-- ALTER TABLE `news` ADD FULLTEXT INDEX `ft_title_content` (`title`, `content_html`); -- content_html 可能太大，需谨慎

-- 最终确认 news 表结构和索引
-- id (PK)
-- title
-- content_html
-- ai_comment
-- source_url (INDEX idx_source_url)
-- source_name
-- published_at (INDEX idx_published_at)
-- created_at (INDEX idx_created_at)
-- news_type (INDEX idx_news_type)
-- keywords
-- rss_feed_id (FK, INDEX auto-created by FK or manually add if needed)
-- raw_data_json
-- feed_item_guid
-- UNIQUE (rss_feed_id, feed_item_guid)

-- 最终确认 rss_feeds 表结构和索引
-- id (PK)
-- url (UNIQUE)
-- name
-- description
-- last_fetched_at (INDEX idx_last_fetched_at)
-- last_error
-- fetch_interval_minutes
-- is_enabled (INDEX idx_is_enabled)
-- created_at
-- updated_at

-- 无更多修改，当前SQL文件已包含上述调整。
