<?php
// main.php - 主页面模板
// 确保 $newsItems 和 $config 变量已由 index.php 设置

// 引入Markdown解析器 (后续创建或引入)
require_once dirname(__DIR__) . '/src/includes/MarkdownParser.php'; // 路径调整
$markdownParser = new SimpleMarkdownParser();

// 日期时间格式
$dateTimeFormat = $config['display']['datetime_format'] ?? 'Y-m-d H:i:s';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI新闻聚合器 - News From AI</title>
    <link rel="stylesheet" href="css/style.css?v=<?php echo time(); // Cache busting ?>">
    <script>
        // JavaScript将用于主题切换，储存在localStorage中
        (function() {
            const theme = localStorage.getItem('theme');
            if (theme === 'dark' || (!theme && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.classList.add('dark-mode');
            } else {
                document.documentElement.classList.remove('dark-mode');
            }
        })();
    </script>
</head>
<body>
    <header>
        <div class="container">
            <h1><a href="/">AI新闻聚合器</a></h1>
            <button id="theme-toggle-button">切换日/夜模式</button>
        </div>
    </header>

    <main class="container">
        <?php if (empty($newsItems)): ?>
            <p class="no-news">目前还没有新闻，请稍后再来看看，或检查定时任务是否正确运行。</p>
        <?php else: ?>
            <div class="news-grid">
                <?php foreach ($newsItems as $item): ?>
                    <article class="news-item card <?php echo htmlspecialchars($item['ai_presentation_format']); // e.g., single_article, timeline_event ?>">
                        <div class="news-item-header">
                            <h2><?php echo htmlspecialchars($item['title']); ?></h2>
                            <div class="news-meta">
                                <?php if (!empty($item['source_name'])): ?>
                                    <span class="source-name">来源: <?php echo htmlspecialchars($item['source_name']); ?></span>
                                <?php endif; ?>
                                <?php if (!empty($item['published_at'])): ?>
                                    <span class="published-time">发布: <?php echo htmlspecialchars(date($dateTimeFormat, strtotime($item['published_at']))); ?></span>
                                <?php endif; ?>
                                <span class="fetched-time">获取: <?php echo htmlspecialchars(date($dateTimeFormat, strtotime($item['created_at']))); ?></span>
                            </div>
                        </div>

                        <div class="news-content-ai">
                            <?php
                            // AI生成的内容已经是Markdown+HTML，或者纯Markdown
                            // 我们需要一个解析器将Markdown部分转为HTML
                            // 这里假设 $item['ai_generated_content'] 包含由AI设计的Markdown+HTML内容
                            echo $markdownParser->parse($item['ai_generated_content']);
                            ?>
                        </div>

                        <div class="news-footer">
                            <?php if (!empty($item['source_url'])): ?>
                                <a href="<?php echo htmlspecialchars($item['source_url']); ?>" target="_blank" rel="noopener noreferrer" class="read-original">查看原文 &rarr;</a>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>

    <footer>
        <div class="container">
            <p>&copy; <?php echo date('Y'); ?> News From AI. 由AI驱动的新闻聚合。</p>
        </div>
    </footer>

    <script src="js/main.js?v=<?php echo time(); // Cache busting ?>"></script>
</body>
</html>
