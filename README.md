# news-from-ai (AI新闻聚合器)

news-from-ai 是一个基于PHP和MySQL的AI新闻聚合器。它通过宝塔面板进行定时任务管理，旨在简化部署和配置，让您轻松搭建自己的AI新闻站。

✨ **功能特性**

*   **AI驱动内容生成**:
    *   **智能关键词**: AI根据用户设定的核心兴趣（如“具身智能的最新研究进展和开源机器人项目”）自动生成每日搜索关键词。
    *   **联网搜索与整合**: 利用生成的关键词，通过外部搜索API（需自行配置，如Google Custom Search API, Bing Search API等）抓取相关新闻素材。
    *   **AI新闻撰写**: AI将收集到的新闻素材进行分析、整合，并选择最合适的呈现形式（如时间线、多源报道、单篇文章深度解析），最终生成结构清晰的Markdown格式新闻。
*   **RSS订阅聚合**:
    *   自动抓取用户配置的多个RSS订阅源。
    *   AI对每篇RSS文章进行总结，提炼核心要点并撰写简短评论，同样以Markdown格式存储。
*   **灵活的AI配置**:
    *   支持为不同任务（关键词生成、新闻分析、RSS摘要）配置不同的AI模型和API服务商（任何兼容OpenAI API格式的服务均可）。
    *   可在 `config.php` 中轻松调整API密钥、URL和模型名称。
*   **数据持久化**: 所有生成的新闻和摘要都存储在MySQL数据库中，方便管理和查阅。
*   **前端展示**:
    *   简洁的响应式前端页面 (`public/index.php`)，通过API动态加载新闻内容。
    *   使用 `marked.js` 将Markdown内容实时渲染为HTML。
    *   自动根据客户端时间切换日间/夜间模式。
    *   新闻条目加载时带有入场动画。
*   **定时任务**: 核心新闻抓取逻辑 (`app/cron/fetch_news.php`) 设计为通过Cron（如宝塔面板的计划任务）定时执行。
*   **易于部署与配置**:
    *   提供 `config.php.example` 作为配置模板。
    *   使用Composer管理PHP依赖（如Guzzle）。
    *   提供数据库表结构SQL。

⚙️ **技术栈**

*   **后端**: PHP 8.1+
*   **数据库**: MySQL
*   **主要依赖**:
    *   `guzzlehttp/guzzle`: 用于HTTP API请求。
*   **前端**: HTML, CSS, JavaScript
    *   `marked.js`: 用于Markdown到HTML的转换。
*   **AI服务**: 兼容OpenAI API格式的任意大模型服务商。
*   **搜索服务**: 任意提供API的联网搜索工具（如Google, Bing, Serper.dev等）。

🚀 **部署指南**

1.  **环境要求**:
    *   Web服务器 (Nginx 或 Apache)
    *   PHP >= 8.1 (确保安装了 `pdo_mysql`, `json`, `xml`, `curl` 扩展)
    *   MySQL数据库
    *   Composer

2.  **下载与安装**:
    *   将项目文件下载或克隆到您的服务器。
    *   进入项目根目录，运行 `composer install` 安装PHP依赖。

3.  **数据库配置**:
    *   在MySQL中创建一个新的数据库。
    *   导入项目提供的 `news_items` 表结构SQL (见下文或 `docs/schema.sql` - 你需要自己创建这个文件并放入SQL)。
        ```sql
        CREATE TABLE `news_items` (
          `id` INT AUTO_INCREMENT PRIMARY KEY,
          `format` VARCHAR(50) NOT NULL COMMENT 'AI决定的呈现形式',
          `content_markdown` TEXT NOT NULL COMMENT 'AI生成的Markdown内容',
          `source_url` VARCHAR(2048) COMMENT '主要来源链接（用于RSS摘要）',
          `sources_json` JSON COMMENT '所有来源链接的JSON数组（用于AI创作的新闻）',
          `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          INDEX `idx_created_at` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ```

4.  **项目配置**:
    *   复制 `config.php.example` 为 `config.php`。
    *   编辑 `config.php`，填入您的实际配置：
        *   数据库连接信息 (`DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`)。
        *   `USER_NEWS_PROMPT`: 您核心关注的新闻主题。
        *   `RSS_FEEDS`: 您想要聚合的RSS订阅源URL数组。
        *   `SEARCH_API_CONFIG`: 您的联网搜索工具的API密钥和URL。
            *   **重要**: `SearchTool.php` 中的实现可能需要根据您选择的搜索API进行调整（请求参数、响应结构等）。
        *   `AI_CONFIGS`: 为 `query_generator`, `news_analyzer`, `rss_summarizer` 配置各自的AI服务API密钥、URL和模型。

5.  **Web服务器配置**:
    *   **重要**: 将Web服务器的文档根目录 (Document Root) 指向项目的 `public` 目录。
    *   确保服务器对项目文件有读取权限，对日志文件/目录（如 `app/cron/cron.log`, `app/cron/cron_error.log`）有写入权限。
    *   **URL重写 (推荐)**: 为了使API调用 (`/app/api/get_news.php`) 和其他潜在的非`public`目录资源能被正确访问，您可能需要配置URL重写规则，或者确保PHP可以直接执行 `app/` 目录下的脚本。
        *   **Nginx 示例 (如果Web根目录是项目根目录)**:
            ```nginx
            location /app/api/ {
                try_files $uri $uri/ /app/api/get_news.php?$query_string; # Adjust if get_news.php handles routing
            }
            location / {
                try_files $uri $uri/ /public/index.php?$query_string;
            }
            location ~ \.php$ {
                include snippets/fastcgi-php.conf;
                fastcgi_pass unix:/var/run/php/php8.1-fpm.sock; # Adjust to your PHP-FPM socket/address
                fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
                include fastcgi_params;
            }
            ```
        *   **Nginx 示例 (如果Web根目录是 `public` 目录)**:
            ```nginx
            root /path/to/your/project/news-from-ai/public;
            index index.php index.html index.htm;

            location / {
                try_files $uri $uri/ /index.php?$query_string;
            }

            # Route API calls to the actual script location outside public
            # This requires PHP to be able to execute scripts outside the doc root,
            # or you can move `app/api` into `public/api` (adjust paths in js).
            location /api/ { # Example: if you want domain.com/api/get_news.php
                 alias /path/to/your/project/news-from-ai/app/api/; # Map /api/ to app/api/
                 try_files $uri $uri/ /app/api/get_news.php?$query_string; # Path relative to project root

                 location ~ ^/api/(.+\.php)$ {
                    include snippets/fastcgi-php.conf;
                    fastcgi_pass unix:/var/run/php/php8.1-fpm.sock; # Adjust PHP-FPM
                    # SCRIPT_FILENAME needs to be the real path to the PHP file
                    fastcgi_param SCRIPT_FILENAME /path/to/your/project/news-from-ai/app/api/$1;
                    include fastcgi_params;
                }
            }


            location ~ \.php$ {
                include snippets/fastcgi-php.conf;
                fastcgi_pass unix:/var/run/php/php8.1-fpm.sock; # Adjust PHP-FPM
                fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
                include fastcgi_params;
            }
            ```
        *   **Apache (`.htaccess` in project root if DocumentRoot is project root)**:
            ```apache
            RewriteEngine On
            # Redirect to public directory
            RewriteCond %{REQUEST_URI} !^/public/
            RewriteRule ^(.*)$ /public/$1 [L]
            ```
            And another `.htaccess` in `public/` directory:
            ```apache
            RewriteEngine On
            RewriteCond %{REQUEST_FILENAME} !-f
            RewriteCond %{REQUEST_FILENAME} !-d
            RewriteRule ^ index.php [QSA,L]

            # For API calls like /app/api/get_news.php (if public is not doc root)
            # This rule might be complex if Apache can't see outside DocumentRoot easily.
            # Consider placing API endpoint within public or using a router.
            ```
            If `public` is the DocumentRoot, for API calls like `../app/api/get_news.php` from JS:
            You might need to adjust `public/js/main.js` to call a routed API endpoint like `/api/news` and handle that route in PHP or server config to point to `app/api/get_news.php`.
            A simpler approach without complex rewrites for `app/api` is to adjust the JS fetch URL to be something like `api.php` in the `public` folder, which then includes and runs `../app/api/get_news.php`.
            Example `public/api.php`:
            ```php
            <?php
            // Basic router or direct include
            // $request_path = $_GET['endpoint'] ?? '';
            // if ($request_path === 'get_news') {
            // require_once __DIR__ . '/../app/api/get_news.php';
            // }
            // For this project, if JS calls `../app/api/get_news.php`, it assumes PHP can execute it.
            // If not, and public is doc root, then `public/js/main.js` fetch path needs reconsideration.
            // The current JS `fetch('../app/api/get_news.php')` assumes that relative path from `public/index.html`
            // can reach and execute the PHP script. This usually means the webserver's config allows execution
            // from the `app` directory or the project root is the web root.
            ```

6.  **定时任务 (Cron Job)**:
    *   在您的服务器（如使用宝塔面板）设置一个Cron计划任务。
    *   **执行命令**: `php /path/to/your/project/news-from-ai/app/cron/fetch_news.php`
        *   确保使用正确的PHP CLI路径。
    *   **推荐频率**: `0 */2 * * *` (每2小时执行一次，在 `config.php` 中 `CRON_SCHEDULE` 也有此推荐值作为参考)。
    *   **日志**: 脚本会尝试在 `app/cron/` 目录下生成 `cron.log` 和 `cron_error.log`。确保PHP有权写入此目录。

💡 **使用提示与定制**

*   **AI Prompt工程**: `USER_NEWS_PROMPT` 和 `fetch_news.php` 中的系统提示词对生成内容的质量至关重要。根据您的需求调整它们。
*   **错误处理与日志**: 脚本包含基本的错误日志记录到 `app/cron/cron_error.log` 和常规日志到 `app/cron/cron.log`。定期检查这些日志。
*   **SearchTool定制**: `SearchTool.php` 的 `search()` 方法是一个通用框架。您**必须**根据您选择的搜索API提供商的文档来调整API请求的参数、头部和响应解析逻辑。
*   **RSS内容长度**: `fetch_news.php` 中对RSS文章内容传递给AI前做了截断处理，以适应模型上下文窗口。可根据所用模型调整 `maxContentLength`。
*   **避免重复处理**: RSS处理部分包含一个基础的URL检查来避免重复总结。对于更严格的重复数据删除，可以考虑检查文章的发布日期或内容哈希值。
*   **安全性**:
    *   确保 `config.php` 文件不可通过Web直接访问。
    *   对所有外部API密钥保密。
    *   如果AI生成的内容包含用户输入，请注意潜在的注入风险（尽管本项目主要由配置驱动）。
    *   `public/js/main.js` 中使用 `marked.parse()`，它默认会转义HTML。如果需要更强的XSS防护，可在 `marked.parse()` 之后使用如DOMPurify之类的库对HTML进行清理。

📄 **文件结构说明**

```plaintext
news-from-ai/
├── app/
│   ├── cron/                     # 定时任务脚本
│   │   └── fetch_news.php        # 核心新闻抓取与处理逻辑
│   ├── lib/                      # 核心PHP类库
│   │   ├── AIConnector.php       # AI模型API调用封装
│   │   ├── Database.php          # 数据库操作封装
│   │   ├── RssParser.php         # RSS解析器
│   │   └── SearchTool.php        # 联网搜索工具封装
│   └── api/                      # 前端API接口
│       └── get_news.php          # 获取新闻数据的API
│
├── public/                       # Web服务器的文档根目录 (Document Root)
│   ├── css/
│   │   └── style.css             # 前端样式表
│   ├── js/
│   │   └── main.js               # 前端交互逻辑
│   └── index.php                 # 前端主入口HTML页面
│
├── vendor/                       # Composer依赖
│
├── config.php                    # 项目配置文件 (需从.example复制并修改, 已被.gitignore)
├── config.php.example            # 项目配置示例文件
├── composer.json                 # Composer配置文件
└── README.md                     # 本文档
```

🤝 **贡献**

欢迎提交Pull Requests或Issues来改进此项目。

📜 **许可证**

该项目采用 [MIT许可证](LICENSE) (您需要自己添加一个LICENSE文件，例如MIT)。
