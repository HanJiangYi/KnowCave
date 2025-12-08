<?php
require_once __DIR__ . '/../functions/auth.php';
require_once __DIR__ . '/../config/db.php';

// 如果已登录，重定向到仪表板
if (Auth::isLoggedIn()) {
    header("Location: dashboard.php");
    exit;
}

$error = '';

// 获取普通用户列表（不包括管理员）
try {
    $pdo = Database::getConnection();
    $stmt = $pdo->prepare("SELECT id, username FROM users WHERE is_admin = 0 AND is_active = 1 ORDER BY username");
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $users = [];
    $error = '数据库连接失败，请联系管理员';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = '请选择用户名并输入密码';
    } elseif (Auth::login($username, $password)) {
        header("Location: dashboard.php");
        exit;
    } else {
        $error = '密码错误或用户不存在';
    }
}

$pageTitle = '登录 - 知识洞天 KnowCave';
$bodyClass = 'login-page';

include __DIR__ . '/../includes/header.php';
?>

<div class="container">
    <div class="row justify-content-center mt-5">
        <div class="col-md-6 col-lg-5">
            <div class="card shadow">
                <div class="card-header bg-primary text-white text-center">
                    <h4 class="mb-0">知识洞天 KnowCave</h4>
                    <small>请登录以继续</small>
                </div>
                <div class="card-body p-4">
                    <?php if ($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show">
                            <?php echo $error; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (empty($users)): ?>
                        <div class="alert alert-warning text-center">
                            <h5>⚠️ 暂无可用用户</h5>
                            <p class="mb-0">请联系管理员添加用户账户</p>
                        </div>
                    <?php else: ?>
                        <form method="POST" action="">
                            <div class="mb-3">
                                <label for="username" class="form-label">用户名</label>
                                <select class="form-select" id="username" name="username" required>
                                    <option value="">请选择用户名</option>
                                    <?php foreach ($users as $user): ?>
                                    <option value="<?php echo htmlspecialchars($user['username']); ?>">
                                        <?php echo htmlspecialchars($user['username']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="password" class="form-label">密码</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary btn-lg">登录</button>
                            </div>
                        </form>
                    <?php endif; ?>
                    
                    <div class="mt-4 text-center">
                        <small class="text-muted">
                            <strong>注意：</strong>本系统不允许用户自主注册。<br>
                            如需账户，请联系管理员添加。
                        </small>
                    </div>
                </div>
                <div class="card-footer text-center text-muted">
                    <small>© 2025 知识洞天 KnowCave - 专为学习设计</small>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // 页面加载完成后设置焦点
    document.addEventListener('DOMContentLoaded', function() {
        const usernameSelect = document.getElementById('username');
        if (usernameSelect) {
            usernameSelect.focus();
        }
        
        // 表单验证
        const form = document.querySelector('form');
        if (form) {
            form.addEventListener('submit', function(e) {
                const username = document.getElementById('username');
                const password = document.getElementById('password');
                
                if (!username.value) {
                    e.preventDefault();
                    alert('请选择用户名');
                    username.focus();
                } else if (!password.value) {
                    e.preventDefault();
                    alert('请输入密码');
                    password.focus();
                }
            });
        }
    });
</script>

<?php
include __DIR__ . '/../includes/footer.php';
?>