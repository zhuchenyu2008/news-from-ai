<?php
require_once __DIR__ . '/src/database.php';

$stmt = $pdo->query('SELECT * FROM news ORDER BY created_at DESC');
$newsItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>news-from-ai</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container">
    <h1>AI 新闻聚合器</h1>
    <?php foreach ($newsItems as $item): ?>
        <div class="news-item">
            <h2><?= htmlspecialchars($item['title']) ?></h2>
            <div class="content"><?= $item['content_html'] ?></div>
            <div class="comment"><strong>AI评论:</strong> <?= nl2br(htmlspecialchars($item['commentary'])) ?></div>
            <div class="source"><a href="<?= htmlspecialchars($item['source_url']) ?>" target="_blank">来源</a></div>
            <div class="time"><?= $item['created_at'] ?></div>
        </div>
    <?php endforeach; ?>
</div>
<script>
// 简单的日夜主题切换
const hour = new Date().getHours();
if (hour >= 18 || hour < 6) {
    document.body.classList.add('night');
}
</script>
</body>
</html>
