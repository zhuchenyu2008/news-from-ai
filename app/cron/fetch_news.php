<?php

// Ensure we are running from CLI or a cron job, not via web
if (php_sapi_name() !== 'cli' && substr(php_sapi_name(), 0, 3) !== 'cgi') {
    die('This script can only be run from the command line or via cron.');
}

// Set a higher execution time limit for this script, as AI calls and parsing can take time
set_time_limit(1800); // 30 minutes, adjust as needed
ini_set('memory_limit', '256M'); // Increase memory limit if processing large amounts of data

// Define project root for easier includes
define('PROJECT_ROOT', dirname(__DIR__, 2));

// 1. Initialization
require_once PROJECT_ROOT . '/config.php'; // Load configuration (defines constants)
// Autoloader is usually included via config.php, but ensure it's loaded.
// If not, uncomment the line below, assuming composer install has been run.
// require_once PROJECT_ROOT . '/vendor/autoload.php';

use App\Lib\Database;
use App\Lib\AIConnector;
use App\Lib\RssParser;
use App\Lib\SearchTool;

// Instantiate helper classes
$db = new Database();
if (!$db->isConnected()) {
    error_log(date('[Y-m-d H:i:s]') . " fetch_news.php: Critical - Failed to connect to the database. Exiting.\n", 3, PROJECT_ROOT . "/app/cron/cron_error.log");
    exit("Critical - Failed to connect to the database. Exiting.\n");
}

$aiConnector = new AIConnector();
$rssParser   = new RssParser();
$searchTool  = new SearchTool();

error_log(date('[Y-m-d H:i:s]') . " fetch_news.php: Script started.\n", 3, PROJECT_ROOT . "/app/cron/cron.log");

// --- Helper function to insert news item ---
function insertNewsItem(Database $db, string $format, string $contentMarkdown, ?string $sourceUrl = null, ?array $sourcesJson = null): bool {
    $sql = "INSERT INTO news_items (format, content_markdown, source_url, sources_json, created_at) VALUES (:format, :content_markdown, :source_url, :sources_json, NOW())";
    $params = [
        ':format' => $format,
        ':content_markdown' => $contentMarkdown,
        ':source_url' => $sourceUrl,
        ':sources_json' => $sourcesJson ? json_encode($sourcesJson) : null,
    ];
    $stmt = $db->query($sql, $params);
    if ($stmt) {
        error_log(date('[Y-m-d H:i:s]') . " fetch_news.php: Successfully inserted news item. Format: {$format}\n", 3, PROJECT_ROOT . "/app/cron/cron.log");
        return true;
    }
    error_log(date('[Y-m-d H:i:s]') . " fetch_news.php: Failed to insert news item. Format: {$format}\n", 3, PROJECT_ROOT . "/app/cron/cron_error.log");
    return false;
}

// 2. Generate Search Queries
$searchKeywords = [];
try {
    $userNewsPrompt = defined('USER_NEWS_PROMPT') ? USER_NEWS_PROMPT : 'Latest advancements in AI and technology.';
    $queryGenSystemPrompt = "You are a news research assistant. Based on the user's core interest: '{$userNewsPrompt}', generate 5 distinct, specific, and timely Google search keywords for today's news. Return as a JSON array of strings. For example: [\"keyword1\", \"keyword2\"]";

    $queryGeneratorConfig = AI_CONFIGS['query_generator'] ?? null;
    if(!$queryGeneratorConfig) throw new Exception("query_generator config missing from AI_CONFIGS");
    // Add response_format hint for models that support it, to ensure JSON output
    $queryGeneratorConfig['response_format'] = 'json_object';


    $rawKeywordsResponse = $aiConnector->generate(
        $queryGenSystemPrompt,
        "User interest: {$userNewsPrompt}. Generate 5 search keywords.",
        $queryGeneratorConfig
    );

    if ($rawKeywordsResponse) {
        // Attempt to find JSON array within the response, as AI might add extra text
        // Added 's' modifier to make dot match newlines, as AI response might be multi-line.
        if (preg_match('/\[.*\]/s', $rawKeywordsResponse, $matches)) {
            $jsonResponse = $matches[0];
            $decodedKeywords = json_decode($jsonResponse, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decodedKeywords)) {
                $searchKeywords = array_filter(array_map('trim', $decodedKeywords), function($kw) { return !empty($kw); });
                error_log(date('[Y-m-d H:i:s]') . " fetch_news.php: Generated search keywords: " . implode(", ", $searchKeywords) . "\n", 3, PROJECT_ROOT . "/app/cron/cron.log");
            } else {
                error_log(date('[Y-m-d H:i:s]') . " fetch_news.php: Failed to decode JSON keywords from AI response. Error: " . json_last_error_msg() . ". Response: " . $rawKeywordsResponse . "\n", 3, PROJECT_ROOT . "/app/cron/cron_error.log");
            }
        } else {
             error_log(date('[Y-m-d H:i:s]') . " fetch_news.php: No JSON array found in AI response for keywords. Response: " . $rawKeywordsResponse . "\n", 3, PROJECT_ROOT . "/app/cron/cron_error.log");
        }
    } else {
        error_log(date('[Y-m-d H:i:s]') . " fetch_news.php: AI did not return a response for keyword generation.\n", 3, PROJECT_ROOT . "/app/cron/cron_error.log");
    }
} catch (Exception $e) {
    error_log(date('[Y-m-d H:i:s]') . " fetch_news.php: Error generating search keywords: " . $e->getMessage() . "\n", 3, PROJECT_ROOT . "/app/cron/cron_error.log");
}

// 3. Execute Web Search
$searchResults = [];
if (!empty($searchKeywords)) {
    foreach ($searchKeywords as $keyword) {
        try {
            error_log(date('[Y-m-d H:i:s]') . " fetch_news.php: Searching for keyword: " . $keyword . "\n", 3, PROJECT_ROOT . "/app/cron/cron.log");
            $results = $searchTool->search($keyword);
            if (!empty($results)) {
                $searchResults = array_merge($searchResults, $results);
                error_log(date('[Y-m-d H:i:s]') . " fetch_news.php: Found " . count($results) . " results for '{$keyword}'.\n", 3, PROJECT_ROOT . "/app/cron/cron.log");
            } else {
                error_log(date('[Y-m-d H:i:s]') . " fetch_news.php: No search results for '{$keyword}'.\n", 3, PROJECT_ROOT . "/app/cron/cron.log");
            }
        } catch (Exception $e) {
            error_log(date('[Y-m-d H:i:s]') . " fetch_news.php: Error during search for keyword '{$keyword}': " . $e->getMessage() . "\n", 3, PROJECT_ROOT . "/app/cron/cron_error.log");
        }
        sleep(1); // Be respectful to search API, add a small delay
    }
} else {
    error_log(date('[Y-m-d H:i:s]') . " fetch_news.php: No search keywords generated, skipping web search.\n", 3, PROJECT_ROOT . "/app/cron/cron.log");
}

// Deduplicate search results by URL
if (!empty($searchResults)) {
    $tempResults = [];
    foreach ($searchResults as $result) {
        if (isset($result['url'])) {
            $tempResults[$result['url']] = $result;
        }
    }
    $searchResults = array_values($tempResults);
    error_log(date('[Y-m-d H:i:s]') . " fetch_news.php: Total unique search results collected: " . count($searchResults) . "\n", 3, PROJECT_ROOT . "/app/cron/cron.log");
}


// 4. AI Analysis and Creation
if (!empty($searchResults)) {
    $newsAnalyzerConfig = AI_CONFIGS['news_analyzer'] ?? null;
    if(!$newsAnalyzerConfig) {
        error_log(date('[Y-m-d H:i:s]') . " fetch_news.php: news_analyzer config missing from AI_CONFIGS. Skipping AI analysis.\n", 3, PROJECT_ROOT . "/app/cron/cron_error.log");
    } else {
        // Add response_format hint for models that support it
        $newsAnalyzerConfig['response_format'] = 'json_object';

        $newsMaterial = "Collected News Materials:\n\n";
        foreach ($searchResults as $index => $result) {
            $newsMaterial .= ($index + 1) . ". Title: " . ($result['title'] ?? 'N/A') . "\n";
            $newsMaterial .= "   URL: " . ($result['url'] ?? '#') . "\n";
            $newsMaterial .= "   Summary: " . ($result['summary'] ?? 'N/A') . "\n\n";
        }

        $analyzerSystemPrompt = "You are a senior news editor. Integrate the following news materials and decide the most appropriate presentation format (choose one from 'timeline', 'multi_source_report', 'single_article_deep_dive'). Then, write a well-structured report in Markdown. Cite information using '[Source](URL)' format for original links. Finally, encapsulate your output in a JSON object with 'format' and 'content' keys. The 'content' should be the Markdown text. Example: {\"format\": \"multi_source_report\", \"content\": \"### Report Title\\n\\nDetails... [Source](URL)...\"}";

        try {
            error_log(date('[Y-m-d H:i:s]') . " fetch_news.php: Sending " . count($searchResults) . " search results to news_analyzer AI.\n", 3, PROJECT_ROOT . "/app/cron/cron.log");
            $analyzedNewsResponse = $aiConnector->generate(
                $analyzerSystemPrompt,
                $newsMaterial,
                $newsAnalyzerConfig
            );

            if ($analyzedNewsResponse) {
                 // Attempt to find JSON object within the response
                if (preg_match('/\{.*\}/s', $analyzedNewsResponse, $matches)) {
                    $jsonResponse = $matches[0];
                    $analyzedNews = json_decode($jsonResponse, true);

                    if (json_last_error() === JSON_ERROR_NONE && isset($analyzedNews['format']) && isset($analyzedNews['content'])) {
                        $sourceUrls = array_map(function($item) { return $item['url']; }, $searchResults);
                        insertNewsItem($db, $analyzedNews['format'], $analyzedNews['content'], null, $sourceUrls);
                        error_log(date('[Y-m-d H:i:s]') . " fetch_news.php: AI analyzed news content generated and saved. Format: " . $analyzedNews['format'] . "\n", 3, PROJECT_ROOT . "/app/cron/cron.log");
                    } else {
                        error_log(date('[Y-m-d H:i:s]') . " fetch_news.php: Failed to decode JSON or missing keys in AI analyzed news response. Error: ".json_last_error_msg()." Response: " . $analyzedNewsResponse . "\n", 3, PROJECT_ROOT . "/app/cron/cron_error.log");
                    }
                } else {
                    error_log(date('[Y-m-d H:i:s]') . " fetch_news.php: No JSON object found in AI response for news analysis. Response: " . $analyzedNewsResponse . "\n", 3, PROJECT_ROOT . "/app/cron/cron_error.log");
                }
            } else {
                error_log(date('[Y-m-d H:i:s]') . " fetch_news.php: AI did not return a response for news analysis.\n", 3, PROJECT_ROOT . "/app/cron/cron_error.log");
            }
        } catch (Exception $e) {
            error_log(date('[Y-m-d H:i:s]') . " fetch_news.php: Error during AI news analysis: " . $e->getMessage() . "\n", 3, PROJECT_ROOT . "/app/cron/cron_error.log");
        }
    }
} else {
    error_log(date('[Y-m-d H:i:s]') . " fetch_news.php: No search results to analyze.\n", 3, PROJECT_ROOT . "/app/cron/cron.log");
}


// 5. Process RSS Feeds
$rssFeeds = defined('RSS_FEEDS') && is_array(RSS_FEEDS) ? RSS_FEEDS : [];
if (!empty($rssFeeds)) {
    $rssSummarizerConfig = AI_CONFIGS['rss_summarizer'] ?? null;
    if(!$rssSummarizerConfig) {
         error_log(date('[Y-m-d H:i:s]') . " fetch_news.php: rss_summarizer config missing from AI_CONFIGS. Skipping RSS processing.\n", 3, PROJECT_ROOT . "/app/cron/cron_error.log");
    } else {
        foreach ($rssFeeds as $feedUrl) {
            if (empty($feedUrl) || !filter_var($feedUrl, FILTER_VALIDATE_URL)) {
                error_log(date('[Y-m-d H:i:s]') . " fetch_news.php: Invalid RSS feed URL: " . $feedUrl . "\n", 3, PROJECT_ROOT . "/app/cron/cron_error.log");
                continue;
            }
            error_log(date('[Y-m-d H:i:s]') . " fetch_news.php: Processing RSS feed: " . $feedUrl . "\n", 3, PROJECT_ROOT . "/app/cron/cron.log");
            try {
                $articles = $rssParser->parse($feedUrl);
                if (empty($articles)) {
                    error_log(date('[Y-m-d H:i:s]') . " fetch_news.php: No articles found or error parsing RSS feed: " . $feedUrl . "\n", 3, PROJECT_ROOT . "/app/cron/cron.log");
                    continue;
                }
                error_log(date('[Y-m-d H:i:s]') . " fetch_news.php: Found " . count($articles) . " articles in " . $feedUrl . "\n", 3, PROJECT_ROOT . "/app/cron/cron.log");

                foreach ($articles as $article) {
                    // Simple check to avoid re-processing: check if URL already exists.
                    // A more robust check might involve checking content hash or publication date.
                    $checkSql = "SELECT id FROM news_items WHERE source_url = :source_url AND format = 'rss_summary' LIMIT 1";
                    $stmtCheck = $db->query($checkSql, [':source_url' => $article['link']]);
                    if ($stmtCheck && $stmtCheck->fetch()) {
                        error_log(date('[Y-m-d H:i:s]') . " fetch_news.php: Article already processed (URL exists): " . $article['link'] . ". Skipping.\n", 3, PROJECT_ROOT . "/app/cron/cron.log");
                        continue;
                    }

                    $articleContentToSummarize = "Title: " . $article['title'] . "\n\nContent: " . $article['content'];
                    // Ensure content is not excessively long for the summarizer model's context window
                    $maxContentLength = 12000; // Approx 3k-4k tokens, adjust based on model (e.g. gpt-3.5-turbo has 4k/16k token limits)
                    if (strlen($articleContentToSummarize) > $maxContentLength) {
                        $articleContentToSummarize = mb_substr($articleContentToSummarize, 0, $maxContentLength, 'UTF-8') . "... (content truncated)";
                    }

                    $rssSystemPrompt = "Summarize the following article content into three key bullet points and write a concluding comment. Use Markdown format. Ensure the output is suitable for direct display.";

                    error_log(date('[Y-m-d H:i:s]') . " fetch_news.php: Summarizing article: " . $article['title'] . "\n", 3, PROJECT_ROOT . "/app/cron/cron.log");
                    $summaryResponse = $aiConnector->generate(
                        $rssSystemPrompt,
                        $articleContentToSummarize,
                        $rssSummarizerConfig
                    );

                    if ($summaryResponse) {
                        insertNewsItem($db, 'rss_summary', $summaryResponse, $article['link']);
                    } else {
                        error_log(date('[Y-m-d H:i:s]') . " fetch_news.php: AI did not return a summary for article: " . $article['title'] . "\n", 3, PROJECT_ROOT . "/app/cron/cron_error.log");
                    }
                    sleep(1); // Delay between AI calls
                }
            } catch (Exception $e) {
                error_log(date('[Y-m-d H:i:s]') . " fetch_news.php: Error processing RSS feed '{$feedUrl}': " . $e->getMessage() . "\n", 3, PROJECT_ROOT . "/app/cron/cron_error.log");
            }
            sleep(2); // Delay between processing different RSS feeds
        }
    }
} else {
    error_log(date('[Y-m-d H:i:s]') . " fetch_news.php: No RSS_FEEDS defined or empty. Skipping RSS processing.\n", 3, PROJECT_ROOT . "/app/cron/cron.log");
}

error_log(date('[Y-m-d H:i:s]') . " fetch_news.php: Script finished.\n", 3, PROJECT_ROOT . "/app/cron/cron.log");
echo "Fetch news script finished.\n";

?>
