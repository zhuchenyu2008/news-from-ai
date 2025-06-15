<?php

class NewsProcessor {
    private AIHelper $aiHelper;
    private string $rawNewsDir;
    private string $htmlOutputDir;

    public function __construct(AIHelper $aiHelper, string $rawNewsDir, string $htmlOutputDir) {
        $this->aiHelper = $aiHelper;
        $this->rawNewsDir = rtrim($rawNewsDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $this->htmlOutputDir = rtrim($htmlOutputDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        if (!is_dir($this->htmlOutputDir)) {
            if (!mkdir($this->htmlOutputDir, 0777, true) && !is_dir($this->htmlOutputDir)) {
                // Log or throw an exception
                error_log(sprintf('Error: Failed to create HTML output directory "%s".', $this->htmlOutputDir));
                // Depending on desired strictness, you might throw an exception here
                // throw new \RuntimeException(sprintf('Failed to create HTML output directory "%s".', $this->htmlOutputDir));
            } else {
                echo "Created HTML output directory: " . $this->htmlOutputDir . "\n";
            }
        }
    }

    public function processNews(): void {
        if (!is_dir($this->rawNewsDir)) {
            echo "Raw news directory not found: {$this->rawNewsDir}\n";
            return;
        }

        $rawFiles = glob($this->rawNewsDir . '*.txt');
        if ($rawFiles === false) {
            echo "Failed to scan raw news directory or no .txt files found: {$this->rawNewsDir}\n";
            return;
        }

        if (empty($rawFiles)) {
            echo "No raw news files found to process in {$this->rawNewsDir}\n";
            return;
        }

        $processedCount = 0;
        $failedCount = 0;

        foreach ($rawFiles as $rawFile) {
            if (!is_readable($rawFile)) {
                error_log("Error: Raw news file {$rawFile} is not readable.");
                echo "Skipping unreadable file: {$rawFile}\n";
                $failedCount++;
                continue;
            }

            $rawNewsText = file_get_contents($rawFile);
            if ($rawNewsText === false) {
                error_log("Error: Failed to read content from {$rawFile}.");
                echo "Failed to read content from: {$rawFile}\n";
                $failedCount++;
                continue;
            }

            if (empty(trim($rawNewsText))) {
                error_log("Warning: Raw news file {$rawFile} is empty. Deleting.");
                echo "Raw news file {$rawFile} is empty. Deleting.\n";
                unlink($rawFile); // Delete empty raw file
                continue;
            }

            $prompt = "Given the following news text, format it as a self-contained HTML snippet for a news feed. The HTML should be well-structured and suitable for direct embedding. Do not include any explanations outside the HTML structure itself, just the HTML code. News text:\n\n" . $rawNewsText;

            $messages = [
                ['role' => 'user', 'content' => $prompt]
            ];

            echo "Processing file: {$rawFile}...\n";
            $htmlContent = $this->aiHelper->sendPrompt($messages);

            if ($htmlContent === false || empty(trim($htmlContent))) {
                error_log("Error processing {$rawFile}: AIHelper returned false or empty content.");
                echo "Failed to get HTML from AI for: {$rawFile}. It will be retried later.\n";
                $failedCount++;
                continue;
            }

            $htmlFilename = $this->htmlOutputDir . basename($rawFile, '.txt') . '.html';

            if (file_put_contents($htmlFilename, $htmlContent)) {
                echo "Successfully converted {$rawFile} to HTML: {$htmlFilename}\n";
                if (unlink($rawFile)) {
                    echo "Successfully deleted raw file: {$rawFile}\n";
                } else {
                    error_log("Error: Failed to delete raw file {$rawFile} after processing.");
                    echo "Warning: Failed to delete raw file {$rawFile} after processing.\n";
                }
                $processedCount++;
            } else {
                error_log("Error: Failed to write HTML content to {$htmlFilename}.");
                echo "Failed to write HTML to {$htmlFilename}. Raw file {$rawFile} kept for retry.\n";
                $failedCount++;
            }
        }

        echo "News processing complete. Processed: {$processedCount}, Failed/Skipped: {$failedCount}\n";
    }
}
