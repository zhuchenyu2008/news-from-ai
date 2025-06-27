<?php

namespace App\Lib;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use SimpleXMLElement;
use Exception;

class RssParser
{
    private Client $client;

    public function __construct()
    {
        $this->client = new Client([
            'timeout' => 15.0, // Timeout for fetching RSS feeds
            'headers' => [ // Set a common user agent
                'User-Agent' => 'NewsFromAI/1.0 (+https://yourprojectdomain.com/bot.html)'
            ]
        ]);
    }

    /**
     * Fetches and parses an RSS feed.
     *
     * @param string $url The URL of the RSS feed.
     * @return array An array of articles, each containing 'title', 'link', and 'description' or 'content'.
     *               Returns an empty array on failure or if no items are found.
     */
    public function parse(string $url): array
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            error_log("RssParser Error: Invalid URL provided: " . $url);
            return [];
        }

        try {
            $response = $this->client->get($url, [
                'http_errors' => true, // Ensure Guzzle throws exceptions for 4xx/5xx responses
            ]);

            $xmlString = (string) $response->getBody();

            if (empty($xmlString)) {
                error_log("RssParser Error: Empty response from URL: " . $url);
                return [];
            }

            // Suppress errors from simplexml_load_string for malformed XML, handle manually
            libxml_use_internal_errors(true);
            $xml = simplexml_load_string($xmlString);
            libxml_clear_errors();

            if ($xml === false) {
                $xmlErrors = [];
                foreach (libxml_get_errors() as $error) {
                    $xmlErrors[] = $error->message;
                }
                error_log("RssParser Error: Failed to parse XML from URL: " . $url . ". Errors: " . implode("; ", $xmlErrors));
                return [];
            }

            $articles = [];
            // Common RSS/Atom item tags
            $itemTags = ['item', 'entry']; // 'item' for RSS, 'entry' for Atom

            foreach ($itemTags as $tag) {
                if (isset($xml->channel->$tag) || isset($xml->$tag)) { // Check channel level or root level for items
                    $items = isset($xml->channel->$tag) ? $xml->channel->$tag : $xml->$tag;
                    foreach ($items as $item) {
                        $title = (string) ($item->title ?? '');
                        $link = '';
                        if (isset($item->link)) {
                            if (is_string($item->link)) {
                                $link = (string) $item->link;
                            } elseif (is_object($item->link) && isset($item->link['href'])) { // Atom links
                                $link = (string) $item->link['href'];
                            } elseif (is_array($item->link) && isset($item->link[0]['href'])){ // Multiple link tags in Atom
                                 $link = (string) $item->link[0]['href'];
                            }
                        }

                        // Content can be in 'description', 'content:encoded', or 'summary'
                        $content = (string) ($item->description ?? '');
                        if (empty($content) && isset($item->summary)) {
                            $content = (string) $item->summary; // Atom summary
                        }
                        if (empty($content) && isset($item->children('content', true)->encoded)) {
                             // Check for <content:encoded> which is common in RSS
                            $content = (string) $item->children('content', true)->encoded;
                        }

                        // Fallback for Atom content if others are empty
                        if (empty($content) && isset($item->content) && !is_array($item->content) && !is_object($item->content)) {
                             $content = (string) $item->content;
                        } elseif (empty($content) && isset($item->content) && is_object($item->content) && property_exists($item->content, '#text')) {
                            // Some Atom feeds might have content in a #text field of a content object
                            $content = (string) $item->content->{'#text'};
                        }


                        if ($title && $link) { // Only add if title and link are present
                            $articles[] = [
                                'title' => trim($title),
                                'link'  => trim($link),
                                'content' => trim(strip_tags($content)) // Basic sanitization, full HTML parsing might be needed
                            ];
                        }
                    }
                    if (!empty($articles)) break; // Found items, no need to check other tags
                }
            }

            if (empty($articles)) {
                 error_log("RssParser Info: No <item> or <entry> tags found or items were empty in feed: " . $url);
            }

            return $articles;

        } catch (RequestException $e) {
            $errorMessage = "RssParser HTTP Request Error for URL {$url}: " . $e->getMessage();
            if ($e->hasResponse()) {
                $errorMessage .= " | Status: " . $e->getResponse()->getStatusCode();
                $errorMessage .= " | Response: " . substr((string) $e->getResponse()->getBody(), 0, 200); // Log snippet of response
            }
            error_log($errorMessage);
            return [];
        } catch (Exception $e) {
            error_log("RssParser General Error for URL {$url}: " . $e->getMessage());
            return [];
        }
    }
}
?>
