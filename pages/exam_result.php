<?php
require_once __DIR__ . '/../functions/auth.php';
Auth::requireLogin();

$examId = $_GET['exam_id'] ?? 0;
if (!$examId) {
    header("Location: dashboard.php");
    exit;
}

$pageTitle = '测验结果 - 知识洞天 KnowCave';

include __DIR__ . '/../includes/header.php';
?>

<nav class="navbar navbar-light bg-light">
    <div class="container">
        <a class="navbar-brand" href="dashboard.php">知识洞天 KnowCave</a>
        <div>
            <a href="logout.php" class="btn btn-sm btn-outline-secondary me-2" onclick="return confirm('确定要退出登录吗？')">退出</a>
            <a href="dashboard.php" class="btn btn-sm btn-outline-primary">返回主菜单</a>
        </div>
    </div>
</nav>

<div class="container mt-4">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card text-center mb-4">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">测验完成</h4>
                </div>
                <div class="card-body py-5">
                    <div class="display-1 mb-4">🎉</div>
                    <h2 class="card-title">测验提交成功！</h2>
                    <p class="card-text text-muted">你的测验答卷已提交，成绩正在计算中...</p>
                    
                    <div class="row mt-4">
                        <div class="col-md-3">
                            <div class="card bg-light">
                                <div class="card-body">
                                    <h6 class="card-subtitle mb-2">测验ID</h6>
                                    <h4 class="card-title">#<?php echo $examId; ?></h4>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-light">
                                <div class="card-body">
                                    <h6 class="card-subtitle mb-2">提交时间</h6>
                                    <h4 class="card-title"><?php echo date('H:i'); ?></h4>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-light">
                                <div class="card-body">
                                    <h6 class="card-subtitle mb-2">题目数量</h6>
                                    <h4 class="card-title">20</h4>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-light">
                                <div class="card-body">
                                    <h6 class="card-subtitle mb-2">测验日期</h6>
                                    <h4 class="card-title"><?php echo date('m/d'); ?></h4>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-4">
                        <p>系统正在批改你的试卷，详细结果将在稍后显示。</p>
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                </div>
                <div class="card-footer">
                    <div class="d-grid gap-2">
                        <a href="dashboard.php" class="btn btn-primary">返回主菜单</a>
                        <a href="stats.php" class="btn btn-outline-secondary">查看学习统计</a>
                    </div>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header bg-light">
                    <h5 class="mb-0">📝 测验回顾</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted">测验详细结果和分析将在批改完成后显示在这里。</p>
                    <p>你可以：</p>
                    <ul>
                        <li>查看每道题的正确答案和解析</li>
                        <li>了解自己的知识薄弱点</li>
                        <li>查看本次测验的时间使用情况</li>
                        <li>与历史测验成绩对比</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // 5秒后自动跳转到统计页面
    setTimeout(() => {
        window.location.href = 'stats.php?exam_id=<?php echo $examId; ?>';
    }, 5000);
</script>

<?php
include __DIR__ . '/../includes/footer.php';
?>