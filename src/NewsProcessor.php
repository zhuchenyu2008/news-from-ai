<?php

class NewsProcessor {
    private AIHelper $aiHelper;
    private string $rawNewsDir;
    private string $htmlOutputDir;
    private string $systemPromptHtml;

    public function __construct(AIHelper $aiHelper, string $rawNewsDir, string $htmlOutputDir, string $systemPromptHtml) {
        $this->aiHelper = $aiHelper;
        $this->rawNewsDir = rtrim($rawNewsDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $this->htmlOutputDir = rtrim($htmlOutputDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $this->systemPromptHtml = $systemPromptHtml;

        if (empty(trim($this->systemPromptHtml))) {
            throw new \InvalidArgumentException("System prompt for HTML generation cannot be empty.");
        }
        if (strpos($this->systemPromptHtml, '[raw_news_text_here]') === false) {
            throw new \InvalidArgumentException("System prompt for HTML generation must contain the placeholder '[raw_news_text_here]'.");
        }

        if (!is_dir($this->htmlOutputDir)) {
            if (!mkdir($this->htmlOutputDir, 0777, true) && !is_dir($this->htmlOutputDir)) {
                // Log or throw an exception
                error_log(sprintf('错误：创建 HTML 输出目录 "%s" 失败。', $this->htmlOutputDir)); // Translated
                // Depending on desired strictness, you might throw an exception here
                // throw new \RuntimeException(sprintf('创建 HTML 输出目录 "%s" 失败。', $this->htmlOutputDir)); // Translated
            } else {
                echo "成功创建 HTML 输出目录：" . $this->htmlOutputDir . "\n"; // Translated
            }
        }
    }

    public function processNews(): void {
        if (!is_dir($this->rawNewsDir)) {
            echo "原始新闻目录未找到：" . $this->rawNewsDir . "\n"; // Translated
            return;
        }

        $rawFiles = glob($this->rawNewsDir . '*.txt');
        if ($rawFiles === false) { // glob can return false on error
            echo "扫描原始新闻目录失败或未找到 .txt 文件：" . $this->rawNewsDir . "\n"; // Translated
            return;
        }

        if (empty($rawFiles)) {
            echo "在 {$this->rawNewsDir} 中找不到要处理的原始新闻文件。\n"; // Translated
            return;
        }

        $processedCount = 0;
        $failedCount = 0;

        foreach ($rawFiles as $rawFile) {
            if (!is_readable($rawFile)) {
                error_log("错误：原始新闻文件 {$rawFile} 不可读。"); // Translated
                echo "跳过不可读文件：" . $rawFile . "\n"; // Translated
                $failedCount++;
                continue;
            }

            $rawNewsText = file_get_contents($rawFile);
            if ($rawNewsText === false) {
                error_log("错误：从 {$rawFile} 读取内容失败。"); // Translated
                echo "从 {$rawFile} 读取内容失败。\n"; // Translated
                $failedCount++;
                continue;
            }

            if (empty(trim($rawNewsText))) {
                error_log("警告：原始新闻文件 {$rawFile} 为空。正在删除。"); // Translated
                echo "原始新闻文件 {$rawFile} 为空。正在删除。\n"; // Translated
                unlink($rawFile); // Delete empty raw file
                continue;
            }

            // Replace placeholder in the system prompt
            $prompt = str_replace('[raw_news_text_here]', $rawNewsText, $this->systemPromptHtml);

            $messages = [
                ['role' => 'user', 'content' => $prompt]
            ];

            echo "正在处理文件：" . $rawFile . "...\n"; // Translated
            $htmlContent = $this->aiHelper->sendPrompt($messages);

            if ($htmlContent === false || empty(trim($htmlContent))) {
                error_log("处理 {$rawFile} 时出错：AIHelper 返回 false 或空内容。"); // Translated
                echo "未能从 AI 获取 {$rawFile} 的 HTML 内容。稍后将重试。\n"; // Translated
                $failedCount++;
                continue;
            }

            $htmlFilename = $this->htmlOutputDir . basename($rawFile, '.txt') . '.html';

            if (file_put_contents($htmlFilename, $htmlContent)) {
                echo "已成功将 {$rawFile} 转换为 HTML：{$htmlFilename}\n"; // Translated
                if (unlink($rawFile)) {
                    echo "已成功删除原始文件：" . $rawFile . "\n"; // Translated
                } else {
                    error_log("错误：处理后删除原始文件 {$rawFile} 失败。"); // Translated
                    echo "警告：处理后删除原始文件 {$rawFile} 失败。\n"; // Translated
                }
                $processedCount++;
            } else {
                error_log("错误：将 HTML 内容写入 {$htmlFilename} 失败。"); // Translated
                echo "将 HTML 写入 {$htmlFilename} 失败。原始文件 {$rawFile} 已保留以供重试。\n"; // Translated
                $failedCount++;
            }
        }

        echo "新闻处理完成。已处理：" . $processedCount . "，失败/跳过：" . $failedCount . "\n"; // Translated
    }
}
