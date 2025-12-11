<?php
// pages/quiz_detail.php

//session_start();
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
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <?php include '../includes/header.php'; ?>
    <style>
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
        .answer-choice {
            padding: 0.75rem;
            margin: 0.5rem 0;
            border-radius: 0.375rem;
            border: 1px solid #dee2e6;
            transition: all 0.2s;
        }
        .answer-choice:hover {
            transform: translateX(5px);
        }
        .answer-choice.correct {
            background-color: rgba(25, 135, 84, 0.1);
            border-color: #198754;
        }
        .answer-choice.selected {
            background-color: rgba(13, 110, 253, 0.1);
            border-color: #0d6efd;
        }
        .answer-choice.wrong-selected {
            background-color: rgba(220, 53, 69, 0.1);
            border-color: #dc3545;
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
    </style>
</head>
<body>
    <div class="container mt-4">
        <div class="action-buttons mb-4">
            <div class="d-flex justify-content-between align-items-center">
                <h2 class="mb-0">测验详情</h2>
                <div>
                    <a href="stats.php" class="btn btn-outline-primary ms-2">
                        <i class="fas fa-chart-bar"></i> 返回学习统计
                    </a>
                </div>
            </div>
        </div>
        
        <?php if ($quizDetail && !isset($quizDetail['error'])): ?>
        <!-- 测验概览 -->
        <div class="stat-box">
            <div class="row text-center">
                <div class="col-md-3 mb-3">
                    <h5>题库</h5>
                    <h4><?php echo $quizDetail['bank_name']; ?></h4>
                </div>
                <div class="col-md-3 mb-3">
                    <h5>得分</h5>
                    <h4><?php echo $quizDetail['score']; ?></h4>
                </div>
                <div class="col-md-3 mb-3">
                    <h5>正确题数</h5>
                    <h4><?php echo $quizDetail['correct_count']; ?>/<?php echo $quizDetail['total_questions']; ?></h4>
                </div>
                <div class="col-md-3 mb-3">
                    <h5>完成时间</h5>
                    <h4><?php echo date('Y-m-d H:i', strtotime($quizDetail['end_time'])); ?></h4>
                </div>
            </div>
            
            <!-- 答题进度可视化 -->
            <div class="mt-3">
                <div class="d-flex justify-content-between mb-2">
                    <small>正确：<?php echo $quizDetail['correct_count']; ?> 题</small>
                    <small>错误/未答：<?php echo $quizDetail['total_questions'] - $quizDetail['correct_count']; ?> 题</small>
                </div>
                <div class="progress-detail">
                    <?php 
                    $correctPercent = $quizDetail['total_questions'] > 0 ? 
                        ($quizDetail['correct_count'] / $quizDetail['total_questions']) * 100 : 0;
                    ?>
                    <div class="progress-correct" style="width: <?php echo $correctPercent; ?>%"></div>
                </div>
            </div>
        </div>
        
        <!-- 题目详情 -->
        <h3 class="mb-4">题目详情（共 <?php echo count($quizDetail['records']); ?> 题）</h3>
        
        <?php if (empty($quizDetail['records'])): ?>
        <div class="alert alert-warning">
            暂无答题记录。
        </div>
        <?php else: ?>
        
        <?php foreach ($quizDetail['records'] as $index => $record): ?>
        <div class="question-container" id="question-<?php echo $index + 1; ?>">
            <div class="d-flex justify-content-between align-items-start mb-3">
                <h5>
                    第 <?php echo $index + 1; ?> 题 
                    <?php if ($record['is_correct'] == 1): ?>
                        <span class="badge bg-success ms-2">正确</span>
                    <?php else: ?>
                        <span class="badge bg-danger ms-2">错误</span>
                    <?php endif; ?>
                </h5>
            </div>
            <div class="mb-3 fs-5">
                <span class="badge bg-light text-dark type-badge">
                        <?php 
                        $typeNames = [1 => '单选题', 2 => '多选题', 3 => '判断题'];
                        echo $typeNames[$record['type']] ?? '未知类型';
                        ?>
                    </span><?php echo nl2br(htmlspecialchars($record['stem'])); ?>
            </div>
            
            <?php if (($record['type'] == 1 || $record['type'] == 2) && !empty($record['options'])): ?>
            <div class="mb-3">
                <h6 class="mb-3">选项：</h6>
                <div class="row">
                    <?php foreach ($record['options'] as $key => $option): ?>
                    <?php
                        $correctAnswer = $record['correct_answer_parsed'];
                        $isCorrect = false;
                        $isSelected = false;
                        
                        // 判断是否正确答案
                        if ($record['type'] == 2) { // 多选题
                            $isCorrect = strpos($correctAnswer, $key) !== false;
                        } else { // 单选题
                            $isCorrect = ($correctAnswer === $key);
                        }
                        
                        // 判断用户是否选择了该选项
                        if ($record['user_answer_parsed'] !== null) {
                            if ($record['type'] == 2) {
                                $isSelected = strpos($record['user_answer_parsed'], $key) !== false;
                            } else {
                                $isSelected = ($record['user_answer_parsed'] === $key);
                            }
                        }
                        
                        $class = 'answer-choice col-md-6';
                        if ($isCorrect) $class .= ' correct';
                        if ($isSelected) {
                            $class .= $isCorrect ? ' selected' : ' wrong-selected';
                        }
                    ?>
                    <div class="<?php echo $class; ?>">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <?php echo htmlspecialchars($option); ?>
                            </div>
                            <div>
                                <?php if ($isCorrect): ?>
                                    <i class="fas fa-check text-success"></i>
                                <?php endif; ?>
                                <?php if ($isSelected && !$isCorrect): ?>
                                    <i class="fas fa-times text-danger"></i>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="answer-section">
                <div class="row">
                    <div class="col-md-6">
                        <h6 class="mb-2">你的答案：</h6>
                        <?php if ($record['user_answer_parsed'] === null): ?>
                            <p class="wrong-answer mb-0">
                                <i class="fas fa-minus-circle me-2"></i>未答
                            </p>
                        <?php else: ?>
                            <div class="<?php echo $record['is_correct'] ? 'user-answer' : 'wrong-answer'; ?>">
                                <div class="d-flex align-items-center">
                                    <div class="flex-grow-1">
                                        <?php 
                                        if ($record['type'] == 2) {
                                            // 多选题，显示每个选项
                                            $userAnswers = str_split($record['user_answer_parsed']);
                                            sort($userAnswers);
                                            echo htmlspecialchars(implode('、', $userAnswers));
                                        } else {
                                            echo nl2br(htmlspecialchars($record['user_answer_parsed']));
                                        }
                                        ?>
                                    </div>
                                    <?php if ($record['is_correct']): ?>
                                        <i class="fas fa-check-circle text-success fs-4"></i>
                                    <?php else: ?>
                                        <i class="fas fa-times-circle text-danger fs-4"></i>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6">
                        <h6 class="mb-2">正确答案：</h6>
                        <p class="correct-answer mb-0">
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
					<div class="col-md-12">
                        <h6 class="mb-2">解析：</h6>
                        <p>
                            <?php echo $record['analysis']; ?>
                        </p>
                    </div>
                </div>
            </div>
            
            <?php if (!empty($record['explanation'])): ?>
            <div class="explanation-box">
                <h6 class="mb-2">
                    <i class="fas fa-lightbulb text-warning me-2"></i>解析：
                </h6>
                <p class="mb-0"><?php echo nl2br(htmlspecialchars($record['explanation'])); ?></p>
            </div>
            <?php endif; ?>
            
            <!-- 错题标记按钮 -->
            <div class="mt-3 pt-3 border-top">
                <button class="btn btn-sm btn-outline-warning mark-wrong-btn" 
                        data-question-id="<?php echo $record['question_id']; ?>"
                        data-quiz-id="<?php echo $quizId; ?>">
                    <i class="fas fa-bookmark me-2"></i>标记为错题
                </button>
                <a href="#question-<?php echo $index + 2; ?>" class="btn btn-sm btn-outline-primary float-end">
                    下一题 <i class="fas fa-arrow-down"></i>
                </a>
            </div>
        </div>
        <?php endforeach; ?>
        
        <div class="text-center mt-4">
            <a href="#" class="btn btn-primary" onclick="window.scrollTo(0, 0);">
                <i class="fas fa-arrow-up"></i> 返回顶部
            </a>
        </div>
        
        <?php endif; ?>
        
        <?php else: ?>
        <div class="alert alert-warning">
            未找到测验结果信息。
        </div>
        <?php endif; ?>
    </div>
    
    <?php include '../includes/footer.php'; ?>
    
    <script>
        // 错题标记功能
        document.querySelectorAll('.mark-wrong-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const questionId = this.dataset.questionId;
                const quizId = this.dataset.quizId;
                
                fetch('../api/mark_question.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        question_id: questionId,
                        quiz_id: quizId,
                        is_wrong: 1
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire({
                            icon: 'success',
                            title: '已标记为错题',
                            text: '该题目已添加到错题本',
                            timer: 1500,
                            showConfirmButton: false
                        });
                        
                        this.innerHTML = '<i class="fas fa-bookmark me-2"></i>已标记';
                        this.classList.remove('btn-outline-warning');
                        this.classList.add('btn-warning');
                        this.disabled = true;
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: '标记失败',
                            text: data.message || '请稍后重试',
                            timer: 2000
                        });
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    Swal.fire({
                        icon: 'error',
                        title: '请求失败',
                        text: '网络错误，请稍后重试',
                        timer: 2000
                    });
                });
            });
        });
        
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
    </script>
</body>
</html>