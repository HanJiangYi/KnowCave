<?php
// admin/question_manager.php
//define('IN_ADMIN', true);
$isAdmin = true;
require_once 'includes/common.php';

if (!isDatabaseInitialized()) {
    echo '<div class="alert alert-danger">请先执行初始安装！</div>';
    exit;
}

$pdo = getDB();
$message = '';
$error = '';

// 处理JSON上传
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['json_file'])) {
    try {
        $file = $_FILES['json_file'];
        
        // 检查文件
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception("文件上传失败 (错误码: {$file['error']})");
        }
        
        if ($file['size'] > 2 * 1024 * 1024) { // 2MB限制
            throw new Exception("文件大小不能超过2MB");
        }
        
        // 读取并解析JSON
        $content = file_get_contents($file['tmp_name']);
        $data = json_decode($content, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("JSON格式错误: " . json_last_error_msg());
        }
        
        // 开始事务
        $pdo->beginTransaction();
        
        // 处理题库
        if (isset($data['question_banks']) && is_array($data['question_banks'])) {
            foreach ($data['question_banks'] as $bank) {
                if (empty($bank['name'])) continue;
                
                // 检查题库是否已存在
                $stmt = $pdo->prepare("SELECT id FROM question_banks WHERE name = ?");
                $stmt->execute([$bank['name']]);
                $existing = $stmt->fetch();
                
                if ($existing) {
                    // 更新题库
                    $stmt = $pdo->prepare("UPDATE question_banks SET description = ?, is_active = ? WHERE id = ?");
                    $stmt->execute([
                        $bank['description'] ?? '',
                        $bank['is_active'] ?? 1,
                        $existing['id']
                    ]);
                    $bankId = $existing['id'];
                } else {
                    // 新增题库
                    $stmt = $pdo->prepare("INSERT INTO question_banks (name, description, is_active) VALUES (?, ?, ?)");
                    $stmt->execute([
                        $bank['name'],
                        $bank['description'] ?? '',
                        $bank['is_active'] ?? 1
                    ]);
                    $bankId = $pdo->lastInsertId();
                }
                
                // 处理题目
                if (isset($bank['questions']) && is_array($bank['questions'])) {
                    foreach ($bank['questions'] as $question) {
                        if (empty($question['stem'])) continue;
                        
                        // 检查题目是否已存在（根据题干和题库）
                        $stmt = $pdo->prepare("SELECT id FROM questions WHERE bank_id = ? AND stem = ?");
                        $stmt->execute([$bankId, $question['stem']]);
                        $existingQuestion = $stmt->fetch();
                        
                        $optionsJson = json_encode($question['options'] ?? [], JSON_UNESCAPED_UNICODE);
                        
                        if ($existingQuestion) {
                            // 更新题目
                            $stmt = $pdo->prepare("UPDATE questions SET type = ?, options_json = ?, answer = ?, analysis = ? WHERE id = ?");
                            $stmt->execute([
                                $question['type'] ?? 1,
                                $optionsJson,
                                $question['answer'] ?? '',
                                $question['analysis'] ?? '',
                                $existingQuestion['id']
                            ]);
                        } else {
                            // 新增题目
                            $stmt = $pdo->prepare("INSERT INTO questions (bank_id, type, stem, options_json, answer, analysis) VALUES (?, ?, ?, ?, ?, ?)");
                            $stmt->execute([
                                $bankId,
                                $question['type'] ?? 1,
                                $question['stem'],
                                $optionsJson,
                                $question['answer'] ?? '',
                                $question['analysis'] ?? ''
                            ]);
                        }
                    }
                }
            }
        }
        
        $pdo->commit();
        $message = "✅ 题库导入成功！";
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "导入失败: " . $e->getMessage();
    }
}

// 获取当前题库统计
$stats = [];
try {
    $stmt = $pdo->query("
        SELECT 
            b.name,
            COUNT(q.id) as question_count,
            SUM(CASE WHEN q.type = 1 THEN 1 ELSE 0 END) as single_choice,
            SUM(CASE WHEN q.type = 2 THEN 1 ELSE 0 END) as multi_choice,
            SUM(CASE WHEN q.type = 3 THEN 1 ELSE 0 END) as true_false,
            b.is_active
        FROM question_banks b
        LEFT JOIN questions q ON b.id = q.bank_id
        GROUP BY b.id
        ORDER BY b.id
    ");
    $stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // 表可能不存在
}

$pageTitle = '题库更新 - 管理员后台';
include __DIR__ . '/../includes/header.php';
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-md-8">
            <h4 class="mb-4">📚 题库更新</h4>
            
            <?php if ($message): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <div class="card mb-4">
                <div class="card-header bg-info text-white">
                    导入题库（JSON格式）
                </div>
                <div class="card-body">
                    <form method="POST" action="" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label for="json_file" class="form-label">选择JSON文件</label>
                            <input class="form-control" type="file" id="json_file" name="json_file" accept=".json" required>
                            <div class="form-text">
                                支持批量导入题库和题目。文件需符合指定的JSON格式。
                                <a href="download_template.php" class="text-decoration-none">📥 下载示例文件</a>
                            </div>
                        </div>
                        
                        <div class="alert alert-warning">
                            <small>
                                <strong>导入规则：</strong><br>
                                1. 根据题库名称匹配，已存在的题库会更新描述和状态<br>
                                2. 根据题干匹配，已存在的题目会更新选项、答案和解析<br>
                                3. 支持单选题（type=1）、多选题（type=2）、判断题（type=3）<br>
                                4. 多选题答案格式：如 "AB" 或 "ACD"<br>
                                5. 文件大小限制：2MB
                            </small>
                        </div>
                        
                        <div class="text-center">
                            <button type="submit" class="btn btn-primary btn-lg px-5">
                                📤 上传并导入
                            </button>
                            <a href="download_template.php" class="btn btn-outline-secondary ms-3">
                                📥 下载示例模板
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card">
                <div class="card-header bg-light">
                    当前题库统计
                </div>
                <div class="card-body">
                    <?php if (empty($stats)): ?>
                        <p class="text-muted text-center py-3">暂无题库数据</p>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($stats as $bank): ?>
                            <div class="list-group-item">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="mb-1">
                                            <?php echo htmlspecialchars($bank['name']); ?>
                                            <?php if (!$bank['is_active']): ?>
                                                <span class="badge bg-secondary">未启用</span>
                                            <?php endif; ?>
                                        </h6>
                                        <small class="text-muted">
                                            题目总数: <?php echo $bank['question_count'] ?? 0; ?>
                                        </small>
                                    </div>
                                </div>
                                <div class="mt-2">
                                    <span class="badge bg-info me-1">单<?php echo $bank['single_choice'] ?? 0; ?></span>
                                    <span class="badge bg-warning me-1">多<?php echo $bank['multi_choice'] ?? 0; ?></span>
                                    <span class="badge bg-success">判<?php echo $bank['true_false'] ?? 0; ?></span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="mt-3 text-center">
                        <a href="?action=main" class="btn btn-sm btn-outline-secondary">返回主菜单</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>