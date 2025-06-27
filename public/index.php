<?php
<?php
// news-from-ai - AI新闻聚合器
// 入口文件

// 定义项目根目录的绝对路径
define('ROOT_PATH', dirname(__DIR__));

// 设置时区
date_default_timezone_set('Asia/Shanghai'); // 根据需要调整

// 引入通用函数（包括配置加载、数据库连接、日志等）
require_once ROOT_PATH . '/src/includes/functions.php';

// 加载配置
try {
    $config = load_config();
} catch (RuntimeException $e) {
    // 如果配置加载失败，显示一个基本错误页面
    header("HTTP/1.1 500 Internal Server Error");
    echo "<h1>配置错误</h1><p>无法加载项目配置，请检查日志获取更多信息。</p>";
    // 尝试记录日志，即使日志配置本身可能有问题
    error_log("FATAL: Failed to load config in index.php: " . $e->getMessage());
    exit;
}

// 获取数据库连接
try {
    $mysqli = get_db_connection();
} catch (RuntimeException $e) {
    // 如果数据库连接失败
    log_message('error', 'index.php - 数据库连接失败: ' . $e->getMessage());
    // 可以在这里显示一个更友好的错误页面，而不是直接暴露错误信息
    // 为了简单起见，这里直接输出
    header("HTTP/1.1 500 Internal Server Error");
    echo "<h1>数据库连接错误</h1><p>无法连接到数据库，请稍后再试或联系管理员。</p>";
    exit;
}

// 从数据库获取新闻条目
// 按创建时间降序排列，获取最新的N条新闻，例如最新的20条
$numberOfNewsItems = $config['display']['items_per_page'] ?? 20;
$newsItems = [];
$sql = "SELECT id, title, source_url, source_name, ai_generated_content, ai_presentation_format, published_at, created_at
        FROM news_articles
        ORDER BY created_at DESC
        LIMIT ?";

$stmt = $mysqli->prepare($sql);
if ($stmt) {
    $stmt->bind_param('i', $numberOfNewsItems);
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        $newsItems = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        log_message('info', '成功从数据库获取 ' . count($newsItems) . ' 条新闻用于展示。');
    } else {
        log_message('error', '执行新闻查询失败: ' . $stmt->error);
    }
} else {
    log_message('error', '准备新闻查询SQL失败: ' . $mysqli->error);
}

// 引入主模板文件
// $newsItems 和 $config 变量将在模板作用域内可用
try {
    require_once ROOT_PATH . '/templates/main.php';
} catch (Throwable $e) { // Throwable捕获Error和Exception
    log_message('error', '渲染主模板时发生错误: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
    header("HTTP/1.1 500 Internal Server Error");
    echo "<h1>页面渲染错误</h1><p>加载页面时发生内部错误，请联系管理员。</p>";
    exit;
}

?>
?>
