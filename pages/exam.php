<?php
require_once __DIR__ . '/../functions/auth.php';
require_once __DIR__ . '/../functions/exam.php';
require_once __DIR__ . '/../config/db.php';

Auth::requireLogin();

$bankId = $_GET['bank_id'] ?? 0;
if (!$bankId) {
    header("Location: dashboard.php");
    exit;
}

// 获取题库信息
$pdo = Database::getConnection();
$stmt = $pdo->prepare("SELECT name FROM question_banks WHERE id = ? AND is_active = 1");
$stmt->execute([$bankId]);
$bank = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$bank) {
    header("Location: dashboard.php");
    exit;
}

// 检查是否已有进行中的测验
if (isset($_SESSION['current_exam']) && $_SESSION['current_exam']['bank_id'] == $bankId) {
    $exam = $_SESSION['current_exam'];
} else {
    // 开始新测验
    $result = ExamManager::startExam($_SESSION['user_id'], $bankId);
    if (isset($result['error'])) {
        die("<script>alert('{$result['error']}'); window.location.href='dashboard.php';</script>");
    }
    $exam = $_SESSION['current_exam'];
}

$currentQuestionIndex = $_GET['question'] ?? 1;
$totalQuestions = count($exam['questions']);
$currentQuestionIndex = max(1, min($currentQuestionIndex, $totalQuestions));
$currentQuestion = $exam['questions'][$currentQuestionIndex - 1];

$options = $currentQuestion['options'];
$isMultiple = ($currentQuestion['type'] == 2);
$currentAnswer = $exam['answers'][$currentQuestion['id']] ?? '';
$isMarked = in_array($currentQuestion['id'], $exam['marked']);

$pageTitle = '测验模式 - ' . htmlspecialchars($bank['name']);

$pageStyles = <<<HTML
<style>
    .option-item {
        padding: 12px 15px;
        border-radius: 8px;
        border: 1px solid #dee2e6;
        margin-bottom: 10px;
        transition: all 0.2s;
        cursor: pointer;
    }
    .option-item:hover {
        background-color: #f8f9fa;
        border-color: #86b7fe;
    }
    .option-item.selected {
        background-color: #e7f1ff;
        border-color: #0d6efd;
    }
    .option-item input[type="checkbox"],
    .option-item input[type="radio"] {
        margin-right: 10px;
    }
    .option-label {
        cursor: pointer;
        width: 100%;
        display: block;
    }
    #timer {
        font-size: 1.2rem;
        font-weight: bold;
        color: #dc3545;
        background-color: #f8d7da;
        padding: 2px 8px;
        border-radius: 4px;
    }
</style>
HTML;

include __DIR__ . '/../includes/header.php';
?>

<!-- 测验计时器 -->
<div class="bg-dark text-white py-2">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-3">
                <span class="badge bg-danger">测验模式</span> - <strong><?php echo htmlspecialchars($bank['name']); ?></strong>
            </div>
            <div class="col-md-3 text-center">
                剩余时间: <span id="timer" class="fw-bold">30:00</span>
            </div>
            <div class="col-md-6 text-end">
                <button class="btn btn-sm btn-danger" onclick="submitExam()">交卷</button>
            </div>
        </div>
    </div>
</div>

<div class="container mt-4">
    <div class="row">
        <!-- 题目导航 -->
        <div class="col-md-3">
            <div class="card mb-4">
                <div class="card-header bg-light">
                    <h6 class="mb-0">题目导航</h6>
                </div>
                <div class="card-body">
                    <div class="question-nav">
                        <?php for ($i = 1; $i <= $totalQuestions; $i++): 
                            $questionId = $exam['questions'][$i-1]['id'];
                            $isAnswered = !empty($exam['answers'][$questionId]);
                            $isCurrent = ($i == $currentQuestionIndex);
                            $isMarkedQ = in_array($questionId, $exam['marked']);
                        ?>
                        <a href="?bank_id=<?php echo $bankId; ?>&question=<?php echo $i; ?>"
                           class="question-nav-item 
                                  <?php echo $isCurrent ? 'current' : ''; ?>
                                  <?php echo $isAnswered ? 'answered' : ''; ?>
                                  <?php echo $isMarkedQ ? 'marked' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                        <?php endfor; ?>
                    </div>
                    
                    <div class="mt-3 small">
                        <div class="d-flex align-items-center mb-1">
                            <span class="nav-legend current me-2"></span> 当前题目
                        </div>
                        <div class="d-flex align-items-center mb-1">
                            <span class="nav-legend answered me-2"></span> 已回答
                        </div>
                        <div class="d-flex align-items-center">
                            <span class="nav-legend marked me-2"></span> 已标记
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="card">
                <div class="card-body text-center">
                    <div class="mb-3">
                        <div class="progress" style="height: 20px;">
                            <div class="progress-bar" role="progressbar" 
                                 style="width: <?php echo (count(array_filter($exam['answers'])) / $totalQuestions) * 100; ?>%">
                            </div>
                        </div>
                        <small class="text-muted">
                            进度: <?php echo count(array_filter($exam['answers'])); ?>/<?php echo $totalQuestions; ?>
                        </small>
                    </div>
                    <div class="d-grid gap-2">
                        <button class="btn btn-sm btn-outline-primary" onclick="prevQuestion()">上一题</button>
                        <button class="btn btn-sm btn-outline-primary" onclick="nextQuestion()">下一题</button>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- 题目区域 -->
        <div class="col-md-9">
            <div class="card">
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <div>
                        <?php if ($isMarked): ?>
                            <span class="badge bg-warning ms-2">已标记</span>
                        <?php endif; ?>
                    </div>
                    <div>
                        <small class="text-muted">
                            第 <?php echo $currentQuestionIndex; ?> 题 / 共 <?php echo $totalQuestions; ?> 题
                        </small>
                    </div>
                </div>
                
                <div class="card-body">
                    <h5 class="card-title mb-4">
                        <span class="badge bg-secondary ms-2">
                            <?php 
                            $typeNames = [1 => '单选题', 2 => '多选题', 3 => '判断题'];
                            echo $typeNames[$currentQuestion['type']] ?? '未知类型';
                            ?>
                        </span>
                        <?php echo htmlspecialchars($currentQuestion['stem']); ?>
                    </h5>
                    
                    <form id="answerForm">
                        <input type="hidden" name="exam_id" value="<?php echo $exam['exam_id']; ?>">
                        <input type="hidden" name="question_id" value="<?php echo $currentQuestion['id']; ?>">
                        
                        <div class="options-list mb-4">
                            <?php if ($isMultiple): ?>
                                <!-- 多选题 -->
                                <?php foreach ($options as $index => $option): 
                                    $optionValue = chr(65 + $index);
                                    $isChecked = strpos($currentAnswer, $optionValue) !== false;
                                ?>
                                <div class="option-item <?php echo $isChecked ? 'selected' : ''; ?>" 
                                     onclick="toggleOption('<?php echo $optionValue; ?>', this)">
                                    <input class="form-check-input" type="checkbox" 
                                           name="answer[]" value="<?php echo $optionValue; ?>" 
                                           id="option<?php echo $index; ?>"
                                           <?php echo $isChecked ? 'checked' : ''; ?>
                                           style="pointer-events: none;">
                                    <label class="form-check-label option-label" for="option<?php echo $index; ?>">
                                        <?php echo htmlspecialchars($option); ?>
                                    </label>
                                </div>
                                <?php endforeach; ?>
                            <?php elseif ($currentQuestion['type'] == 3): ?>
                                <!-- 判断题 -->
                                <div class="option-item <?php echo $currentAnswer == '对' ? 'selected' : ''; ?>" 
                                     onclick="selectSingleOption('对', this)">
                                    <input class="form-check-input" type="radio" name="answer" value="对" 
                                           id="optionTrue" <?php echo $currentAnswer == '对' ? 'checked' : ''; ?>
                                           style="pointer-events: none;">
                                    <label class="form-check-label option-label" for="optionTrue">对</label>
                                </div>
                                <div class="option-item <?php echo $currentAnswer == '错' ? 'selected' : ''; ?>" 
                                     onclick="selectSingleOption('错', this)">
                                    <input class="form-check-input" type="radio" name="answer" value="错" 
                                           id="optionFalse" <?php echo $currentAnswer == '错' ? 'checked' : ''; ?>
                                           style="pointer-events: none;">
                                    <label class="form-check-label option-label" for="optionFalse">错</label>
                                </div>
                            <?php else: ?>
                                <!-- 单选题 -->
                                <?php foreach ($options as $index => $option): 
                                    $optionValue = chr(65 + $index);
                                    $isChecked = ($currentAnswer == $optionValue);
                                ?>
                                <div class="option-item <?php echo $isChecked ? 'selected' : ''; ?>" 
                                     onclick="selectSingleOption('<?php echo $optionValue; ?>', this)">
                                    <input class="form-check-input" type="radio" name="answer" 
                                           value="<?php echo $optionValue; ?>" id="option<?php echo $index; ?>"
                                           <?php echo $isChecked ? 'checked' : ''; ?>
                                           style="pointer-events: none;">
                                    <label class="form-check-label option-label" for="option<?php echo $index; ?>">
                                        <?php echo htmlspecialchars($option); ?>
                                    </label>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        
                        <div class="d-flex justify-content-between">
                            <div>
                                <button type="button" class="btn btn-secondary me-2" onclick="clearAnswer()">清除答案</button>
                                <button type="button" class="btn btn-warning" onclick="toggleMark()" id="markBtn">
                                    <?php echo $isMarked ? '取消标记' : '标记此题'; ?>
                                </button>
                            </div>
                            <div>
                                <button type="button" class="btn btn-outline-primary me-2" onclick="prevQuestion()">上一题</button>
                                <button type="button" class="btn btn-primary" onclick="nextQuestion()">下一题</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- 测验说明 -->
            <div class="card mt-4">
                <div class="card-body">
                    <h6>测验规则：</h6>
                    <ul class="mb-0">
                        <li>测验时间：30分钟，时间到自动交卷</li>
                        <li>题目数量：20题（单选、多选、判断）</li>
                        <li>可以标记题目以便复查</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 交卷确认模态框 -->
<div class="modal fade" id="submitModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">确认交卷</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>你确定要交卷吗？交卷后将无法修改答案。</p>
                <div class="alert alert-info">
                    <strong>答题情况：</strong><br>
                    已完成：<span id="answeredCount"><?php echo count(array_filter($exam['answers'])); ?></span>/<?php echo $totalQuestions; ?> 题<br>
                    已标记：<span id="markedCount"><?php echo count($exam['marked']); ?></span> 题
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">继续答题</button>
                <button type="button" class="btn btn-danger" onclick="doSubmitExam()">确认交卷</button>
            </div>
        </div>
    </div>
</div>

<script>
    // 将PHP变量输出到JavaScript
    const examConfig = {
        bankId: <?php echo $bankId; ?>,
        currentQuestionId: <?php echo $currentQuestion['id']; ?>,
        examId: <?php echo $exam['exam_id']; ?>,
        currentQuestionIndex: <?php echo $currentQuestionIndex; ?>,
        totalQuestions: <?php echo $totalQuestions; ?>,
        isMultiple: <?php echo $isMultiple ? 'true' : 'false'; ?>,
        userId: <?php echo $_SESSION['user_id']; ?>,
        remainingTime: <?php echo ExamManager::getRemainingTime($exam['exam_id']); ?>,
        answeredCount: <?php echo count(array_filter($exam['answers'])); ?>,
        markedCount: <?php echo count($exam['marked']); ?>,
        bankName: "<?php echo htmlspecialchars($bank['name']); ?>"
    };

    // 测验计时器
    let totalSeconds = examConfig.remainingTime;
    
    function updateTimer() {
        if (totalSeconds <= 0) {
            clearInterval(timerInterval);
            submitExam(true);
            return;
        }
        
        totalSeconds--;
        const minutes = Math.floor(totalSeconds / 60);
        const seconds = totalSeconds % 60;
        document.getElementById('timer').textContent = 
            minutes.toString().padStart(2, '0') + ':' + seconds.toString().padStart(2, '0');
        
        // 最后5分钟提示
        if (totalSeconds === 300) {
            alert('测验还剩最后5分钟！');
        }
    }
    
    const timerInterval = setInterval(updateTimer, 1000);
    updateTimer(); // 初始显示
    
    // 处理多选题选项点击
    function toggleOption(value, element) {
        const checkbox = element.querySelector('input[type="checkbox"]');
        checkbox.checked = !checkbox.checked;
        
        if (checkbox.checked) {
            element.classList.add('selected');
        } else {
            element.classList.remove('selected');
        }
        
        saveAnswer();
    }
    
    // 处理单选题/判断题选项点击
    function selectSingleOption(value, element) {
        // 移除所有选项的选中状态
        const allOptions = document.querySelectorAll('.option-item');
        allOptions.forEach(opt => {
            opt.classList.remove('selected');
            const input = opt.querySelector('input');
            if (input) input.checked = false;
        });
        
        // 选中当前选项
        element.classList.add('selected');
        const radio = element.querySelector('input[type="radio"]');
        if (radio) radio.checked = true;
        
        saveAnswer();
    }
    
    // 保存答案
    async function saveAnswer() {
        const questionId = examConfig.currentQuestionId;
        const examId = examConfig.examId;
        
        // 收集当前题目的答案
        let answer = '';
        
        if (examConfig.isMultiple) {
            // 多选题
            const checkboxes = document.querySelectorAll('input[type="checkbox"]:checked');
            const values = Array.from(checkboxes).map(cb => cb.value).sort();
            answer = values.join('');
        } else {
            // 单选题或判断题
            const radio = document.querySelector('input[type="radio"]:checked');
            answer = radio ? radio.value : '';
        }
        
        try {
            const response = await fetch('../api/save_exam_answer.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    exam_id: examId,
                    question_id: questionId,
                    answer: answer
                })
            });
            
            if (!response.ok) {
                throw new Error('保存失败');
            }
            
            const result = await response.json();
            if (result.success) {
                // 更新已答题目计数和左侧进度
                updateAnswerStatus(answer);
            }
        } catch (error) {
            console.error('保存答案失败:', error);
        }
    }
    
    // 更新答题状态和左侧进度
    function updateAnswerStatus(answer) {
        // 获取当前题目的导航项
        const navItem = document.querySelector(`a[href="?bank_id=${examConfig.bankId}&question=${examConfig.currentQuestionIndex}"]`);
        
        // 检查当前题目之前是否有答案
        const hadAnswer = navItem.classList.contains('answered');
        
        if (answer !== '') {
            // 如果有新答案
            if (!hadAnswer) {
                // 之前没有答案，现在有答案，增加计数
                examConfig.answeredCount++;
                navItem.classList.add('answered');
            }
        } else {
            // 如果清除了答案
            if (hadAnswer) {
                // 之前有答案，现在清除了，减少计数
                examConfig.answeredCount--;
                navItem.classList.remove('answered');
            }
        }
        
        // 更新左侧进度显示
        updateLeftProgress();
    }
    
    // 更新左侧进度显示
    function updateLeftProgress() {
        // 更新进度条
        const progress = (examConfig.answeredCount / examConfig.totalQuestions) * 100;
        document.querySelector('.progress-bar').style.width = progress + '%';
        
        // 更新进度文本
        const progressText = document.querySelector('.progress-bar').closest('.mb-3').querySelector('small.text-muted');
        progressText.textContent = `进度: ${examConfig.answeredCount}/${examConfig.totalQuestions}`;
    }
    
    // 标记题目
    async function toggleMark() {
        const questionId = examConfig.currentQuestionId;
        const examId = examConfig.examId;
        
        try {
            const response = await fetch('../api/mark_question.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    exam_id: examId,
                    question_id: questionId
                })
            });
            
            const result = await response.json();
            if (result.success) {
                // 更新按钮文本
                const markBtn = document.getElementById('markBtn');
                const isMarked = markBtn.innerHTML.includes('取消标记');
                
                if (isMarked) {
                    markBtn.innerHTML = '标记此题';
                } else {
                    markBtn.innerHTML = '取消标记';
                }
                
                // 更新导航标记
                const navItem = document.querySelector(`a[href="?bank_id=${examConfig.bankId}&question=${examConfig.currentQuestionIndex}"]`);
                if (navItem) {
                    if (isMarked) {
                        navItem.classList.remove('marked');
                        examConfig.markedCount--;
                    } else {
                        navItem.classList.add('marked');
                        examConfig.markedCount++;
                    }
                }
                
                // 更新标记计数
                document.getElementById('markedCount').textContent = examConfig.markedCount;
            }
        } catch (error) {
            console.error('标记题目失败:', error);
            alert('标记失败，请重试');
        }
    }
    
    // 清除当前题目答案
    function clearAnswer() {
        if (confirm('确定要清除本题的答案吗？')) {
            const checkboxes = document.querySelectorAll('input[type="checkbox"]');
            const radios = document.querySelectorAll('input[type="radio"]');
            
            checkboxes.forEach(cb => {
                cb.checked = false;
                cb.closest('.option-item')?.classList.remove('selected');
            });
            radios.forEach(rb => {
                rb.checked = false;
                rb.closest('.option-item')?.classList.remove('selected');
            });
            
            saveAnswer();
        }
    }
    
    // 题目导航
    function prevQuestion() {
        // 暂时移除beforeunload事件，避免导航时弹出提示
        window.removeEventListener('beforeunload', handleBeforeUnload);
        const current = examConfig.currentQuestionIndex;
        if (current > 1) {
            window.location.href = `?bank_id=${examConfig.bankId}&question=${current - 1}`;
        } else {
            // 重新添加事件监听
            window.addEventListener('beforeunload', handleBeforeUnload);
        }
    }
    
    function nextQuestion() {
        // 暂时移除beforeunload事件，避免导航时弹出提示
        window.removeEventListener('beforeunload', handleBeforeUnload);
        const current = examConfig.currentQuestionIndex;
        const total = examConfig.totalQuestions;
        if (current < total) {
            window.location.href = `?bank_id=${examConfig.bankId}&question=${current + 1}`;
        } else {
            // 重新添加事件监听
            window.addEventListener('beforeunload', handleBeforeUnload);
        }
    }
    
    // 交卷
    function submitExam(auto = false) {
        if (auto) {
            doSubmitExam();
        } else {
            // 更新计数
            document.getElementById('answeredCount').textContent = examConfig.answeredCount;
            document.getElementById('markedCount').textContent = examConfig.markedCount;
            
            const modal = new bootstrap.Modal(document.getElementById('submitModal'));
            modal.show();
        }
    }
    
    async function doSubmitExam() {
        try {
            // 显示加载状态
            Swal.fire({
                title: '正在交卷...',
                text: '请稍候，正在批改试卷',
                allowOutsideClick: false,
                allowEscapeKey: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            const response = await fetch('../api/submit_exam.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    exam_id: examConfig.examId,
                    user_id: examConfig.userId
                })
            });
            
            if (!response.ok) {
                throw new Error('HTTP错误: ' + response.status);
            }
            
            const result = await response.json();
            
            if (result.success) {
                // 移除离开警告
                window.removeEventListener('beforeunload', handleBeforeUnload);
                
                Swal.fire({
                    title: '交卷成功！',
                    text: '得分: ' + result.score + '%，正确: ' + result.correct + '/' + result.total,
                    icon: 'success',
                    confirmButtonText: '查看结果'
                }).then(() => {
                    window.location.href = 'exam_result.php?exam_id=' + result.exam_id;
                });
            } else {
                throw new Error(result.error || '交卷失败');
            }
        } catch (error) {
            console.error('交卷错误详情:', error);
            Swal.fire({
                title: '交卷失败',
                text: '网络错误，请重试。如果问题持续存在，请联系管理员。',
                icon: 'error',
                confirmButtonText: '重试'
            }).then(() => {
                // 关闭模态框
                const modal = bootstrap.Modal.getInstance(document.getElementById('submitModal'));
                modal.hide();
            });
        }
    }
    
    // 处理离开页面的函数
    function handleBeforeUnload(e) {
        // 取消事件，显示标准提示
        e.preventDefault();
        e.returnValue = '测验正在进行中，确定要离开吗？';
        return '测验正在进行中，确定要离开吗？';
    }
    
    // 初始化事件监听
    function initEventListeners() {
        // 监听题目导航链接点击，防止触发beforeunload
        const navLinks = document.querySelectorAll('.question-nav-item');
        navLinks.forEach(link => {
            link.addEventListener('click', (e) => {
                // 如果是当前题目，不处理
                if (link.classList.contains('current')) {
                    e.preventDefault();
                    return;
                }
                // 移除beforeunload事件监听
                window.removeEventListener('beforeunload', handleBeforeUnload);
            });
        });
    }
    
    // 防止意外离开 - 使用事件监听器而不是onbeforeunload属性
    window.addEventListener('beforeunload', handleBeforeUnload);
    
    // 初始化
    document.addEventListener('DOMContentLoaded', function() {
        initEventListeners();
    });
</script>

<?php
include __DIR__ . '/../includes/footer.php';
?>