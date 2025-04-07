<?php
// File: includes/content/assessment_result.php
// Verificar que el usuario haya iniciado sesión
requireLogin();

// Obtener ID del resultado
$result_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$result_id) {
    // Redirigir si no hay ID
    header('Location: index.php?page=study');
    exit;
}

// Obtener detalles del resultado
$stmt = $conn->prepare("
    SELECT r.*, a.title, a.topic_id, a.passing_score, t.name as topic_name, s.name as subject_name, s.id as subject_id
    FROM assessment_results r
    JOIN self_assessments a ON r.assessment_id = a.id
    JOIN study_topics t ON a.topic_id = t.id
    JOIN study_subjects s ON t.subject_id = s.id
    WHERE r.id = ? AND r.user_id = ?
");
$stmt->bind_param("ii", $result_id, $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();

if (!$result) {
    // Resultado no encontrado o no pertenece al usuario
    header('Location: index.php?page=study');
    exit;
}

// Calcular porcentaje y tiempo
$percentage = ($result['max_score'] > 0) ? ($result['score'] / $result['max_score']) * 100 : 0;
$minutes = floor($result['time_taken_seconds'] / 60);
$seconds = $result['time_taken_seconds'] % 60;
?>

<div class="max-w-4xl mx-auto">
    <div class="card rounded-xl p-6 mb-6">
        <div class="flex items-center mb-6">
            <a href="index.php?page=study&topic=<?php echo $result['topic_id']; ?>" class="mr-3 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300">
                <i class="fas fa-arrow-left"></i>
            </a>
            <div>
                <h1 class="text-2xl font-bold">Kết quả bài kiểm tra</h1>
                <div class="flex items-center mt-1">
                    <span><?php echo htmlspecialchars($result['title']); ?></span>
                    <span class="mx-2">•</span>
                    <span><?php echo htmlspecialchars($result['subject_name']); ?> - <?php echo htmlspecialchars($result['topic_name']); ?></span>
                </div>
            </div>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <!-- Puntuación -->
            <div class="bg-indigo-50 dark:bg-indigo-900/20 p-6 rounded-xl flex flex-col items-center justify-center">
                <div class="relative">
                    <svg class="w-32 h-32" viewBox="0 0 36 36">
                        <path class="stroke-current text-indigo-100 dark:text-indigo-900" stroke-width="2" fill="none" d="M18 2.0845 a 15.9155 15.9155 0 0 0 0 31.831 a 15.9155 15.9155 0 0 0 0 -31.831"></path>
                        <path class="stroke-current <?php echo $result['passed'] ? 'text-green-500' : 'text-red-500'; ?>" stroke-width="2" fill="none" stroke-linecap="round" stroke-dasharray="<?php echo $percentage; ?>, 100" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"></path>
                        <text x="18" y="20.5" class="fill-current <?php echo $result['passed'] ? 'text-green-700 dark:text-green-300' : 'text-red-700 dark:text-red-300'; ?>" font-size="8" text-anchor="middle"><?php echo round($percentage); ?>%</text>
                    </svg>
                </div>
                <div class="text-lg font-medium mt-3">Điểm số: <?php echo $result['score']; ?>/<?php echo $result['max_score']; ?></div>
                <div class="<?php echo $result['passed'] ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'; ?> font-medium mt-1">
                    <?php echo $result['passed'] ? 'Đạt' : 'Chưa đạt'; ?>
                </div>
            </div>
            
            <!-- Tiempo y fecha -->
            <div class="bg-blue-50 dark:bg-blue-900/20 p-6 rounded-xl flex flex-col items-center justify-center">
                <div class="text-4xl text-blue-600 dark:text-blue-400 mb-2">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="text-lg font-medium">Thời gian làm bài</div>
                <div class="text-2xl font-bold mt-2"><?php printf("%02d:%02d", $minutes, $seconds); ?></div>
                <div class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                    <?php echo date('d/m/Y H:i', strtotime($result['completed_at'])); ?>
                </div>
            </div>
            
            <!-- Recompensa -->
            <div class="bg-purple-50 dark:bg-purple-900/20 p-6 rounded-xl flex flex-col items-center justify-center">
                <div class="text-4xl text-purple-600 dark:text-purple-400 mb-2">
                    <i class="fas fa-award"></i>
                </div>
                <div class="text-lg font-medium">Điểm đạt tối thiểu</div>
                <div class="text-2xl font-bold mt-2"><?php echo $result['passing_score']; ?>%</div>
                <?php if ($result['passed']): ?>
                <div class="text-green-600 dark:text-green-400 text-sm mt-3">
                    <i class="fas fa-check-circle mr-1"></i>Hoàn thành tốt!
                </div>
                <?php else: ?>
                <div class="text-orange-600 dark:text-orange-400 text-sm mt-3">
                    <i class="fas fa-info-circle mr-1"></i>Hãy thử lại!
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Acciones -->
        <div class="flex flex-col sm:flex-row gap-4 justify-center">
            <a href="index.php?page=assessment&id=<?php echo $result['assessment_id']; ?>" class="btn btn-primary py-2 px-6 text-center">
                <i class="fas fa-redo mr-2"></i>Làm lại bài kiểm tra
            </a>
            <a href="index.php?page=study&topic=<?php echo $result['topic_id']; ?>" class="btn btn-secondary py-2 px-6 text-center">
                <i class="fas fa-book mr-2"></i>Quay lại học tập
            </a>
        </div>
    </div>
    
    <!-- Sugerencias y recomendaciones -->
    <div class="card rounded-xl p-6">
        <h2 class="text-xl font-semibold mb-4">Gợi ý tiếp theo</h2>
        
        <?php if ($result['passed']): ?>
        <div class="bg-green-50 dark:bg-green-900/20 border-l-4 border-green-500 p-4 mb-4">
            <div class="flex">
                <div class="flex-shrink-0">
                    <i class="fas fa-check-circle text-green-500"></i>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-green-700 dark:text-green-300">Bạn đã hoàn thành tốt bài kiểm tra này!</p>
                </div>
            </div>
        </div>
        
        <p class="mb-4">Dưới đây là một số gợi ý để tiếp tục học tập:</p>
        
        <?php
        // Obtener temas avanzados en la misma asignatura
        $stmt = $conn->prepare("
            SELECT t.id, t.name, t.difficulty
            FROM study_topics t
            WHERE t.subject_id = ? AND t.id != ? AND t.difficulty >= 'intermediate'
            ORDER BY RAND()
            LIMIT 3
        ");
        $stmt->bind_param("ii", $result['subject_id'], $result['topic_id']);
        $stmt->execute();
        $suggested_topics = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        ?>
        
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <?php foreach ($suggested_topics as $topic): ?>
            <a href="index.php?page=study&topic=<?php echo $topic['id']; ?>" class="border border-gray-200 dark:border-gray-700 rounded-lg p-4 hover:bg-gray-50 dark:hover:bg-gray-800 transition">
                <h3 class="font-medium"><?php echo htmlspecialchars($topic['name']); ?></h3>
                <div class="text-xs px-2 py-0.5 rounded-full mt-2 inline-block
                    <?php 
                    switch($topic['difficulty']) {
                        case 'beginner': echo 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400'; break;
                        case 'intermediate': echo 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400'; break;
                        case 'advanced': echo 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400'; break;
                    }
                    ?>">
                    <?php 
                    switch($topic['difficulty']) {
                        case 'beginner': echo 'Cơ bản'; break;
                        case 'intermediate': echo 'Trung bình'; break;
                        case 'advanced': echo 'Nâng cao'; break;
                    }
                    ?>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="bg-yellow-50 dark:bg-yellow-900/20 border-l-4 border-yellow-500 p-4 mb-4">
            <div class="flex">
                <div class="flex-shrink-0">
                    <i class="fas fa-exclamation-circle text-yellow-500"></i>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-yellow-700 dark:text-yellow-300">Bạn cần học thêm để đạt điểm tốt hơn.</p>
                </div>
            </div>
        </div>
        
        <p class="mb-4">Dưới đây là một số gợi ý để cải thiện:</p>
        
        <ul class="space-y-3 mb-6">
            <li class="flex items-start">
                <div class="flex-shrink-0 w-5 h-5 rounded-full bg-blue-500 flex items-center justify-center text-white text-xs mt-0.5">
                    <i class="fas fa-book"></i>
                </div>
                <div class="ml-3">
                    <h3 class="font-medium">Ôn tập lại tài liệu</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Xem lại các tài liệu học tập và ví dụ để nắm vững hơn.</p>
                    <a href="index.php?page=study&topic=<?php echo $result['topic_id']; ?>#materials" class="text-indigo-600 dark:text-indigo-400 text-sm mt-1 inline-block">
                        <i class="fas fa-arrow-right mr-1"></i>Xem tài liệu
                    </a>
                </div>
            </li>
            <li class="flex items-start">
                <div class="flex-shrink-0 w-5 h-5 rounded-full bg-green-500 flex items-center justify-center text-white text-xs mt-0.5">
                    <i class="fas fa-pencil-alt"></i>
                </div>
                <div class="ml-3">
                    <h3 class="font-medium">Luyện tập thêm</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Làm các bài tập luyện tập để làm quen với dạng bài.</p>
                    <a href="index.php?page=study&topic=<?php echo $result['topic_id']; ?>#exercises" class="text-indigo-600 dark:text-indigo-400 text-sm mt-1 inline-block">
                        <i class="fas fa-arrow-right mr-1"></i>Làm bài tập
                    </a>
                </div>
            </li>
            <li class="flex items-start">
                <div class="flex-shrink-0 w-5 h-5 rounded-full bg-purple-500 flex items-center justify-center text-white text-xs mt-0.5">
                    <i class="fas fa-clone"></i>
                </div>
                <div class="ml-3">
                    <h3 class="font-medium">Học với flashcards</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Sử dụng flashcards để nhớ các khái niệm và công thức quan trọng.</p>
                    <a href="index.php?page=study&topic=<?php echo $result['topic_id']; ?>#flashcards" class="text-indigo-600 dark:text-indigo-400 text-sm mt-1 inline-block">
                        <i class="fas fa-arrow-right mr-1"></i>Xem flashcards
                    </a>
                </div>
            </li>
        </ul>
        <?php endif; ?>
    </div>
</div>