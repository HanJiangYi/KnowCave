<?php
// api/save_exam_answer.php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../functions/auth.php';
require_once __DIR__ . '/../functions/exam.php';

header('Content-Type: application/json');

//session_start();
if (!Auth::isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => '未登录']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$examId = $data['exam_id'] ?? 0;
$questionId = $data['question_id'] ?? 0;
$answer = $data['answer'] ?? '';

if (!$examId || !$questionId) {
    echo json_encode(['success' => false, 'error' => '参数错误']);
    exit;
}

try {
    // 检查是否有进行中的考试
    if (!isset($_SESSION['current_exam']) || $_SESSION['current_exam']['exam_id'] != $examId) {
        throw new Exception('考试会话不存在');
    }
    
    // 保存答案到session
    $_SESSION['current_exam']['answers'][$questionId] = $answer;
    
    // 更新进度
    $answeredCount = count(array_filter($_SESSION['current_exam']['answers']));
    
    echo json_encode([
        'success' => true,
        'answered_count' => $answeredCount,
        'total_questions' => count($_SESSION['current_exam']['questions'])
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>