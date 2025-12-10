<?php
require_once __DIR__ . '/../functions/auth.php';
require_once __DIR__ . '/../functions/question_picker.php';
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

// 获取题目
$question = QuestionPicker::getPracticeQuestion($_SESSION['user_id'], $bankId);
if (!$question) {
    echo "<script>alert('该题库暂无题目，请联系管理员添加题目！'); window.location.href='dashboard.php';</script>";
    exit;
}

$options = json_decode($question['options_json'], true);
$isMultiple = ($question['type'] == 2);

$pageTitle = '练习模式 - ' . htmlspecialchars($bank['name']);

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
    .option-item.correct {
        background-color: #d1e7dd;
        border-color: #198754;
    }
    .option-item.incorrect {
        background-color: #f8d7da;
        border-color: #dc3545;
    }
    .option-item input[type="checkbox"],
    .option-item input[type="radio"] {
        margin-right: 10px;
    }
    .answer-feedback {
        animation: fadeIn 0.5s;
    }
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(-10px); }
        to { opacity: 1; transform: translateY(0); }
    }
    #submitBtn {
        position: relative;
        min-height: 50px;
    }
    .spinner-border {
        width: 1.2rem;
        height: 1.2rem;
        border-width: 0.15em;
    }
    .analysis-content {
        background-color: #f8f9fa;
        padding: 15px;
        border-radius: 8px;
        border-left: 4px solid #0dcaf0;
    }
</style>
HTML;

include __DIR__ . '/../includes/header.php';
?>

<div class="bg-dark text-white py-2">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-3">
                <span class="badge bg-info">练习模式</span> - <strong><?php echo htmlspecialchars($bank['name']); ?></strong>
            </div>
            <div class="col-md-3 text-center">无时间限制</div>
            <div class="col-md-6 text-end">
                <a href="dashboard.php" class="btn btn-sm btn-secondary" onclick="return confirm('确定要结束练习吗？')">结束练习</a>
            </div>
        </div>
    </div>
</div>

<div class="container mt-4">
    <div class="row">
        <div class="col-lg-8 offset-lg-2">
            <!-- 题目区域 -->
            <div class="card mb-4">
                <div class="card-body">
					<h5 class="card-title mb-4">
						<span class="badge bg-secondary ms-2">
							<?php 
							$typeNames = [1 => '单选题', 2 => '多选题', 3 => '判断题'];
							echo $typeNames[$question['type']] ?? '未知类型';
							?>
						</span>
						<?php echo htmlspecialchars($question['stem']); ?>
					</h5>
                    
                    <form id="answerForm">
                        <input type="hidden" name="question_id" value="<?php echo $question['id']; ?>">
                        <input type="hidden" name="bank_id" value="<?php echo $bankId; ?>">
                        
                        <div class="options-list mb-4" id="optionsContainer">
                            <?php if ($isMultiple): ?>
                                <!-- 多选题 -->
                                <?php foreach ($options as $index => $option): ?>
                                <div class="option-item" onclick="toggleOption(this)">
                                    <input class="form-check-input" type="checkbox" 
                                           name="answer[]" value="<?php echo chr(65 + $index); ?>" 
                                           id="option<?php echo $index; ?>"
                                           style="pointer-events: none;">
                                    <label class="form-check-label option-label" for="option<?php echo $index; ?>">
                                        <?php echo htmlspecialchars($option); ?>
                                    </label>
                                </div>
                                <?php endforeach; ?>
                            <?php elseif ($question['type'] == 3): ?>
                                <!-- 判断题 -->
                                <div class="option-item" onclick="selectSingleOption(this, '对')">
                                    <input class="form-check-input" type="radio" name="answer" value="对" id="optionTrue" style="pointer-events: none;">
                                    <label class="form-check-label option-label" for="optionTrue">对</label>
                                </div>
                                <div class="option-item" onclick="selectSingleOption(this, '错')">
                                    <input class="form-check-input" type="radio" name="answer" value="错" id="optionFalse" style="pointer-events: none;">
                                    <label class="form-check-label option-label" for="optionFalse">错</label>
                                </div>
                            <?php else: ?>
                                <!-- 单选题 -->
                                <?php foreach ($options as $index => $option): ?>
                                <div class="option-item" onclick="selectSingleOption(this, '<?php echo chr(65 + $index); ?>')">
                                    <input class="form-check-input" type="radio" name="answer" 
                                           value="<?php echo chr(65 + $index); ?>" id="option<?php echo $index; ?>" style="pointer-events: none;">
                                    <label class="form-check-label option-label" for="option<?php echo $index; ?>">
                                        <?php echo htmlspecialchars($option); ?>
                                    </label>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <button type="button" class="btn btn-primary btn-lg" id="submitBtn" onclick="submitAnswer()" disabled>
                                <span id="submitText">提交答案</span>
                                <span id="submitSpinner" class="spinner-border spinner-border-sm d-none" role="status"></span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- 答案反馈区域（初始隐藏） -->
            <div id="feedbackArea" class="card mb-4 answer-feedback" style="display: none;">
                <div class="card-header" id="feedbackHeader">
                    <!-- 通过JS动态设置 -->
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <h6>你的答案：</h6>
                        <p id="userAnswer" class="fst-italic"></p>
                    </div>
                    
                    <div class="mb-3">
                        <h6>正确答案：</h6>
                        <p id="correctAnswer" class="fw-bold text-success"></p>
                    </div>
                    
                    <div class="mb-3">
                        <h6>题目解析：</h6>
                        <div class="analysis-content" id="questionAnalysis">
                            <!-- 解析内容通过JS动态设置 -->
                        </div>
                    </div>
                    
                    <div class="d-grid">
                        <button type="button" class="btn btn-success btn-lg" onclick="nextQuestion()" id="nextBtn">
                            继续下一题
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- 统计信息 -->
            <div class="card">
                <div class="card-body text-center">
                    <small class="text-muted">
                        提示：练习模式会优先抽取你错误率较高、久未复习的题目。
                    </small>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // 当前答案状态
    let hasAnswer = false;
    let isSubmitted = false;
    let currentQuestionId = <?php echo $question['id']; ?>;
    
    // 处理多选题选项点击
    function toggleOption(element) {
        if (isSubmitted) return;
        
        const checkbox = element.querySelector('input[type="checkbox"]');
        checkbox.checked = !checkbox.checked;
        
        if (checkbox.checked) {
            element.classList.add('selected');
        } else {
            element.classList.remove('selected');
        }
        
        checkAnswerStatus();
    }
    
    // 处理单选题/判断题选项点击
    function selectSingleOption(element, value) {
        if (isSubmitted) return;
        
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
        
        checkAnswerStatus();
    }
    
    // 检查是否有答案，启用/禁用提交按钮
    function checkAnswerStatus() {
        const form = document.getElementById('answerForm');
        const formData = new FormData(form);
        
        let hasSelection = false;
        
        // 检查单选/判断题
        if (formData.get('answer')) {
            hasSelection = true;
        }
        
        // 检查多选题
        const checkboxes = document.querySelectorAll('input[type="checkbox"]:checked');
        if (checkboxes.length > 0) {
            hasSelection = true;
        }
        
        if (hasSelection && !isSubmitted) {
            hasAnswer = true;
            document.getElementById('submitBtn').disabled = false;
        } else {
            hasAnswer = false;
            document.getElementById('submitBtn').disabled = true;
        }
    }
    
    // 提交答案
    async function submitAnswer() {
        if (!hasAnswer || isSubmitted) return;
        
        // 禁用提交按钮，显示加载状态
        const submitBtn = document.getElementById('submitBtn');
        const submitText = document.getElementById('submitText');
        const submitSpinner = document.getElementById('submitSpinner');
        
        submitBtn.disabled = true;
        submitText.textContent = '提交中...';
        submitSpinner.classList.remove('d-none');
        
        // 收集表单数据
        const form = document.getElementById('answerForm');
        const formData = new FormData(form);
        const data = {};
        
        // 处理表单数据
        for (let [key, value] of formData.entries()) {
            if (key === 'answer[]') {
                if (!data['answer']) data['answer'] = [];
                data['answer'].push(value);
            } else {
                data[key] = value;
            }
        }
        
        // 处理多选题答案（排序并连接）
        if (data.answer && Array.isArray(data.answer)) {
            data.answer = data.answer.sort().join('');
        }
        
        try {
            console.log('提交数据:', data);
            
            // 使用正确的API路径
            const response = await fetch('../api/submit_practice.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(data)
            });
            
            console.log('响应状态:', response.status);
            
            if (!response.ok) {
                throw new Error('HTTP错误: ' + response.status);
            }
            
            const result = await response.json();
            console.log('响应结果:', result);
            
            // 恢复按钮状态
            submitText.textContent = '已提交';
            submitSpinner.classList.add('d-none');
            
            if (result.success) {
                isSubmitted = true;
                showFeedback(result);
            } else {
                alert('提交失败：' + (result.error || '未知错误'));
                submitBtn.disabled = false;
                submitText.textContent = '提交答案';
            }
        } catch (error) {
            console.error('提交错误详情:', error);
            
            // 如果所有尝试都失败，显示错误
            alert('网络错误，请检查API接口是否正常。错误详情请查看控制台(F12)');
            
            // 恢复按钮状态
            submitBtn.disabled = false;
            submitText.textContent = '提交答案';
            submitSpinner.classList.add('d-none');
        }
    }
    
    // 显示反馈信息
    function showFeedback(result) {
        const feedbackArea = document.getElementById('feedbackArea');
        const feedbackHeader = document.getElementById('feedbackHeader');
        const userAnswer = document.getElementById('userAnswer');
        const correctAnswer = document.getElementById('correctAnswer');
        const questionAnalysis = document.getElementById('questionAnalysis');
        
        // 设置反馈头部
        if (result.is_correct) {
            feedbackHeader.className = 'card-header bg-success text-white';
            feedbackHeader.innerHTML = '<h5 class="mb-0">✅ 回答正确！</h5>';
        } else {
            feedbackHeader.className = 'card-header bg-danger text-white';
            feedbackHeader.innerHTML = '<h5 class="mb-0">❌ 回答错误</h5>';
        }
        
        // 显示用户答案和正确答案
        userAnswer.textContent = result.user_answer || '未答';
        correctAnswer.textContent = result.correct_answer;
        
        // 显示解析
        if (result.analysis && result.analysis.trim() !== '') {
            questionAnalysis.innerHTML = result.analysis;
        } else {
            questionAnalysis.innerHTML = '<em class="text-muted">本题暂无解析</em>';
        }
        
        // 高亮显示正确和错误的选项
        highlightOptions(result.correct_answer, result.user_answer, result.is_correct);
        
        // 显示反馈区域
        feedbackArea.style.display = 'block';
        
        // 滚动到反馈区域
        feedbackArea.scrollIntoView({ behavior: 'smooth' });
    }
    
    // 高亮显示选项
    function highlightOptions(correctAnswer, userAnswer, isCorrect) {
        const options = document.querySelectorAll('.option-item');
        
        // 首先重置所有选项样式
        options.forEach(option => {
            option.classList.remove('correct', 'incorrect');
        });
        
        // 处理判断题
        if (correctAnswer === '对' || correctAnswer === '错') {
            const trueOption = document.querySelector('input[value="对"]')?.closest('.option-item');
            const falseOption = document.querySelector('input[value="错"]')?.closest('.option-item');
            
            if (correctAnswer === '对' && trueOption) {
                trueOption.classList.add('correct');
                if (userAnswer === '错' && falseOption) {
                    falseOption.classList.add('incorrect');
                }
            } else if (correctAnswer === '错' && falseOption) {
                falseOption.classList.add('correct');
                if (userAnswer === '对' && trueOption) {
                    trueOption.classList.add('incorrect');
                }
            }
            return;
        }
        
        // 处理选择题
        const correctAnswers = correctAnswer.split('');
        const userAnswers = userAnswer ? userAnswer.split('') : [];
        
        // 标记正确答案
        correctAnswers.forEach(answer => {
            const optionElement = document.querySelector('input[value="' + answer + '"]')?.closest('.option-item');
            if (optionElement) {
                optionElement.classList.add('correct');
            }
        });
        
        // 标记错误答案（如果用户答错了）
        if (!isCorrect && userAnswer) {
            userAnswers.forEach(answer => {
                if (!correctAnswers.includes(answer)) {
                    const optionElement = document.querySelector('input[value="' + answer + '"]')?.closest('.option-item');
                    if (optionElement) {
                        optionElement.classList.add('incorrect');
                    }
                }
            });
        }
    }
    
    function nextQuestion() {
        // 重新加载页面获取新题目
        window.location.reload();
    }
    
    // 页面卸载时提示
    window.onbeforeunload = function() {
        if (!isSubmitted) {
            return '确定要离开吗？当前题目的答案尚未提交。';
        }
    };
    
    // 初始检查答案状态
    checkAnswerStatus();
</script>

<?php
include __DIR__ . '/../includes/footer.php';
?>