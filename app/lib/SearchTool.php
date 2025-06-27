<?php

namespace App\Lib;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Exception;

class SearchTool
{
    private Client $client;
    private string $apiKey;
    private string $apiUrl;
    private string $cx; // Programmable Search Engine ID

    public function __construct()
    {
        // Ensure config.php is loaded. This might be better handled by a central bootstrap file.
        if (!defined('SEARCH_API_CONFIG')) {
            $configFile = __DIR__ . '/../../config.php';
            if (file_exists($configFile)) {
                require_once $configFile;
            } else {
                error_log("SearchTool Error: Configuration file not found or SEARCH_API_CONFIG not defined.");
                throw new Exception("SearchTool configuration is missing.");
            }
        }

        // Validate Google Custom Search specific config
        if (!is_array(SEARCH_API_CONFIG) ||
            empty(SEARCH_API_CONFIG['api_key']) ||
            empty(SEARCH_API_CONFIG['api_url']) ||
            empty(SEARCH_API_CONFIG['cx'])) {
            error_log('SearchTool Error: Invalid or missing SEARCH_API_CONFIG (api_key, api_url, cx for Google Search).');
            throw new Exception('SearchTool Error: Invalid configuration for Google Custom Search API.');
        }

        $this->apiKey = SEARCH_API_CONFIG['api_key'];
        $this->apiUrl = SEARCH_API_CONFIG['api_url'];
        $this->cx = SEARCH_API_CONFIG['cx'];

        $this->client = new Client([
            'timeout' => 20.0, // Timeout for search API requests
        ]);
    }

    /**
     * Performs a web search using the configured search API.
     *
     * @param string $query The search query.
     * @return array An array of search results, each containing 'url', 'title', and 'snippet'.
     *               Returns an empty array on failure.
     */
    public function search(string $query): array
    {
        if (empty(trim($query))) {
            error_log("SearchTool Error: Search query cannot be empty.");
            return [];
        }

        // This is a generic structure. Specific APIs will have different request formats.
        // For example, some might take the API key in a header, others as a query parameter.
        // This example assumes the API key is sent as a Bearer token and query as a 'q' parameter.
        // You MUST adjust this based on the actual search API provider's documentation.

        // Parameters for Google Custom Search JSON API
        $parameters = [
            'key' => $this->apiKey,
            'cx'  => $this->cx,
            'q'   => $query,
            'num' => 5 // Request 5 results. Google API allows 1-10.
                       // This could be made configurable if needed.
        ];

        // Google Custom Search API takes API key as a query parameter, not in headers.
        $headers = [
            'Accept' => 'application/json',
        ];

        // Example for an API that takes API key as a query param (e.g. Google Custom Search JSON API style)
        // unset($headers['Authorization']); // If key is not in header
        // $parameters['key'] = $this->apiKey; // Add API key to query parameters

        try {
            $response = $this->client->get($this->apiUrl, [
                'query'   => $parameters,
                'headers' => $headers,
                'http_errors' => true, // Guzzle will throw an exception for 4xx/5xx responses
            ]);

            $body = json_decode((string) $response->getBody(), true);

            // The structure of the response is highly dependent on the search API provider.
            // The following is a common pattern, but will likely need adjustment.
            // For example, results might be under 'items', 'results', 'webPages.value', etc.
            $results = [];
            if (isset($body['items']) && is_array($body['items'])) { // Google Custom Search like
                foreach ($body['items'] as $item) {
                    $results[] = [
                        'title'   => $item['title'] ?? 'N/A',
                        'link'    => $item['link'] ?? ($item['url'] ?? '#'), // Common variations
                        'snippet' => $item['snippet'] ?? ($item['description'] ?? 'N/A'), // Common variations
                    ];
                }
            } elseif (isset($body['webPages']['value']) && is_array($body['webPages']['value'])) { // Bing Search API like
                 foreach ($body['webPages']['value'] as $item) {
                    $results[] = [
                        'title'   => $item['name'] ?? 'N/A',
                        'link'    => $item['url'] ?? '#',
                        'snippet' => $item['snippet'] ?? 'N/A',
                    ];
                }
            } else if (isset($body['results']) && is_array($body['results'])) { // Other generic structure
                 foreach ($body['results'] as $item) {
                    $results[] = [
                        'title'   => $item['title'] ?? 'N/A',
                        'link'    => $item['url'] ?? ($item['link'] ?? '#'),
                        'snippet' => $item['snippet'] ?? ($item['description'] ?? 'N/A'),
                    ];
                }
            }
            else {
                error_log('SearchTool Error: Unexpected API response structure. Query: ' . $query . ' Response: ' . json_encode($body));
                return [];
            }

            // Transform results to a common format: 'url', 'title', 'summary'
            $formattedResults = [];
            foreach($results as $res) {
                $formattedResults[] = [
                    'url' => $res['link'],
                    'title' => $res['title'],
                    'summary' => $res['snippet']
                ];
            }

            return $formattedResults;

        } catch (RequestException $e) {
            $errorMessage = "SearchTool HTTP Request Error for query '{$query}': " . $e->getMessage();
            if ($e->hasResponse()) {
                 $errorMessage .= " | Status: " . $e->getResponse()->getStatusCode();
                 $errorMessage .= " | Response: " . substr((string) $e->getResponse()->getBody(), 0, 500);
            }
            error_log($errorMessage);
            return [];
        } catch (Exception $e) {
            error_log("SearchTool General Error for query '{$query}': " . $e->getMessage());
            return [];
        }
    }
}
?>
