<?php
// news-from-ai - 通用函数库

/**
 * 加载项目配置
 * @return array 配置数组
 */
function load_config(): array {
    static $config = null;
    if ($config === null) {
        $configPath = dirname(__DIR__, 2) . '/config/config.php'; // 项目根目录/config/config.php
        if (!file_exists($configPath)) {
            log_message('error', "配置文件未找到: {$configPath}");
            throw new RuntimeException("配置文件未找到: {$configPath}");
        }
        $config = require $configPath;
    }
    return $config;
}

/**
 * 获取数据库连接 (MySQLi)
 * @return mysqli 数据库连接对象
 * @throws RuntimeException 如果连接失败
 */
function get_db_connection(): mysqli {
    static $mysqli = null;
    if ($mysqli === null || $mysqli->connect_errno) {
        $config = load_config();
        $dbConfig = $config['db'];

        $mysqli = new mysqli($dbConfig['host'], $dbConfig['username'], $dbConfig['password'], $dbConfig['dbname']);

        if ($mysqli->connect_error) {
            log_message('error', "数据库连接失败: " . $mysqli->connect_error);
            throw new RuntimeException("数据库连接失败: " . $mysqli->connect_error);
        }

        if (!$mysqli->set_charset($dbConfig['charset'])) {
            log_message('error', "设置数据库字符集失败: " . $mysqli->error);
            throw new RuntimeException("设置数据库字符集失败: " . $mysqli->error);
        }
    }
    return $mysqli;
}

/**
 * 记录日志消息
 * @param string $level 日志级别 (e.g., 'info', 'error', 'debug')
 * @param string $message 日志消息
 * @param array $context 附加信息 (可选)
 */
function log_message(string $level, string $message, array $context = []): void {
    try {
        $config = load_config();
        $logConfig = $config['logging'];
    } catch (RuntimeException $e) {
        // 如果配置加载失败，则无法获取日志路径，直接输出到 stderr
        error_log(sprintf("[%s] [%s] %s %s\n", date('Y-m-d H:i:s'), strtoupper($level), $message, $context ? json_encode($context) : ''));
        return;
    }

    $logFilePath = $logConfig['file_path'];
    $logLevelConfig = strtoupper($logConfig['level']);

    $levelMap = ['DEBUG' => 1, 'INFO' => 2, 'WARNING' => 3, 'ERROR' => 4];
    $currentLevelNum = $levelMap[strtoupper($level)] ?? 0;
    $configLevelNum = $levelMap[$logLevelConfig] ?? 1; // Default to DEBUG if not set

    if ($currentLevelNum < $configLevelNum) {
        return; // 低于配置的日志级别，不记录
    }

    $formattedMessage = sprintf("[%s] [%s] %s %s\n",
        date('Y-m-d H:i:s'),
        strtoupper($level),
        $message,
        $context ? json_encode($context, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) : ''
    );

    // 确保日志目录存在
    $logDir = dirname($logFilePath);
    if (!is_dir($logDir)) {
        if (!mkdir($logDir, 0775, true) && !is_dir($logDir)) {
            error_log("无法创建日志目录: {$logDir}");
            return;
        }
    }

    file_put_contents($logFilePath, $formattedMessage, FILE_APPEND | LOCK_EX);
}

/**
 * 执行参数化的SQL查询 (SELECT) - 返回所有行
 * @param string $sql SQL查询语句，使用 ? 作为占位符
 * @param array $params 参数数组，类型和值交替 (e.g., ['s', $username, 'i', $id])
 * @return array|null 查询结果数组，或在失败时返回null
 */
function db_query_select(string $sql, array $params = []): ?array {
    $mysqli = get_db_connection();
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        log_message('error', "数据库查询准备失败 (SELECT): " . $mysqli->error, ['sql' => $sql]);
        return null;
    }

    if (!empty($params)) {
        $types = array_shift($params); // 第一个元素是类型字符串，如 "si"
        if (!empty($params)) { // 确保在调用 bind_param 之前 $params 不为空
             $stmt->bind_param($types, ...$params);
        }
    }

    if (!$stmt->execute()) {
        log_message('error', "数据库查询执行失败 (SELECT): " . $stmt->error, ['sql' => $sql]);
        $stmt->close();
        return null;
    }

    $result = $stmt->get_result();
    $data = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $data;
}

/**
 * 执行参数化的SQL查询 (INSERT, UPDATE, DELETE) - 返回影响的行数或最后插入的ID
 * @param string $sql SQL查询语句，使用 ? 作为占位符
 * @param array $params 参数数组，类型和值交替 (e.g., ['s', $username, 'i', $id])
 * @param bool $returnLastInsertId 如果为true且是INSERT操作, 返回最后插入的ID，否则返回影响的行数
 * @return int|string|false 影响的行数，或最后插入的ID，或在失败时返回false
 */
function db_execute_query(string $sql, array $params = [], bool $returnLastInsertId = false) {
    $mysqli = get_db_connection();
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        log_message('error', "数据库查询准备失败 (EXECUTE): " . $mysqli->error, ['sql' => $sql]);
        return false;
    }

    if (!empty($params)) {
        $types = array_shift($params); // 第一个元素是类型字符串
         if (!empty($params)) { // 确保在调用 bind_param 之前 $params 不为空
            $stmt->bind_param($types, ...$params);
        }
    }

    if (!$stmt->execute()) {
        log_message('error', "数据库查询执行失败 (EXECUTE): " . $stmt->error, ['sql' => $sql, 'params' => $params]);
        $stmt->close();
        return false;
    }

    if ($returnLastInsertId) {
        $lastId = $mysqli->insert_id;
        $stmt->close();
        return $lastId;
    } else {
        $affectedRows = $stmt->affected_rows;
        $stmt->close();
        return $affectedRows;
    }
}

?>
