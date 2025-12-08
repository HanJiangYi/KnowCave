<?php
// api/submit_quiz.php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../functions/auth.php';
require_once __DIR__ . '/../functions/question_picker.php';
require_once __DIR__ . '/../functions/quiz.php';

header('Content-Type: application/json');

//session_start();
if (!Auth::isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => '未登录']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$quizId = $data['quiz_id'] ?? 0;
$userId = $data['user_id'] ?? 0;

if (!$quizId || !$userId) {
    echo json_encode(['success' => false, 'error' => '参数错误']);
    exit;
}

try {
    // 检查是否有进行中的测验
    if (!isset($_SESSION['current_quiz']) || $_SESSION['current_quiz']['quiz_id'] != $quizId) {
        throw new Exception('测验会话不存在');
    }
    
    $quiz = $_SESSION['current_quiz'];
    $pdo = Database::getConnection();
    
    // 获取所有题目的正确答案
    $questionIds = array_keys($quiz['answers']);
    $correctAnswers = [];
    
    if (!empty($questionIds)) {
        $placeholders = str_repeat('?,', count($questionIds) - 1) . '?';
        $stmt = $pdo->prepare("SELECT id, answer FROM questions WHERE id IN ($placeholders)");
        $stmt->execute($questionIds);
        $correctAnswers = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    }
    
    // 计算成绩
    $total = count($quiz['answers']);
    $correct = 0;
    
    foreach ($quiz['answers'] as $qid => $userAnswer) {
        $isCorrect = ($userAnswer === ($correctAnswers[$qid] ?? ''));
        if ($isCorrect) $correct++;
        
        // 保存答题记录
        QuestionPicker::saveAnswer(
            $userId,
            $qid,
            $quiz['bank_id'],
            $userAnswer,
            $isCorrect,
            2, // 测验模式
            0
        );
    }
    
    // 更新测验会话状态
    $endTime = date('Y-m-d H:i:s');
    $timeSpent = time() - $quiz['start_time'];
    
    $stmt = $pdo->prepare("
        UPDATE quiz_sessions 
        SET end_time = ?, correct_count = ?, status = 2 
        WHERE id = ?
    ");
    $stmt->execute([$endTime, $correct, $quizId]);
    
    // 清除session中的测验数据
    unset($_SESSION['current_quiz']);
    
    // 返回结果
    echo json_encode([
        'success' => true,
        'quiz_id' => $quizId,
        'total' => $total,
        'correct' => $correct,
        'score' => round(($correct / $total) * 100, 1),
        'time_spent' => $timeSpent
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>