<?php
// pages/quiz_result.php - 修改为跳转到详情页面

session_start();
require_once '../config/db.php';
require_once '../functions/auth.php';

if (!Auth::isLoggedIn()) {
    header('Location: login.php');
    exit;
}

// 检查是否有上次的测验结果
if (!isset($_SESSION['last_quiz_result'])) {
    header('Location: dashboard.php');
    exit;
}

$quizResult = $_SESSION['last_quiz_result'];
$pageTitle = "测验完成 - 知识洞穴";
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <?php include '../includes/header.php'; ?>
    <style>
        .result-card {
            max-width: 500px;
            margin: 2rem auto;
            text-align: center;
        }
        .score-circle {
            width: 200px;
            height: 200px;
            border-radius: 50%;
            margin: 2rem auto;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            font-weight: bold;
            border: 10px solid;
        }
        .score-excellent {
            background: linear-gradient(135deg, #d4fc79 0%, #96e6a1 100%);
            border-color: #96e6a1;
            color: #2d5016;
        }
        .score-good {
            background: linear-gradient(135deg, #a1c4fd 0%, #c2e9fb 100%);
            border-color: #a1c4fd;
            color: #1e3c72;
        }
        .score-average {
            background: linear-gradient(135deg, #fccb90 0%, #d57eeb 100%);
            border-color: #d57eeb;
            color: #6a3093;
        }
        .score-poor {
            background: linear-gradient(135deg, #ff9a9e 0%, #fad0c4 100%);
            border-color: #ff9a9e;
            color: #870000;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="result-card">
            <h1 class="mb-4">测验完成！</h1>
            
            <?php
            $scoreClass = 'score-poor';
            if ($quizResult['score'] >= 90) $scoreClass = 'score-excellent';
            elseif ($quizResult['score'] >= 70) $scoreClass = 'score-good';
            elseif ($quizResult['score'] >= 60) $scoreClass = 'score-average';
            ?>
            
            <div class="score-circle <?php echo $scoreClass; ?>">
                <?php echo $quizResult['score']; ?>%
            </div>
            
            <div class="mb-4">
                <h3>正确率：<?php echo $quizResult['correct']; ?>/<?php echo $quizResult['total']; ?></h3>
            </div>
            
            <div class="alert alert-info mb-4">
                <h5><i class="fas fa-info-circle"></i> 正在加载题目详情...</h5>
                <p class="mb-0">请稍候，系统正在为您准备详细的答题分析。</p>
            </div>
            
            <a href="quiz_detail.php?id=<?php echo $quizResult['quiz_id']; ?>" class="btn btn-primary btn-lg mb-3">
                <i class="fas fa-eye"></i> 立即查看题目详情
            </a>
            <br>
            <a href="dashboard.php" class="btn btn-outline-secondary">
                <i class="fas fa-home"></i> 返回首页
            </a>
        </div>
    </div>
    
    <?php include '../includes/footer.php'; ?>
    
    <script>
        // 5秒后自动跳转到详情页面
        setTimeout(function() {
            window.location.href = 'quiz_detail.php?id=<?php echo $quizResult['quiz_id']; ?>';
        }, 5000);
    </script>
</body>
</html>