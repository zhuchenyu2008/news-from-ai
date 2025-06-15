# AI 驱动的新闻聚合器

## 概述

本项目是一个基于 PHP 的 AI 驱动新闻聚合器。它使用 AI（例如 OpenAI 的 GPT 模型）自动收集用户定义主题的新闻内容，然后再次利用 AI 将这些新闻格式化为 HTML 代码段，并在一个简约的网页上展示。网页会根据时间自动切换日间/夜间主题。新闻主题和抓取计划均可由用户配置。

## 功能特性

*   **自动新闻抓取：** 根据可配置的提示词/主题，使用 AI 收集新闻。
*   **AI 内容格式化：** 利用 AI 将原始新闻文本转换为可供展示的 HTML 代码段。
*   **可配置主题：** 用户可以定义他们感兴趣的多个新闻主题。
*   **可配置计划：** 可以使用 cron 任务自动化新闻抓取和处理流程。
*   **动态主题：** 前端页面根据时间自动在日间和夜间主题间切换。
*   **简约 Web 界面：** 以整洁、时间有序的方式展示处理后的新闻条目。
*   **模块化结构：** 代码被组织到配置、源码、脚本和面向公众的文件中。

## 目录结构

*   `config/`: 包含配置文件。
    *   `config.php`: 主要配置文件，用于 API 密钥、API 端点、计划任务和用户提示词。
*   `src/`: 包含核心 PHP 类文件。
    *   `AIHelper.php`: 负责与 AI API 交互的类。
    *   `NewsProcessor.php`: 用于获取原始新闻并通过 AI 将其转换为 HTML 的类。
    *   `load_config.php`: (实用脚本，但 `config.php` 通常被直接使用)。
*   `public/`: Web 服务器的文档根目录，包含面向用户的文件。
    *   `index.php`: 展示新闻的主页面（中文界面）。
    *   `style.css`: 用于前端页面样式的 CSS 文件。
*   `scripts/`: 包含用于自动化的命令行脚本（输出信息已中文化）。
    *   `fetch_news.php`: 从 AI 获取原始新闻并存储。
    *   `run_processor.php`: 使用 AI 处理原始新闻以生成 HTML 版本。
*   `data/`: 存储动态应用程序数据。运行 PHP 脚本的用户需要对此目录有写入权限。
    *   `news_raw/`: 存储由 `fetch_news.php` 获取的原始文本新闻条目。
    *   `news_html/`: 存储由 `run_processor.php` 生成的处理后的 HTML 新闻代码段。
*   `logs/`: (推荐) 用于存储 cron 任务日志的目录。您需要手动创建此目录。

## 安装说明

### 先决条件

*   **PHP:** 版本 7.4 或更高。
*   **PHP cURL 扩展:** 必须启用，以便 `AIHelper.php` 与 API 通信。(在 Debian/Ubuntu 上可通过 `sudo apt-get install php-curl` 安装)。
*   **Web 服务器:** Apache, Nginx 或类似的，配置为可运行 PHP 文件。
*   **Cron 后台程序:** 用于计划自动化任务。

### 配置

1.  **编辑 `config/config.php`:**
    打开 `config/config.php` (该文件及其注释已中文化) 并修改以下值：
    *   `'api_key'`: **必需。** 您的 AI 服务 API 密钥（例如 OpenAI）。您可以从 [https://platform.openai.com/account/api-keys](https://platform.openai.com/account/api-keys) 获取 OpenAI API 密钥。
    *   `'api_endpoint'`: AI API 的端点。默认为 OpenAI 的聊天补全 API 端点。如果使用兼容的替代方案，可以更改此设置。
    *   `'news_schedule'`: 这主要是一个供人工阅读的 cron 调度建议。实际的计划在您的 cron 任务计划表 (crontab) 中定义（见下文）。例如：
        *   `'0 * * * *'` (配置文件中的注释)：建议每小时运行一次。
        *   `60` (整数，如果由自定义调度程序使用)：建议每 60 分钟运行一次。
    *   `'user_prompts'`: 字符串数组。每个字符串都是一个供 AI 抓取新闻的主题或提示词。
        *   例如：`["可再生能源领域的最新进展", "全球股市趋势"]`
    *   `'ai_model'`: 用于AI交互的模型ID (例如: 'gpt-3.5-turbo', 'gpt-4')。确保您选择的模型与您的API密钥兼容并且适合任务需求。此设置由 `AIHelper` 使用。
    *   `'html_generation_prompt_template'`: 一个详细的系统提示词模板，指导AI如何将原始新闻文本转换为HTML。此模板包含占位符 `[preferred_style_placeholder]` 和 `[raw_news_text_here]`，它们将由系统动态替换。用户可以根据高级需求调整此模板，但需谨慎。(此键替换了旧的 `system_prompt_html`)。
    *   `'preferred_html_style'`: 用户首选的新闻HTML输出样式。AI将尝试遵循此设置。
        *   `'auto'`：(默认) AI自动根据新闻内容判断最合适的输出样式。
        *   `'timeline'`：时间线样式，适用于按时间顺序排列的事件。
        *   `'detailed_article'`：详细单篇文章样式，适用于深入报道。
        *   `'multi_faceted_report'`：多方面报告样式，适用于综合多个来源或观点。
        *   注意：实际产生的HTML样式取决于AI对 `html_generation_prompt_template` 中指令的理解程度以及具体的新闻内容。

### 自动化任务 (Cron Jobs)

您需要设置 cron 任务来自动化新闻抓取和处理。

1.  **创建日志目录 (推荐):**
    ```bash
    mkdir logs
    ```

2.  **编辑您的 crontab:**
    打开您的 crontab 编辑器：
    ```bash
    crontab -e
    ```
    添加以下行，请根据您项目的实际位置调整路径：

    ```cron
    # 每小时的第0分钟抓取新的新闻文章
    0 * * * * cd /path/to/your/project/ && php scripts/fetch_news.php >> /path/to/your/project/logs/fetch_news.log 2>&1

    # 抓取后不久处理原始新闻为 HTML，例如每小时的第5分钟
    5 * * * * cd /path/to/your/project/ && php scripts/run_processor.php >> /path/to/your/project/logs/run_processor.log 2>&1
    ```
    *   将 `/path/to/your/project/` 替换为您项目根目录的绝对路径。
    *   第一个任务每小时运行一次 `fetch_news.php`。
    *   第二个任务在每小时的第5分钟运行 `run_processor.php`，给 `fetch_news.php` 留出完成时间。
    *   这些脚本的输出和错误将附加到 `logs/` 目录中的日志文件。

#### 宝塔面板用户 (BT Panel Users)

宝塔面板用户可以通过其图形界面轻松设置定时任务。以下是如何为 `fetch_news.php` 和 `run_processor.php` 脚本设置定时任务的步骤：

1.  **登录宝塔面板：** 打开您的宝塔面板。
2.  **进入计划任务：** 在左侧菜单中，点击“计划任务”。
3.  **添加计划任务：**
    *   **任务类型：** 选择“Shell脚本”。
    *   **任务名称：** 输入一个描述性的名称，例如：“获取AI新闻”或“处理AI新闻”。
    *   **执行周期：** 根据您的需求设置任务执行的频率（例如，每小时执行一次，可以设置为“每1小时”的“0分钟”）。
    *   **脚本内容：** 这是关键步骤。您需要输入完整的命令。
        *   **对于 `fetch_news.php`：**
            ```bash
            cd /www/wwwroot/your_project_directory/scripts && /usr/bin/php fetch_news.php >> /www/wwwroot/your_project_directory/logs/fetch_news.log 2>&1
            ```
        *   **对于 `run_processor.php`：**
            ```bash
            cd /www/wwwroot/your_project_directory/scripts && /usr/bin/php run_processor.php >> /www/wwwroot/your_project_directory/logs/run_processor.log 2>&1
            ```
        *   **重要提示：**
            *   请将 `/www/wwwroot/your_project_directory/` 替换为您项目的实际绝对路径。在宝塔面板中，网站通常位于 `/www/wwwroot/` 目录下。
            *   `/usr/bin/php` 是 PHP CLI 的常见路径。如果不确定，您可以在宝塔面板的终端中使用 `which php` 命令查找确切路径。
            *   确保 `logs` 目录存在并且可写，以便记录脚本输出和错误。如果 `logs` 目录不存在，请在您的项目根目录下创建它。

4.  **确认并保存：** 点击“添加任务”按钮。

对 `fetch_news.php` 和 `run_processor.php` 重复这些步骤。建议将 `run_processor.php` 的执行时间设置在 `fetch_news.php` 完成之后（例如，晚几分钟执行），以确保有新的原始新闻可供处理。

通过以上步骤，您的定时任务就应该可以在宝塔面板中自动运行了。

### Web 服务器设置

1.  **文档根目录 (Document Root):** 配置您的 Web 服务器 (Apache, Nginx 等) 使用 `public/` 目录作为您站点的文档根目录。
2.  **PHP 处理:** 确保您的 Web 服务器配置为可以处理 PHP 文件 (例如，通过 `libapache2-mod-php` 或 `php-fpm`)。

    *Apache VirtualHost 配置示例:*
    ```apache
    <VirtualHost *:80>
        ServerName yourdomain.com
        DocumentRoot /path/to/your/project/public

        <Directory /path/to/your/project/public>
            AllowOverride All
            Require all granted
            DirectoryIndex index.php
        </Directory>

        ErrorLog ${APACHE_LOG_DIR}/error.log
        CustomLog ${APACHE_LOG_DIR}/access.log combined
    </VirtualHost>
    ```

    *Nginx Server Block 配置示例:*
    ```nginx
    server {
        listen 80;
        server_name yourdomain.com;
        root /path/to/your/project/public;

        index index.php;

        location / {
            try_files $uri $uri/ /index.php?$query_string;
        }

        location ~ \.php$ {
            include snippets/fastcgi-php.conf;
            fastcgi_pass unix:/var/run/php/php8.x-fpm.sock; # 根据您的 PHP 版本调整
        }
    }
    ```

## 工作原理

应用程序按以下顺序运行：

1.  **抓取新闻 (自动化):**
    *   cron 任务触发 `scripts/fetch_news.php` (脚本输出已中文化)。
    *   此脚本从 `config/config.php` 读取 `user_prompts` (用户定义的提示词)。
    *   对于每个提示词，它使用 `AIHelper`向配置的 AI API 发送请求。
    *   AI 根据提示词返回原始文本内容。
    *   原始文本内容作为唯一的 `.txt` 文件保存在 `data/news_raw/` 目录中。

2.  **处理新闻 (自动化):**
    *   抓取后不久，另一个 cron 任务触发 `scripts/run_processor.php` (脚本输出已中文化)。
    *   此脚本实例化 `NewsProcessor`。
    *   `NewsProcessor` 扫描 `data/news_raw/` 目录中的新 `.txt` 文件。
    *   对于每个原始新闻文件，它会根据 `config.php` 中定义的 `html_generation_prompt_template` 和 `preferred_html_style`，指示 AI 将原始新闻转换为特定风格的 HTML 代码段。
    *   它使用 `AIHelper` 将此格式化请求发送给 AI。
    *   AI 返回一个 HTML 字符串。
    *   此 HTML 代码段作为 `.html` 文件保存在 `data/news_html/` 目录中。然后删除原始的 `.txt` 文件。

3.  **展示新闻 (用户访问):**
    *   当用户在浏览器中访问 `index.php` (通过 Web 服务器) 时：
        *   脚本确定当前时间，以便在 `<body>` 标签上设置 `theme-light` (日间主题) 或 `theme-dark` (夜间主题) 类。 (页面本身已中文化)
        *   它扫描 `data/news_html/` 目录以查找处理后的 `.html` 文件。
        *   它读取每个 HTML 文件的内容，并将其直接嵌入到网页中。
        *   `style.css` 提供页面的样式，包括主题和新闻条目的外观。

## 自定义

*   **AI 内容格式化行为：**
    *   核心的 HTML 生成逻辑由 `config/config.php` 文件中的 `html_generation_prompt_template` 键控制。这是一个详细的提示模板，指导 AI 如何根据原始文本和期望的风格（通过 `preferred_html_style` 设置）来创建 HTML。高级用户可以通过修改此模板来深度定制 AI 生成 HTML 的方式，例如调整不同风格的 HTML 结构、CSS 类名或对特定类型新闻内容的强调方式。
    *   用户可以尝试定义全新的HTML样式。这需要在 `html_generation_prompt_template` 中详细描述新样式的适用场景、期望的HTML结构，并提供清晰的示例。这需要较强的提示工程能力，并且可能需要多次迭代才能达到理想效果。
*   **首选HTML样式：** 通过修改 `config/config.php` 中的 `preferred_html_style` 值，用户可以指定默认的 HTML 输出风格（例如 `'timeline'`, `'detailed_article'` 等）。
*   **CSS 样式：** 新闻条目和整个页面的外观可以通过编辑 `public/style.css` 文件进行完全自定义。
*   **新闻主题的用户提示词：** 通过编辑 `config/config.php` 中的 `user_prompts` 数组，可以轻松更改用于新闻抓取的核心主题。

```
