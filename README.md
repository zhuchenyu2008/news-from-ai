# News From AI - AI新闻聚合器

**News From AI** 是一个PHP驱动的网页应用，它利用人工智能（AI）自动收集、处理和展示时事新闻。AI负责从互联网搜索新闻、进行多方比对、生成评论，并将新闻以用户友好的HTML格式（如时间线、多方证实、单篇文章等）呈现出来。应用还支持从RSS源获取文章，并由AI进行摘要和评论。

所有新闻均提供来源链接，确保可追溯性。应用界面简洁美观，支持白天/夜间模式自动切换和细腻的动画效果。

## ✨ 功能特性

*   **AI自动新闻获取**: 根据用户在配置文件中设定的关键词，AI通过Google Custom Search API搜索最新新闻。
*   **AI内容处理**:
    *   **新闻获取AI**: 初步筛选和结构化原始搜索结果。
    *   **新闻评论AI**: 对每条新闻进行分析和评论。
    *   **新闻整理汇总AI**: 将新闻内容、AI评论等信息整合成丰富的HTML在前端展示。
*   **RSS源支持**:
    *   自动从用户配置的RSS源获取最新文章。
    *   可配置AI对RSS文章进行摘要。
*   **可定制化**:
    *   新闻种类（关键词）、AI提示词、API配置（URL、密钥、模型）均可在配置文件中灵活设置。
    *   每个AI任务可以独立配置API端点和密钥。
*   **用户界面**:
    *   简洁美观的网页展示。
    *   白天/夜间模式（可根据系统时间自动切换或手动切换）。
    *   细腻的列表项入场动画。
    *   分页浏览新闻。
*   **数据存储**: 使用MySQL数据库存储新闻数据。
*   **日志系统**: 详细的日志输出，方便排查问题和监控运行状态，支持中文日志。
*   **定时任务**: 通过外部cron服务（如宝塔面板）调用核心脚本，实现定时更新。

## 🛠️ 技术栈

*   **后端**: PHP
*   **数据库**: MySQL
*   **前端**: HTML, CSS, JavaScript (原生)
*   **AI API**: 兼容OpenAI API格式的接口
*   **搜索API**: Google Custom Search API

## 🚀 安装与配置

### 1. 环境要求

*   PHP >= 7.4 (推荐 PHP 8.0+)
    *   启用 `pdo_mysql` 扩展 (用于数据库连接)
    *   启用 `simplexml` 扩展 (用于RSS解析)
    *   启用 `curl` 扩展 (用于API调用)
    *   启用 `json` 扩展
    *   启用 `mbstring` 扩展
*   MySQL >= 5.7 (为了JSON数据类型支持，如果使用旧版MySQL，`raw_data`字段应改为TEXT类型，并在代码中相应调整JSON处理)
*   Web服务器 (Nginx, Apache等)，配置网站根目录指向项目的 `public` 文件夹。
*   Composer (可选，如果未来引入PHP库依赖)

### 2. 下载项目

克隆或下载本项目到您的服务器。

```bash
git clone https://github.com/your-username/news-from-ai.git # 请替换为实际的仓库地址
cd news-from-ai
```

### 3. 创建数据库

在您的MySQL服务器中创建一个新的数据库，例如 `news_from_ai_db`，并确保使用 `utf8mb4` 字符集。

### 4. 导入数据库表结构

使用项目根目录下的 `database_setup.sql` 文件创建所需的 `news` 表。

```bash
mysql -u your_db_user -p your_db_name < database_setup.sql
```
(请替换 `your_db_user` 和 `your_db_name`)

### 5. 配置应用

直接编辑 `config/config.php` 文件，并根据您的环境填写以下信息：

*   **数据库连接**:
    *   `DB_HOST`
    *   `DB_USER`
    *   `DB_PASS`
    *   `DB_NAME`
*   **AI API 配置**:
    *   `NEWS_FETCH_AI_API_URL`, `NEWS_FETCH_AI_API_KEY`, `NEWS_FETCH_AI_MODEL`, `NEWS_FETCH_AI_PROMPT`
    *   `NEWS_COMMENT_AI_API_URL`, `NEWS_COMMENT_AI_API_KEY`, `NEWS_COMMENT_AI_MODEL`, `NEWS_COMMENT_AI_PROMPT`
    *   `NEWS_FORMAT_AI_API_URL`, `NEWS_FORMAT_AI_API_KEY`, `NEWS_FORMAT_AI_MODEL`, `NEWS_FORMAT_AI_PROMPT`
    *   (可选) `RSS_SUMMARY_AI_API_URL`, `RSS_SUMMARY_AI_API_KEY`, `RSS_SUMMARY_AI_MODEL`, `RSS_SUMMARY_AI_PROMPT`
*   **Google Custom Search API 配置**:
    *   `GOOGLE_SEARCH_API_KEY`
    *   `GOOGLE_SEARCH_CX` (您的Programmable Search Engine ID)
*   **用户新闻偏好**:
    *   `NEWS_KEYWORDS` (数组，定义您感兴趣的新闻主题)
*   **RSS 源配置**:
    *   `RSS_SOURCES` (数组，配置RSS源URL、获取数量、分类等)
*   **日志文件路径**:
    *   `LOG_FILE_PATH` (默认为项目 `logs/app.log`，确保 `logs` 目录可写)

**重要**:
*   确保 `logs` 目录存在并且PHP进程对其有写入权限。如果不存在，请手动创建 `mkdir logs`。
*   仔细检查并替换所有示例API密钥 (`sk-your_openai_api_key_here_...`, `your_google_api_key_here`, `your_google_search_engine_id_here`)为您自己的有效密钥。
*   将数据库用户名 (`your_db_user`) 和密码 (`your_db_password`) 替换为您的实际凭据。

### 6. 设置Web服务器

将您的Web服务器（如Nginx或Apache）的网站根目录指向项目中的 `public` 目录。

**Nginx 示例配置片段:**
```nginx
server {
    listen 80;
    server_name yourdomain.com; # 替换为您的域名
    root /path/to/your/news-from-ai/public; # 指向 public 目录

    index index.php index.html;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock; # 根据您的PHP-FPM版本和配置调整
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # 禁止访问项目根目录下的敏感文件
    location ~ /(config|core|cron|logs|database_setup\.sql|README\.md)$ {
        deny all;
        return 404; # 或者 return 403;
    }
    # 允许访问 public/assets
     location /assets/ {
        # try_files $uri =404; # Or allow direct access
        # No specific rule needed if public is root, as assets is inside public
    }

}
```
**Apache**: 确保 `AllowOverride All` 在您的Apache配置中为项目目录启用，以便 `.htaccess` 文件（如果使用）可以工作。通常，将DocumentRoot指向 `public` 目录即可。如果项目不在域名的根目录，可能需要调整 `RewriteBase`。

一个简单的 `.htaccess` 文件可以放在 `public` 目录下，用于处理URL重写 (如果Apache `mod_rewrite` 开启):
```apacheconfig
RewriteEngine On
RewriteBase / # 如果项目在子目录, 改为 /your-subdirectory/

RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^ index.php [QSA,L]
```

### 7. 设置定时任务 (Cron Job)

为了让应用能自动获取和处理新闻，您需要设置一个定时任务来执行 `cron/fetch_and_process_news.php` 脚本。

例如，使用宝塔面板：
1.  登录宝塔面板。
2.  进入 "计划任务" 或 "Cron" 部分。
3.  任务类型选择 "Shell脚本"。
4.  任务名称自定义，例如 "AI新闻聚合器更新"。
5.  执行周期根据您的需求设置（例如：每小时执行一次 `0 */1 * * *`，或每15分钟 `*/15 * * * *`）。
6.  脚本内容填写执行PHP脚本的命令，例如：
    ```bash
    /www/server/php/81/bin/php /www/wwwroot/yourdomain.com/news-from-ai/cron/fetch_and_process_news.php
    ```
    (请确保PHP CLI路径 `/www/server/php/81/bin/php` 和项目路径 `/www/wwwroot/yourdomain.com/news-from-ai/` 替换为您的实际路径。)
7.  保存任务。

您可以通过查看 `logs/app.log` 文件来确认定时任务是否按预期执行并查看其输出。

## 📄 使用说明

1.  完成上述安装和配置步骤。
2.  确保定时任务已设置并成功运行至少一次以填充初始新闻。
3.  通过浏览器访问您配置的域名或IP地址，即可看到聚合的新闻。
4.  使用页面右上角的主题切换按钮更改日间/夜间模式。
5.  如果新闻较多，底部会显示分页导航。

## 🐛 日志与调试

*   应用的主要日志输出到 `logs/app.log` 文件（路径可在 `config.php` 中配置）。
*   PHP错误日志（如果发生严重错误）通常位于Web服务器的错误日志文件或PHP-FPM的日志中。
*   在开发或调试时，可以暂时在 `config/config.php` 中设置 `error_reporting(E_ALL)` 和 `ini_set('display_errors', 1)` 以在浏览器中显示PHP错误。**生产环境请关闭 `display_errors`**。

## 🤝 贡献

欢迎提交 Issues 和 Pull Requests！

## 📜 开源许可

本项目采用 MIT License。 (您可以在项目根目录创建一个 `LICENSE` 文件并写入MIT许可文本)。

例如，创建一个 `LICENSE` 文件并粘贴以下内容：
```text
MIT License

Copyright (c) [Year] [Your Name or Organization]

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
```
请将 `[Year]` 和 `[Your Name or Organization]` 替换为实际信息。

---

**注意**: AI API调用和Google Search API调用可能会产生费用，请根据您的API提供商的定价策略合理配置和使用。确保您的API密钥安全，不要将其公开提交到版本控制系统（如果使用git，可以将 `config/config.php` 加入 `.gitignore`，然后提供一个 `config/config.php.example` 作为模板）。
