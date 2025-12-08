<?php
date_default_timezone_set('Asia/Shanghai');

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/question_picker.php';

class QuizManager {
    public static function startQuiz($userId, $bankId, $questionCount = 20, $timeLimit = 1800) {
        $pdo = Database::getConnection();
        
        // 获取测验题目
        $questions = QuestionPicker::getQuizQuestions($bankId, $questionCount);
        if (isset($questions['error'])) {
            return $questions;
        }
        
        // 创建测验会话
        $stmt = $pdo->prepare("
            INSERT INTO quiz_sessions 
            (user_id, bank_id, time_limit, total_questions, status) 
            VALUES (?, ?, ?, ?, 1)
        ");
        $stmt->execute([$userId, $bankId, $timeLimit, $questionCount]);
        $quizId = $pdo->lastInsertId();
        
        // 准备题目信息（不含答案）
        $questionList = [];
        foreach ($questions as $index => $question) {
            $questionList[] = [
                'id' => $question['id'],
                'type' => $question['type'],
                'stem' => $question['stem'],
                'options' => json_decode($question['options_json'], true),
                'index' => $index + 1
            ];
        }
        
        // 存储到session
        $_SESSION['current_quiz'] = [
            'quiz_id' => $quizId,
            'bank_id' => $bankId,
            'start_time' => time(),
            'time_limit' => $timeLimit,
            'questions' => $questionList,
            'answers' => array_fill_keys(array_column($questionList, 'id'), ''),
            'marked' => []
        ];
        
        return [
            'quiz_id' => $quizId,
            'question_count' => $questionCount,
            'time_limit' => $timeLimit,
            'questions' => $questionList
        ];
    }
    
    public static function submitAnswer($quizId, $questionId, $answer) {
        if (!isset($_SESSION['current_quiz']) || $_SESSION['current_quiz']['quiz_id'] != $quizId) {
            return ['error' => '测验会话不存在'];
        }
        
        $_SESSION['current_quiz']['answers'][$questionId] = $answer;
        return ['success' => true];
    }
    
    public static function toggleMark($questionId) {
        if (!isset($_SESSION['current_quiz'])) {
            return false;
        }
        
        $marked = &$_SESSION['current_quiz']['marked'];
        if (in_array($questionId, $marked)) {
            $marked = array_diff($marked, [$questionId]);
        } else {
            $marked[] = $questionId;
        }
        return true;
    }
    
    public static function finishQuiz($userId, $quizId) {
        if (!isset($_SESSION['current_quiz']) || $_SESSION['current_quiz']['quiz_id'] != $quizId) {
            return ['error' => '测验会话不存在'];
        }
        
        $quiz = $_SESSION['current_quiz'];
        $pdo = Database::getConnection();
        
        // 获取正确答案
        $questionIds = array_keys($quiz['answers']);
        $placeholders = str_repeat('?,', count($questionIds) - 1) . '?';
        $stmt = $pdo->prepare("SELECT id, answer FROM questions WHERE id IN ($placeholders)");
        $stmt->execute($questionIds);
        $correctAnswers = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        // 计算成绩
        $total = count($quiz['answers']);
        $correct = 0;
        $details = [];
        
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
            
            // 获取题目详情
            $stmt = $pdo->prepare("SELECT * FROM questions WHERE id = ?");
            $stmt->execute([$qid]);
            $question = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $details[] = [
                'question' => $question['stem'],
                'user_answer' => $userAnswer,
                'correct_answer' => $question['answer'],
                'is_correct' => $isCorrect,
                'analysis' => $question['analysis'],
                'options' => json_decode($question['options_json'], true)
            ];
        }
        
        // 更新测验会话
        $endTime = time();
        $timeSpent = $endTime - $quiz['start_time'];
        
        $stmt = $pdo->prepare("
            UPDATE quiz_sessions 
            SET end_time = ?, correct_count = ?, status = 2 
            WHERE id = ?
        ");
        $stmt->execute([date('Y-m-d H:i:s', $endTime), $correct, $quizId]);
        
        // 清除session
        unset($_SESSION['current_quiz']);
        
        return [
            'quiz_id' => $quizId,
            'total' => $total,
            'correct' => $correct,
            'score' => round(($correct / $total) * 100, 1),
            'time_spent' => $timeSpent,
            'details' => $details
        ];
    }
    
    public static function getRemainingTime($quizId) {
        if (!isset($_SESSION['current_quiz']) || $_SESSION['current_quiz']['quiz_id'] != $quizId) {
            return 0;
        }
        
        $quiz = $_SESSION['current_quiz'];
        $elapsed = time() - $quiz['start_time'];
        $remaining = $quiz['time_limit'] - $elapsed;
        
        return max(0, $remaining);
    }
}
?>