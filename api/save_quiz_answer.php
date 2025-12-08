<?php
// api/save_quiz_answer.php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../functions/auth.php';
require_once __DIR__ . '/../functions/quiz.php';

header('Content-Type: application/json');

//session_start();
if (!Auth::isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => '未登录']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$quizId = $data['quiz_id'] ?? 0;
$questionId = $data['question_id'] ?? 0;
$answer = $data['answer'] ?? '';

if (!$quizId || !$questionId) {
    echo json_encode(['success' => false, 'error' => '参数错误']);
    exit;
}

try {
    // 检查是否有进行中的测验
    if (!isset($_SESSION['current_quiz']) || $_SESSION['current_quiz']['quiz_id'] != $quizId) {
        throw new Exception('测验会话不存在');
    }
    
    // 保存答案到session
    $_SESSION['current_quiz']['answers'][$questionId] = $answer;
    
    // 更新进度
    $answeredCount = count(array_filter($_SESSION['current_quiz']['answers']));
    
    echo json_encode([
        'success' => true,
        'answered_count' => $answeredCount,
        'total_questions' => count($_SESSION['current_quiz']['questions'])
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>