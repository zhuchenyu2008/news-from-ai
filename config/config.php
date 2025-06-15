<?php

return [
    'api_key' => 'YOUR_OPENAI_API_KEY',
    'api_endpoint' => 'https://api.openai.com/v1/chat/completions',
    'news_schedule' => '0 * * * *', // Hourly cron schedule
    'user_prompts' => [
        "Latest technology news",
        "Global economic updates",
    ],
];
