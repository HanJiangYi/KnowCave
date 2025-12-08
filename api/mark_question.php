<?php
// api/mark_question.php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../functions/auth.php';

header('Content-Type: application/json');

//session_start();
if (!Auth::isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => '未登录']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$examId = $data['exam_id'] ?? 0;
$questionId = $data['question_id'] ?? 0;

if (!$examId || !$questionId) {
    echo json_encode(['success' => false, 'error' => '参数错误']);
    exit;
}

try {
    // 检查是否有进行中的测验
    if (!isset($_SESSION['current_exam']) || $_SESSION['current_exam']['exam_id'] != $examId) {
        throw new Exception('测验会话不存在');
    }
    
    $marked = &$_SESSION['current_exam']['marked'];
    
    // 切换标记状态
    $index = array_search($questionId, $marked);
    if ($index !== false) {
        // 已标记，取消标记
        array_splice($marked, $index, 1);
        $action = 'unmarked';
    } else {
        // 未标记，添加标记
        $marked[] = $questionId;
        $action = 'marked';
    }
    
    echo json_encode([
        'success' => true,
        'action' => $action,
        'marked_count' => count($marked)
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>