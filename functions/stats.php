<?php
require_once __DIR__ . '/../config/db.php';

class Stats {
    public static function getUserStats($userId, $bankId = null) {
        $pdo = Database::getConnection();
        
        // 基础条件
        $where = "WHERE ar.user_id = :user_id";
        $params = [':user_id' => $userId];
        
        if ($bankId) {
            $where .= " AND ar.bank_id = :bank_id";
            $params[':bank_id'] = $bankId;
        }
        
        // 总体统计
        $sql = "
        SELECT 
            COUNT(*) as total_answered,
            SUM(CASE WHEN ar.is_correct = 1 THEN 1 ELSE 0 END) as total_correct,
            SUM(CASE WHEN ar.is_correct = 0 THEN 1 ELSE 0 END) as total_wrong,
            ROUND(
                AVG(CASE WHEN ar.is_correct = 1 THEN 1.0 ELSE 0.0 END) * 100, 
                1
            ) as accuracy_rate,
            AVG(ar.time_spent) as avg_time_per_question
        FROM answer_records ar
        $where";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $overall = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // 答题进度
        $progress = self::getProgress($userId, $bankId);
        
        // 最近活动
        $recent = self::getRecentActivity($userId, $bankId);
        
        // 各题型统计
        $byType = self::getStatsByType($userId, $bankId);
        
        // 测验记录
        $exams = self::getExamHistory($userId, $bankId);
        
        return [
            'overall' => $overall,
            'progress' => $progress,
            'recent' => $recent,
            'by_type' => $byType,
            'exams' => $exams
        ];
    }
    
    private static function getProgress($userId, $bankId = null) {
        $pdo = Database::getConnection();
        
        if ($bankId) {
            // 单个题库进度
            $sql = "
            SELECT 
                COUNT(DISTINCT ar.question_id) as answered,
                (SELECT COUNT(*) FROM questions WHERE bank_id = ?) as total
            FROM answer_records ar
            WHERE ar.user_id = ? AND ar.bank_id = ?";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$bankId, $userId, $bankId]);
        } else {
            // 总体进度
            $sql = "
            SELECT 
                COUNT(DISTINCT ar.question_id) as answered,
                (SELECT COUNT(*) FROM questions) as total
            FROM answer_records ar
            WHERE ar.user_id = ?";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$userId]);
        }
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $result['percentage'] = $result['total'] > 0 
            ? round(($result['answered'] / $result['total']) * 100, 1) 
            : 0;
        
        return $result;
    }
    
    private static function getRecentActivity($userId, $bankId = null) {
        $pdo = Database::getConnection();
        
        $where = "WHERE ar.user_id = :user_id";
        $params = [':user_id' => $userId];
        
        if ($bankId) {
            $where .= " AND ar.bank_id = :bank_id";
            $params[':bank_id'] = $bankId;
        }
        
        $sql = "
        SELECT 
            DATE(ar.created_at) as date,
            COUNT(*) as total,
            SUM(CASE WHEN ar.is_correct = 1 THEN 1 ELSE 0 END) as correct,
            SUM(CASE WHEN ar.mode = 2 THEN 1 ELSE 0 END) as exam_questions
        FROM answer_records ar
        $where
        GROUP BY DATE(ar.created_at)
        ORDER BY date DESC
        LIMIT 7";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    private static function getStatsByType($userId, $bankId = null) {
        $pdo = Database::getConnection();
        
        $where = "WHERE ar.user_id = :user_id AND q.id IS NOT NULL";
        $params = [':user_id' => $userId];
        
        if ($bankId) {
            $where .= " AND ar.bank_id = :bank_id";
            $params[':bank_id'] = $bankId;
        }
        
        $sql = "
        SELECT 
            q.type,
            COUNT(*) as total,
            SUM(CASE WHEN ar.is_correct = 1 THEN 1 ELSE 0 END) as correct,
            ROUND(
                AVG(CASE WHEN ar.is_correct = 1 THEN 1.0 ELSE 0.0 END) * 100, 
                1
            ) as accuracy
        FROM answer_records ar
        JOIN questions q ON ar.question_id = q.id
        $where
        GROUP BY q.type
        ORDER BY q.type";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    private static function getExamHistory($userId, $bankId = null) {
        $pdo = Database::getConnection();
        
        $where = "WHERE es.user_id = :user_id AND es.status = 2";
        $params = [':user_id' => $userId];
        
        if ($bankId) {
            $where .= " AND es.bank_id = :bank_id";
            $params[':bank_id'] = $bankId;
        }
        
        $sql = "
        SELECT 
            es.id,
            es.start_time,
            es.end_time,
            es.total_questions,
            es.correct_count,
            es.time_limit,
            ROUND((es.correct_count * 1.0 / es.total_questions) * 100, 1) as score,
            b.name as bank_name
        FROM exam_sessions es
        JOIN question_banks b ON es.bank_id = b.id
        $where
        ORDER BY es.start_time DESC
        LIMIT 10";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>