<?php
return [
    // 数据库配置
    'db' => [
        'host' => 'localhost',
        'dbname' => 'news_ai',
        'user' => 'root',
        'pass' => '',
        'charset' => 'utf8mb4',
    ],

    // OpenAI API配置，可单独设置不同用途
    'openai' => [
        // 默认API
        'default' => [
            'api_key' => 'YOUR_OPENAI_API_KEY',
            'base_url' => 'https://api.openai.com/v1',
            'model' => 'gpt-3.5-turbo'
        ],
        // 新闻评论AI
        'comment' => [
            'api_key' => 'YOUR_OPENAI_API_KEY',
            'base_url' => 'https://api.openai.com/v1',
            'model' => 'gpt-3.5-turbo'
        ],
        // 新闻整理汇总AI
        'summary' => [
            'api_key' => 'YOUR_OPENAI_API_KEY',
            'base_url' => 'https://api.openai.com/v1',
            'model' => 'gpt-3.5-turbo'
        ],
    ],

    // Google Custom Search API配置
    'google_search' => [
        'api_key' => 'YOUR_GOOGLE_API_KEY',
        'cx' => 'YOUR_SEARCH_ENGINE_ID'
    ],

    // 用户希望关注的新闻主题关键词
    'topics' => [
        '财经',
        '军事'
    ],

    // rss订阅地址
    'rss_feeds' => [
        'https://example.com/rss.xml'
    ],

    // 获取rss文章的数量
    'rss_article_limit' => 5,

    // 三个AI的提示词
    'prompts' => [
        // 新闻获取AI
        'search' => "请搜索最新的与这些关键词相关的新闻，并给出主要来源链接。",
        // 新闻评论AI
        'comment' => "请对以上新闻内容进行专业评论。",
        // 新闻整理汇总AI
        'summary' => "请将以上新闻整理成便于阅读的Markdown+HTML格式。"
    ],
];
