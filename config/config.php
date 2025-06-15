<?php

return [
    // 您的 OpenAI API 密钥
    'api_key' => 'YOUR_OPENAI_API_KEY',

    // AI 模型的 API 端点
    'api_endpoint' => 'https://api.openai.com/v1/chat/completions',

    // 新闻收集计划（例如，cron 表达式，如 '0 * * * *' 代表每小时执行一次）
    'news_schedule' => '0 * * * *',

    // 用户定义的新闻主题提示词（供 AI 使用）
    'user_prompts' => [
        "Latest technology news",
        "Global economic updates",
        // 在此处添加更多提示词
    ],

    // 用于要求 AI 为新闻条目生成 HTML 的预设系统提示词。
    // 此提示词供 NewsProcessor.php 使用。
    // 请确保保留 [raw_news_text_here] 占位符。
    'system_prompt_html' => '根据以下新闻文本，请将其格式化为一个独立的新闻摘要 HTML 代码段。HTML 结构应清晰合理，适合直接嵌入网页。请不要在 HTML 结构之外包含任何解释性文字，仅提供 HTML 代码。新闻文本：[raw_news_text_here]'
];
