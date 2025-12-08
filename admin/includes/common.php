<?php
// admin/includes/common.php
// 管理员后台公共文件
define('IN_ADMIN', true);
date_default_timezone_set('Asia/Shanghai');

session_start();
require_once __DIR__ . '/../../config/db.php';

// 简单防护：虽然不验证身份，但可以防止直接访问功能文件
// 真正的权限控制将由后续的Nginx IP限制实现
if (!defined('IN_ADMIN')) {
    die('非法访问');
}

// 获取数据库连接
function getDB() {
    return Database::getConnection();
}

// 安全跳转函数
function redirect($url) {
    header("Location: $url");
    exit;
}

// 检查数据库是否已初始化
function isDatabaseInitialized() {
    try {
        $pdo = getDB();
        // 尝试查询一个应该存在的表
        $result = $pdo->query("SELECT 1 FROM sqlite_master WHERE type='table' AND name='users'");
        return $result->fetch() !== false;
    } catch (Exception $e) {
        return false;
    }
}
?>