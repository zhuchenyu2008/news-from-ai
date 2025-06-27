### **第一部分：项目概述**

  * **项目名称**: `news-from-ai` (AI新闻聚合器)
  * **项目简介**: `news-from-ai`是一个基于PHP和MySQL的AI新闻聚合器，通过宝塔面板进行定时任务管理。它能根据用户设定的主题，全自动地从互联网抓取、分析、并以多种创新形式重塑新闻内容，同时集成RSS订阅源的AI摘要功能，致力于提供一个简洁、美观、个性化且信息深度丰富的新闻阅读体验。
  * **功能特性清单**:
      * **AI核心驱动**: 自动收集、分析、整合新闻。
      * **动态呈现**: AI根据新闻内容决定最佳展示形式（时间线、多方报告等）。
      * **极简依赖**: 后端仅需Composer安装Guzzle，前端库通过CDN加载，无需手动安装。
      * **宝塔面板优化**: 提供针对宝塔面板的详细定时任务设置指南。
      * **高度可配置**: 通过单个配置文件即可定义新闻主题、AI模型、API密钥和定时任务。
      * **RSS集成**: 自动抓取RSS源，并由AI进行摘要总结。
      * **美观的UI**: 简洁优雅的设计，支持日间/夜间模式自动切换，并有纯CSS实现的细腻动画效果。
      * **来源可溯**: 所有新闻内容均可链接至原文。
      * **通用API格式**: 支持所有兼容OpenAI格式的AI API。

### **第二部分：技术选型与架构**

  * **技术栈列表**:

      * **后端**: PHP 8.1+
      * **HTTP客户端**: Guzzle (通过Composer管理)
      * **数据库**: MySQL 5.7+
      * **前端**:
          * HTML5
          * CSS3 (含Flexbox/Grid布局, Variables, Transitions, Keyframes)
          * 原生JavaScript (ES6+)
      * **Markdown渲染**: marked.js (通过CDN加载: `https://cdn.jsdelivr.net/npm/marked/marked.min.js`)
      * **服务器环境**: Nginx/Apache + PHP + MySQL (典型的宝塔面板环境)
      * **定时任务**: 宝塔面板 (BT Panel) 的 “计划任务”

  * **系统架构说明**:
    本项目采用经典的前后端分离架构，各部分职责分明：

    1.  **宝塔面板 (调度层)**: 作为任务调度器，根据用户设定的时间频率，通过Shell命令触发后端的PHP脚本。
    2.  **后端PHP (逻辑层)**:
          * **定时脚本**: 被宝塔面板调用，执行所有核心的数据抓取和处理工作，包括调用AI和搜索工具，解析RSS，并将最终结果存入数据库。这是整个系统的大脑。
          * **API接口**: 提供一个简单的HTTP端点，供前端请求已处理好的新闻数据。
    3.  **MySQL数据库 (持久层)**: 负责永久存储所有由AI生成的新闻内容、格式、来源和时间戳等信息。
    4.  **前端 (表现层)**: 用户通过浏览器访问。它不处理任何业务逻辑，仅负责从后端API获取JSON数据，并将其动态渲染成美观、可交互的网页。主题切换和动画效果也在此层完成。

    **数据流**: 宝塔面板触发 -\> PHP定时脚本 -\> (调用外部搜索API & AI API) -\> 数据存入MySQL -\> 用户访问网页 -\> 前端JS请求PHP API接口 -\> PHP从MySQL读取数据返回 -\> 前端JS渲染页面。

### **第三部分：文件与目录结构**

```
news-from-ai/
├── app/                      # 核心后端逻辑
│   ├── cron/
│   │   └── fetch_news.php    # 定时任务执行的脚本
│   ├── lib/
│   │   ├── AIConnector.php   # 封装AI API调用的类
│   │   ├── Database.php      # 数据库连接和操作类
│   │   ├── RssParser.php     # RSS解析类
│   │   └── SearchTool.php    # 封装联网搜索API的类
│   └── api/
│       └── get_news.php      # 前端获取新闻数据的API端点
│
├── public/                   # Web服务器的根目录，前端文件
│   ├── css/
│   │   └── style.css         # 主要样式文件
│   ├── js/
│   │   └── main.js           # 主要的JavaScript逻辑
│   └── index.php             # 前端主入口页面
│
├── vendor/                   # Composer安装的依赖 (Guzzle)
│
├── config.php                # 配置文件 (重要！需设为私有)
├── config.php.example        # 配置文件的模板
├── composer.json             # Composer依赖定义
└── README.md                 # GitHub项目说明
```

### **第四部分：核心模块功能详解**

  * **4.1 配置文件 (`config.php`)**:
    这是系统的总控制中心。它是一个纯PHP文件，通过`define`定义一系列全局常量。其内容应包括：

      * **数据库配置**: `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`。
      * **用户自定义配置**:
          * `USER_NEWS_PROMPT`: 字符串，用户希望AI关注的新闻核心主题。例如“关于具身智能的最新研究进展和开源机器人项目”。
          * `CRON_SCHEDULE`: 字符串，Cron表达式，用于`README`中提示用户如何设置，例如 `'0 */2 * * *'` 代表每两小时。
      * **RSS订阅源**: `RSS_FEEDS`，一个包含多个RSS URL字符串的PHP数组。
      * **联网搜索工具配置**: `SEARCH_API_CONFIG`，一个包含API密钥和API URL的关联数组。
      * **AI服务配置**: `AI_CONFIGS`，一个多维关联数组。键名代表任务（如`news_analyzer`, `rss_summarizer`），值是包含该任务所需API密钥、API URL和模型名称的又一个关联数组。这种设计实现了对不同任务使用不同AI服务的高度灵活性。

  * **4.2 后端定时脚本 (`app/cron/fetch_news.php`)**:
    此脚本是整个自动化流程的核心，其执行逻辑如下：

    1.  **初始化**: 加载`config.php`和`vendor/autoload.php`，并实例化所有`lib`目录下的辅助类。
    2.  **生成搜索查询**: 调用`query_generator` AI。给它的系统提示词大意为：“你是一个新闻研究员。根据用户的核心兴趣：‘{USER\_NEWS\_PROMPT}’，生成5个今天最值得搜索的、具体的、时效性强的Google搜索关键词。以JSON数组格式返回。”
    3.  **执行联网搜索**: 遍历AI返回的关键词，使用`SearchTool`类调用外部搜索API，获取每个关键词对应的多条新闻URL、标题和摘要。
    4.  **AI分析与创作**: 将一批相关的新闻素材（URL和摘要）整合后，喂给`news_analyzer` AI。它的系统提示词是关键，大意为：“你是一位资深新闻编辑。请整合以下新闻素材，并决定最合适的呈现形式（从'timeline', 'multi\_source\_report', 'single\_article\_deep\_dive'中选择一个）。然后，使用Markdown撰写一篇结构清晰的报道，并在引用信息处用 `[来源](URL)` 格式标注原文链接。最后，将你的输出封装在一个JSON对象中，包含`format`和`content`两个键。”
    5.  **处理RSS订阅**: 使用`RssParser`类遍历`RSS_FEEDS`，获取新文章。对每篇新文章，调用`rss_summarizer` AI进行总结。其提示词大意为：“请将以下文章内容总结为三点核心摘要，并写一段总结性评论。使用Markdown格式。”
    6.  **数据持久化**: 将上述两种方式获得的、格式化的新闻内容（包括`format`类型和Markdown正文）以及所有相关的源链接，存入MySQL的`news_items`表中。

  * **4.3 后端数据接口 (`app/api/get_news.php`)**:
    此接口非常简单，它不接受任何参数。其功能是：连接数据库，查询`news_items`表，按时间倒序获取最新的若干条新闻，然后将结果以JSON数组的形式输出给前端。

  * **4.4 前端实现 (`public/` 目录)**:

      * **`index.php`**: 包含基本的HTML5文档结构，如`<header>`, `<main id="news-feed">`和`<footer>`。在`<body>`的末尾，通过`<script>`标签引入CDN上的`marked.js`和本地的`main.js`。
      * **`js/main.js`**: 页面加载后执行。主要逻辑包括：
        1.  检查当前客户端的小时数，为`<body>`标签添加`light-theme`或`dark-theme`类。
        2.  使用`fetch`函数异步请求`/app/api/get_news.php`接口。
        3.  请求成功后，遍历返回的JSON数组。对每一条新闻数据，动态创建一个`<article>`元素。
        4.  使用`marked.parse()`函数将新闻的Markdown内容转换为HTML。
        5.  将转换后的HTML和元数据（如发布时间）填充到`<article>`元素中，并将其附加到`<main id="news-feed">`容器里。
        6.  通过为新创建的`<article>`元素添加一个CSS类（如`animate-in`）来触发CSS动画。
      * **`css/style.css`**:
        1.  使用CSS变量定义日间和夜间两套主题的颜色（背景、文字、卡片背景等）。
        2.  根据`body`上的`.light-theme`或`.dark-theme`类来应用不同的颜色变量。
        3.  定义新闻卡片`.news-item`的通用样式。
        4.  使用`@keyframes`定义一个从下到上、从透明到不透明的入场动画。
        5.  定义`.animate-in`类，当这个类被添加到元素上时，应用上述动画。

  * **4.5 数据库设计**:
    在MySQL中创建一个名为`news_items`的表，其结构应包含以下字段：

      * `id`: `INT`, `AUTO_INCREMENT`, `PRIMARY KEY` - 唯一标识符。
      * `format`: `VARCHAR(50)`, `NOT NULL` - AI决定的呈现形式，如'timeline'。
      * `content_markdown`: `TEXT`, `NOT NULL` - AI生成的Markdown格式的新闻正文。
      * `source_url`: `VARCHAR(2048)` - 主要来源链接（主要用于RSS摘要）。
      * `sources_json`: `JSON` - 存储所有引用来源的JSON数组，每个对象包含title和url。
      * `created_at`: `TIMESTAMP`, `DEFAULT CURRENT_TIMESTAMP` - 条目创建时间，用于排序。

### **第五部分：部署指南与README文档**

以下是可直接复制使用的`README.md`文件全文。

-----

# news-from-ai (AI新闻聚合器)

`news-from-ai` 是一个基于PHP和MySQL的AI新闻聚合器。它通过宝塔面板进行定时任务管理，旨在简化部署和配置，让您轻松搭建自己的AI新闻站。

## ✨ 功能特性

  - **AI核心驱动**: 自动收集、分析、整合新闻。
  - **动态呈现**: AI根据新闻内容决定最佳展示形式（时间线、多方报告等）。
  - **极简依赖**: 后端仅需Composer安装Guzzle，前端库通过CDN加载，无需手动安装。
  - **宝塔面板优化**: 提供针对宝塔面板的详细定时任务设置指南。
  - **美观的UI**: 简洁优雅的设计，支持日间/夜间模式自动切换，并有纯CSS实现的细腻动画效果。
  - **来源可溯**: 所有新闻内容均可链接至原文。

## 🛠️ 技术栈

  - **后端**: PHP 8.1+
  - **HTTP客户端**: Guzzle
  - **前端**: Vanilla JavaScript, marked.js (via CDN), 纯CSS动画
  - **数据库**: MySQL
  - **定时任务**: 宝塔面板 (BT Panel)

## 🚀 在宝塔面板上部署

1.  **准备工作**

      - 登录您的宝塔面板。
      - 确保已安装 **PHP 8.1或更高版本**、**Nginx/Apache** 和 **MySQL**。

2.  **上传代码**

      - 点击左侧菜单的 “文件”。
      - 进入您的网站根目录（例如 `/www/wwwroot/yourdomain.com`）。
      - 将本项目的所有文件上传到这里。

3.  **创建数据库**

      - 点击左侧菜单的 “数据库”。
      - 点击 “添加数据库”，创建一个新的MySQL数据库，并记下数据库名、用户名和密码。
      - 进入创建好的数据库管理界面，执行以下SQL语句创建`news_items`表：
        ```sql
        CREATE TABLE `news_items` (
          `id` INT AUTO_INCREMENT PRIMARY KEY,
          `format` VARCHAR(50) NOT NULL COMMENT 'AI决定的呈现形式',
          `content_markdown` TEXT NOT NULL COMMENT 'AI生成的Markdown内容',
          `source_url` VARCHAR(2048) COMMENT '主要来源链接（用于RSS摘要）',
          `sources_json` JSON COMMENT '所有来源链接的JSON数组',
          `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          INDEX `idx_created_at` (`created_at`)
        );
        ```

4.  **安装Guzzle**

      - 点击左侧菜单的 “终端”。
      - 输入以下命令进入您的网站目录：
        ```bash
        cd /www/wwwroot/yourdomain.com
        ```
      - 执行Composer安装命令：
        ```bash
        composer install
        ```
        这会自动下载`guzzlehttp/guzzle`并生成`vendor`目录。

5.  **修改配置**

      - 在宝塔文件管理器中，将`config.php.example`复制并重命名为`config.php`。
      - 编辑 `config.php`，填入您在第3步中创建的**数据库信息**。
      - 填入您的**AI API密钥**、**搜索工具API密钥**以及您想关注的**新闻主题**。

6.  **设置网站**

      - 点击左侧菜单的 “网站”。
      - 找到您的网站，点击 “设置”。
      - 在 “网站目录” 设置中，将运行目录设置为 `/public`，然后保存。这可以提高网站安全性。

7.  **设置定时任务 (核心步骤)**

      - 点击左侧菜单的 “计划任务”。
      - **任务类型**: 选择 **“Shell脚本”**。
      - **任务名称**: 自定义，例如 “抓取AI新闻”。
      - **执行周期**: 根据您的需求设置，例如 “每2小时”，就在小时框里填入 `*/2`。
      - **脚本内容**: 填入以下命令，**注意替换路径**：
        ```bash
        /www/server/php/81/bin/php /www/wwwroot/yourdomain.com/app/cron/fetch_news.php
        ```
        > **重要**: `/www/server/php/81/bin/php` 是宝塔中PHP 8.1的默认路径，`/www/wwwroot/yourdomain.com` 是您的网站路径。请根据您的实际情况进行修改！
      - 点击 “添加任务”。

8.  **完成！**

      - 您可以手动执行一次计划任务进行测试。执行成功后，刷新您的网站，就应该能看到由AI生成的第一批新闻了。

## 📄 许可证

本项目采用 [MIT License](https://www.google.com/search?q=LICENSE)。
