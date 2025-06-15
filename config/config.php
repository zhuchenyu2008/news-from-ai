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

    // HTML 生成的提示模板。
    // 此提示词供 NewsProcessor.php 使用。
    // 包含 [preferred_style_placeholder] 和 [raw_news_text_here] 占位符。
    'html_generation_prompt_template' => "## 指令 ##
您是一个专门将原始新闻文本转换为结构化 HTML 代码段的 AI 助手。您的任务是根据用户偏好和新闻内容，生成最合适、信息最丰富的 HTML 表示形式。

## 用户偏好 ##
用户期望的输出样式为：'[preferred_style_placeholder]'。
- 如果样式为 'auto'，请根据新闻内容的性质，从以下可用样式中智能选择最合适的一种。
- 如果指定了特定样式（例如 'timeline', 'detailed_article', 'multi_faceted_report'），请严格按照该样式进行输出。

## 可用 HTML 样式详细说明 ##
1.  **时间线 (timeline):**
    *   适用场景：按时间顺序发生的一系列事件、发展历程、历史回顾。
    *   HTML结构：应包含清晰的时间标记和对应的事件描述。可使用 `<ul>` 或 `<dl>` 结合自定义的 CSS 类 (例如 `class='timeline-item'`, `class='timeline-time'`, `class='timeline-content'`) 来构建。
    *   例如 (概念性，具体标签和类名可调整):
        ```html
        <div class='news-item news-timeline'>
          <h3>[新闻标题]</h3>
          <ul>
            <li class='timeline-item'>
              <span class='timeline-time'>[日期/时间1]</span>
              <p class='timeline-content'>[事件描述1]</p>
            </li>
            <li class='timeline-item'>
              <span class='timeline-time'>[日期/时间2]</span>
              <p class='timeline-content'>[事件描述2]</p>
            </li>
          </ul>
        </div>
        ```
2.  **详细单篇文章 (detailed_article):**
    *   适用场景：对单一主题、事件或人物的深入报道。
    *   HTML结构：应包含标题 (h2, h3), 段落 (p), 可能的引用 (blockquote), 列表 (ul/ol), 以及强调 (strong/em)。确保内容流畅，易于阅读。
    *   例如:
        ```html
        <article class='news-item news-article'>
          <h2>[文章主标题]</h2>
          <p class='article-meta'>发布于：[日期] | 来源：[来源]</p>
          <p>[段落1...]</p>
          <p>[段落2...]</p>
          <blockquote>[引用内容...]</blockquote>
        </article>
        ```
3.  **多方面报告/多方证实 (multi_faceted_report):**
    *   适用场景：综合多个来源或观点，呈现事件的多个侧面，或对比不同信息源的证实情况。
    *   HTML结构：可能包含小标题来区分不同方面或来源，引用不同来源的文本片段。
    *   例如:
        ```html
        <section class='news-item news-multi-report'>
          <h3>[报告总标题]</h3>
          <div class='report-aspect'>
            <h4>[方面1/来源1]</h4>
            <p>[内容/引述1]</p>
          </div>
          <div class='report-aspect'>
            <h4>[方面2/来源2]</h4>
            <p>[内容/引述2]</p>
            <p>证实情况：[证实状态]</p>
          </div>
        </section>
        ```

## 输出要求 ##
-   **仅输出 HTML:** 您的回复必须只包含所要求的 HTML 代码片段。不要包含任何解释性文字、Markdown 标记或任何非 HTML 内容。
-   **自包含:** 生成的 HTML 应该可以独立显示，不应依赖外部的特定 CSS 类（除非是上面示例中建议的通用类名，如 news-item, news-article 等，这些类名有助于基本样式化）。
-   **结构清晰:** HTML 结构必须逻辑清晰、语义正确、并且易于解析。
-   **占位符内容:** 请用实际从新闻文本中提取或总结的内容替换示例中的 "[...]" 占位符。
-   **安全性:** 不要生成任何 `<script>` 标签或可能导致安全风险的 HTML。

## 新闻文本 ##
[raw_news_text_here]

请根据以上指示处理提供的新闻文本。
",

    // 首选的 HTML 输出样式。可选值: 'auto' (自动判断), 'timeline' (时间线), 'detailed_article' (详细文章), 'multi_faceted_report' (多方面报告). AI 将尝试遵循此设置。
    'preferred_html_style' => 'auto',

    // 使用的 AI 模型 (例如: "gpt-3.5-turbo", "gpt-4")
    'ai_model' => 'gpt-3.5-turbo',
];
