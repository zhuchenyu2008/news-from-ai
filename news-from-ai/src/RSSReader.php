<?php
// news-from-ai/src/RSSReader.php

namespace NewsFromAI;

use SimpleXMLElement;
use Exception;

class RSSReader {
    // private Logger $logger; // Logger is static

    public function __construct(/* Logger $logger (removed) */) {
        // $this->logger = $logger; // Removed
    }

    /**
     * 获取并解析RSS源
     * @param string $feedUrl RSS源的URL
     * @param int $maxItems 要获取的最大条目数
     * @return array|null 解析后的RSS条目数组，失败则返回null
     */
    public function fetchFeed(string $feedUrl, int $maxItems = 5): ?array {
        Logger::debug("RSSReader: Attempting to fetch RSS feed", ['url' => $feedUrl, 'max_items' => $maxItems]);

        // 使用 cURL 获取内容，以便更好地控制超时和错误处理
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $feedUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // 跟随重定向
        curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15); // 总超时15秒
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5); // 连接超时5秒
        curl_setopt($ch, CURLOPT_USERAGENT, 'NewsFromAI_RSS_Reader/1.0'); // 设置 User-Agent
        // 禁用SSL证书验证（在某些环境下可能需要，但有安全风险，生产环境应确保证书有效）
        // curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        // curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        $xmlContent = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            Logger::error("RSSReader: cURL error while fetching feed '{$feedUrl}'", ['error' => $curlError]);
            return null;
        }

        if ($httpCode !== 200) {
            Logger::error("RSSReader: Failed to fetch RSS feed '{$feedUrl}'. HTTP status code: {$httpCode}", ['content_preview' => substr($xmlContent ?: '', 0, 200)]);
            return null;
        }

        if (empty($xmlContent)) {
            Logger::error("RSSReader: Fetched empty content from '{$feedUrl}'.");
            return null;
        }

        // 清理XML内容，移除无效字符，特别是XML声明前的BOM等
        // $xmlContent = preg_replace('/[^\x{0009}\x{000a}\x{000d}\x{0020}-\x{D7FF}\x{E000}-\x{FFFD}]+/u', '', $xmlContent);
        // 更安全的做法是检查内容是否以 <?xml 开头
        if (strpos(ltrim($xmlContent), '<?xml') !== 0) {
             // 尝试查找 <?xml 声明
            $xmlDeclPos = strpos($xmlContent, '<?xml');
            if ($xmlDeclPos > 0) {
                Logger::warning("RSSReader: XML content from '{$feedUrl}' has leading characters. Attempting to strip them.");
                $xmlContent = substr($xmlContent, $xmlDeclPos);
            } else {
                Logger::warning("RSSReader: XML content from '{$feedUrl}' does not start with <?xml. Parsing might fail.", ['preview' => substr($xmlContent, 0, 100)]);
            }
        }


        try {
            // 禁用外部实体加载，防止XXE攻击
            $previousLibxmlEntityLoaderState = libxml_disable_entity_loader(true);
            $xml = new SimpleXMLElement($xmlContent, LIBXML_NOCDATA | LIBXML_NOWARNING | LIBXML_NOERROR);
            libxml_disable_entity_loader($previousLibxmlEntityLoaderState); // 恢复之前的设置
            libxml_clear_errors(); // 清除libxml的错误，我们自己处理

        } catch (Exception $e) {
            Logger::error("RSSReader: Failed to parse XML from '{$feedUrl}'", [
                'error' => $e->getMessage(),
                'xml_preview' => substr($xmlContent, 0, 500) // 记录部分XML内容用于调试
            ]);
            if (isset($previousLibxmlEntityLoaderState)) { // 确保恢复
                 libxml_disable_entity_loader($previousLibxmlEntityLoaderState);
            }
            return null;
        }

        $items = [];
        $count = 0;

        // RSS 2.0 items are in <channel><item>
        if (isset($xml->channel->item)) {
            foreach ($xml->channel->item as $item) {
                if ($count >= $maxItems) break;
                $items[] = self::parseItem($item, $feedUrl); // Changed to static call
                $count++;
            }
        }
        // Atom 1.0 items are in <feed><entry>
        elseif (isset($xml->entry)) {
            foreach ($xml->entry as $entry) {
                if ($count >= $maxItems) break;
                $items[] = self::parseEntry($entry, $feedUrl); // Changed to static call
                $count++;
            }
        } else {
            Logger::warning("RSSReader: Could not find <item> or <entry> tags in the feed.", ['url' => $feedUrl]);
            return null;
        }

        // 获取Feed的元数据
        $feedMetadata = self::parseFeedMetadata($xml); // Changed to static call

        Logger::info("RSSReader: Successfully fetched and parsed " . count($items) . " items from '{$feedUrl}'.", ['feed_title' => $feedMetadata['title'] ?? 'N/A']);
        return ['metadata' => $feedMetadata, 'items' => $items];
    }

    /**
     * 解析单个 <item> (RSS) 或 <entry> (Atom) 元素
     * @param SimpleXMLElement $xmlItem
     * @param string $feedUrl (用于日志或回退)
     * @return array
     */
    private static function parseItem(SimpleXMLElement $xmlItem, string $feedUrl): array { // Changed to static
        // Atom通常使用<content>，RSS通常使用<description>，有时有<content:encoded>
        $description = (string)($xmlItem->description ?? '');
        $contentEncoded = $xmlItem->children('content', true)->encoded; // for <content:encoded>
        $content = (string)($contentEncoded ?? $description); // 优先使用 content:encoded

        // 获取guid，对于RSS，guid可能是isPermaLink属性
        $guid = (string)($xmlItem->guid ?? '');
        if ($xmlItem->guid && isset($xmlItem->guid['isPermaLink']) && (string)$xmlItem->guid['isPermaLink'] == 'false' && !empty($guid)) {
             // guid不是永久链接，则link字段更可靠作为URL
        } elseif (empty($guid) && isset($xmlItem->link)) {
             $guid = (string)($xmlItem->link ?? ''); // 如果guid为空，尝试使用link作为唯一标识
        }

        // 处理发布日期，尝试多种格式
        $pubDate = null;
        if (isset($xmlItem->pubDate)) {
            $pubDate = self::formatDate((string)$xmlItem->pubDate);
        } elseif (isset($xmlItem->children('dc', true)->date)) { // Dublin Core date
            $pubDate = self::formatDate((string)$xmlItem->children('dc', true)->date);
        } elseif (isset($xmlItem->updated)) { // Atom <updated>
             $pubDate = self::formatDate((string)$xmlItem->updated);
        } elseif (isset($xmlItem->published)) { // Atom <published>
             $pubDate = self::formatDate((string)$xmlItem->published);
        }


        return [
            'title' => (string)($xmlItem->title ?? ''),
            'link' => (string)($xmlItem->link['href'] ?? $xmlItem->link ?? ''), // Atom link can have href attribute
            'description' => strip_tags($description), // 通常是简短描述
            'content' => $content, // 可能包含HTML
            'pubDate_raw' => (string)($xmlItem->pubDate ?? $xmlItem->updated ?? $xmlItem->published ?? ''),
            'pubDate' => $pubDate,
            'guid' => $guid, // 唯一标识符
            'categories' => isset($xmlItem->category) ? (array)$xmlItem->category : [],
            'source_feed_url' => $feedUrl
        ];
    }

    /**
     * 解析 Atom <entry> 元素 (与parseItem类似，但字段名不同)
     */
    private static function parseEntry(SimpleXMLElement $xmlEntry, string $feedUrl): array { // Changed to static
        $title = (string)($xmlEntry->title ?? '');

        // Atom <link> может быть несколько, ищем rel="alternate" или без rel
        $link = '';
        if (isset($xmlEntry->link)) {
            foreach ($xmlEntry->link as $l) {
                if (isset($l['rel']) && $l['rel'] == 'alternate') {
                    $link = (string)$l['href'];
                    break;
                }
                if (!isset($l['rel']) && isset($l['href'])) { // No rel, take first href
                    $link = (string)$l['href'];
                    // break; // continue searching for alternate if available
                }
            }
            if(empty($link) && isset($xmlEntry->link[0]['href'])) { // fallback to first link if no alternate found
                $link = (string)$xmlEntry->link[0]['href'];
            }
        }

        $content = (string)($xmlEntry->content ?? $xmlEntry->summary ?? ''); // Atom <content> or <summary>
        $description = strip_tags((string)($xmlEntry->summary ?? $xmlEntry->content ?? '')); // Atom <summary> or <content> (stripped)

        $pubDate = null;
        if (isset($xmlEntry->updated)) {
            $pubDate = self::formatDate((string)$xmlEntry->updated);
        } elseif (isset($xmlEntry->published)) {
            $pubDate = self::formatDate((string)$xmlEntry->published);
        }

        $guid = (string)($xmlEntry->id ?? $link); // Atom <id> is usually the GUID

        $categories = [];
        if (isset($xmlEntry->category)) {
            foreach ($xmlEntry->category as $cat) {
                $categories[] = (string)($cat['term'] ?? '');
            }
        }

        return [
            'title' => $title,
            'link' => $link,
            'description' => $description,
            'content' => $content,
            'pubDate_raw' => (string)($xmlEntry->updated ?? $xmlEntry->published ?? ''),
            'pubDate' => $pubDate,
            'guid' => $guid,
            'categories' => $categories,
            'source_feed_url' => $feedUrl
        ];
    }

    /**
     * 尝试将日期字符串转换为 Y-m-d H:i:s 格式
     * @param string $dateString
     * @return string|null
     */
    private static function formatDate(string $dateString): ?string { // Changed to static
        if (empty($dateString)) return null;
        try {
            // 尝试多种常见格式
            $formats = [
                \DateTimeInterface::RFC2822, // Standard RSS pubDate format e.g. "Mon, 15 Aug 2005 15:52:01 +0000"
                \DateTimeInterface::ATOM,    // Standard Atom updated/published format e.g. "2003-12-13T18:30:02Z"
                'Y-m-d\TH:i:sP',
                'Y-m-d H:i:s',
                'D, d M Y H:i:s O', // RFC822/RFC1123
            ];
            foreach ($formats as $format) {
                $date = \DateTime::createFromFormat($format, $dateString);
                if ($date instanceof \DateTime) {
                    return $date->format('Y-m-d H:i:s');
                }
            }
            // 如果标准格式都失败，尝试用strtotime（容错性更强，但可能不精确）
            $timestamp = strtotime($dateString);
            if ($timestamp !== false) {
                return date('Y-m-d H:i:s', $timestamp);
            }
        } catch (Exception $e) {
            Logger::debug("RSSReader: Could not parse date string '{$dateString}'", ['error' => $e->getMessage()]);
        }
        return null;
    }

    /**
     * 解析Feed的元数据，如标题、描述等
     * @param SimpleXMLElement $xml
     * @return array
     */
    private static function parseFeedMetadata(SimpleXMLElement $xml): array { // Changed to static
        $metadata = [
            'title' => null,
            'link' => null,
            'description' => null,
        ];

        if (isset($xml->channel)) { // RSS
            $metadata['title'] = (string)($xml->channel->title ?? '');
            $metadata['link'] = (string)($xml->channel->link ?? '');
            $metadata['description'] = (string)($xml->channel->description ?? '');
        } elseif (isset($xml->title)) { // Atom (feed level)
            $metadata['title'] = (string)($xml->title ?? '');
            if (isset($xml->link)) {
                 foreach ($xml->link as $l) {
                    if (isset($l['rel']) && $l['rel'] == 'alternate') {
                        $metadata['link'] = (string)$l['href'];
                        break;
                    }
                    if (!isset($l['rel']) && isset($l['href'])) { // No rel, take first href
                        $metadata['link'] = (string)$l['href'];
                    }
                }
                 if(empty($metadata['link']) && isset($xml->link[0]['href'])) {
                    $metadata['link'] = (string)$xml->link[0]['href'];
                }
            }
            $metadata['description'] = (string)($xml->subtitle ?? ''); // Atom uses <subtitle> for description
        }
        return $metadata;
    }
}
?>
