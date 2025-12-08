<?php
require_once __DIR__ . '/../config/db.php';

class QuestionPicker {
    public static function getPracticeQuestion($userId, $bankId) {
        $pdo = Database::getConnection();
        
        // 检查用户是否做过该题库的题
        $checkStmt = $pdo->prepare("
            SELECT COUNT(*) as total FROM answer_records 
            WHERE user_id = ? AND bank_id = ?
        ");
        $checkStmt->execute([$userId, $bankId]);
        $result = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result['total'] == 0) {
            // 如果是第一次做该题库，完全随机
            return self::getRandomQuestion($bankId);
        }
        
        // 智能抽题算法
        $query = "
        SELECT 
            q.id,
            q.type,
            q.stem,
            q.options_json,
            q.answer,
            q.analysis,
            (
                -- 1. 出现次数权重 (40%) - 出现越少优先级越高
                (1.0 / (0.1 + (
                    SELECT COUNT(*) FROM answer_records ar 
                    WHERE ar.question_id = q.id AND ar.user_id = ?
                ))) * 0.4
                +
                -- 2. 错误率权重 (40%) - 错误率越高优先级越高
                COALESCE((
                    SELECT (SUM(CASE WHEN is_correct = 0 THEN 1 ELSE 0 END) * 1.0 / COUNT(*)) 
                    FROM answer_records ar 
                    WHERE ar.question_id = q.id AND ar.user_id = ?
                ), 0.5) * 0.4
                +
                -- 3. 时间间隔权重 (20%) - 距离上次答题越久优先级越高
                COALESCE((
                    SELECT (JULIANDAY('now') - JULIANDAY(MAX(created_at))) / 30.0
                    FROM answer_records ar 
                    WHERE ar.question_id = q.id AND ar.user_id = ?
                ), 1.0) * 0.2
            ) as priority_score
        FROM questions q
        WHERE q.bank_id = ?
        ORDER BY priority_score DESC
        LIMIT 10";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([$userId, $userId, $userId, $bankId]);
        $candidates = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($candidates)) {
            return self::getRandomQuestion($bankId);
        }
        
        // 从优先级最高的10题中随机选1题，增加随机性
        shuffle($candidates);
        return $candidates[0];
    }
    
    private static function getRandomQuestion($bankId) {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("
            SELECT * FROM questions 
            WHERE bank_id = ? 
            ORDER BY RANDOM() 
            LIMIT 1
        ");
        $stmt->execute([$bankId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public static function saveAnswer($userId, $questionId, $bankId, $userAnswer, $isCorrect, $mode = 1, $timeSpent = 0) {
        $pdo = Database::getConnection();
        $sql = "INSERT INTO answer_records 
                (user_id, question_id, bank_id, user_answer, is_correct, mode, time_spent) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([$userId, $questionId, $bankId, $userAnswer, $isCorrect ? 1 : 0, $mode, $timeSpent]);
    }
    
    public static function getQuizQuestions($bankId, $count = 20) {
        $pdo = Database::getConnection();
        
        // 检查题目数量是否足够
        $checkStmt = $pdo->prepare("SELECT COUNT(*) as total FROM questions WHERE bank_id = ?");
        $checkStmt->execute([$bankId]);
        $result = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result['total'] < $count) {
            return ['error' => "题库题目不足，需要{$count}题，当前只有{$result['total']}题"];
        }
        
        // 随机抽取测验题目
        $stmt = $pdo->prepare("
            SELECT * FROM questions 
            WHERE bank_id = ? 
            ORDER BY RANDOM() 
            LIMIT ?
        ");
        $stmt->bindValue(1, $bankId, PDO::PARAM_INT);
        $stmt->bindValue(2, $count, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>