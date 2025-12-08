<?php
date_default_timezone_set('Asia/Shanghai');

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/question_picker.php';

class ExamManager {
    public static function startExam($userId, $bankId, $questionCount = 20, $timeLimit = 1800) {
        $pdo = Database::getConnection();
        
        // 获取考试题目
        $questions = QuestionPicker::getExamQuestions($bankId, $questionCount);
        if (isset($questions['error'])) {
            return $questions;
        }
        
        // 创建考试会话
        $stmt = $pdo->prepare("
            INSERT INTO exam_sessions 
            (user_id, bank_id, time_limit, total_questions, status) 
            VALUES (?, ?, ?, ?, 1)
        ");
        $stmt->execute([$userId, $bankId, $timeLimit, $questionCount]);
        $examId = $pdo->lastInsertId();
        
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
        $_SESSION['current_exam'] = [
            'exam_id' => $examId,
            'bank_id' => $bankId,
            'start_time' => time(),
            'time_limit' => $timeLimit,
            'questions' => $questionList,
            'answers' => array_fill_keys(array_column($questionList, 'id'), ''),
            'marked' => []
        ];
        
        return [
            'exam_id' => $examId,
            'question_count' => $questionCount,
            'time_limit' => $timeLimit,
            'questions' => $questionList
        ];
    }
    
    public static function submitAnswer($examId, $questionId, $answer) {
        if (!isset($_SESSION['current_exam']) || $_SESSION['current_exam']['exam_id'] != $examId) {
            return ['error' => '考试会话不存在'];
        }
        
        $_SESSION['current_exam']['answers'][$questionId] = $answer;
        return ['success' => true];
    }
    
    public static function toggleMark($questionId) {
        if (!isset($_SESSION['current_exam'])) {
            return false;
        }
        
        $marked = &$_SESSION['current_exam']['marked'];
        if (in_array($questionId, $marked)) {
            $marked = array_diff($marked, [$questionId]);
        } else {
            $marked[] = $questionId;
        }
        return true;
    }
    
    public static function finishExam($userId, $examId) {
        if (!isset($_SESSION['current_exam']) || $_SESSION['current_exam']['exam_id'] != $examId) {
            return ['error' => '考试会话不存在'];
        }
        
        $exam = $_SESSION['current_exam'];
        $pdo = Database::getConnection();
        
        // 获取正确答案
        $questionIds = array_keys($exam['answers']);
        $placeholders = str_repeat('?,', count($questionIds) - 1) . '?';
        $stmt = $pdo->prepare("SELECT id, answer FROM questions WHERE id IN ($placeholders)");
        $stmt->execute($questionIds);
        $correctAnswers = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        // 计算成绩
        $total = count($exam['answers']);
        $correct = 0;
        $details = [];
        
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
                2, // 考试模式
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
        
        // 更新考试会话
        $endTime = time();
        $timeSpent = $endTime - $exam['start_time'];
        
        $stmt = $pdo->prepare("
            UPDATE exam_sessions 
            SET end_time = ?, correct_count = ?, status = 2 
            WHERE id = ?
        ");
        $stmt->execute([date('Y-m-d H:i:s', $endTime), $correct, $examId]);
        
        // 清除session
        unset($_SESSION['current_exam']);
        
        return [
            'exam_id' => $examId,
            'total' => $total,
            'correct' => $correct,
            'score' => round(($correct / $total) * 100, 1),
            'time_spent' => $timeSpent,
            'details' => $details
        ];
    }
    
    public static function getRemainingTime($examId) {
        if (!isset($_SESSION['current_exam']) || $_SESSION['current_exam']['exam_id'] != $examId) {
            return 0;
        }
        
        $exam = $_SESSION['current_exam'];
        $elapsed = time() - $exam['start_time'];
        $remaining = $exam['time_limit'] - $elapsed;
        
        return max(0, $remaining);
    }
}
?>