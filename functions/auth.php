<?php
// functions/auth.php
date_default_timezone_set('Asia/Shanghai');

session_start();
require_once __DIR__ . '/../config/db.php';

class Auth {
    public static function login($username, $password) {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT id, username, password_hash, is_admin, is_active FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && $user['is_active'] && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['is_admin'] = $user['is_admin'];
            
            // 更新最后登录时间
            $stmt = $pdo->prepare("UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->execute([$user['id']]);
            
            return true;
        }
        return false;
    }
    
    public static function logout() {
        // 清除所有会话变量
        $_SESSION = array();
        
        // 删除会话cookie
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        
        // 销毁会话
        session_destroy();
    }
    
    public static function isLoggedIn() {
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }
    
    public static function getUser() {
        if (!self::isLoggedIn()) return null;
        
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT id, username, is_admin, created_at, last_login FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public static function requireLogin() {
        if (!self::isLoggedIn()) {
            header("Location: ../pages/login.php");
            exit;
        }
    }
    
    public static function requireAdmin() {
        if (!self::isLoggedIn() || !$_SESSION['is_admin']) {
            header("Location: ../pages/login.php");
            exit;
        }
    }
}
?>