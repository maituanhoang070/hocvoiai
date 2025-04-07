<?php
// File: includes/content/assessment.php
// Verificar que el usuario haya iniciado sesión
requireLogin();

// Obtener ID de la evaluación
$assessment_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$assessment_id) {
    // Redirigir si no hay ID
    header('Location: index.php?page=study');
    exit;
}

// Obtener detalles de la evaluación
$stmt = $conn->prepare("
    SELECT a.*, t.name as topic_name, t.id as topic_id, s.name as subject_name, s.id as subject_id
    FROM self_assessments a
    JOIN study_topics t ON a.topic_id = t.id
    JOIN study_subjects s ON t.subject_id = s.id
    WHERE a.id = ?
");
$stmt->bind_param("i", $assessment_id);
$stmt->execute();
$assessment = $stmt->get_result()->fetch_assoc();

if (!$assessment) {
    // Evaluación no encontrada
    header('Location: index.php?page=study');
    exit;
}

// Verificar si el usuario ya realizó este examen
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT COUNT(*) FROM assessment_results WHERE user_id = ? AND assessment_id = ?");
$stmt->bind_param("ii", $user_id, $assessment_id);
$stmt->execute();
$already_taken = $stmt->get_result()->fetch_row()[0] > 0;

// Obtener preguntas
$stmt = $conn->prepare("SELECT * FROM assessment_questions WHERE assessment_id = ? ORDER BY id");
$stmt->bind_param("i", $assessment_id);
$stmt->execute();
$questions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Contar el puntaje máximo posible
$max_score = 0;
foreach ($questions as $question) {
    $max_score += $question['points'];
}
?>

<div class="max-w-4xl mx-auto">
    <!-- Cabecera de la evaluación -->
    <div class="card rounded-xl p-6 mb-6">
        <div class="flex items-center mb-4">
            <a href="index.php?page=study&topic=<?php echo $assessment['topic_id']; ?>" class="mr-3 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300">
                <i class="fas fa-arrow-left"></i>
            </a>
            <div>
                <h1 class="text-2xl font-bold"><?php echo htmlspecialchars($assessment['title']); ?></h1>
                <div class="flex items-center mt-1">
                    <span><?php echo htmlspecialchars($assessment['subject_name']); ?></span>
                    <span class="mx-2">•</span>
                    <span><?php echo htmlspecialchars($assessment['topic_name']); ?></span>
                </div>
            </div>
        </div>
        
        <p class="mb-4"><?php echo nl2br(htmlspecialchars($assessment['description'])); ?></p>
        
        <div class="flex flex-wrap gap-4 mb-4">
            <div class="flex items-center">
                <i class="fas fa-clock text-gray-500 dark:text-gray-400 mr-2"></i>
                <span>Thời gian: <?php echo $assessment['time_limit_minutes']; ?> phút</span>
            </div>
            <div class="flex items-center">
                <i class="fas fa-question-circle text-gray-500 dark:text-gray-400 mr-2"></i>
                <span>Số câu hỏi: <?php echo count($questions); ?></span>
            </div>
            <div class="flex items-center">
                <i class="fas fa-award text-gray-500 dark:text-gray-400 mr-2"></i>
                <span>Điểm đạt: <?php echo $assessment['passing_score']; ?>%</span>
            </div>
        </div>
        
        <?php if ($already_taken): ?>
        <div class="bg-yellow-50 dark:bg-yellow-900/20 text-yellow-800 dark:text-yellow-300 p-4 rounded-lg mb-4">
            <div class="flex">
                <div class="flex-shrink-0">
                    <i class="fas fa-exclamation-circle text-yellow-500 dark:text-yellow-400"></i>
                </div>
                <div class="ml-3">
                    <p>Bạn đã làm bài kiểm tra này trước đó. Kết quả mới sẽ ghi đè kết quả cũ.</p>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <button id="start-assessment" class="btn btn-primary py-2 px-6">
            <i class="fas fa-play mr-2"></i>Bắt đầu làm bài
        </button>
    </div>
    
    <!-- Contenedor de la evaluación (oculto inicialmente) -->
    <div id="assessment-container" class="hidden">
        <div class="card rounded-xl p-6 mb-6">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-xl font-semibold">
                    <span id="current-question">1</span> / <span id="total-questions"><?php echo count($questions); ?></span>
                </h2>
                <div class="flex items-center">
                    <i class="fas fa-clock mr-2"></i>
                    <span id="timer">00:00</span>
                </div>
            </div>
            
            <form id="assessment-form">
                <?php foreach ($questions as $index => $question): ?>
                <div class="question-item <?php echo $index === 0 ? '' : 'hidden'; ?>" data-question="<?php echo $index + 1; ?>">
                    <div class="mb-4">
                        <h3 class="font-medium text-lg"><?php echo nl2br(htmlspecialchars($question['question'])); ?></h3>
                        <div class="text-sm text-gray-500 dark:text-gray-400">
                            Điểm: <?php echo $question['points']; ?>
                        </div>
                    </div>
                    
                    <input type="hidden" name="question_id[<?php echo $question['id']; ?>]" value="<?php echo $question['id']; ?>">
                    
                    <?php if ($question['question_type'] === 'multiple_choice'): ?>
                        <?php 
                        $options = json_decode($question['options'], true);
                        shuffle($options); // Mezclar opciones para evitar trampa
                        ?>
                        <div class="space-y-3 mb-6">
                            <?php foreach ($options as $option): ?>
                            <label class="flex items-start">
                                <div class="flex items-center h-5">
                                    <input type="radio" name="answer[<?php echo $question['id']; ?>]" value="<?php echo htmlspecialchars($option); ?>" class="focus:ring-indigo-500 h-4 w-4 text-indigo-600 border-gray-300">
                                </div>
                                <div class="ml-3 text-sm">
                                    <?php echo htmlspecialchars($option); ?>
                                </div>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    <?php elseif ($question['question_type'] === 'true_false'): ?>
                        <div class="space-y-3 mb-6">
                            <label class="flex items-start">
                                <div class="flex items-center h-5">
                                    <input type="radio" name="answer[<?php echo $question['id']; ?>]" value="true" class="focus:ring-indigo-500 h-4 w-4 text-indigo-600 border-gray-300">
                                </div>
                                <div class="ml-3 text-sm">
                                    Đúng
                                </div>
                            </label>
                            <label class="flex items-start">
                                <div class="flex items-center h-5">
                                    <input type="radio" name="answer[<?php echo $question['id']; ?>]" value="false" class="focus:ring-indigo-500 h-4 w-4 text-indigo-600 border-gray-300">
                                </div>
                                <div class="ml-3 text-sm">
                                    Sai
                                </div>
                            </label>
                        </div>
                    <?php elseif ($question['question_type'] === 'short_answer'): ?>
                        <div class="mb-6">
                            <textarea name="answer[<?php echo $question['id']; ?>]" rows="3" class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                        </div>
                    <?php endif; ?>
                    
                    <div class="flex justify-between">
                        <button type="button" class="prev-question btn btn-secondary py-2 px-4 <?php echo $index === 0 ? 'invisible' : ''; ?>">
                            <i class="fas fa-chevron-left mr-2"></i>Câu trước
                        </button>
                        <?php if ($index === count($questions) - 1): ?>
                        <button type="submit" class="submit-assessment btn btn-primary py-2 px-4">
                            <i class="fas fa-check mr-2"></i>Nộp bài
                        </button>
                        <?php else: ?>
                        <button type="button" class="next-question btn btn-primary py-2 px-4">
                            Câu tiếp theo<i class="fas fa-chevron-right ml-2"></i>
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
                
                <input type="hidden" name="assessment_id" value="<?php echo $assessment_id; ?>">
                <input type="hidden" name="time_taken" id="time-taken" value="0">
                <input type="hidden" name="max_score" value="<?php echo $max_score; ?>">
            </form>
        </div>
        
        <!-- Navegación de preguntas -->
        <div class="card rounded-xl p-4">
            <h3 class="font-medium mb-3">Câu hỏi</h3>
            <div class="flex flex-wrap gap-2">
                <?php for ($i = 1; $i <= count($questions); $i++): ?>
                <button class="question-nav-btn h-8 w-8 rounded-full text-sm flex items-center justify-center <?php echo $i === 1 ? 'bg-indigo-600 text-white' : 'bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300'; ?>" data-question="<?php echo $i; ?>">
                    <?php echo $i; ?>
                </button>
                <?php endfor; ?>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const startBtn = document.getElementById('start-assessment');
    const assessmentContainer = document.getElementById('assessment-container');
    const form = document.getElementById('assessment-form');
    const timerEl = document.getElementById('timer');
    const timeTakenInput = document.getElementById('time-taken');
    const currentQuestionEl = document.getElementById('current-question');
    const totalQuestionsEl = document.getElementById('total-questions');
    const questionItems = document.querySelectorAll('.question-item');
    const navBtns = document.querySelectorAll('.question-nav-btn');
    
    let timerId;
    let secondsElapsed = 0;
    let timeLimit = <?php echo $assessment['time_limit_minutes'] * 60; ?>;
    let currentQuestion = 1;
    let totalQuestions = <?php echo count($questions); ?>;
    
    // Función para formatear el tiempo
    function formatTime(seconds) {
        const mins = Math.floor(seconds / 60).toString().padStart(2, '0');
        const secs = (seconds % 60).toString().padStart(2, '0');
        return `${mins}:${secs}`;
    }
    
    // Función para iniciar el temporizador
    function startTimer() {
        timerId = setInterval(function() {
            secondsElapsed++;
            timeTakenInput.value = secondsElapsed;
            
            // Actualizar el temporizador en la UI
            timerEl.textContent = formatTime(secondsElapsed);
            
            // Verificar si se acabó el tiempo
            if (timeLimit && secondsElapsed >= timeLimit) {
                clearInterval(timerId);
                // Enviar formulario automáticamente
                form.dispatchEvent(new Event('submit'));
            }
        }, 1000);
    }
    
    // Función para mostrar una pregunta específica
    function showQuestion(questionNumber) {
        // Ocultar todas las preguntas
        questionItems.forEach(item => {
            item.classList.add('hidden');
        });
        
        // Mostrar la pregunta actual
        document.querySelector(`.question-item[data-question="${questionNumber}"]`).classList.remove('hidden');
        
        // Actualizar navegación
        currentQuestionEl.textContent = questionNumber;
        
        // Actualizar botones de navegación
        navBtns.forEach(btn => {
            btn.classList.remove('bg-indigo-600', 'text-white');
            btn.classList.add('bg-gray-200', 'dark:bg-gray-700', 'text-gray-700', 'dark:text-gray-300');
            
            // Marcar si la pregunta tiene respuesta
            const question = document.querySelector(`.question-item[data-question="${btn.dataset.question}"]`);
            const questionId = question.querySelector('input[type="hidden"][name^="question_id"]').value;
            const answered = isQuestionAnswered(questionId);
            
            if (answered) {
                btn.classList.add('bg-green-500', 'text-white');
                btn.classList.remove('bg-gray-200', 'dark:bg-gray-700', 'text-gray-700', 'dark:text-gray-300');
            }
            
            // Resaltar pregunta actual
            if (parseInt(btn.dataset.question) === questionNumber) {
                btn.classList.add('bg-indigo-600', 'text-white');
                btn.classList.remove('bg-gray-200', 'dark:bg-gray-700', 'text-gray-700', 'dark:text-gray-300', 'bg-green-500');
            }
        });
        
        currentQuestion = questionNumber;
    }
    
    // Verificar si una pregunta ha sido respondida
    function isQuestionAnswered(questionId) {
        const radios = form.querySelectorAll(`input[type="radio"][name="answer[${questionId}]"]`);
        const textarea = form.querySelector(`textarea[name="answer[${questionId}]"]`);
        
        if (radios.length > 0) {
            return Array.from(radios).some(radio => radio.checked);
        } else if (textarea) {
            return textarea.value.trim() !== '';
        }
        
        return false;
    }
    
    // Iniciar la evaluación
    startBtn.addEventListener('click', function() {
        startBtn.parentElement.classList.add('hidden');
        assessmentContainer.classList.remove('hidden');
        startTimer();
    });
    
    // Navegación entre preguntas
    document.querySelectorAll('.next-question').forEach(btn => {
        btn.addEventListener('click', function() {
            showQuestion(currentQuestion + 1);
        });
    });
    
    document.querySelectorAll('.prev-question').forEach(btn => {
        btn.addEventListener('click', function() {
            showQuestion(currentQuestion - 1);
        });
    });
    
    // Navegación directa a preguntas
    navBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            showQuestion(parseInt(this.dataset.question));
        });
    });
    
    // Marcar preguntas como respondidas
    form.addEventListener('change', function(e) {
        const target = e.target;
        if (target.type === 'radio' || target.tagName === 'TEXTAREA') {
            // Actualizar el estado de los botones de navegación
            const questionItem = target.closest('.question-item');
            if (questionItem) {
                const questionNumber = parseInt(questionItem.dataset.question);
                navBtns.forEach(btn => {
                    if (parseInt(btn.dataset.question) === questionNumber) {
                        btn.classList.add('bg-green-500', 'text-white');
                        btn.classList.remove('bg-gray-200', 'dark:bg-gray-700', 'text-gray-700', 'dark:text-gray-300', 'bg-indigo-600');
                    }
                });
            }
        }
    });
    
    // Enviar formulario
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        // Detener el temporizador
        clearInterval(timerId);
        
        // Confirmar envío
        if (confirm('Bạn có chắc chắn muốn nộp bài?')) {
            // Crear FormData
            const formData = new FormData(form);
            
            // Enviar datos al servidor
            fetch('includes/api/submit_assessment.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Redirigir a la página de resultados
                    window.location.href = `index.php?page=assessment_result&id=${data.result_id}`;
                } else {
                    alert('Có lỗi xảy ra: ' + data.message);
                    // Reiniciar el temporizador si hay error
                    startTimer();
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Có lỗi xảy ra khi gửi bài, vui lòng thử lại.');
                // Reiniciar el temporizador si hay error
                startTimer();
            });
        } else {
            // Si el usuario cancela, reiniciar el temporizador
            startTimer();
        }
    });
});
</script>