# news-from-ai (AI新闻聚合器)

一个PHP网页应用，使用AI定时收集、处理和展示时事新闻及RSS订阅内容。

## 功能特性

- **AI驱动的新闻收集**: 根据用户配置的关键词，利用AI自动搜索（通过Google Custom Search API）和筛选新闻。
- **多样化的新闻呈现**: AI自行设计新闻在网页中的呈现形式（时间线、多方证实、单个文章等），输出为Markdown+HTML。
- **AI评论与解读**: 每个新闻都配有专门AI生成的评论和解读。
- **RSS订阅聚合**:
    - 从数据库中读取用户配置的RSS源。
    - AI对获取的RSS文章进行摘要、评论和内容整理。
- **来源追踪**: 所有新闻均提供来源链接，可查看原文。
- **个性化配置 (`config/config.ini`)**:
    - 数据库连接信息。
    - AI配置：三个核心AI（新闻获取、新闻评论、新闻整理汇总）的API密钥、API地址、使用模型及详细提示词。
    - Google Custom Search API密钥和CX ID。
    - 新闻搜索关键词。
    - RSS文章获取数量及特定AI提示词。
    - 日志级别和路径。
- **动态主题**: 网页主题根据用户本地时间自动切换（白天白色，夜晚黑色），用户也可手动切换并保存偏好。
- **简洁美观的界面**: 注重用户体验，包含细腻的CSS动画效果。
- **详细日志**:
    - 应用运行日志 (`logs/app.log`)。
    - 定时任务执行日志 (数据库表 `cron_job_logs`)。
    - AI API调用日志 (数据库表 `ai_api_logs`，当前为基本结构，可扩展)。
- **定时任务**: 通过 `public/cron_runner.php` 脚本执行，支持细分的任务类型。

## 技术栈

- **后端**: PHP (原生，无框架依赖，PHP 8.0+推荐)
- **数据库**: MySQL (兼容MariaDB)
- **前端**: HTML5, CSS3, JavaScript (原生)
- **AI**: 通过OpenAI兼容API与大语言模型交互 (如GPT系列)
- **搜索**: Google Custom Search API
- **Markdown解析**: Parsedown (已内置)

## 目录结构

```
news-from-ai/
├── config/
│   └── config.ini          # 核心配置文件
├── database_schema.sql     # 数据库表结构创建脚本
├── logs/                   # 应用日志目录 (需确保Web服务器有写入权限)
│   └── app.log             # (示例日志文件名，以config.ini中配置为准)
├── public/                 # Web服务器的根目录
│   ├── css/
│   │   └── style.css
│   ├── js/
│   │   └── main.js
│   ├── images/             # 图片资源
│   │   └── placeholder.png # (需要用户自行放置一个占位图片)
│   ├── index.php           # 应用主入口
│   └── cron_runner.php     # 定时任务执行脚本
├── src/                    # PHP核心代码
│   ├── lib/
│   │   └── Parsedown.php   # Markdown解析库
│   ├── AIClient.php
│   ├── Config.php
│   ├── Database.php
│   ├── GoogleSearch.php
│   ├── Logger.php
│   ├── NewsGatheringService.php
│   ├── NewsProcessingService.php
│   ├── RSSReader.php
│   └── bootstrap.php       # 应用初始化引导脚本
└── README.md               # 本文件
```

## 安装与配置

### 1. 环境要求
- PHP 8.0 或更高版本 (使用了部分PHP 8特性如 `mixed` 类型提示, `Throwable`)
    - 启用 `pdo_mysql` 扩展
    - 启用 `curl` 扩展
    - 启用 `simplexml` 扩展 (通常默认启用)
    - 启用 `mbstring` 扩展 (通常默认启用)
- MySQL 5.7 或更高版本 (或MariaDB 10.2+)
- Web服务器 (Nginx, Apache等)
- Composer (可选, 如果未来引入依赖管理)

### 2. 下载项目
```bash
git clone https://github.com/your-username/news-from-ai.git
cd news-from-ai
```
(请将 `your-username` 替换为实际的仓库地址)

### 3. 创建数据库
- 使用MySQL客户端 (如phpMyAdmin, DBeaver, 或命令行) 创建一个数据库，例如 `news_from_ai_db`，字符集推荐 `utf8mb4`。
- 导入 `database_schema.sql` 文件来创建所需的表结构：
  ```bash
  mysql -u your_mysql_user -p your_database_name < database_schema.sql
  ```
  或者通过数据库管理工具导入该SQL文件。

### 4. 配置 `config/config.ini`
复制或重命名 `config/config.ini.example` (如果提供了示例文件，否则直接编辑 `config/config.ini`) 并修改以下关键部分：

- **[database]**:
    - `db_host`: 数据库主机地址 (通常是 `localhost` 或 `127.0.0.1`)
    - `db_name`: 你创建的数据库名称
    - `db_user`: 数据库用户名
    - `db_password`: 数据库密码
- **[logging]**:
    - `log_file`: 日志文件路径。确保Web服务器和CLI用户对此路径有写入权限。默认为项目根目录下的 `logs/app.log`。
    - `log_level`: 日志级别 (DEBUG, INFO, WARNING, ERROR, CRITICAL)
- **AI API密钥和地址**:
    - **[ai_general]**: 如果所有AI任务使用相同的API端点和密钥，可在此处配置。
        - `common_api_url`
        - `common_api_key`
    - **[ai_news_gathering]**, **[ai_news_commenting]**, **[ai_news_summarizing]**:
        - `api_url`: 各AI任务的API端点 (OpenAI兼容格式, e.g., `https://api.openai.com/v1/chat/completions`)。如果为空，则使用 `ai_general.common_api_url`。
        - `api_key`: 各AI任务的API密钥。如果为空，则使用 `ai_general.common_api_key`。
        - `model`: 使用的AI模型 (e.g., `gpt-3.5-turbo`, `gpt-4`)。
        - `prompt`: 各AI任务的提示词。**这是项目的核心，请仔细根据你的需求和AI模型特性进行调整。** 提示词中的 `{variable_name}` 会被程序动态替换。
- **Google Custom Search API**:
    - **[news_sources]**:
        - `google_search_api_key`: 你的Google Cloud API密钥 (需要启用Custom Search API)。
        - `google_search_cx_id`: 你的Google Programmable Search Engine ID (CX ID)。
        - `keywords`: AI新闻搜索的关键词列表，用英文逗号分隔 (e.g., `科技,财经,巴以冲突`)。
- **RSS配置**:
    - **[rss]**:
        - `enabled`: (此配置项当前未直接在代码中使用，RSS源的启用状态通过数据库 `rss_feeds` 表的 `is_enabled` 字段控制，但可以保留作为未来全局开关的参考)。
        - `rss_urls[]`: 此配置项用于**首次填充数据库**。`cron_runner.php` 会从数据库读取启用的RSS源。你可以手动在 `rss_feeds` 表中添加或管理RSS源。`database_schema.sql` 文件中有示例INSERT语句。
        - `articles_per_feed`: 每次从每个RSS源获取的文章数量。
        - `rss_ai_comment_prompt`, `rss_ai_summary_prompt`: RSS新闻专用的AI提示词 (如果想与普通新闻的提示词不同)。
- **占位符图片**:
    - **[developer]**:
        - `placeholder_image_url`: AI建议图片时使用的占位符图片路径。默认为 `public/images/placeholder.png`。请确保在 `public/images/` 目录下放置一个名为 `placeholder.png` 的图片文件。

### 5. 设置Web服务器
将Web服务器的文档根目录 (DocumentRoot) 指向 `news-from-ai/public` 目录。
例如，对于Nginx，配置可能类似：
```nginx
server {
    listen 80;
    server_name yourdomain.com; # 替换为你的域名或IP
    root /path/to/news-from-ai/public; # 替换为项目实际路径

    index index.php index.html;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.x-fpm.sock; # 确保PHP-FPM路径正确
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.ht {
        deny all;
    }
}
```
对于Apache，确保 `mod_rewrite` 已启用，并且 `public/.htaccess` 文件（如果需要）配置正确以将所有请求路由到 `index.php`。通常，如果 `AllowOverride All` 在主配置中为 `public` 目录启用，则不需要额外配置。

### 6. 设置定时任务 (Cron Job)
为了让新闻自动更新，你需要设置定时任务来执行 `public/cron_runner.php` 脚本。
使用宝塔面板或其他cron工具，添加以下类型的命令：

- **获取AI搜索的新闻** (例如，每2小时执行一次):
  ```bash
  /usr/bin/php /path/to/news-from-ai/public/cron_runner.php fetch_ai_news >> /path/to/news-from-ai/logs/cron_output.log 2>&1
  ```
- **获取RSS订阅的新闻** (例如，每1小时执行一次):
  ```bash
  /usr/bin/php /path/to/news-from-ai/public/cron_runner.php fetch_rss_news >> /path/to/news-from-ai/logs/cron_output.log 2>&1
  ```
- **执行所有任务 (包括可选的日志清理)** (例如，每日执行一次):
  ```bash
  /usr/bin/php /path/to/news-from-ai/public/cron_runner.php all >> /path/to/news-from-ai/logs/cron_output.log 2>&1
  ```
- **单独执行日志清理** (例如，每周执行一次):
  ```bash
  /usr/bin/php /path/to/news-from-ai/public/cron_runner.php cleanup_logs >> /path/to/news-from-ai/logs/cron_output.log 2>&1
  ```

**注意**:
- 将 `/usr/bin/php` 替换为你的PHP CLI可执行文件的实际路径。
- 将 `/path/to/news-from-ai/` 替换为项目的实际绝对路径。
- `>> /path/to/news-from-ai/logs/cron_output.log 2>&1` 是可选的，用于将cron脚本的输出记录到文件，方便排查问题。确保 `logs` 目录可写。

### 7. 权限
确保Web服务器用户和执行cron任务的用户对 `logs/` 目录有写入权限。

## 使用说明

1.  **访问首页**: 在浏览器中打开你配置的Web服务器地址 (e.g., `http://yourdomain.com`)。
2.  **查看新闻**: 首页会展示最新获取和处理的新闻。
3.  **主题切换**: 页面右上角（如果JS正确加载）应有主题切换按钮。主题偏好会保存在浏览器本地存储中。
4.  **后台更新**: 定时任务会根据你的设置在后台自动获取新内容。

## AI提示词调整 (重要!)

项目的核心在于 `config/config.ini` 中为三个AI角色（`ai_news_gathering`, `ai_news_commenting`, `ai_news_summarizing`）配置的提示词。默认提供的提示词是示例，你**必须**根据你选择的AI模型的能力、期望的输出风格和具体需求进行仔细调整和优化。

- **`ai_news_gathering.prompt`**:
    - 这个AI负责从Google搜索结果（作为上下文变量 `{preliminary_search_results}` 传入）中筛选、验证和提取结构化的新闻信息。
    - 它应该输出一个JSON数组，每个对象包含 `title`, `summary`, `url`, `published_at`, `source_name`。
    - **注意**：目前的 `NewsGatheringService.php` 实现中，变量 `{preliminary_search_results}` 会包含Google搜索的JSON结果。你需要确保你的提示词能够理解并处理这个变量。如果你的提示词是让AI自己通过工具搜索（而不是处理你提供的结果），你需要修改 `NewsGatheringService.php` 中调用此AI的逻辑，或者调整提示词。当前设计是：PHP代码执行Google搜索，然后将结果作为上下文交给此AI进行提炼。

- **`ai_news_commenting.prompt`**:
    - 接收新闻的 `title`, `summary`, `source_name`, `published_at` 作为变量。
    - 输出一段对新闻的评论和解读。

- **`ai_news_summarizing.prompt`**:
    - 接收新闻的原始信息 (`title`, `summary`, `url`, etc.) 和AI评论 (`ai_comment`) 作为变量。
    - 输出一段适合在网页上展示的HTML内容。它可以运用Markdown进行排版（程序会将其转换为HTML）。
    - 它可以建议图片位置，例如使用 `<img src="placeholder_image.jpg" alt="[AI生成的图片描述]">`，程序会尝试替换 `placeholder_image.jpg` 为配置文件中指定的占位图路径。

- **RSS相关的提示词 (`rss_ai_comment_prompt`, `rss_ai_summary_prompt`)**:
    - 类似地，为RSS文章的评论和摘要生成定制提示词。

**提示词调试技巧**:
- 从简单的提示词开始，逐步迭代。
- 使用AI提供商的Playground工具测试和优化提示词。
- 注意控制输出的格式（JSON, HTML片段等）和长度。
- 在 `config.ini` 中调整 `max_tokens` (如果AIClient支持或通过API参数) 和 `temperature` 来影响生成结果。

## 开发者信息

- **日志**: 详细的日志记录在 `logs/app.log` (或配置的路径) 和数据库的 `cron_job_logs`, `ai_api_logs` 表中，方便调试。将 `config.ini` 中的 `log_level` 设置为 `DEBUG` 可以获取最详细的日志。
- **错误处理**: 应用包含基本的错误和异常处理，会将严重错误记录到日志。
- **扩展性**:
    - 可以通过在 `NewsProcessingService` 中添加新方法并更新 `cron_runner.php` 来添加新的定时任务类型。
    - 可以通过实现新的搜索提供者类（类似 `GoogleSearch.php`）并在 `NewsGatheringService.php` 中集成，来替换或增加新闻搜索源。

## 待办事项 / 未来可实现功能

- [ ] 用户界面管理RSS源 (增删改查)。
- [ ] 用户认证和个性化新闻偏好设置。
- [ ] 更高级的新闻分类和标签系统。
- [ ] 前端分页或无限滚动加载新闻。
- [ ] 真实图片获取与展示（不仅仅是占位符）。
- [ ] 国际化支持。
- [ ] 使用Composer进行依赖管理。
- [ ] 更完善的单元测试和集成测试。

## 贡献

欢迎各种形式的贡献！请先开一个Issue讨论你想要做的更改。

## 许可证

本项目采用 [MIT许可证](LICENSE.txt) (如果添加了LICENSE.txt文件)。
(请自行添加一个 `LICENSE.txt` 文件，例如MIT许可证内容)
