<?php
class Database {
    private static $pdo = null;
    
    public static function getConnection() {
        if (self::$pdo === null) {
            // 数据库文件现在在admin目录下
            $dbFile = dirname(__DIR__) . '/admin/database.db';
            
            // 确保数据库文件所在目录可写
            $dbDir = dirname($dbFile);
            if (!is_dir($dbDir)) {
                mkdir($dbDir, 0755, true);
            }
            
            try {
                self::$pdo = new PDO("sqlite:" . $dbFile);
                self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                self::$pdo->exec("PRAGMA journal_mode = WAL;");
                self::$pdo->exec("PRAGMA synchronous = NORMAL;");
                self::$pdo->exec("PRAGMA cache_size = 10000;");
            } catch (PDOException $e) {
                die("数据库连接失败: " . $e->getMessage());
            }
        }
        return self::$pdo;
    }
    
    public static function initDatabase() {
        $sqlFile = dirname(__DIR__) . '/admin/database.sql';
        if (!file_exists($sqlFile)) {
            return false;
        }
        
        $sql = file_get_contents($sqlFile);
        $pdo = self::getConnection();
        
        try {
            $pdo->exec($sql);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
}
?>