CREATE TABLE IF NOT EXISTS `news` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(255) NOT NULL COMMENT '新闻标题',
  `content_html` TEXT NOT NULL COMMENT 'AI生成的新闻内容 (HTML格式)',
  `ai_comment` TEXT NULL COMMENT 'AI生成的评论内容',
  `source_url` VARCHAR(2048) NOT NULL COMMENT '新闻来源的URL',
  `source_name` VARCHAR(255) NULL COMMENT '新闻来源的名称',
  `type` ENUM('ai_generated', 'rss') NOT NULL COMMENT '新闻类型 (AI生成 或 RSS)',
  `raw_data` JSON NULL COMMENT '存储原始获取的数据 (JSON格式)',
  `category` VARCHAR(100) NULL COMMENT '新闻分类',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '新闻条目入库时间',
  `fetched_at` DATETIME NOT NULL COMMENT '新闻原始获取/发布时间'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='存储AI聚合的新闻信息';

-- 为了提高查询效率，可以为经常用于查询和排序的列添加索引
CREATE INDEX idx_type ON news(type);
CREATE INDEX idx_category ON news(category);
CREATE INDEX idx_fetched_at ON news(fetched_at);
CREATE INDEX idx_created_at ON news(created_at);

-- 关于重复新闻：
-- 考虑到AI生成的内容和评论每次可能略有不同，source_url 可能是判断重复的主要依据。
-- 如果希望严格避免同一来源URL的新闻被多次添加，可以添加唯一索引：
-- ALTER TABLE `news` ADD UNIQUE `idx_unique_source_url` (`source_url`(767)); -- 注意InnoDB对VARCHAR唯一索引长度限制
-- 但更灵活的做法是在应用层面，在插入前检查 source_url 是否已存在，并决定是否更新或忽略。
-- 目前脚本不强制添加此唯一约束，由应用逻辑处理。
