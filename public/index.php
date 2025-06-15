<?php
// --- Configuration ---
$news_html_path = __DIR__ . '/../data/news_html/'; // Path to HTML news snippets
$site_title = "AI 新闻聚合器"; // Translated

// --- Theme Switching Logic ---
$current_hour = (int)date('G'); // 'G' for 24-hour format without leading zeros
$theme_class = ($current_hour >= 6 && $current_hour < 18) ? 'theme-light' : 'theme-dark';

// --- Load News Files ---
$news_files = glob($news_html_path . '*.html');
if ($news_files === false) {
    $news_files = []; // Ensure $news_files is an array even if glob fails
}

// Optional: Sort files. Assuming filenames might contain timestamps or be otherwise sortable.
// krsort sorts an array by its keys in reverse order.
// Since glob returns an array with numeric keys, this might not be the desired sorting for filenames.
// A more robust way would be to use usort with filemtime or by parsing filenames if they have timestamps.
// For now, let's sort by filename string in reverse (descending) order.
if (!empty($news_files)) {
    rsort($news_files, SORT_STRING); // Sorts in reverse alphabetical/numerical order
}

?>
<!DOCTYPE html>
<html lang="zh-CN"> <!-- Set language to Chinese -->
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($site_title); ?></title>
    <link rel="stylesheet" href="style.css">
    <!--
        Future JavaScript for client-side enhancements (e.g., manual theme toggle) could go here.
        <script src="script.js" defer></script>
    -->
</head>
<body class="<?php echo htmlspecialchars($theme_class); ?>">

    <header>
        <h1>今日新闻</h1> <!-- Translated -->
    </header>

    <main id="news-container">
        <?php if (!empty($news_files)) : ?>
            <?php foreach ($news_files as $file_path) : ?>
                <?php
                // Ensure the file is readable before attempting to get its contents
                if (is_readable($file_path)) {
                    $news_item_content = file_get_contents($file_path);
                    if ($news_item_content !== false) {
                        echo $news_item_content; // Output HTML content directly
                    } else {
                        echo '<p class="error-message">Error: Could not read news item: ' . htmlspecialchars(basename($file_path)) . '</p>';
                    }
                } else {
                    echo '<p class="error-message">Error: News item not readable: ' . htmlspecialchars(basename($file_path)) . '</p>';
                }
                ?>
            <?php endforeach; ?>
        <?php else : ?>
            <p>暂时没有新闻。请稍后再回来查看。</p> <!-- Translated -->
        <?php endif; ?>
    </main>

    <footer>
        <p>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($site_title); ?>. 版权所有。</p> <!-- Translated "All rights reserved" and site title is already Chinese -->
    </footer>

</body>
</html>
