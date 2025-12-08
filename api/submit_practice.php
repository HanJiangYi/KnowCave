<?php
// api/submit_practice.php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../functions/auth.php';
require_once __DIR__ . '/../functions/question_picker.php';

// 设置响应头为JSON
header('Content-Type: application/json; charset=utf-8');

// 启用错误报告（调试用，生产环境应关闭）
//error_reporting(E_ALL);
//ini_set('display_errors', 0);

//session_start();

// 检查用户是否登录
if (!Auth::isLoggedIn()) {
    echo json_encode([
        'success' => false,
        'error' => '未登录，请先登录'
    ]);
    exit;
}

// 获取POST数据
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// 检查数据是否有效
if (!$data) {
    echo json_encode([
        'success' => false,
        'error' => '无效的请求数据'
    ]);
    exit;
}

$questionId = $data['question_id'] ?? 0;
$bankId = $data['bank_id'] ?? 0;
$userAnswer = $data['answer'] ?? '';

if (!$questionId || !$bankId) {
    echo json_encode([
        'success' => false,
        'error' => '缺少必要参数'
    ]);
    exit;
}

try {
    // 获取数据库连接
    $pdo = Database::getConnection();
    
    // 获取题目信息（包括答案和解析）
    $stmt = $pdo->prepare("SELECT answer, analysis, type FROM questions WHERE id = ?");
    $stmt->execute([$questionId]);
    $question = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$question) {
        throw new Exception('题目不存在');
    }
    
    $correctAnswer = $question['answer'];
    $analysis = $question['analysis'] ?? '';
    $questionType = $question['type'];
    
    // 判断答案是否正确
    $isCorrect = false;
    
    if (!empty($userAnswer)) {
        // 对于多选题，答案可能无序，需要排序比较
        if ($questionType == 2 && strlen($userAnswer) > 1 && strlen($correctAnswer) > 1) {
            // 将答案字符串转换为数组，排序后再比较
            $userArr = str_split($userAnswer);
            $correctArr = str_split($correctAnswer);
            sort($userArr);
            sort($correctArr);
            $isCorrect = implode('', $userArr) === implode('', $correctArr);
        } else {
            // 单选题、判断题直接比较
            $isCorrect = ($userAnswer === $correctAnswer);
        }
    }
    
    // 保存答题记录
    $saveResult = QuestionPicker::saveAnswer(
        $_SESSION['user_id'], 
        $questionId, 
        $bankId, 
        $userAnswer, 
        $isCorrect, 
        1,  // 练习模式
        0   // 耗时（练习模式不计时）
    );
    
    if (!$saveResult) {
        throw new Exception('保存答题记录失败');
    }
    
    // 返回成功响应
    echo json_encode([
        'success' => true,
        'is_correct' => $isCorrect,
        'correct_answer' => $correctAnswer,
        'user_answer' => $userAnswer,
        'analysis' => $analysis
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>