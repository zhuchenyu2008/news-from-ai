<?php
// news-from-ai/src/Config.php

namespace NewsFromAI;

// Logger class is expected to be in the same namespace and autoloadable or included.
// If not using an autoloader, ensure Logger.php is included before this class is used.
// require_once __DIR__ . '/Logger.php'; // Example if not using autoloader

class Config {
    private array $settings = [];
    // private Logger $logger; // Logger is static, no need for instance here

    /**
     * Config constructor.
     * @param string $filePath Path to the INI configuration file.
     * // Logger instance is no longer needed as Logger methods are static
     */
    public function __construct(string $filePath) {
        // $this->logger = $loggerInstance; // Removed

        if (!file_exists($filePath)) {
            Logger::critical("Config: Configuration file not found: {$filePath}");
            // throw new \RuntimeException("Configuration file not found: {$filePath}");
            return;
        }
        if (!is_readable($filePath)) {
            Logger::critical("Config: Configuration file not readable: {$filePath}");
            // throw new \RuntimeException("Configuration file not readable: {$filePath}");
            return;
        }

        $this->settings = parse_ini_file($filePath, true, INI_SCANNER_TYPED);

        if ($this->settings === false) {
            Logger::critical("Config: Failed to parse INI file: {$filePath}. Error: " . (error_get_last()['message'] ?? 'Unknown error'));
            $this->settings = []; // Reset to empty array on failure
            // throw new \RuntimeException("Failed to parse INI file: {$filePath}");
        } else {
            Logger::info("Config: Successfully loaded configuration from {$filePath}");
        }
    }

    /**
     * Get a configuration value.
     * Supports dot notation for nested keys (e.g., 'database.db_host').
     * @param string $key The configuration key.
     * @param mixed $default Default value to return if the key is not found.
     * @return mixed The configuration value or the default value.
     */
    public function get(string $key, $default = null) {
        $keys = explode('.', $key);
        $value = $this->settings;

        foreach ($keys as $k) {
            if (is_array($value) && array_key_exists($k, $value)) {
                $value = $value[$k];
            } else {
                // Log that a default value is being used, but not as an error, more like debug/info
                // $this->logger->debug("Config: Key '{$key}' (part '{$k}') not found, using default value.", ['default_used' => $default]);
                return $default;
            }
        }
        return $value;
    }

    /**
     * Get all settings.
     * @return array All configuration settings.
     */
    public function getAll(): array {
        return $this->settings;
    }

    /**
     * Checks if a configuration key exists.
     * Supports dot notation for nested keys.
     * @param string $key The configuration key.
     * @return bool True if the key exists, false otherwise.
     */
    public function has(string $key): bool {
        $keys = explode('.', $key);
        $value = $this->settings;

        foreach ($keys as $k) {
            if (is_array($value) && array_key_exists($k, $value)) {
                $value = $value[$k];
            } else {
                return false;
            }
        }
        return true;
    }
}
?>
