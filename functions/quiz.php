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
    
	/*完成测验并计算分数*/
    public static function completeQuiz($quizId, $userId, $answers) {
        if (!isset($_SESSION['current_quiz']) || $_SESSION['current_quiz']['quiz_id'] != $quizId) {
            return ['error' => '测验会话不存在'];
        }
        
        $quiz = $_SESSION['current_quiz'];
        $pdo = Database::getConnection();
        
        // 获取测验的所有题目
        $questionIds = [];
        foreach ($quiz['questions'] as $question) {
            $questionIds[] = $question['id'];
        }
        
        if (empty($questionIds)) {
            return ['error' => '没有测验题目'];
        }
        
        $correctCount = 0;
        $totalQuestions = count($questionIds);
        
        // 开始事务
        $pdo->beginTransaction();
        
        try {
            // 获取题目正确答案
            $placeholders = str_repeat('?,', count($questionIds) - 1) . '?';
            $stmt = $pdo->prepare("SELECT id, type, answer FROM questions WHERE id IN ($placeholders)");
            $stmt->execute($questionIds);
            $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $questionMap = [];
            foreach ($questions as $q) {
                $questionMap[$q['id']] = $q;
            }
            
            // 处理每个题目
            foreach ($questionIds as $questionId) {
                $userAnswer = $answers[$questionId] ?? '';
                $isCorrect = 0;
                
                if (!empty($userAnswer) && isset($questionMap[$questionId])) {
                    $question = $questionMap[$questionId];
                    
                    // 判断答案是否正确
                    if ($question['type'] == 1 || $question['type'] == 3) { // 单选或判断
                        $correctAnswer = $question['answer'];
                        $isCorrect = (trim($userAnswer) === trim($correctAnswer)) ? 1 : 0;
                    } elseif ($question['type'] == 2) { // 多选
                        $correctAnswer = $question['answer'];
                        // 多选题答案可能是用逗号分隔的字母
                        $userAnswerArray = str_split($userAnswer);
                        sort($userAnswerArray);
                        $correctAnswerArray = str_split($correctAnswer);
                        sort($correctAnswerArray);
                        $isCorrect = (implode('', $userAnswerArray) === implode('', $correctAnswerArray)) ? 1 : 0;
                    }
                    
                    if ($isCorrect) {
                        $correctCount++;
                    }
                    
                    // 保存用户答案（如果是数组转为字符串）
                    $userAnswerSave = is_array($userAnswer) ? implode('', $userAnswer) : $userAnswer;
                } else {
                    $userAnswerSave = null;
                }
                
                // 保存到answer_records表
                $stmt = $pdo->prepare("
                    INSERT INTO answer_records 
                    (user_id, question_id, bank_id, quiz_id, user_answer, is_correct, mode, time_spent, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, 2, 0, datetime('now'))
                ");
                $stmt->execute([
                    $userId,
                    $questionId,
                    $quiz['bank_id'],
                    $quizId,
                    $userAnswerSave,
                    $isCorrect
                ]);
            }
            
            // 计算分数（四舍五入为整数）
            $score = $totalQuestions > 0 ? round(($correctCount / $totalQuestions) * 100) : 0;
            
            // 更新测验会话状态
            $stmt = $pdo->prepare("
                UPDATE quiz_sessions 
                SET end_time = datetime('now'), 
                    status = 2, 
                    correct_count = ?, 
                    score = ? 
                WHERE id = ? AND user_id = ?
            ");
            $stmt->execute([$correctCount, $score, $quizId, $userId]);
            
            // 提交事务
            $pdo->commit();
            
            // 清除session中的测验数据
            unset($_SESSION['current_quiz']);
            
            return [
                'success' => true,
                'quiz_id' => $quizId,
                'score' => $score,
                'correct' => $correctCount,
                'total' => $totalQuestions,
                'bank_id' => $quiz['bank_id']
            ];
            
        } catch (Exception $e) {
            $pdo->rollBack();
            return ['error' => '提交失败: ' . $e->getMessage()];
        }
    }
    
    /*获取测验详情*/
    public static function getQuizDetail($quizId, $userId) {
        $pdo = Database::getConnection();
        
        // 获取测验会话信息
        $stmt = $pdo->prepare("
            SELECT qs.*, u.username, b.name as bank_name 
            FROM quiz_sessions qs 
            LEFT JOIN users u ON qs.user_id = u.id 
            LEFT JOIN question_banks b ON qs.bank_id = b.id 
            WHERE qs.id = ? AND qs.user_id = ?
        ");
        $stmt->execute([$quizId, $userId]);
        $quiz = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$quiz) {
            return ['error' => '测验不存在或没有权限'];
        }
        
        // 获取测验的所有答题记录
        $stmt = $pdo->prepare("
            SELECT ar.*, q.type, q.stem, q.options_json, q.answer, q.analysis 
            FROM answer_records ar 
            JOIN questions q ON ar.question_id = q.id 
            WHERE ar.quiz_id = ? AND ar.user_id = ? 
            ORDER BY ar.created_at
        ");
        $stmt->execute([$quizId, $userId]);
        $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // 处理选项和答案
        foreach ($records as &$record) {
            $record['options'] = json_decode($record['options_json'], true) ?: [];
            $record['correct_answer_parsed'] = $record['answer'];
            
            // 解析用户答案
            if ($record['user_answer'] !== null) {
                $record['user_answer_parsed'] = $record['user_answer'];
            } else {
                $record['user_answer_parsed'] = null;
            }
        }
        
        $quiz['records'] = $records;
        return $quiz;
    }
    
    /*获取用户的测验历史*/
    public static function getUserQuizHistory($userId, $limit = 10) {
        $pdo = Database::getConnection();
        
        $stmt = $pdo->prepare("
            SELECT qs.*, b.name as bank_name 
            FROM quiz_sessions qs 
            LEFT JOIN question_banks b ON qs.bank_id = b.id 
            WHERE qs.user_id = ? 
            AND qs.status = 2  -- 已完成
            ORDER BY qs.end_time DESC 
            LIMIT ?
        ");
        $stmt->execute([$userId, $limit]);
        $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // 获取每个测验的答题数量
        foreach ($history as &$item) {
            $stmt2 = $pdo->prepare("
                SELECT COUNT(*) as total, 
                       SUM(CASE WHEN is_correct = 1 THEN 1 ELSE 0 END) as correct 
                FROM answer_records 
                WHERE quiz_id = ? AND user_id = ? AND mode = 2
            ");
            $stmt2->execute([$item['id'], $userId]);
            $stats = $stmt2->fetch(PDO::FETCH_ASSOC);
            
            $item['total_questions'] = $stats['total'] ?? 0;
            $item['correct_count'] = $stats['correct'] ?? 0;
        }
        
        return $history;
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

	/*获取剩余的测验时间*/
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