<?php
// admin/install.php
//define('IN_ADMIN', true);
$isAdmin = true;
require_once 'includes/common.php';

$step = $_GET['step'] ?? '1';
$message = '';
$error = '';

// 处理安装请求
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['install'])) {
    try {
        $pdo = getDB();
        
        // 1. 初始化数据库
        if (Database::initDatabase()) {
            // 2. 创建初始管理员账户
            $username = trim($_POST['admin_username']);
            $password = $_POST['admin_password'];
            $confirm = $_POST['admin_confirm'];
            
            if (empty($username) || empty($password)) {
                throw new Exception("用户名和密码不能为空");
            }
            
            if ($password !== $confirm) {
                throw new Exception("两次输入的密码不一致");
            }
            
            if (strlen($password) < 6) {
                throw new Exception("密码长度至少6位");
            }
            
            $passwordHash = password_hash($password, PASSWORD_BCRYPT);
            
            $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, is_admin) VALUES (?, ?, 1)");
            $stmt->execute([$username, $passwordHash]);
            
            $message = "✅ 系统安装成功！<br>
                       • 数据库表已创建<br>
                       • 管理员账户 <strong>{$username}</strong> 已创建<br>
                       <div class='alert alert-info mt-3'>
                           <strong>重要提示：</strong><br>
                           1. 请立即记录此管理员账户信息<br>
                           2. 安装完成后，建议删除或重命名本安装文件<br>
                           3. 后续请通过Nginx配置IP白名单限制访问
                       </div>";
        } else {
            throw new Exception("数据库初始化失败");
        }
        
    } catch (Exception $e) {
        $error = "安装失败: " . $e->getMessage();
    }
}

$pageTitle = '初始安装 - 管理员后台';
include __DIR__ . '/../includes/header.php';
?>

<div class="row">
    <div class="col-md-8 offset-md-2">
        <h4 class="mb-4">🚀 系统初始安装</h4>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo $message; ?></div>
            <div class="text-center mt-4">
                <a href="?action=main" class="btn btn-success me-2">返回主菜单</a>
                <a href="?action=users" class="btn btn-primary">进入用户管理</a>
            </div>
        <?php else: ?>
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    安装说明
                </div>
                <div class="card-body">
                    <p>此操作将：</p>
                    <ol>
                        <li>创建所有必要的数据库表（users, question_banks, questions, answer_records）</li>
                        <li>创建初始管理员账户（用于登录答题系统前台）</li>
                        <li><strong class="text-danger">注意：如果数据库已存在，部分表可能会被覆盖！</strong></li>
                    </ol>
                </div>
            </div>
            
            <form method="POST" action="">
                <div class="card">
                    <div class="card-header bg-warning">
                        创建初始管理员账户
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="admin_username" class="form-label">管理员用户名 *</label>
                            <input type="text" class="form-control" id="admin_username" name="admin_username" 
                                   value="admin" required>
                            <div class="form-text">用于登录答题系统前台的用户名</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="admin_password" class="form-label">密码 *</label>
                            <input type="password" class="form-control" id="admin_password" name="admin_password" 
                                   required minlength="6">
                        </div>
                        
                        <div class="mb-3">
                            <label for="admin_confirm" class="form-label">确认密码 *</label>
                            <input type="password" class="form-control" id="admin_confirm" name="admin_confirm" 
                                   required minlength="6">
                        </div>
                        
                        <div class="alert alert-info">
                            <small>
                                <strong>安全提示：</strong><br>
                                1. 此账户将拥有系统前台的管理员权限<br>
                                2. 后续可在"用户维护"中创建更多账户<br>
                                3. 请勿使用过于简单的密码
                            </small>
                        </div>
                    </div>
                </div>
                
                <div class="mt-4 text-center">
                    <button type="submit" name="install" value="1" class="btn btn-danger btn-lg px-5"
                            onclick="return confirmAction('确定要初始化数据库并创建管理员账户吗？此操作可能无法撤销！')">
                        🚀 开始安装系统
                    </button>
                    <a href="?action=main" class="btn btn-secondary ms-3">取消</a>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>