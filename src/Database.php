<?php
// news-from-ai/src/Database.php

namespace NewsFromAI;

use PDO;
use PDOException;
use PDOStatement;

class Database {
    private static ?PDO $pdo = null;
    private static array $config = [];

    /**
     * 初始化数据库配置
     * @param array $config 数据库配置数组
     */
    public static function init(array $config): void {
        self::$config = $config;
    }

    /**
     * 获取PDO连接实例 (单例模式)
     * @return PDO|null PDO实例，如果连接失败则返回null
     */
    public static function getConnection(): ?PDO {
        if (self::$pdo === null) {
            if (empty(self::$config)) {
                // 通常应该通过Logger记录错误，这里暂时简单处理
                error_log("数据库配置未初始化。请先调用 Database::init()");
                return null;
            }

            $dsn = "mysql:host=" . self::$config['db_host'] . ";dbname=" . self::$config['db_name'] . ";charset=" . self::$config['db_charset'];
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];

            try {
                self::$pdo = new PDO($dsn, self::$config['db_user'], self::$config['db_password'], $options);
            } catch (PDOException $e) {
                // 同样，应该使用Logger
                error_log("数据库连接失败: " . $e->getMessage());
                // 可以抛出自定义异常或返回null，具体取决于错误处理策略
                // throw new \RuntimeException("数据库连接失败: " . $e->getMessage());
                return null;
            }
        }
        return self::$pdo;
    }

    /**
     * 执行查询并返回PDOStatement对象
     * @param string $sql SQL查询语句
     * @param array $params 参数数组
     * @return PDOStatement|false 执行失败返回false
     */
    public static function query(string $sql, array $params = []): PDOStatement|false {
        $conn = self::getConnection();
        if (!$conn) {
            return false;
        }
        try {
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            error_log("数据库查询错误: " . $e->getMessage() . " SQL: " . $sql . " Params: " . print_r($params, true));
            return false;
        }
    }

    /**
     * 执行查询并获取所有结果行
     * @param string $sql
     * @param array $params
     * @return array|false
     */
    public static function fetchAll(string $sql, array $params = []): array|false {
        $stmt = self::query($sql, $params);
        return $stmt ? $stmt->fetchAll() : false;
    }

    /**
     * 执行查询并获取单行结果
     * @param string $sql
     * @param array $params
     * @return mixed|false
     */
    public static function fetchOne(string $sql, array $params = []): mixed {
        $stmt = self::query($sql, $params);
        return $stmt ? $stmt->fetch() : false;
    }

    /**
     * 执行查询并获取单个值
     * @param string $sql
     * @param array $params
     * @return mixed|false
     */
    public static function fetchColumn(string $sql, array $params = []): mixed {
        $stmt = self::query($sql, $params);
        return $stmt ? $stmt->fetchColumn() : false;
    }

    /**
     * 执行INSERT, UPDATE, DELETE等操作，并返回影响的行数
     * @param string $sql
     * @param array $params
     * @return int|false 影响的行数，失败返回false
     */
    public static function execute(string $sql, array $params = []): int|false {
        $stmt = self::query($sql, $params);
        return $stmt ? $stmt->rowCount() : false;
    }

    /**
     * 获取最后插入的ID
     * @return string|false
     */
    public static function lastInsertId(): string|false {
        $conn = self::getConnection();
        return $conn ? $conn->lastInsertId() : false;
    }

    /**
     * 开始事务
     * @return bool
     */
    public static function beginTransaction(): bool {
        $conn = self::getConnection();
        return $conn ? $conn->beginTransaction() : false;
    }

    /**
     * 提交事务
     * @return bool
     */
    public static function commit(): bool {
        $conn = self::getConnection();
        return $conn ? $conn->commit() : false;
    }

    /**
     * 回滚事务
     * @return bool
     */
    public static function rollBack(): bool {
        $conn = self::getConnection();
        return $conn ? $conn->rollBack() : false;
    }

    /**
     * 检查是否在事务中
     * @return bool
     */
    public static function inTransaction(): bool {
        $conn = self::getConnection();
        return $conn ? $conn->inTransaction() : false;
    }
}
?>
