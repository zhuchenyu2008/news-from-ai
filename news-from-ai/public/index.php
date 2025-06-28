<?php
// news-from-ai/public/index.php

// 引入启动引导文件
$app = require_once __DIR__ . '/../src/bootstrap.php';

/** @var NewsFromAI\Config $config */
$config = $app['config']; // 类型提示，方便IDE

/** @var NewsFromAI\NewsProcessingService $newsProcessingService */
$newsProcessingService = $app['newsProcessingService'];

// 获取配置
$siteTitle = $config->get('app.site_title', 'AI新闻聚合器');
$itemsPerPage = (int)$config->get('app.items_per_page', 10);
$defaultTheme = $config->get('theme.default_theme', 'auto'); // 'auto', 'light', 'dark'

// 获取新闻数据 (简单获取最新10条)
$newsItems = [];
if ($newsProcessingService && $app['db']::getConnection()) { // 确保数据库连接成功
    try {
        $newsItems = $newsProcessingService->getHomepageNews($itemsPerPage, 0);
        NewsFromAI\Logger::info("Index: Fetched " . count($newsItems) . " news items for homepage.");
    } catch (Throwable $e) {
        NewsFromAI\Logger::error("Index: Error fetching news for homepage.", ['error' => $e->getMessage()]);
        // 可以设置一个错误消息给用户
        $pageError = "无法加载新闻内容，请稍后再试。";
    }
} else {
    NewsFromAI\Logger::warning("Index: NewsProcessingService or DB connection not available. Cannot fetch news.");
    if (!$app['db']::getConnection()) {
        $pageError = "数据库连接失败，无法加载新闻。请检查配置和日志。";
    } else {
        $pageError = "新闻服务初始化失败，无法加载新闻。";
    }
}


// 主题确定逻辑 (优先JS控制，PHP作为后备或初始设置)
$theme_class = '';
if ($defaultTheme === 'light' || $defaultTheme === 'dark') {
    $theme_class = 'theme-' . $defaultTheme;
} else { // auto or other
    $hour = date('G'); // 0-23
    $theme_class = ($hour >= 7 && $hour < 19) ? 'theme-light' : 'theme-dark';
}

?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($siteTitle); ?></title>
    <link rel="stylesheet" href="css/style.css?v=<?php echo filemtime('css/style.css'); // Cache busting ?>">
    <meta name="description" content="由AI聚合和解读的最新时事新闻。">
    <!-- 未来可以添加 PWA manifest, favicons 等 -->
</head>
<body class="<?php echo htmlspecialchars($theme_class); ?>">
    <header>
        <h1><?php echo htmlspecialchars($siteTitle); ?></h1>
        <p>由AI为您带来最新资讯</p>
        <!-- 可以添加一个主题切换按钮 -->
        <button id="theme-toggle-button" style="display:none; position: absolute; top: 10px; right:10px; padding: 8px;">切换主题</button>
    </header>

    <main id="news-container">
        <?php if (!empty($pageError)): ?>
            <div class="error-message" style="color: red; text-align: center; padding: 20px; background-color: #ffe0e0; border: 1px solid red; border-radius: 5px;">
                <?php echo htmlspecialchars($pageError); ?>
            </div>
        <?php endif; ?>

        <?php if (empty($newsItems) && empty($pageError)): ?>
            <p style="text-align:center; padding: 20px;">暂无新闻。请稍后查看，或检查定时任务是否正确运行。</p>
        <?php endif; ?>

        <?php foreach ($newsItems as $item): ?>
            <article class="news-item">
                <h2><?php echo htmlspecialchars($item['title'] ?? '无标题'); ?></h2>

                <div class="news-content">
                    <?php
                        // content_html 是AI生成的，理论上应该是安全的HTML
                        // 但如果考虑AI可能生成恶意内容（虽然不太可能直接生成JS脚本并执行），
                        // 则需要进行HTML净化。Parsedown本身不做净化，它转换Markdown。
                        // 对于AI直接输出HTML的情况，如果AI不可信，需要一个HTML Purifier库。
                        // 假设这里的AI输出是可信的，或者经过了某种形式的审查。
                        echo $item['content_html'] ?? '<p>内容加载失败。</p>';
                    ?>
                </div>

                <?php if (!empty($item['ai_comment'])): ?>
                <div class="ai-comment">
                    <p><strong>AI解读:</strong> <?php echo nl2br(htmlspecialchars($item['ai_comment'])); // nl2br用于保留评论中的换行 ?></p>
                </div>
                <?php endif; ?>

                <div class="news-meta">
                    <?php if (!empty($item['source_name'])): ?>
                        <span class="source-name">来源: <?php echo htmlspecialchars($item['source_name']); ?></span> |
                    <?php endif; ?>
                    <?php if (!empty($item['published_at'])): ?>
                        <span class="publish-date">发布: <?php echo htmlspecialchars(date('Y-m-d H:i', strtotime($item['published_at']))); ?></span> |
                    <?php endif; ?>
                    <span class="created-date">收录: <?php echo htmlspecialchars(date('Y-m-d H:i', strtotime($item['created_at']))); ?></span>
                </div>

                <?php if (!empty($item['source_url'])): ?>
                <div class="news-source">
                    <a href="<?php echo htmlspecialchars($item['source_url']); ?>" target="_blank" rel="noopener noreferrer">查看原文 &raquo;</a>
                </div>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>

        <!-- 可以在这里添加分页链接 -->

    </main>

    <footer>
        <p>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($siteTitle); ?>. All rights reserved.</p>
        <p><small>页面加载时间: <?php echo round(microtime(true) - $_SERVER["REQUEST_TIME_FLOAT"], 4); ?> 秒</small></p>
    </footer>

    <script src="js/main.js?v=<?php echo filemtime('js/main.js'); // Cache busting ?>"></script>
</body>
</html>
