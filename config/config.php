<?php
// news-from-ai - AI新闻聚合器
// 配置文件

return [
    'db' => [
        'host' => 'localhost',
        'username' => 'your_db_user',
        'password' => 'your_db_password',
        'dbname' => 'news_from_ai_db',
        'charset' => 'utf8mb4',
    ],
    'ai' => [
        'default' => [ // 默认AI配置
            'api_key' => 'YOUR_OPENAI_API_KEY',
            'api_url' => 'https://api.openai.com/v1/chat/completions', // OpenAI通用格式API地址
            'model' => 'gpt-3.5-turbo', // 默认模型
        ],
        'news_sourcing' => [ // 用于新闻收集的AI配置 (可以与默认不同)
            'api_key' => 'YOUR_OPENAI_API_KEY_FOR_SOURCING',
            'api_url' => 'https://api.openai.com/v1/chat/completions',
            'model' => 'gpt-4', // 假设新闻收集用更强的模型
        ],
        'rss_summary' => [ // 用于RSS摘要的AI配置
            'api_key' => 'YOUR_OPENAI_API_KEY_FOR_RSS',
            'api_url' => 'https://api.openai.com/v1/chat/completions',
            'model' => 'gpt-3.5-turbo',
        ],
        // 可以根据需要添加更多特定任务的AI配置
    ],
    'google_search' => [
        'api_key' => 'YOUR_GOOGLE_SEARCH_API_KEY',
        'cse_id' => 'YOUR_GOOGLE_CUSTOM_SEARCH_ENGINE_ID', // Google Programmable Search Engine ID
    ],
    'rss_feeds' => [
        // 示例RSS源, 用户可以自行添加更多
        // 'BBC News' => 'http://feeds.bbci.co.uk/news/rss.xml',
        // 'TechCrunch' => 'https://techcrunch.com/feed/',
    ],
    'cron_task' => [
        'user_defined_schedule' => '0 * * * *', // 默认每小时执行一次，用户可自定义
    ],
    'logging' => [
        'level' => 'DEBUG', // 日志级别: DEBUG, INFO, WARNING, ERROR
        'file_path' => dirname(__DIR__) . '/logs/app.log', // 日志文件路径, dirname(__DIR__) 指向 config/ 的上一级目录，即项目根目录
    ],
    'prompts' => [ // AI提示词配置
        'rss_summary' => "请将以下RSS文章内容进行简洁的摘要总结，不超过200字：\n{article_content}",
        'news_sourcing_initial_topics' => [ // 用于AI主动搜索新闻的初始主题或指令
            "查找今天最重要的5条国际新闻。",
            "分析当前科技领域的热门趋势新闻。",
        ],
        'news_presentation_design' => "针对以下新闻内容，请设计其在网页中的呈现形式。你需要决定是采用“timeline”（时间线）、“multi_confirm”（多方证实，需整合多个来源）还是“single_article”（单个文章）的形式。请严格按照以下JSON格式输出，不要有任何额外的解释或Markdown标记：\n" .
                                      "{\"format\": \"<chosen_format>\", \"content\": \"<markdown_html_content>\"}\n" .
                                      "其中 <markdown_html_content> 是可以直接嵌入HTML的Markdown格式内容，确保包含新闻来源链接。\n" .
                                      "新闻内容：\n{news_content}\n已知相关信息或链接：\n{related_info}",
        // 可以添加更多自定义提示词
    ],
    'display' => [
        'datetime_format' => 'Y-m-d H:i:s', // 日期时间显示格式
    ]
];
?>
