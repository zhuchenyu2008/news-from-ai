<?php

class AIHelper {
    private string $apiKey;
    private string $apiEndpoint;
    private string $modelName;

    public function __construct(string $apiKey, string $apiEndpoint, string $modelName) {
        $this->apiKey = $apiKey;
        $this->apiEndpoint = $apiEndpoint;
        $this->modelName = $modelName;

        if (empty(trim($this->modelName))) {
            // This error message is not directly user-facing via console but could be caught and logged by calling scripts.
            // For consistency with other new error messages, it can be in English or Chinese. Let's use English for internal logic.
            throw new \InvalidArgumentException("AI model name cannot be empty.");
        }
    }

    public function sendPrompt(array $messages): string|false {
        $ch = curl_init($this->apiEndpoint);

        if (!$ch) {
            error_log("Failed to initialize cURL session."); // This log is not directly user-facing
            return false;
        }

        $postData = [
            'model' => $this->modelName, // Use the dynamic model name
            'messages' => $messages,
            // Future: Consider adding other parameters like temperature, max_tokens here
            // 'temperature' => 0.7,
        ];

        $headers = [
            'Authorization: Bearer ' . $this->apiKey,
            'Content-Type: application/json',
        ];

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60); // 60 seconds timeout

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            error_log("cURL error: " . curl_error($ch));
            curl_close($ch);
            return false;
        }

        $httpStatusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpStatusCode >= 400) {
            error_log("HTTP error: " . $httpStatusCode . " - Response: " . $response);
            // Specific error handling based on status code
            if ($httpStatusCode === 401) {
                error_log("Authentication error: Invalid API key or token.");
            } elseif ($httpStatusCode === 429) {
                error_log("Rate limit exceeded. Please try again later.");
            }
            return false;
        }

        $responseData = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("JSON decode error: " . json_last_error_msg());
            return false;
        }

        if (isset($responseData['error'])) {
            error_log("API error: " . $responseData['error']['message']);
            return false;
        }

        if (isset($responseData['choices'][0]['message']['content'])) {
            return $responseData['choices'][0]['message']['content'];
        } else {
            error_log("Unexpected API response structure: 'choices[0].message.content' not found.");
            error_log("Full API response: " . $response);
            return false;
        }
    }
}
