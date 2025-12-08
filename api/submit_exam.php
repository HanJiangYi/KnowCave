<?php
// api/submit_exam.php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../functions/auth.php';
require_once __DIR__ . '/../functions/question_picker.php';
require_once __DIR__ . '/../functions/exam.php';

header('Content-Type: application/json');

//session_start();
if (!Auth::isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => '未登录']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$examId = $data['exam_id'] ?? 0;
$userId = $data['user_id'] ?? 0;

if (!$examId || !$userId) {
    echo json_encode(['success' => false, 'error' => '参数错误']);
    exit;
}

try {
    // 检查是否有进行中的测验
    if (!isset($_SESSION['current_exam']) || $_SESSION['current_exam']['exam_id'] != $examId) {
        throw new Exception('测验会话不存在');
    }
    
    $exam = $_SESSION['current_exam'];
    $pdo = Database::getConnection();
    
    // 获取所有题目的正确答案
    $questionIds = array_keys($exam['answers']);
    $correctAnswers = [];
    
    if (!empty($questionIds)) {
        $placeholders = str_repeat('?,', count($questionIds) - 1) . '?';
        $stmt = $pdo->prepare("SELECT id, answer FROM questions WHERE id IN ($placeholders)");
        $stmt->execute($questionIds);
        $correctAnswers = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    }
    
    // 计算成绩
    $total = count($exam['answers']);
    $correct = 0;
    
    foreach ($exam['answers'] as $qid => $userAnswer) {
        $isCorrect = ($userAnswer === ($correctAnswers[$qid] ?? ''));
        if ($isCorrect) $correct++;
        
        // 保存答题记录
        QuestionPicker::saveAnswer(
            $userId,
            $qid,
            $exam['bank_id'],
            $userAnswer,
            $isCorrect,
            2, // 测验模式
            0
        );
    }
    
    // 更新测验会话状态
    $endTime = date('Y-m-d H:i:s');
    $timeSpent = time() - $exam['start_time'];
    
    $stmt = $pdo->prepare("
        UPDATE exam_sessions 
        SET end_time = ?, correct_count = ?, status = 2 
        WHERE id = ?
    ");
    $stmt->execute([$endTime, $correct, $examId]);
    
    // 清除session中的测验数据
    unset($_SESSION['current_exam']);
    
    // 返回结果
    echo json_encode([
        'success' => true,
        'exam_id' => $examId,
        'total' => $total,
        'correct' => $correct,
        'score' => round(($correct / $total) * 100, 1),
        'time_spent' => $timeSpent
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>