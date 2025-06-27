# news-from-ai (AI新闻聚合器)

`news-from-ai` 是一个PHP驱动的网页应用，它利用AI技术定时收集、处理并展示时事新闻。AI不仅负责内容的聚合，还能够根据预设的提示词自行设计新闻在网页上的呈现形式（如时间线、多方观点对比、单篇文章等）。

## 主要特性

*   **AI驱动的新闻收集**：定时从多种来源（包括RSS订阅和AI主动搜索）获取最新新闻。
*   **AI设计呈现形式**：AI根据新闻内容和预设提示词，智能决定新闻的展示方式（Markdown + HTML）。
*   **RSS整合与AI摘要**：支持添加RSS源，并使用AI对文章进行摘要和总结。
*   **来源可追溯**：所有新闻均提供来源链接或原文，保证信息透明度。
*   **动态主题**：根据时间自动切换白天（白色主题）和夜晚（黑色主题）模式，提供舒适的阅读体验。
*   **细腻动画**：简洁、美观的界面过渡和交互动画。
*   **高度可配置**：
    *   AI API（支持OpenAI通用格式）及模型可针对不同任务单独配置。
    *   新闻收集的定时周期由用户决定（通过宝塔面板等工具）。
    *   AI提示词可在配置文件中灵活修改。
    *   Google Custom Search API 用于AI联网搜索。
*   **数据库存储**：使用MySQL存储新闻数据和配置信息。
*   **详细日志**：中文日志输出，方便追踪和调试。

## 技术栈

*   **后端**：PHP (推荐 PHP 7.4 或更高版本)
*   **数据库**：MySQL
*   **前端**：HTML, CSS, JavaScript
*   **AI集成**：通过API与支持OpenAI通用格式的AI模型交互。
*   **定时任务**：推荐使用宝塔面板的计划任务，或其他类似的cron job工具。

## 安装与配置

1.  **环境要求**：
    *   PHP (推荐 PHP 7.4 或更高版本，已启用 `mysqli` 和 `curl` 扩展)。
    *   MySQL数据库服务器。
    *   Web服务器 (如 Nginx 或 Apache)。
    *   （可选）Composer，如果未来项目引入了通过Composer管理的依赖。

2.  **下载代码**：
    *   如果您从Git仓库克隆：
        ```bash
        git clone YOUR_GIT_REPO_URL_HERE news-from-ai
        cd news-from-ai
        ```
      请将 `YOUR_GIT_REPO_URL_HERE` 替换为实际的仓库地址。
    *   或者，如果您下载的是ZIP压缩包，请解压到您的服务器 Web 目录下。

3.  **配置文件**：
    *   进入项目根目录下的 `config/` 目录。
    *   打开 `config.php` 文件。
    *   **仔细修改以下占位符为您自己的实际配置信息**：
        *   **数据库连接** (`db` 部分): `host`, `username`, `password`, `dbname`。
        *   **AI API配置** (`ai` 部分):
            *   为 `default`, `news_sourcing`, `rss_summary` (或其他您添加的特定任务) 配置 `api_key` 和 `api_url`。`api_url` 通常指向类似 `https://api.openai.com/v1/chat/completions` 的地址。
            *   确保为不同的AI任务（如果需要隔离）使用对应的API密钥。
        *   **Google Custom Search API** (`google_search` 部分): `api_key` 和 `cse_id` (Programmable Search Engine ID)。如果不需要AI主动搜索新闻功能，可以暂时留空占位符，但相关功能将不可用。
        *   **RSS源** (`rss_feeds` 部分): 根据您的喜好添加或修改RSS订阅源列表，格式为 `'名称' => 'URL'`。
        *   **日志配置** (`logging` 部分): 通常可以保留默认的 `file_path` (`项目根目录/logs/app.log`) 和 `level` (`DEBUG`)。确保 `logs` 目录对PHP进程可写。
        *   **AI提示词** (`prompts` 部分): 您可以根据需求调整预设的AI提示词。

4.  **数据库设置**：
    *   确保您在 `config.php` 中配置的数据库用户具有创建数据库（如果 `dbname` 指定的数据库不存在）和创建表的权限。
    *   在项目根目录下，通过命令行执行以下PHP脚本来初始化数据库和表结构：
        ```bash
        php src/setup_database.php
        ```
    *   检查脚本输出，确保数据库和表（`news_articles`, `rss_feeds`, `ai_tasks_log`）都已成功创建。

5.  **Web服务器配置**：
    *   将您的Web服务器（如Nginx或Apache）的文档根目录 (DocumentRoot) 指向本项目根目录下的 `public` 文件夹。
    *   **URL重写** (推荐，用于美观的URL，但本项目当前结构不强制依赖)：
        *   **Nginx**:
            ```nginx
            location / {
                try_files $uri $uri/ /index.php?$query_string;
            }
            ```
        *   **Apache**: 确保 `AllowOverride All` 在您的虚拟主机配置中已启用，并在 `public/.htaccess` 文件中（如果尚不存在，则创建）添加：
            ```apache
            RewriteEngine On
            RewriteCond %{REQUEST_FILENAME} !-f
            RewriteCond %{REQUEST_FILENAME} !-d
            RewriteRule ^ index.php [L]
            ```
            (注意: 本项目目前所有请求都直接指向 `public/index.php`，没有复杂的路由，所以上述重写规则主要是为了隐藏 `index.php`)

6.  **定时任务 (Cron Job)**：
    *   为了让应用能够自动定时收集新闻，您需要设置一个定时任务（Cron Job）来定期执行 `src/cron_job.php` 脚本。
    *   **脚本路径**：假设您的项目完整路径是 `/var/www/html/news-from-ai`，那么需要执行的命令是 `php /var/www/html/news-from-ai/src/cron_job.php`。请务必替换为您的实际项目路径。
    *   **执行周期**：参考 `config/config.php` 文件中的 `cron_task.user_defined_schedule` (此为注释，实际周期在cron工具中设置)。例如，`0 * * * *` 表示每小时的第0分钟执行一次。
    *   **使用宝塔面板设置** (推荐)：
        1.  登录宝塔面板 -> 左侧菜单“计划任务”。
        2.  任务类型: “Shell脚本”。
        3.  任务名称: (自定义, e.g., “AI新闻聚合器”)。
        4.  执行周期: (根据需求选择, e.g., 每小时的第5分钟)。
        5.  脚本内容:
            ```bash
            php /YOUR_PROJECT_PATH/src/cron_job.php >> /YOUR_PROJECT_PATH/logs/cron_output.log 2>&1
            ```
            替换 `/YOUR_PROJECT_PATH/` 为您项目的实际绝对路径。重定向输出到 `cron_output.log` 有助于调试。
        7.  点击“添加任务”。
    *   **使用Linux Crontab手动设置**：
        1.  SSH登录服务器，执行 `crontab -e`。
        2.  添加行 (例如每小时执行):
            ```cron
            0 * * * * /usr/bin/php /YOUR_PROJECT_PATH/src/cron_job.php >> /YOUR_PROJECT_PATH/logs/cron_output.log 2>&1
            ```
            确保 `/usr/bin/php` 是正确的PHP CLI路径 (用 `which php` 查看)，并替换项目路径。
        4.  保存退出。
    *   **检查执行**：查看 `logs/app.log` (应用日志) 和 `logs/cron_output.log` (cron直接输出) 来确认任务运行情况。

## 如何运行

1.  完成上述所有“安装与配置”步骤。
2.  确保您的Web服务器已启动并正确配置指向 `public` 目录。
3.  通过浏览器访问您的网站域名 (例如 `http://yourdomain.com` 或 `http://localhost/path-to-project/public/`，取决于您的设置)。
4.  首次运行时，页面可能没有新闻。您需要等待定时任务执行一次（或手动通过命令行 `php src/cron_job.php` 执行一次进行测试）来填充新闻数据。

## 目录结构

```
news-from-ai/
├── public/             # Web可访问目录 (文档根目录应指向此)
│   ├── css/
│   │   └── style.css
│   ├── js/
│   │   └── main.js
│   └── index.php       # 应用主入口
├── src/                # PHP核心代码
│   ├── includes/       # 通用函数、Markdown解析器等
│   ├── services/       # AI服务、搜索服务、新闻处理服务
│   ├── cron_job.php    # 定时任务执行脚本
│   └── setup_database.php # 数据库初始化脚本
├── config/             # 配置文件
│   └── config.php
├── templates/          # HTML模板文件
│   └── main.php
├── logs/               # 日志文件 (确保此目录对PHP可写)
└── README.md           # 本文件
```

## 已完成功能

*   [x] 数据库表结构设计与创建脚本
*   [x] AI交互模块实现 (OpenAI兼容API)
*   [x] Google Search API 集成
*   [x] RSS抓取与处理模块
*   [x] AI进行RSS摘要总结
*   [x] AI根据内容设计呈现形式 (Markdown+HTML, 格式选择)
*   [x] 新闻呈现逻辑与前端模板 (PHP + HTML)
*   [x] 日夜主题切换与CSS动画效果
*   [x] 详细的日志系统 (级别控制、文件存储)
*   [x] 详细的安装、配置和使用文档

## 贡献

欢迎提交 Pull Requests 或 Issues 改进此项目。

## 许可证

本项目采用 [MIT 许可证](LICENSE)。

(请在项目根目录创建一个名为 `LICENSE` 的文件，并将MIT许可证文本粘贴进去。)

---
AI新闻聚合器，为您带来智能化的新闻阅读体验！
