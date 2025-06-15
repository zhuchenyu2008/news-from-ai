# news-from-ai





### AI 自动新闻聚合器 (AI-Powered News Aggregator)

这是一个 PHP 项目，旨在自动化新闻的收集、排版和展示流程。它会定时通过 AI 获取最新的时事新闻，并再次利用 AI 为每条新闻智能地设计呈现布局（例如：时间轴、多方观点、单篇文章摘要），最终在一个具备日夜间模式自动切换的优雅网页上展示出来。

✨ **项目特色**

  * **AI 驱动内容**：完全由 AI 负责获取和整理新闻内容。
  * **智能排版设计**：AI 会为每条新闻选择最佳呈现方式并生成 HTML，让每则新闻都以最合适的形式展现。
  * **高度可配置**：您可以通过 `config.php` 轻松设定：
      * 新闻主题（您关心的领域）
      * AI API 的密钥 (API Key) 和端点 (Endpoint)
      * 触发更新的频率（通过服务器的 Cron Job）
  * **自动化**：设定好 Cron Job 后，网站内容将会自动更新，无需人工干预。
  * **优雅的用户体验**：
      * 简洁美观的界面设计。
      * 流畅的 CSS 动画效果。
      * 根据服务器时间自动切换白天（亮色）和夜晚（暗色）主题。

🤖 **运作原理**

1.  **定时任务 (Cron Job)**：服务器上的 Cron Job 会定时执行 `fetch_news.php` 这个 PHP 脚本。
2.  **获取新闻标题**：`fetch_news.php` 首先读取 `config.php` 中的设定，向指定的 AI API 发送一个请求，获取关于用户指定主题的最新新闻标题列表。
3.  **生成新闻区块**：脚本会遍历获取的每一个标题，再次向 AI API 发送请求。这次的提示词 (Prompt) 要求 AI 扮演一个“新闻编辑暨网页设计师”，为该标题选择一个最合适的展示格式（时间轴、多方证实、文章摘要三选一），并生成一段独立、带有 Tailwind CSS 样式的 HTML 代码。
4.  **缓存内容**：所有生成的新闻 HTML 区块会被组合起来，并储存到 `news_cache.html` 文件中。这可以极大地提升网页加载速度，并减少不必要的 API 请求。
5.  **前端展示**：当用户访问 `index.php` 时，该页面会检查当前时间来决定使用白天或夜晚主题，然后直接加载 `news_cache.html` 中已生成好的新闻内容进行展示。

🚀 **安装与设定**

1.  **下载代码**：

      * 将所有项目文件下载或复制到您的 PHP 网站服务器目录中。

2.  **设定 `config.php`**：

      * 打开 `config.php` 文件，并填写以下内容：
        ```php
        <?php
        return [
            // 您的 AI API 密钥 (例如 OpenAI)
            'API_KEY' => 'sk-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',

            // AI API 的通用端点
            'API_ENDPOINT' => 'https://api.openai.com/v1/chat/completions',

            // 您感兴趣的新闻主题。AI 会围绕此主题寻找新闻。
            // 例如：'全球科技业最新动态', '人工智能领域的突破', '环境保护与气候变迁'
            'USER_NEWS_PROMPT' => '关于AI与半导体产业的全球新闻',

            // 建议的 Cron Job 设定（此为说明，实际设定在服务器上）
            'CRON_SCHEDULE_INFO' => '建议设定为每小时执行一次: "0 * * * *"'
        ];
        ```

3.  **设定服务器定时任务 (Cron Job)**：

      * 这是本项目自动化的关键。您需要在您的服务器（例如使用 cPanel, Plesk 或直接在 Linux shell）中设定一个 Cron Job。
      * 目标是定时执行 `fetch_news.php`。
      * 以下是一个范例指令，它会每小时的第 0 分钟执行一次脚本：
        ```bash
        0 * * * * cd /path/to/your/project && /usr/bin/php fetch_news.php > /dev/null 2>&1
        ```
      * **注意**: 请将 `/path/to/your/project` 替换为您项目在服务器上的实际绝对路径。`/usr/bin/php` 是 PHP 执行文件的常见路径，您的服务器可能会有所不同。`> /dev/null 2>&1` 是为了避免产生不必要的日志邮件。

4.  **权限设定**：

      * 请确保您的 PHP 脚本有权限写入 `news_cache.html` 这个文件。您可以赋予网站目录或该文件写入权限。
        ```bash
        touch news_cache.html
        chmod 664 news_cache.html
        ```
      * (具体权限设定可能因服务器环境而异)

5.  **浏览**：

      * 在浏览器中打开 `index.php` 的 URL，即可看到您的 AI 新闻站。第一次访问时可能没有内容，请手动执行一次 `fetch_news.php` 或等待 Cron Job 执行。

📄 **授权**

本项目采用 [MIT License](https://opensource.org/licenses/MIT) 授权。
