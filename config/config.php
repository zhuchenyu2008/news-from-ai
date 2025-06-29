<?php
// config/config.php

// 启用错误报告以便调试，生产环境可以关闭或调整级别
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// --- 数据库配置 (MySQL) ---
define('DB_HOST', 'localhost');          // 数据库主机
define('DB_USER', 'your_db_user');       // 数据库用户名
define('DB_PASS', 'your_db_password');   // 数据库密码
define('DB_NAME', 'news_from_ai_db');    // 数据库名称
define('DB_CHARSET', 'utf8mb4');         // 数据库字符集

// --- AI API 配置 ---
// OpenAI API 通用格式配置
// 可以为每个AI任务使用不同的API端点和密钥

// 1. 新闻获取AI (负责初步处理搜索结果)
define('NEWS_FETCH_AI_API_URL', 'https://api.openai.com/v1/chat/completions'); // 或其他兼容的API地址
define('NEWS_FETCH_AI_API_KEY', 'sk-your_openai_api_key_here_1');
define('NEWS_FETCH_AI_MODEL', 'gpt-3.5-turbo'); // 使用的模型
define('NEWS_FETCH_AI_PROMPT', "请你扮演一个专业的新闻编辑。我会给你提供一些通过关键词搜索到的原始新闻摘要或链接列表。请你基于这些信息，筛选出与指定主题最相关、看起来最可靠的几条新闻。对于每条新闻，请提取或生成一个初步的标题、主要内容摘要、以及原始来源链接。请以JSON格式返回结果，包含一个新闻列表，每个新闻对象应有 'title', 'summary', 'source_url' 字段。原始输入：\n{search_results}");

// 2. 新闻评论AI
define('NEWS_COMMENT_AI_API_URL', 'https://api.openai.com/v1/chat/completions'); // 或其他兼容的API地址
define('NEWS_COMMENT_AI_API_KEY', 'sk-your_openai_api_key_here_2');
define('NEWS_COMMENT_AI_MODEL', 'gpt-3.5-turbo');
define('NEWS_COMMENT_AI_PROMPT', "请你扮演一个资深新闻评论员。我会提供给你一条新闻的标题、摘要和可能的原始数据。请你对这条新闻进行简洁而深刻的评论，分析其潜在影响或不同角度的解读。新闻标题：'{news_title}'，新闻摘要：'{news_summary}'。你的评论：");

// 3. 新闻整理汇总AI (负责生成最终展示的HTML)
define('NEWS_FORMAT_AI_API_URL', 'https://api.openai.com/v1/chat/completions'); // 或其他兼容的API地址
define('NEWS_FORMAT_AI_API_KEY', 'sk-your_openai_api_key_here_3');
define('NEWS_FORMAT_AI_MODEL', 'gpt-4'); // 建议使用能力更强的模型进行格式化
define('NEWS_FORMAT_AI_PROMPT', "你是一个精通Markdown和HTML的前端内容展示专家。我会提供给你一条新闻的标题、AI评论、原始摘要以及来源链接。你的任务是根据这些信息，设计并生成该新闻在网页中呈现的HTML内容。请考虑以下呈现形式（你可以根据新闻特点自行选择或组合，但不仅限于这些）：\n- **时间线**: 如果事件有明显的时间发展顺序。\n- **多方证实**: 如果新闻有多个来源或不同角度的报道，可以并列展示。\n- **单个文章**: 标准的文章格式。\n请确保内容结构清晰，易于阅读。最终输出必须是纯HTML代码片段，用于嵌入到网页中。确保所有外部链接（如新闻来源）在新标签页打开。新闻标题：'{news_title}'，AI评论：'{ai_comment}'，新闻摘要：'{news_summary}'，新闻来源：'{source_url}'。请生成对应的HTML内容：");

// --- Google Custom Search API 配置 ---
// 用于AI联网搜索新闻
define('GOOGLE_SEARCH_API_KEY', 'your_google_api_key_here');
define('GOOGLE_SEARCH_CX', 'your_google_search_engine_id_here'); // Programmable Search Engine ID
// 可选：按日期排序并限制搜索时间范围，例如 'date' 和 'd1' 表示按时间排序，只搜索最近一天的内容
define('GOOGLE_SEARCH_SORT', 'date'); // 或留空使用默认相关性排序
define('GOOGLE_SEARCH_DATE_RESTRICT', 'd1'); // d1=1天, w1=1周, m1=1个月等

// --- 用户新闻偏好设置 ---
// 用户想要看到的新闻方面/关键词，可以是数组
define('NEWS_KEYWORDS', [
    '科技最新进展',
    '全球财经动态',
    '巴以冲突最新消息',
    '人工智能行业新闻'
]);
// 每次执行定时任务时，随机选择一个或几个关键词进行搜索，或者按顺序轮流搜索
define('NEWS_KEYWORDS_PER_RUN', 1); // 每次任务处理的关键词数量

// --- RSS 源配置 ---
define('RSS_SOURCES', [
    ['url' => 'https://www.theverge.com/rss/index.xml', 'fetch_count' => 3, 'category' => '科技'],
    ['url' => 'http://feeds.bbci.co.uk/news/world/rss.xml', 'fetch_count' => 5, 'category' => '国际'],
    // 添加更多RSS源...
]);
// 每篇文章摘要的AI提示词 (如果需要对RSS内容也进行AI摘要)
define('RSS_SUMMARY_AI_API_URL', 'https://api.openai.com/v1/chat/completions');
define('RSS_SUMMARY_AI_API_KEY', 'sk-your_openai_api_key_here_4');
define('RSS_SUMMARY_AI_MODEL', 'gpt-3.5-turbo');
define('RSS_SUMMARY_AI_PROMPT', "请将以下RSS文章内容进行简洁的摘要，提取核心信息。文章内容：\n{article_content}");


// --- 定时任务设置 ---
// (这些是给用户的参考，实际定时由宝塔面板设置)
// define('CRON_JOB_INTERVAL', '0 * * * *'); // 例如：每小时执行一次

// --- 日志文件路径 ---
// __DIR__ 是当前文件(config.php)所在的目录
define('LOG_FILE_PATH', __DIR__ . '/../logs/app.log');

// --- 其他配置 ---
define('APP_NAME', 'News From AI - AI新闻聚合器');
define('MAX_AI_NEWS_PER_KEYWORD', 3); // 每个关键词通过AI获取的新闻数量上限
define('MAX_RSS_ITEMS_TO_PROCESS_PER_SOURCE', 5); // 每个RSS源一次处理的文章数量（如果RSS本身返回很多）

// 时区设置，确保时间相关的操作准确
date_default_timezone_set('Asia/Shanghai');

?>
