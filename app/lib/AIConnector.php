<?php

namespace App\Lib;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Exception;

class AIConnector
{
    private Client $client;

    public function __construct()
    {
        $this->client = new Client([
            'timeout' => 60.0, // Default timeout for requests
        ]);
    }

    /**
     * Generates content using an AI model compatible with OpenAI's API.
     *
     * @param string $systemPrompt The system prompt for the AI.
     * @param string $userPrompt The user prompt for the AI.
     * @param array $config Configuration array for the specific AI task,
     *                      expected to contain 'api_key', 'api_url', and 'model'.
     * @return string|null The AI's response content, or null on failure.
     * @throws Exception If configuration is invalid.
     */
    public function generate(string $systemPrompt, string $userPrompt, array $config): ?string
    {
        if (empty($config['api_key']) || empty($config['api_url']) || empty($config['model'])) {
            error_log('AIConnector Error: Missing API key, URL, or model in configuration.');
            throw new Exception('AIConnector Error: Invalid configuration provided.');
        }

        $payload = [
            'model' => $config['model'],
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userPrompt]
            ],
            'temperature' => 0.7, // Common default, can be made configurable
        ];

        // Add 'response_format' if the model supports it (e.g., for JSON output)
        if (isset($config['response_format']) && $config['response_format'] === 'json_object') {
            $payload['response_format'] = ['type' => 'json_object'];
        }

        try {
            $response = $this->client->post($config['api_url'], [
                'headers' => [
                    'Authorization' => 'Bearer ' . $config['api_key'],
                    'Content-Type'  => 'application/json',
                ],
                'json' => $payload,
            ]);

            $body = json_decode((string) $response->getBody(), true);

            if (isset($body['choices'][0]['message']['content'])) {
                return $body['choices'][0]['message']['content'];
            } else {
                error_log('AIConnector Error: Unexpected API response structure. Response: ' . json_encode($body));
                return null;
            }
        } catch (RequestException $e) {
            $errorMessage = 'AIConnector HTTP Request Error: ' . $e->getMessage();
            if ($e->hasResponse()) {
                $errorMessage .= ' | Response: ' . (string) $e->getResponse()->getBody();
            }
            error_log($errorMessage);
            return null;
        } catch (Exception $e) {
            error_log('AIConnector General Error: ' . $e->getMessage());
            return null;
        }
    }
}
?>
