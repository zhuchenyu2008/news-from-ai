# news-from-ai (AI新闻聚合器)

一个使用PHP编写的新闻聚合器，借助多个AI自动搜索、整理并展示新闻。

## 环境要求
- PHP 8.0 及以上，需启用 `curl`、`PDO`、`simplexml` 等常用扩展
- MySQL 5.7/8.0
- 服务器可联网访问 GoogleCustom Search API 及 OpenAI API

## 安装步骤
1. 克隆本项目到服务器：
   ```bash
   git clone https://github.com/yourname/news-from-ai.git
   cd news-from-ai
   ```
2. 创建 MySQL 数据库并执行以下表结构：
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
3. 复制并修改 `config.php`，填写数据库账号、OpenAI 与 Google API 密钥等信息；可根据需要调整关注的新闻主题、RSS 地址及 AI 提示词。
4. 配置 Web 服务器（Apache/Nginx 等）使 `index.php` 可访问。确保 `logs/` 目录可写。
5. 设置定时任务执行 `cron.php`。在宝塔面板中可新建计划任务，或使用 Linux `crontab`，例如：
   ```bash
   # 每半小时抓取一次新闻
   */30 * * * * /usr/bin/php /path/to/news-from-ai/cron.php
   ```
6. 访问 `index.php` 即可查看汇总后的新闻列表。

## 功能概览
- **新闻获取AI** 使用 Google Custom Search API 获取最新新闻并记录来源
- **新闻评论AI** 对新闻进行点评，可根据提示词控制语气或角度
- **新闻整理汇总AI** 将新闻和评论整理成 Markdown+HTML，以适合阅读的形式展示
- 支持读取 RSS 并用 AI 生成摘要与评论
- 所有运行信息都会写入 `logs/app.log` 便于排查问题
- 自动根据时间切换日/夜配色

## 配置文件说明
`config.php` 包含以下主要项目：
- `db`：数据库连接信息
- `openai`：三个 AI 的 API Key、接口地址与模型（`default`、`comment`、`summary`）
- `google_search`：GoogleCustom Search API 的密钥和搜索引擎 ID
- `topics`：用户关注的新闻关键词数组
- `rss_feeds`：需要抓取的 RSS 地址列表
- `rss_article_limit`：每个 RSS 源抓取的文章数量
- `prompts`：三个 AI 所使用的提示词，可根据需要调整

修改配置后运行 `php cron.php` 进行一次抓取，若成功则可在网页中看到生成的新闻条目。

## 目录结构
- `index.php`：前端展示页面
- `cron.php`：定时任务入口，负责调用各类 AI 并保存结果
- `src/`：内部库文件，包含数据库连接、日志、AI 客户端及抓取逻辑
- `style.css`：基础样式表
- `logs/`：保存运行日志的目录

## 许可
本项目以 MIT License 发布，欢迎自由修改与分享。
