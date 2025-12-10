<?php
require_once __DIR__ . '/../functions/auth.php';
require_once __DIR__ . '/../functions/stats.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../functions/quiz.php';

Auth::requireLogin();

$user = Auth::getUser();
$bankId = $_GET['bank_id'] ?? null;
$stats = Stats::getUserStats($user['id'], $bankId);

// 获取题库列表
$pdo = Database::getConnection();
$stmt = $pdo->query("
    SELECT b.*, COUNT(q.id) as question_count 
    FROM question_banks b 
    LEFT JOIN questions q ON b.id = q.bank_id 
    WHERE b.is_active = 1 
    GROUP BY b.id 
    ORDER BY b.name
");
$banks = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 获取当前题库信息
$currentBank = null;
if ($bankId) {
    $stmt = $pdo->prepare("SELECT name FROM question_banks WHERE id = ?");
    $stmt->execute([$bankId]);
    $currentBank = $stmt->fetch(PDO::FETCH_ASSOC);
}

$pageTitle = '学习统计 - 知识洞天 KnowCave';

include __DIR__ . '/../includes/header.php';
?>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">知识洞天 KnowCave</a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="dashboard.php">🏠 主菜单</a>
                <a class="nav-link" href="#" onclick="window.print()">🖨️ 打印报告</a>
            </div>
        </div>
    </nav>
    
    <div class="container mt-4">
        <!-- 题库选择 -->
        <div class="card mb-4">
            <div class="card-body">
                <h5 class="card-title mb-3">选择统计范围</h5>
                <div class="d-flex flex-wrap gap-2">
                    <a href="stats.php" class="btn <?php echo !$bankId ? 'btn-primary' : 'btn-outline-primary'; ?>">
                        全部题库
                    </a>
                    <?php foreach ($banks as $bank): ?>
                    <a href="stats.php?bank_id=<?php echo $bank['id']; ?>" 
                       class="btn <?php echo $bankId == $bank['id'] ? 'btn-primary' : 'btn-outline-primary'; ?>">
                        <?php echo htmlspecialchars($bank['name']); ?>
                        <span class="badge bg-light text-dark ms-1"><?php echo $bank['question_count']; ?></span>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        
        <!-- 统计概览 -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card text-white bg-primary">
                    <div class="card-body">
                        <h6 class="card-subtitle mb-2">总答题数</h6>
                        <h2 class="card-title"><?php echo $stats['overall']['total_answered'] ?? 0; ?></h2>
                        <p class="card-text mb-0">
                            <small>练习: <?php echo ($stats['overall']['total_answered'] ?? 0) - ($stats['quizzes'][0]['total_questions'] ?? 0) * count($stats['quizzes'] ?? []); ?></small><br>
                            <small>测验: <?php echo ($stats['quizzes'][0]['total_questions'] ?? 0) * count($stats['quizzes'] ?? []); ?></small>
                        </p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-white bg-success">
                    <div class="card-body">
                        <h6 class="card-subtitle mb-2">正确率</h6>
                        <h2 class="card-title"><?php echo $stats['overall']['accuracy_rate'] ?? 0; ?>%</h2>
                        <p class="card-text mb-0">
                            <small>正确: <?php echo $stats['overall']['total_correct'] ?? 0; ?></small><br>
                            <small>错误: <?php echo $stats['overall']['total_wrong'] ?? 0; ?></small>
                        </p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-white bg-info">
                    <div class="card-body">
                        <h6 class="card-subtitle mb-2">答题进度</h6>
                        <h2 class="card-title"><?php echo $stats['progress']['percentage'] ?? 0; ?>%</h2>
                        <p class="card-text mb-0">
                            <small>已做: <?php echo $stats['progress']['answered'] ?? 0; ?></small><br>
                            <small>总数: <?php echo $stats['progress']['total'] ?? 0; ?></small>
                        </p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-white bg-warning">
                    <div class="card-body">
                        <h6 class="card-subtitle mb-2">平均用时</h6>
                        <h2 class="card-title"><?php echo round(($stats['overall']['avg_time_per_question'] ?? 0) / 60, 1); ?>分钟</h2>
                        <p class="card-text mb-0">
                            <small>总时长: <?php echo round(($stats['overall']['total_answered'] ?? 0) * ($stats['overall']['avg_time_per_question'] ?? 0) / 3600, 1); ?>小时</small>
                        </p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- 图表和详细统计 -->
        <div class="row">
            <!-- 题型分布 -->
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header bg-light">
                        <h5 class="mb-0">📊 题型分布</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="typeChart" height="200"></canvas>
                        <div class="mt-3">
                            <?php foreach ($stats['by_type'] as $type): 
                                $typeName = match($type['type']) {
                                    1 => '单选题',
                                    2 => '多选题',
                                    3 => '判断题',
                                    default => '未知'
                                };
                            ?>
                            <div class="d-flex justify-content-between mb-1">
                                <span><?php echo $typeName; ?></span>
                                <span>
                                    <?php echo $type['correct']; ?>/<?php echo $type['total']; ?>
                                    (<?php echo $type['accuracy']; ?>%)
                                </span>
                            </div>
                            <div class="progress mb-2" style="height: 8px;">
                                <div class="progress-bar" role="progressbar" 
                                     style="width: <?php echo $type['accuracy']; ?>%">
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- 最近活动 -->
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header bg-light">
                        <h5 class="mb-0">📅 最近7天活动</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="activityChart" height="200"></canvas>
                        <div class="table-responsive mt-3">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>日期</th>
                                        <th>答题数</th>
                                        <th>正确</th>
                                        <th>正确率</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($stats['recent'] as $day): ?>
                                    <tr>
                                        <td><?php echo $day['date']; ?></td>
                                        <td><?php echo $day['total']; ?></td>
                                        <td><?php echo $day['correct']; ?></td>
                                        <td>
                                            <?php echo $day['total'] > 0 ? round(($day['correct'] / $day['total']) * 100, 1) : 0; ?>%
                                            <?php if ($day['quiz_questions'] > 0): ?>
                                            <span class="badge bg-info ms-1">考<?php echo $day['quiz_questions']; ?></span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- 测验记录 -->
        <div class="card mb-4">
            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                <h5 class="mb-0">📝 测验记录</h5>
                <small>最近10次测验</small>
            </div>
            <div class="card-body">
                <?php if (empty($stats['quizzes'])): ?>
                    <p class="text-muted text-center py-3">暂无测验记录</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>测验时间</th>
                                    <th>题库</th>
                                    <th>题目数</th>
                                    <th>正确数</th>
                                    <th>得分</th>
                                    <th>用时</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($stats['quizzes'] as $quiz): 
                                    $startTime = strtotime($quiz['start_time']);
                                    $endTime = strtotime($quiz['end_time']);
                                    $duration = $endTime - $startTime;
                                    $durationStr = $duration > 3600 
                                        ? floor($duration/3600) . '小时' . floor(($duration%3600)/60) . '分'
                                        : floor($duration/60) . '分钟';
                                ?>
                                <tr>
                                    <td><?php echo date('m/d H:i', $startTime); ?></td>
                                    <td><?php echo htmlspecialchars($quiz['bank_name']); ?></td>
                                    <td><?php echo $quiz['total_questions']; ?></td>
                                    <td>
                                        <?php echo $quiz['correct_count']; ?>
                                        <small class="text-muted">/<?php echo $quiz['total_questions']; ?></small>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo $quiz['score'] >= 60 ? 'bg-success' : 'bg-danger'; ?>">
                                            <?php echo $quiz['score']; ?>%
                                        </span>
                                    </td>
                                    <td><?php echo $durationStr; ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- 知识薄弱点分析 -->
        <div class="card">
            <div class="card-header bg-light">
                <h5 class="mb-0">🎯 学习建议</h5>
            </div>
            <div class="card-body">
                <?php
                $accuracy = $stats['overall']['accuracy_rate'] ?? 0;
                $completion = $stats['progress']['percentage'] ?? 0;
                
                if ($accuracy >= 80 && $completion >= 80) {
                    echo '<div class="alert alert-success">
                            <h5>👍 优秀的学习者！</h5>
                            <p>你的正确率和学习进度都很高，继续保持！建议：</p>
                            <ul>
                                <li>挑战更高难度的题目</li>
                                <li>尝试在更短时间内完成练习</li>
                                <li>帮助其他同学解决难题</li>
                            </ul>
                          </div>';
                } elseif ($accuracy >= 60) {
                    echo '<div class="alert alert-info">
                            <h5>📈 稳步进步中</h5>
                            <p>你的学习状态良好，但还有提升空间。建议：</p>
                            <ul>
                                <li>重点复习错误率较高的题型</li>
                                <li>每天坚持练习，保持学习节奏</li>
                                <li>参加模拟测验检验学习效果</li>
                            </ul>
                          </div>';
                } else {
                    echo '<div class="alert alert-warning">
                            <h5>💪 需要加强练习</h5>
                            <p>你的正确率还有较大提升空间。建议：</p>
                            <ul>
                                <li>先从基础题目开始，逐步提升难度</li>
                                <li>仔细阅读题目解析，理解知识点</li>
                                <li>使用错题本功能重点复习错题</li>
                                <li>增加每天的练习时间</li>
                            </ul>
                          </div>';
                }
                ?>
                
                <div class="row">
                    <div class="col-md-6">
                        <h6>下一步学习计划：</h6>
                        <ol>
                            <li>完成剩余的 <?php echo max(0, $stats['progress']['total'] - $stats['progress']['answered']); ?> 道新题</li>
                            <li>复习错题本中的 <?php echo $stats['overall']['total_wrong'] ?? 0; ?> 道错题</li>
                            <li>每周至少参加1次模拟测验</li>
                        </ol>
                    </div>
                    <div class="col-md-6">
                        <h6>目标设定：</h6>
                        <div class="mb-3">
                            <label class="form-label">下个月正确率目标</label>
                            <input type="range" class="form-range" min="<?php echo $accuracy; ?>" max="100" value="<?php echo min(100, $accuracy + 10); ?>" disabled>
                            <div class="d-flex justify-content-between">
                                <small>当前: <?php echo $accuracy; ?>%</small>
                                <small>目标: <?php echo min(100, $accuracy + 10); ?>%</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

	<script src="/../assets/js/chart.js"></script>
	<script>
        // 题型分布图表
        const typeCtx = document.getElementById('typeChart').getContext('2d');
        const typeChart = new Chart(typeCtx, {
            type: 'doughnut',
            data: {
                labels: [
                    <?php foreach ($stats['by_type'] as $type): 
                        $typeName = match($type['type']) {
                            1 => '单选题',
                            2 => '多选题',
                            3 => '判断题',
                            default => '未知'
                        };
                        echo "'$typeName',";
                    endforeach; ?>
                ],
                datasets: [{
                    data: [
                        <?php foreach ($stats['by_type'] as $type): 
                            echo $type['total'] . ',';
                        endforeach; ?>
                    ],
                    backgroundColor: [
                        '#36a2eb', '#ff6384', '#4bc0c0', '#ff9f40', '#9966ff'
                    ]
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
        
        // 活动图表
        const activityCtx = document.getElementById('activityChart').getContext('2d');
        const activityChart = new Chart(activityCtx, {
            type: 'bar',
            data: {
                labels: [
                    <?php foreach ($stats['recent'] as $day): 
                        echo "'" . substr($day['date'], 5) . "',";
                    endforeach; ?>
                ],
                datasets: [{
                    label: '答题数',
                    data: [
                        <?php foreach ($stats['recent'] as $day): 
                            echo $day['total'] . ',';
                        endforeach; ?>
                    ],
                    backgroundColor: '#36a2eb'
                }, {
                    label: '正确数',
                    data: [
                        <?php foreach ($stats['recent'] as $day): 
                            echo $day['correct'] . ',';
                        endforeach; ?>
                    ],
                    backgroundColor: '#4bc0c0'
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    </script>

<?php include __DIR__ . '/../includes/footer.php'; ?>