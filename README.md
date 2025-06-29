# news-from-ai (AI新闻聚合器)

一个使用PHP编写的新闻聚合器，借助多个AI自动搜索、整理并展示新闻。

## 安装
1. 创建 MySQL 数据库并执行以下表结构：
   ```sql
   CREATE TABLE `news` (
     `id` int NOT NULL AUTO_INCREMENT,
     `title` varchar(255) NOT NULL,
     `content_html` text NOT NULL,
     `commentary` text,
     `source_url` varchar(500) NOT NULL,
     `created_at` datetime NOT NULL,
     PRIMARY KEY (`id`)
   ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
   ```
2. 复制 `config.php` ，根据需要填写 API 密钥、主题关键词等配置。
3. 配置 Web 服务器使 `index.php` 可访问，定时执行 `cron.php`（例如使用宝塔计划任务）。

## 功能概览
- **新闻获取AI** 使用 Google Custom Search API 获取最新新闻。
- **新闻评论AI** 对新闻进行点评。
- **新闻整理汇总AI** 生成带有 Markdown+HTML 的网页内容。
- 支持读取 RSS 并用 AI 生成摘要与评论。
- 简单的日志输出位于 `logs/app.log` 方便排查问题。
- 根据时间自动切换日夜配色。

## 配置说明
`config.php` 中可配置：
- 数据库连接信息
- 不同 AI 的 API Key、模型及地址
- 新闻关键词、RSS 地址以及 AI 提示词

设置完成后即可运行。页面会显示标题、AI 整理后的内容、评论以及来源链接。
