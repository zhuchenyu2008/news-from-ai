<?php

// Define project root for easier includes if this file is moved or document root is different
define('PROJECT_ROOT_API', dirname(__DIR__, 2));

// 1. Include config.php and Database.php
// It's crucial that config.php is loaded first as it defines constants used by Database.php
// and potentially sets up the autoloader.
$configPath = PROJECT_ROOT_API . '/config.php';
$dbLibPath = PROJECT_ROOT_API . '/app/lib/Database.php'; // Direct include if not using autoloader via config

if (file_exists($configPath)) {
    require_once $configPath;
} else {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Configuration file missing.']);
    exit;
}

// If Database class is not autoloaded via composer (e.g. by config.php loading vendor/autoload.php),
// then include it manually.
if (!class_exists('App\\Lib\\Database')) {
    if (file_exists($dbLibPath)) {
        require_once $dbLibPath;
    } else {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Database library file missing.']);
        exit;
    }
}

use App\Lib\Database;

// 2. Connect to the database
$database = new Database();

if (!$database->isConnected()) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Failed to connect to the database. Check server logs.']);
    exit;
}

// 3. Query news_items table
$newsItems = [];
try {
    $sql = "SELECT id, format, content_markdown, source_url, sources_json, created_at
            FROM news_items
            ORDER BY created_at DESC
            LIMIT 20";
    $stmt = $database->query($sql);

    if ($stmt) {
        $newsItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        // query() method in Database class already logs errors.
        // We send a generic error to client.
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Error fetching news items.']);
        exit;
    }

} catch (PDOException $e) {
    // Log the detailed error on the server
    error_log("API Error: get_news.php - Database query failed: " . $e->getMessage());

    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'An error occurred while retrieving news.']);
    exit;
} catch (Exception $e) {
    error_log("API Error: get_news.php - General error: " . $e->getMessage());

    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'An unexpected error occurred.']);
    exit;
}

// 4. Set HTTP response header
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *'); // Basic CORS header, adjust for production

// 5. Output results as JSON
echo json_encode($newsItems);

?>
