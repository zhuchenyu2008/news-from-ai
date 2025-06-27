<?php
// src/includes/MarkdownParser.php

class SimpleMarkdownParser {
    public function parse(string $text): string {
        // 0. 预处理HTML，避免双重转义，但要小心XSS如果内容本身不可信
        // 假设AI生成的内容是安全的Markdown+HTML混合，我们主要处理Markdown部分
        // 或者，如果AI只应生成纯Markdown，则这里应该先htmlspecialchars($text)

        // 1. 处理标题 (### Title -> <h3>Title</h3>)
        $text = preg_replace('/^#{1,6}\s+(.+)$/m', function($matches) {
            $level = strlen($matches[1]); // Mistake here, should be strlen of leading hashes
            // Corrected: Count '#' from the full match $matches[0]
            preg_match('/^(#+)\s/', $matches[0], $hashMatch);
            $level = strlen($hashMatch[1]);
            return "<h{$level}>" . trim($matches[1]) . "</h{$level}>";
        }, $text);

        // 2. 处理加粗 (**bold** or __bold__)
        $text = preg_replace('/\*\*(.*?)\*\*|__(.*?)__/s', '<strong>$1$2</strong>', $text);

        // 3. 处理斜体 (*italic* or _italic_)
        // 需要确保这个替换不会影响到加粗的 `<strong>` 标签的 `*`
        // 通过更具体的模式或后处理来避免
        $text = preg_replace('/(?<!\*)\*(?!\s)(.*?)(?<!\s)\*(?!\*)|(?<!\_)\_(?!\s)(.*?)(?<!\s)\_(?!\_)/s', '<em>$1$2</em>', $text);


        // 4. 处理链接 ([text](url "title"))
        $text = preg_replace_callback(
            '/\[([^\]]+)\]\(([^)\s]+)(?:\s+"([^"]+)")?\)/',
            function ($matches) {
                $linkText = $matches[1];
                $url = htmlspecialchars($matches[2]); // Sanitize URL
                $title = isset($matches[3]) ? htmlspecialchars($matches[3]) : '';
                $titleAttr = !empty($title) ? " title=\"{$title}\"" : '';
                return "<a href=\"{$url}\"{$titleAttr}>{$linkText}</a>";
            },
            $text
        );

        // 5. 处理图片 (![alt](src "title"))
        $text = preg_replace_callback(
            '/!\[([^\]]*)\]\(([^)\s]+)(?:\s+"([^"]+)")?\)/',
            function ($matches) {
                $altText = htmlspecialchars($matches[1]);
                $src = htmlspecialchars($matches[2]); // Sanitize URL
                $title = isset($matches[3]) ? htmlspecialchars($matches[3]) : '';
                $titleAttr = !empty($title) ? " title=\"{$title}\"" : '';
                return "<img src=\"{$src}\" alt=\"{$altText}\"{$titleAttr}>";
            },
            $text
        );

        // 6. 处理无序列表 (* item, - item, + item) - 简单版本，不支持嵌套
        $text = preg_replace('/^\s*[\*\-\+]\s+(.+)$/m', '<ul><li>$1</li></ul>', $text);
        // 合并相邻的<ul><li>...</li></ul>为单个<ul>
        $text = preg_replace('/<\/ul>\s*<ul>/s', '', $text);


        // 7. 处理换行符 (nl2br) - 在段落处理之后或之前？这里先放在后面
        // 如果AI生成的内容中已经有 <p> 标签，nl2br可能会产生多余的 <br>
        // 假设AI的Markdown输出不包含 <p> 标签，而是依赖换行来分隔段落
        // 一个更好的方法是按双换行分割成段落，然后对每段应用行内Markdown，再包<p>

        // 简单的段落处理：用<p>包裹由两个或多个换行符分隔的文本块
        $paragraphs = preg_split('/\n{2,}/', $text);
        $html = "";
        foreach ($paragraphs as $p) {
            $p = trim($p);
            if (!empty($p)) {
                 // 避免将已经有块级标签的内容（如列表、标题）再次包裹P标签
                if (!preg_match('/^\s*<(h[1-6]|ul|ol|li|blockquote|pre|hr)/i', $p)) {
                    $html .= "<p>" . nl2br(trim($p)) . "</p>\n"; // nl2br用于段落内的单换行
                } else {
                    $html .= $p . "\n"; // 已经是块级元素
                }
            }
        }
        // 如果原始文本就是一行，或者没有双换行，上面的逻辑可能不完美
        // 若 $html 为空但 $text 不空，说明可能没有双换行，整个作为一段
        if (empty(trim($html)) && !empty(trim($text))) {
             if (!preg_match('/^\s*<(h[1-6]|ul|ol|li|blockquote|pre|hr)/i', $text)) {
                return "<p>" . nl2br(trim($text)) . "</p>";
             }
             return trim($text); // 已经是块级
        }

        return $html;
    }
}

// Example Usage:
// $parser = new SimpleMarkdownParser();
// $markdown = "## This is a title\n\nThis is **bold** and *italic*.\n\n[Link to Google](https://google.com \"Google Homepage\")\n\n* Item 1\n* Item 2";
// echo $parser->parse($markdown);
?>
