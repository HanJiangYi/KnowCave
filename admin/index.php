<?php
// admin/index.php
//define('IN_ADMIN', true);
$isAdmin = true;
require_once 'includes/common.php';

$action = $_GET['action'] ?? 'main';

$pageTitle = '答题系统 - 管理员后台';
$pageStyles = <<<HTML
<style>
    body { padding-top: 20px; background-color: #f8f9fa; }
    .admin-menu { margin-bottom: 30px; }
    .menu-card { cursor: pointer; transition: transform 0.2s; height: 100%; }
    .menu-card:hover { transform: translateY(-5px); box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
    .content-area { min-height: 400px; }
    .status-badge { font-size: 0.8rem; }
</style>
HTML;

include __DIR__ . '/../includes/header.php';
?>

<div class="container">
    <div class="row mb-4">
        <div class="col-12">
            <h2 class="text-center">📋 答题系统管理后台</h2>
            <p class="text-center text-muted">当前时间: <?php echo date('Y-m-d H:i:s'); ?></p>
            
            <?php if (!isDatabaseInitialized()): ?>
            <div class="alert alert-warning text-center">
                <strong>⚠️ 系统未初始化!</strong> 请先执行 <a href="?action=install" class="alert-link">初始安装</a>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="row admin-menu">
        <!-- 初始安装 -->
        <div class="col-md-4 mb-3">
            <div class="card menu-card text-center" onclick="window.location='?action=install'">
                <div class="card-body">
                    <div class="display-4 mb-3">🚀</div>
                    <h5 class="card-title">初始安装</h5>
                    <p class="card-text">初始化数据库表结构并创建初始管理员账户</p>
                    <?php if (!isDatabaseInitialized()): ?>
                        <span class="badge bg-danger status-badge">待执行</span>
                    <?php else: ?>
                        <span class="badge bg-success status-badge">已完成</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- 用户维护 -->
        <div class="col-md-4 mb-3">
            <div class="card menu-card text-center" onclick="window.location='?action=users'" 
                 <?php echo !isDatabaseInitialized() ? 'style="opacity:0.6"' : '' ?>>
                <div class="card-body">
                    <div class="display-4 mb-3">👥</div>
                    <h5 class="card-title">用户维护</h5>
                    <p class="card-text">添加、修改、停用系统用户账户</p>
                    <?php if (!isDatabaseInitialized()): ?>
                        <span class="badge bg-secondary status-badge">需先初始化</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- 题库更新 -->
        <div class="col-md-4 mb-3">
            <div class="card menu-card text-center" onclick="window.location='?action=questions'"
                 <?php echo !isDatabaseInitialized() ? 'style="opacity:0.6"' : '' ?>>
                <div class="card-body">
                    <div class="display-4 mb-3">📚</div>
                    <h5 class="card-title">题库更新</h5>
                    <p class="card-text">上传JSON文件批量导入题库和题目</p>
                    <?php if (!isDatabaseInitialized()): ?>
                        <span class="badge bg-secondary status-badge">需先初始化</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- 功能内容区 -->
    <div class="row">
        <div class="col-12">
            <div class="card content-area">
                <div class="card-body">
                    <?php
                    // 动态加载功能模块
                    switch ($action) {
                        case 'install':
                            include 'install.php';
                            break;
                        case 'users':
                            if (isDatabaseInitialized()) {
                                include 'user_manager.php';
                            } else {
                                echo '<div class="alert alert-danger">请先执行初始安装！</div>';
                            }
                            break;
                        case 'questions':
                            if (isDatabaseInitialized()) {
                                include 'question_manager.php';
                            } else {
                                echo '<div class="alert alert-danger">请先执行初始安装！</div>';
                            }
                            break;
                        default:
                            echo '<div class="text-center py-5">
                                    <h4>欢迎使用管理后台</h4>
                                    <p class="text-muted">请从上方菜单中选择要执行的操作</p>
                                  </div>';
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>

    <div class="row mt-4">
        <div class="col-12 text-center">
            <p class="text-muted">
                <small>管理员后台 | 访问IP: <?php echo $_SERVER['REMOTE_ADDR']; ?></small>
            </p>
        </div>
    </div>
</div>

<script>
    // 简单的操作确认
    function confirmAction(msg) {
        return confirm(msg || '确定要执行此操作吗？');
    }
</script>

<?php
include __DIR__ . '/../includes/footer.php';
?>