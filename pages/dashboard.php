<?php
require_once __DIR__ . '/../functions/auth.php';
require_once __DIR__ . '/../config/db.php';

Auth::requireLogin();

$user = Auth::getUser();
$pdo = Database::getConnection();

// 获取题库列表
$stmt = $pdo->query("
    SELECT b.*, COUNT(q.id) as question_count 
    FROM question_banks b 
    LEFT JOIN questions q ON b.id = q.bank_id 
    WHERE b.is_active = 1 
    GROUP BY b.id 
    ORDER BY b.name
");
$banks = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 获取用户统计
require_once __DIR__ . '/../functions/stats.php';
$stats = Stats::getUserStats($user['id']);

$pageTitle = '主菜单 - 知识洞天 KnowCave';

include __DIR__ . '/../includes/header.php';
?>

<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container">
        <a class="navbar-brand" href="dashboard.php">知识洞天 KnowCave</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <span class="nav-link">欢迎, <?php echo htmlspecialchars($user['username']); ?></span>
                </li>
                <!--li class="nav-item">
                    <a class="nav-link" href="stats.php">📊 学习统计</a>
                </li-->
                <li class="nav-item">
                    <a class="nav-link" href="logout.php" onclick="return confirm('确定要退出登录吗？')">退出</a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<div class="container mt-4">
    <!-- 统计概览 -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card text-white bg-primary">
                <div class="card-body text-center">
                    <h6 class="card-subtitle mb-2">总答题数</h6>
                    <h3 class="card-title"><?php echo $stats['overall']['total_answered'] ?? 0; ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-white bg-success">
                <div class="card-body text-center">
                    <h6 class="card-subtitle mb-2">正确率</h6>
                    <h3 class="card-title"><?php echo $stats['overall']['accuracy_rate'] ?? 0; ?>%</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-white bg-info">
                <div class="card-body text-center">
                    <h6 class="card-subtitle mb-2">答题进度</h6>
                    <h3 class="card-title"><?php echo $stats['progress']['percentage'] ?? 0; ?>%</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-white bg-warning">
                <div class="card-body text-center">
                    <h6 class="card-subtitle mb-2">平均用时</h6>
                    <h3 class="card-title"><?php echo round(($stats['overall']['avg_time_per_question'] ?? 0) / 60, 1); ?>分钟</h3>
                </div>
            </div>
        </div>
    </div>
    
    <!-- 题库选择 -->
    <div class="card">
        <div class="card-header bg-light">
            <h5 class="mb-0">📚 请选择题库</h5>
        </div>
        <div class="card-body">
            <?php if (empty($banks)): ?>
                <div class="alert alert-info text-center py-4">
                    <h5>暂无可用题库</h5>
                    <p class="mb-0">请联系管理员添加题库和题目</p>
                </div>
            <?php else: ?>
                <div class="row">
                    <?php foreach ($banks as $bank): ?>
                    <div class="col-md-6 mb-3">
                        <div class="card h-100">
                            <div class="card-body">
                                <h5 class="card-title"><?php echo htmlspecialchars($bank['name']); ?></h5>
                                <p class="card-text text-muted">
                                    <small><?php echo htmlspecialchars($bank['description']); ?></small>
                                </p>
                                <div class="mb-3">
                                    <span class="badge bg-primary"><?php echo $bank['question_count']; ?> 题</span>
                                    <?php if ($bank['question_count'] < 20): ?>
                                        <span class="badge bg-warning ms-1">考试需20题</span>
                                    <?php endif; ?>
                                </div>
                                <div class="d-grid gap-2">
                                    <a href="practice.php?bank_id=<?php echo $bank['id']; ?>" 
                                       class="btn btn-outline-primary">开始练习</a>
                                    <a href="exam.php?bank_id=<?php echo $bank['id']; ?>" 
                                       class="btn btn-primary <?php echo $bank['question_count'] < 20 ? 'disabled' : ''; ?>"
                                       <?php if ($bank['question_count'] < 20): ?>
                                       title="该题库题目不足20题，无法进行考试"
                                       <?php endif; ?>>
                                       开始考试 (30分钟)
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- 快速入口 -->
    <div class="row mt-4">
        <div class="col-md-4">
            <a href="stats.php" class="card text-decoration-none text-dark">
                <div class="card-body text-center">
                    <div class="display-4 mb-3">📊</div>
                    <h5>学习统计</h5>
                    <p class="text-muted">查看详细的学习报告和进度</p>
                </div>
            </a>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-body text-center">
                    <div class="display-4 mb-3">📝</div>
                    <h5>错题本</h5>
                    <p class="text-muted">功能开发中</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <?php if ($user['is_admin']): ?>
            <a href="../admin/" class="card text-decoration-none text-dark" target="_blank">
                <div class="card-body text-center">
                    <div class="display-4 mb-3">⚙️</div>
                    <h5>管理后台</h5>
                    <p class="text-muted">系统管理设置</p>
                </div>
            </a>
            <?php else: ?>
            <div class="card">
                <div class="card-body text-center">
                    <div class="display-4 mb-3">👤</div>
                    <h5>个人中心</h5>
                    <p class="text-muted">用户信息管理</p>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>