<?php
// api/submit_quiz.php

//session_start();
require_once '../config/db.php';
require_once '../functions/auth.php';
require_once '../functions/quiz.php';

if (!Auth::isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => '请先登录']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$quizId = $data['quiz_id'] ?? 0;
$userId = $data['user_id'] ?? $_SESSION['user_id'];

if (!$quizId || !$userId) {
    echo json_encode(['success' => false, 'message' => '无效的测验数据']);
    exit;
}

// 检查会话中是否有测验数据
if (!isset($_SESSION['current_quiz']) || $_SESSION['current_quiz']['quiz_id'] != $quizId) {
    echo json_encode(['success' => false, 'message' => '测验会话不存在或已过期']);
    exit;
}

// 获取测验答案
$quiz = $_SESSION['current_quiz'];
$answers = $quiz['answers'];

// 使用QuizManager类的completeQuiz函数
$result = QuizManager::completeQuiz($quizId, $userId, $answers);

if (isset($result['error'])) {
    echo json_encode(['success' => false, 'message' => $result['error']]);
    exit;
}

// 保存结果到session，供quiz_result.php使用
$_SESSION['last_quiz_result'] = $result;

echo json_encode([
    'success' => true,
    'quiz_id' => $result['quiz_id'],
    'score' => $result['score'],
    'correct' => $result['correct'],
    'total' => $result['total'],
    'message' => '测验提交成功'
]);
?>