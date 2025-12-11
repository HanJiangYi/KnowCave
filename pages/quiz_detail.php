<?php
// pages/quiz_detail.php

require_once '../config/db.php';
require_once '../functions/auth.php';
require_once '../functions/quiz.php';

if (!Auth::isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$quizId = $_GET['id'] ?? ($_SESSION['last_quiz_result']['quiz_id'] ?? 0);

if (!$quizId) {
    header('Location: dashboard.php');
    exit;
}

// 使用QuizManager类的getQuizDetail函数
$quizDetail = QuizManager::getQuizDetail($quizId, $_SESSION['user_id']);

if (isset($quizDetail['error'])) {
    // 如果没有权限或不存在，跳转到首页
    header('Location: dashboard.php');
    exit;
}

$pageTitle = "测验详情 - 知识洞穴";

$pageStyles = <<<HTML
<style>
    .option-item {
        padding: 12px 15px;
        border-radius: 8px;
        border: 1px solid #dee2e6;
        margin-bottom: 10px;
        transition: all 0.2s;
        cursor: default; /* 详情页面不可点击 */
    }
    .option-item:hover {
        transform: translateX(5px);
    }
    .option-item.selected {
        background-color: #e7f1ff;
        border-color: #0d6efd;
    }
    .option-item.correct {
        background-color: #d1e7dd;
        border-color: #198754;
    }
    .option-item.incorrect {
        background-color: #f8d7da;
        border-color: #dc3545;
    }
    .option-item input[type="checkbox"],
    .option-item input[type="radio"] {
        margin-right: 10px;
    }
    .analysis-content {
        background-color: #f8f9fa;
        padding: 15px;
        border-radius: 8px;
        border-left: 4px solid #0dcaf0;
    }
    
    /* 测验详情特有样式 */
    .question-container {
        margin-bottom: 2rem;
        padding: 1.5rem;
        border: 1px solid #dee2e6;
        border-radius: 0.375rem;
        background-color: #fff;
        transition: all 0.3s;
    }
    .question-container:hover {
        box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
    }
    .correct-answer {
        color: #198754;
        font-weight: bold;
    }
    .user-answer {
        color: #0d6efd;
        font-weight: bold;
    }
    .wrong-answer {
        color: #dc3545;
        font-weight: bold;
    }
    .no-answer {
        color: #6c757d;
        font-style: italic;
    }
    .answer-section {
        padding: 1rem;
        margin: 1rem 0;
        border-radius: 0.375rem;
        background-color: #f8f9fa;
    }
    .badge-difficulty {
        font-size: 0.8em;
    }
    .explanation-box {
        background-color: #e9ecef;
        border-left: 4px solid #0d6efd;
        padding: 1rem;
        margin-top: 1rem;
        border-radius: 0.25rem;
    }
    .stat-box {
        background: linear-gradient(135deg, #6a11cb 0%, #2575fc 100%);
        color: white;
        border-radius: 0.5rem;
        padding: 1.5rem;
        margin-bottom: 2rem;
        box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
    }
    .progress-detail {
        height: 12px;
        border-radius: 6px;
        overflow: hidden;
        background-color: #e9ecef;
    }
    .progress-correct {
        background-color: #198754;
        height: 100%;
    }
    .quiz-meta {
        font-size: 0.9rem;
        color: #6c757d;
    }
    .action-buttons {
        position: sticky;
        top: 20px;
        z-index: 1000;
    }
    .type-badge {
        font-size: 0.8rem;
    }
    
    /* 答案状态指示器 */
    .answer-status {
        display: inline-block;
        width: 24px;
        height: 24px;
        line-height: 24px;
        text-align: center;
        border-radius: 50%;
        margin-right: 8px;
    }
    .status-correct {
        background-color: #198754;
        color: white;
    }
    .status-wrong {
        background-color: #dc3545;
        color: white;
    }
    
    /* 卡片样式 */
    .card {
        border: none;
        box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
    }
    .card-header {
        background-color: #f8f9fa;
        border-bottom: 1px solid #dee2e6;
    }
    
    /* 导航按钮样式 */
    .nav-buttons {
        position: sticky;
        bottom: 20px;
        background: rgba(255, 255, 255, 0.95);
        padding: 10px;
        border-radius: 8px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        z-index: 1000;
    }
</style>
HTML;

include __DIR__ . '/../includes/header.php';
?>
		
<div class="bg-dark text-white py-2">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-3">
                <span class="badge bg-info">测验详情</span> - <strong><?php echo htmlspecialchars($quizDetail['bank_name']); ?></strong>
            </div>
            <div class="col-md-3 text-center">完成时间: <?php echo date('Y-m-d H:i', strtotime($quizDetail['end_time'])); ?></div>
            <div class="col-md-6 text-end">
				<a href="stats.php" class="btn btn-sm btn-secondary">返回学习统计</a>
            </div>
        </div>
    </div>
</div>

<?php if ($quizDetail && !isset($quizDetail['error'])): ?>
<!-- 测验概览卡片 -->
<div class="container mt-4">
    <div class="card-body">
        <div class="row text-center">
            <div class="col-md-6 mb-3">
                <div class="display-6 fw-bold text-primary"><?php echo $quizDetail['score']; ?></div>
                <small class="text-muted">得分</small>
            </div>
            <div class="col-md-6 mb-3">
                <div class="display-6 fw-bold <?php echo $quizDetail['correct_count'] > $quizDetail['total_questions'] / 2 ? 'text-success' : 'text-danger'; ?>">
                    <?php echo $quizDetail['correct_count']; ?>/<?php echo $quizDetail['total_questions']; ?>
                </div>
                <small class="text-muted">正确题数</small>
            </div>
        </div>
        
        <!-- 答题进度条 -->
        <div class="mt-3">
            <div class="d-flex justify-content-between mb-2">
                <small>正确：<?php echo $quizDetail['correct_count']; ?> 题</small>
                <small>错误/未答：<?php echo $quizDetail['total_questions'] - $quizDetail['correct_count']; ?> 题</small>
            </div>
            <div class="progress" style="height: 20px;">
                <?php 
                $correctPercent = $quizDetail['total_questions'] > 0 ? 
                    ($quizDetail['correct_count'] / $quizDetail['total_questions']) * 100 : 0;
                ?>
                <div class="progress-bar bg-success" role="progressbar" 
                     style="width: <?php echo $correctPercent; ?>%">
                    <?php echo round($correctPercent); ?>%
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 题目详情 -->
<h3 class="container mt-4">题目详情（共 <?php echo count($quizDetail['records']); ?> 题）</h3>

<?php if (empty($quizDetail['records'])): ?>
<div class="container mt-4 alert alert-warning">
    暂无答题记录。
</div>
<?php else: ?>

<?php foreach ($quizDetail['records'] as $index => $record): ?>
<div class="container mt-4" id="question-<?php echo $index + 1; ?>">
    <div class="card-header bg-light d-flex justify-content-between align-items-center">
       <h5 class="mb-0">第 <?php echo $index + 1; ?> 题</h5>
    </div>
    
    <div class="card-body">
        <!-- 题目内容 -->
        <div class="mb-4">
            <div class="fs-5 mb-3">
                <span class="badge bg-secondary me-2">
                <?php 
                $typeNames = [1 => '单选题', 2 => '多选题', 3 => '判断题'];
                echo $typeNames[$record['type']] ?? '未知类型';
                ?>
            </span><?php echo nl2br(htmlspecialchars($record['stem'])); ?>
            </div>
        </div>
        
        <!-- 选项 -->
        <?php if (($record['type'] == 1 || $record['type'] == 2) && !empty($record['options'])): ?>
        <div class="mb-4">
            <h6 class="mb-3">选项：</h6>
            <?php foreach ($record['options'] as $key => $option): ?>
            <?php
                $correctAnswer = $record['correct_answer_parsed'];
                $userAnswer = $record['user_answer_parsed'];
                
                // 判断选项状态
                $isCorrectOption = false;
                $isUserSelected = false;
                
                if ($record['type'] == 2) { // 多选题
                    $isCorrectOption = strpos($correctAnswer, $key) !== false;
                    $isUserSelected = $userAnswer && strpos($userAnswer, $key) !== false;
                } else { // 单选题
                    $isCorrectOption = ($correctAnswer === $key);
                    $isUserSelected = ($userAnswer === $key);
                }
                
                $optionClass = 'option-item';
                if ($isCorrectOption) {
                    $optionClass .= ' correct';
                }
                if ($isUserSelected && !$isCorrectOption) {
                    $optionClass .= ' incorrect';
                } elseif ($isUserSelected && $isCorrectOption) {
                    $optionClass .= ' selected';
                }
            ?>
            <div class="<?php echo $optionClass; ?>">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <?php echo htmlspecialchars($option); ?>
                    </div>
                    <div>
                        <?php if ($isCorrectOption): ?>
                            <i class="fas fa-check text-success"></i>
                        <?php endif; ?>
                        <?php if ($isUserSelected && !$isCorrectOption): ?>
                            <i class="fas fa-times text-danger"></i>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        
        <!-- 答案反馈区域 -->
        <div class="answer-feedback mb-4">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <div class="card border-<?php echo $record['is_correct'] ? 'success' : 'danger'; ?>">
                        <div class="card-header bg-<?php echo $record['is_correct'] ? 'success' : 'danger'; ?> text-white">
                            <h6 class="mb-0">你的答案</h6>
                        </div>
                        <div class="card-body">
                            <?php if ($record['user_answer_parsed'] === null): ?>
                                <p class="text-muted fst-italic mb-0">
                                    <i class="fas fa-minus-circle me-2"></i>未作答
                                </p>
                            <?php else: ?>
                                <p class="fw-bold mb-0">
                                    <?php 
                                    if ($record['type'] == 2) {
                                        $userAnswers = str_split($record['user_answer_parsed']);
                                        sort($userAnswers);
                                        echo htmlspecialchars(implode('、', $userAnswers));
                                    } else {
                                        echo nl2br(htmlspecialchars($record['user_answer_parsed']));
                                    }
                                    ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6 mb-3">
                    <div class="card border-success">
                        <div class="card-header bg-success text-white">
                            <h6 class="mb-0">正确答案</h6>
                        </div>
                        <div class="card-body">
                            <p class="fw-bold text-success mb-0">
                                <?php 
                                if ($record['type'] == 2) {
                                    $correctAnswers = str_split($record['correct_answer_parsed']);
                                    sort($correctAnswers);
                                    echo htmlspecialchars(implode('、', $correctAnswers));
                                } else {
                                    echo nl2br(htmlspecialchars($record['correct_answer_parsed']));
                                }
                                ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- 解析区域 -->
            <?php if (!empty($record['analysis'])): ?>
            <div class="mt-3">
                <h6>题目解析：</h6>
                <div class="analysis-content">
                    <?php echo nl2br(htmlspecialchars($record['analysis'])); ?>
                </div>
            </div>
            <?php elseif (!empty($record['explanation'])): ?>
            <div class="mt-3">
                <h6>题目解析：</h6>
                <div class="analysis-content">
                    <?php echo nl2br(htmlspecialchars($record['explanation'])); ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endforeach; ?>

<!-- 底部导航按钮 -->
<div class="nav-buttons mt-4">
    <div class="d-flex justify-content-between">
        <a href="#" class="btn btn-outline-secondary" onclick="window.scrollTo(0, 0);">返回顶部</a>
    </div>
</div>
<?php endif; ?>

<?php else: ?>
<div class="card">
    <div class="card-body">
        <div class="text-center py-5">
            <i class="fas fa-exclamation-triangle fa-3x text-warning mb-3"></i>
            <h4 class="mb-3">未找到测验结果信息</h4>
            <p class="text-muted mb-4">请检查测验ID是否正确，或该测验可能已被删除</p>
            <a href="stats.php" class="btn btn-primary">
                <i class="fas fa-arrow-left me-2"></i>返回学习统计
            </a>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
    // 平滑滚动
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
            const href = this.getAttribute('href');
            if (href !== '#') {
                e.preventDefault();
                const target = document.querySelector(href);
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            }
        });
    });
    
    // 动态显示/隐藏导航按钮
    let lastScrollTop = 0;
    window.addEventListener('scroll', function() {
        const navButtons = document.querySelector('.nav-buttons');
        if (!navButtons) return;
        
        const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
        
        if (scrollTop > lastScrollTop) {
            // 向下滚动
            if (scrollTop > 100) {
                navButtons.style.opacity = '0.9';
                navButtons.style.transform = 'translateY(0)';
            }
        } else {
            // 向上滚动
            navButtons.style.opacity = '1';
        }
        
        lastScrollTop = scrollTop <= 0 ? 0 : scrollTop;
    });
</script>
<?php include '../includes/footer.php'; ?>