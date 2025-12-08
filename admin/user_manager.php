<?php
// admin/user_manager.php
//define('IN_ADMIN', true);
$isAdmin = true;
require_once 'includes/common.php';

if (!isDatabaseInitialized()) {
    echo '<div class="alert alert-danger">请先执行初始安装！</div>';
    exit;
}

$pdo = getDB();
$action = $_GET['user_action'] ?? 'list';
$message = '';
$error = '';

// 处理用户操作
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['add_user'])) {
            // 添加用户
            $username = trim($_POST['username']);
            $password = $_POST['password'];
            $is_admin = isset($_POST['is_admin']) ? 1 : 0;
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            
            // 检查用户名是否存在
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->fetch()) {
                throw new Exception("用户名已存在");
            }
            
            $passwordHash = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, is_admin, is_active) VALUES (?, ?, ?, ?)");
            $stmt->execute([$username, $passwordHash, $is_admin, $is_active]);
            
            $message = "用户 {$username} 添加成功";
            
        } elseif (isset($_POST['update_user'])) {
            // 更新用户
            $user_id = $_POST['user_id'];
            $is_admin = isset($_POST['is_admin']) ? 1 : 0;
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            
            $stmt = $pdo->prepare("UPDATE users SET is_admin = ?, is_active = ? WHERE id = ?");
            $stmt->execute([$is_admin, $is_active, $user_id]);
            
            $message = "用户信息更新成功";
            
            // 如果需要修改密码
            if (!empty($_POST['new_password'])) {
                $newPassword = $_POST['new_password'];
                if (strlen($newPassword) >= 6) {
                    $newHash = password_hash($newPassword, PASSWORD_BCRYPT);
                    $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                    $stmt->execute([$newHash, $user_id]);
                    $message .= "，密码已更新";
                }
            }
        }
    } catch (Exception $e) {
        $error = "操作失败: " . $e->getMessage();
    }
}

// 获取用户列表
$stmt = $pdo->query("SELECT id, username, is_admin, is_active, created_at FROM users ORDER BY id");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = '用户维护 - 管理员后台';
include __DIR__ . '/../includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4>👥 用户维护</h4>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
                ＋ 添加新用户
            </button>
        </div>
        
        <?php if ($message): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>用户名</th>
                                <th>角色</th>
                                <th>状态</th>
                                <th>创建时间</th>
                                <th>操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($users)): ?>
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">暂无用户数据</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($users as $user): ?>
                                <tr>
                                    <td><?php echo $user['id']; ?></td>
                                    <td>
                                        <?php echo htmlspecialchars($user['username']); ?>
                                        <?php if ($user['is_admin']): ?>
                                            <span class="badge bg-primary ms-1">管理员</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php echo $user['is_admin'] ? '管理员' : '普通用户'; ?>
                                    </td>
                                    <td>
                                        <?php if ($user['is_active']): ?>
                                            <span class="badge bg-success">正常</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">停用</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo $user['created_at']; ?></td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-outline-primary" 
                                                data-bs-toggle="modal" data-bs-target="#editUserModal"
                                                onclick="loadUserData(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>', 
                                                         <?php echo $user['is_admin']; ?>, <?php echo $user['is_active']; ?>)">
                                            编辑
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <div class="mt-3 text-muted">
            <small>提示：用户停用后将无法登录系统，但历史答题记录会被保留。</small>
        </div>
    </div>
</div>

<!-- 添加用户模态框 -->
<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="">
                <div class="modal-header">
                    <h5 class="modal-title">添加新用户</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">用户名 *</label>
                        <input type="text" class="form-control" name="username" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">密码 *</label>
                        <input type="password" class="form-control" name="password" required minlength="6">
                    </div>
                    <div class="row mb-3">
                        <div class="col">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_admin" value="1" id="addIsAdmin">
                                <label class="form-check-label" for="addIsAdmin">
                                    设为管理员
                                </label>
                            </div>
                        </div>
                        <div class="col">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_active" value="1" id="addIsActive" checked>
                                <label class="form-check-label" for="addIsActive">
                                    启用账户
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                    <button type="submit" name="add_user" value="1" class="btn btn-primary">添加用户</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- 编辑用户模态框 -->
<div class="modal fade" id="editUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="">
                <input type="hidden" name="user_id" id="editUserId">
                <div class="modal-header">
                    <h5 class="modal-title">编辑用户</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">用户名</label>
                        <input type="text" class="form-control" id="editUsername" readonly style="background-color:#f8f9fa;">
                        <div class="form-text">用户名创建后不可修改</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">新密码（留空不修改）</label>
                        <input type="password" class="form-control" name="new_password" minlength="6">
                    </div>
                    <div class="row mb-3">
                        <div class="col">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_admin" value="1" id="editIsAdmin">
                                <label class="form-check-label" for="editIsAdmin">
                                    管理员权限
                                </label>
                            </div>
                        </div>
                        <div class="col">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_active" value="1" id="editIsActive">
                                <label class="form-check-label" for="editIsActive">
                                    启用账户
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                    <button type="submit" name="update_user" value="1" class="btn btn-primary">保存更改</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function loadUserData(id, username, isAdmin, isActive) {
    document.getElementById('editUserId').value = id;
    document.getElementById('editUsername').value = username;
    document.getElementById('editIsAdmin').checked = isAdmin == 1;
    document.getElementById('editIsActive').checked = isActive == 1;
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>