<?php
// public/index.php

// --- 1. 加载必要文件 ---
$baseDir = __DIR__ . '/..';
require_once $baseDir . '/config/config.php';

// 日志模块的加载可以根据需要，如果前端不需要记录特定日志，可以不加载
// 但db_handler.php中使用了log_error等，所以还是需要logger.php能被包含
// 或者在db_handler.php等核心库中做好函数存在性检查
if (file_exists($baseDir . '/core/logger.php')) {
    require_once $baseDir . '/core/logger.php';
} else {
    // Fallback basic logging if logger.php is missing
    if (!function_exists('log_error')) { function log_error($msg) { error_log("ERROR: ".$msg); } }
    if (!function_exists('log_info')) { function log_info($msg) { error_log("INFO: ".$msg); } }
}
require_once $baseDir . '/core/db_handler.php'; // 数据库操作

// --- 2. 获取数据库连接 ---
$db = get_db_connection();
if (!$db) {
    $error_message = "抱歉，新闻服务暂时不可用（无法连接数据库），请稍后再试。";
    if (function_exists('log_error')) log_error("Index.php: 无法连接到数据库。");
    // Display a user-friendly error page without dying immediately if possible
    // For simplicity, we'll die here for now if DB is critical and unavailable.
    // In a real app, you might render a proper error template.
    // die($error_message);
}

// --- 3. 从数据库获取新闻 ---
$newsPerPage = defined('NEWS_PER_PAGE') ? (int)NEWS_PER_PAGE : 10;
$page = isset($_GET['page']) && filter_var($_GET['page'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $newsPerPage;

$newsItems = [];
$totalNews = 0;
$databaseError = false;

if ($db) { // Proceed only if DB connection was successful
    try {
        $totalStmt = $db->query("SELECT COUNT(*) FROM news");
        if ($totalStmt) {
            $totalNews = (int)$totalStmt->fetchColumn();
        } else {
            $databaseError = true;
            if(function_exists('log_error')) log_error("无法获取总新闻数。");
        }

        if (!$databaseError) {
            // 主要按fetched_at排序，确保新闻按真实发生时间排序，其次按ID（约等于入库时间）
            $stmt = $db->prepare("SELECT * FROM news ORDER BY fetched_at DESC, id DESC LIMIT :limit OFFSET :offset");
            $stmt->bindParam(':limit', $newsPerPage, PDO::PARAM_INT);
            $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            $newsItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

    } catch (PDOException $e) {
        $databaseError = true;
        if(function_exists('log_error')) log_error("从数据库获取新闻失败: " . $e->getMessage());
        $newsItems = [];
    }
} else {
    $databaseError = true; // DB connection failed earlier
}

$totalPages = $db && $totalNews > 0 ? ceil($totalNews / $newsPerPage) : 0;
if ($page > $totalPages && $totalPages > 0) { // 如果请求的页面超过总页数，重定向到最后一页
    header('Location: ?page=' . $totalPages);
    exit;
}


?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo defined('APP_NAME') ? htmlspecialchars(APP_NAME) : 'AI新闻聚合器'; ?></title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo filemtime($baseDir . '/public/assets/css/style.css'); // More robust cache busting ?>">
    <meta name="description" content="AI驱动的新闻聚合器，为您带来最新、最相关的时事热点。">
</head>
<body>
    <button id="themeSwitcher" class="theme-switcher" title="切换日间/夜间模式">🌙 夜间模式</button>

    <div class="container">
        <header>
            <h1><?php echo defined('APP_NAME') ? htmlspecialchars(APP_NAME) : 'AI新闻聚合器'; ?></h1>
            <p>由AI为您定时收集、解读和呈现时事热点</p>
        </header>

        <main>
            <?php if ($databaseError && empty($newsItems)): // Show DB error only if no items could be loaded at all ?>
                 <div class="no-news">
                    <p>😢 糟糕，加载新闻时遇到问题。</p>
                    <p>我们的工程师（AI）正在紧急处理，请稍后再试。</p>
                </div>
            <?php elseif (!empty($newsItems)): ?>
                <?php foreach ($newsItems as $item): ?>
                    <article class="news-item">
                        <h2><a href="<?php echo htmlspecialchars($item['source_url']); ?>" target="_blank" rel="noopener noreferrer"><?php echo htmlspecialchars($item['title']); ?></a></h2>
                        <div class="meta-info">
                            <span>📅 <?php echo htmlspecialchars(date('Y-m-d H:i', strtotime($item['fetched_at']))); ?></span>
                            <?php if (!empty($item['category'])): ?>
                                <span class="category"><?php echo htmlspecialchars($item['category']); ?></span>
                            <?php endif; ?>
                            <span class="type-<?php echo htmlspecialchars(str_replace('_', '-', $item['type'])); ?>"> <!-- ai_generated to ai-generated for CSS class -->
                                <?php echo $item['type'] === 'rss' ? 'RSS源' : 'AI生成'; ?>
                            </span>
                            <?php if (!empty($item['source_name'])): ?>
                                <span>📰 <?php echo htmlspecialchars($item['source_name']); ?></span>
                            <?php endif; ?>
                        </div>

                        <div class="news-content">
                            <?php
                                // AI生成的HTML，应在生成时确保安全。
                                // 如果担心，可以使用HTML Purifier等库进行清理。
                                // echo html_purifier_purify($item['content_html']);
                                echo $item['content_html'];
                            ?>
                        </div>

                        <?php if (!empty($item['ai_comment'])): ?>
                            <div class="ai-comment">
                                <h4>🤖 AI解读与评论</h4>
                                <p><?php echo nl2br(htmlspecialchars($item['ai_comment'])); ?></p>
                            </div>
                        <?php endif; ?>

                        <div class="source-link">
                            <a href="<?php echo htmlspecialchars($item['source_url']); ?>" target="_blank" rel="noopener noreferrer">查看原文 &raquo;</a>
                        </div>
                    </article>
                <?php endforeach; ?>

                <?php if ($totalPages > 1): ?>
                    <nav class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?php echo $page - 1; ?>" title="上一页">&laquo; 上一页</a>
                        <?php else: ?>
                            <span class="disabled-page-link">&laquo; 上一页</span>
                         <?php endif; ?>

                        <span>第 <?php echo $page; ?> 页 / 共 <?php echo $totalPages; ?> 页</span>

                        <?php if ($page < $totalPages): ?>
                            <a href="?page=<?php echo $page + 1; ?>" title="下一页">下一页 &raquo;</a>
                        <?php else: ?>
                            <span class="disabled-page-link">下一页 &raquo;</span>
                        <?php endif; ?>
                    </nav>
                     <style> /* Quick style for disabled pagination links */
                        .disabled-page-link {
                            margin: 0 5px;
                            padding: 8px 12px;
                            color: #ccc;
                            border: 1px solid #eee;
                            border-radius: 4px;
                            cursor: default;
                        }
                        body.dark-theme .disabled-page-link {
                            color: #777;
                            border-color: #555;
                        }
                    </style>
                <?php endif; ?>

            <?php else: ?>
                <div class="no-news">
                    <p>😮 暂时还没有新闻哦，AI正在努力收集中...</p>
                    <p>请稍后刷新页面，或检查定时任务是否已正确配置并运行。</p>
                </div>
            <?php endif; ?>
        </main>

        <footer>
            <p>&copy; <?php echo date('Y'); ?> <?php echo defined('APP_NAME') ? htmlspecialchars(APP_NAME) : 'AI新闻聚合器'; ?>.</p>
        </footer>
    </div>

    <script src="assets/js/script.js?v=<?php echo filemtime($baseDir . '/public/assets/js/script.js'); // Cache busting ?>"></script>
</body>
</html>
