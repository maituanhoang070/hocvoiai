<?php
// File: includes/content/study.php
requireLogin();
$subject_id = isset($_GET['subject']) ? intval($_GET['subject']) : null;
$topic_id = isset($_GET['topic']) ? intval($_GET['topic']) : null;

// Obtener todas las asignaturas para la navegación
$stmt = $conn->prepare("SELECT id, name, icon, description FROM study_subjects ORDER BY name");
$stmt->execute();
$subjects = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Si se especificó un tema, obtener sus detalles
$current_topic = null;
$current_subject = null;
$materials = [];
$exercises = [];
$flashcards = [];
$assessments = [];

if ($topic_id) {
    $stmt = $conn->prepare("
        SELECT t.*, s.name as subject_name, s.icon as subject_icon 
        FROM study_topics t
        JOIN study_subjects s ON t.subject_id = s.id
        WHERE t.id = ?
    ");
    $stmt->bind_param("i", $topic_id);
    $stmt->execute();
    $current_topic = $stmt->get_result()->fetch_assoc();
    
    if ($current_topic) {
        $current_subject = [
            'id' => $current_topic['subject_id'],
            'name' => $current_topic['subject_name'],
            'icon' => $current_topic['subject_icon']
        ];
        
        $stmt = $conn->prepare("SELECT * FROM study_materials WHERE topic_id = ? ORDER BY material_type, id");
        $stmt->bind_param("i", $topic_id);
        $stmt->execute();
        $materials = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        $stmt = $conn->prepare("SELECT * FROM practice_exercises WHERE topic_id = ? ORDER BY difficulty, id");
        $stmt->bind_param("i", $topic_id);
        $stmt->execute();
        $exercises = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        $stmt = $conn->prepare("SELECT * FROM study_flashcards WHERE topic_id = ? ORDER BY id");
        $stmt->bind_param("i", $topic_id);
        $stmt->execute();
        $flashcards = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        $stmt = $conn->prepare("SELECT * FROM self_assessments WHERE topic_id = ? ORDER BY id");
        $stmt->bind_param("i", $topic_id);
        $stmt->execute();
        $assessments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        if (isLoggedIn()) {
            $user_id = $_SESSION['user_id'];
            $stmt = $conn->prepare("SELECT id FROM user_study_progress WHERE user_id = ? AND topic_id = ?");
            $stmt->bind_param("ii", $user_id, $topic_id);
            $stmt->execute();
            $progress = $stmt->get_result()->fetch_assoc();
            
            if ($progress) {
                $stmt = $conn->prepare("UPDATE user_study_progress SET last_studied = NOW() WHERE id = ?");
                $stmt->bind_param("i", $progress['id']);
                $stmt->execute();
            } else {
                $stmt = $conn->prepare("INSERT INTO user_study_progress (user_id, topic_id, last_studied) VALUES (?, ?, NOW())");
                $stmt->bind_param("ii", $user_id, $topic_id);
                $stmt->execute();
            }
        }
    } else {
        $topic_id = null;
    }
} else if ($subject_id) {
    $stmt = $conn->prepare("SELECT * FROM study_subjects WHERE id = ?");
    $stmt->bind_param("i", $subject_id);
    $stmt->execute();
    $current_subject = $stmt->get_result()->fetch_assoc();
    
    if ($current_subject) {
        $stmt = $conn->prepare("SELECT * FROM study_topics WHERE subject_id = ? ORDER BY name");
        $stmt->bind_param("i", $subject_id);
        $stmt->execute();
        $topics = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    } else {
        $subject_id = null;
    }
}
?>

<div class="max-w-7xl mx-auto">
    <div class="flex flex-col md:flex-row gap-6">
        <!-- Sidebar de navegación -->
        <div class="w-full md:w-64 lg:w-72 md:flex-shrink-0">
            <div class="card rounded-xl p-4 mb-4">
                <h2 class="text-lg font-semibold mb-3" style="color: var(--primary-color)">
                    <i class="fas fa-book mr-2"></i>Môn học
                </h2>

                <!-- Dropdown cho thiết bị di động -->
                <div class="block md:hidden mb-4">
                    <select id="subjectDropdown" class="w-full p-2 border border-gray-200 dark:border-gray-700 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100">
                        <option value="">Chọn môn học</option>
                        <?php foreach ($subjects as $subject): ?>
                        <option value="<?php echo $subject['id']; ?>" <?php echo ($current_subject && $current_subject['id'] == $subject['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($subject['name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Danh sách dọc cho desktop -->
                <ul class="space-y-1 hidden md:block">
                    <?php foreach ($subjects as $subject): ?>
                    <li>
                        <a href="index.php?page=study&subject=<?php echo $subject['id']; ?>" 
                           class="flex items-center px-3 py-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 <?php echo ($current_subject && $current_subject['id'] == $subject['id']) ? 'bg-indigo-50 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-400 font-medium' : ''; ?>">
                            <i class="fas fa-<?php echo htmlspecialchars($subject['icon']); ?> mr-3 w-5 text-center"></i>
                            <span><?php echo htmlspecialchars($subject['name']); ?></span>
                        </a>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            
            <?php if (isLoggedIn()): ?>
            <div class="card rounded-xl p-4">
                <h2 class="text-lg font-semibold mb-3" style="color: var(--primary-color)">
                    <i class="fas fa-chart-line mr-2"></i>Tiến độ học tập
                </h2>
                
                <?php
                if (isLoggedIn()) {
                    $user_id = $_SESSION['user_id'];
                    $stmt = $conn->prepare("
                        SELECT t.name, s.name as subject_name, p.mastery_level, p.last_studied
                        FROM user_study_progress p
                        JOIN study_topics t ON p.topic_id = t.id
                        JOIN study_subjects s ON t.subject_id = s.id
                        WHERE p.user_id = ?
                        ORDER BY p.last_studied DESC
                        LIMIT 5
                    ");
                    $stmt->bind_param("i", $user_id);
                    $stmt->execute();
                    $progress_items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                    
                    if (count($progress_items) > 0):
                ?>
                <div class="space-y-3">
                    <?php foreach ($progress_items as $item): ?>
                    <div class="p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
                        <div class="flex justify-between items-center mb-1">
                            <div class="font-medium"><?php echo htmlspecialchars($item['name']); ?></div>
                            <div class="text-xs text-gray-500 dark:text-gray-400">
                                <?php echo date('d/m/Y', strtotime($item['last_studied'])); ?>
                            </div>
                        </div>
                        <div class="text-xs"><?php echo htmlspecialchars($item['subject_name']); ?></div>
                        <div class="mt-2 w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2.5">
                            <div class="bg-indigo-600 h-2.5 rounded-full" style="width: <?php echo min(100, max(5, $item['mastery_level'])); ?>%"></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="text-center py-3 text-gray-500 dark:text-gray-400">
                    <p>Bạn chưa có tiến độ học tập nào</p>
                </div>
                <?php 
                    endif;
                }
                ?>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Contenido principal -->
        <div class="flex-1">
            <?php if ($current_topic): ?>
            <!-- Vista de tema específico -->
            <div class="card rounded-xl p-6 mb-6">
                <div class="flex items-center mb-4">
                    <a href="index.php?page=study&subject=<?php echo $current_subject['id']; ?>" class="mr-3 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300">
                        <i class="fas fa-arrow-left"></i>
                    </a>
                    <div>
                        <h1 class="text-2xl font-bold"><?php echo htmlspecialchars($current_topic['name']); ?></h1>
                        <div class="flex items-center mt-1">
                            <i class="fas fa-<?php echo htmlspecialchars($current_subject['icon']); ?> mr-2"></i>
                            <span><?php echo htmlspecialchars($current_subject['name']); ?></span>
                            <span class="mx-2">•</span>
                            <span class="text-sm px-2 py-0.5 rounded-full 
                                <?php 
                                switch($current_topic['difficulty']) {
                                    case 'beginner': echo 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400'; break;
                                    case 'intermediate': echo 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400'; break;
                                    case 'advanced': echo 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400'; break;
                                }
                                ?>">
                                <?php 
                                switch($current_topic['difficulty']) {
                                    case 'beginner': echo 'Cơ bản'; break;
                                    case 'intermediate': echo 'Trung bình'; break;
                                    case 'advanced': echo 'Nâng cao'; break;
                                }
                                ?>
                            </span>
                        </div>
                    </div>
                </div>
                
                <div class="mb-4">
                    <p><?php echo nl2br(htmlspecialchars($current_topic['description'])); ?></p>
                </div>
                
                <!-- Pestañas de navegación -->
                <div class="border-b border-gray-200 dark:border-gray-700 mb-6">
                    <ul class="flex flex-wrap -mb-px">
                        <li class="mr-2">
                            <a href="#materials" class="inline-block py-2 px-4 border-b-2 border-indigo-600 font-medium text-indigo-600 dark:text-indigo-400 active-tab" data-target="materials-content">
                                <i class="fas fa-book-open mr-2"></i>Tài liệu
                            </a>
                        </li>
                        <li class="mr-2">
                            <a href="#exercises" class="inline-block py-2 px-4 border-b-2 border-transparent hover:border-gray-300 hover:text-gray-600 dark:hover:text-gray-300" data-target="exercises-content">
                                <i class="fas fa-pencil-alt mr-2"></i>Bài tập
                            </a>
                        </li>
                        <li class="mr-2">
                            <a href="#flashcards" class="inline-block py-2 px-4 border-b-2 border-transparent hover:border-gray-300 hover:text-gray-600 dark:hover:text-gray-300" data-target="flashcards-content">
                                <i class="fas fa-clone mr-2"></i>Flashcards
                            </a>
                        </li>
                        <li>
                            <a href="#assessments" class="inline-block py-2 px-4 border-b-2 border-transparent hover:border-gray-300 hover:text-gray-600 dark:hover:text-gray-300" data-target="assessments-content">
                                <i class="fas fa-tasks mr-2"></i>Kiểm tra
                            </a>
                        </li>
                    </ul>
                </div>
                
                <!-- Contenido de las pestañas -->
                <div id="materials-content" class="tab-content active">
                    <h2 class="text-xl font-semibold mb-4">Tài liệu học tập</h2>
                    
                    <?php if (empty($materials)): ?>
                    <div class="text-center py-6 text-gray-500 dark:text-gray-400">
                        <i class="fas fa-book text-4xl mb-3"></i>
                        <p>Chưa có tài liệu nào cho chủ đề này</p>
                    </div>
                    <?php else: ?>
                    <div class="space-y-6">
                        <?php foreach ($materials as $material): ?>
                        <div class="border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden">
                            <div class="p-4 bg-gray-50 dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 flex justify-between items-center">
                                <h3 class="font-medium">
                                    <?php 
                                    $icon = '';
                                    switch($material['material_type']) {
                                        case 'explanation': $icon = 'info-circle'; break;
                                        case 'formula': $icon = 'square-root-alt'; break;
                                        case 'example': $icon = 'lightbulb'; break;
                                        case 'quiz': $icon = 'question-circle'; break;
                                    }
                                    ?>
                                    <i class="fas fa-<?php echo $icon; ?> mr-2"></i>
                                    <?php echo htmlspecialchars($material['title']); ?>
                                </h3>
                                <span class="text-xs text-gray-500 dark:text-gray-400">
                                    <?php
                                    switch($material['material_type']) {
                                        case 'explanation': echo 'Giải thích'; break;
                                        case 'formula': echo 'Công thức'; break;
                                        case 'example': echo 'Ví dụ'; break;
                                        case 'quiz': echo 'Câu hỏi'; break;
                                    }
                                    ?>
                                </span>
                            </div>
                            <div class="p-4 response-content">
                                <?php echo $material['content']; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div id="exercises-content" class="tab-content hidden">
                    <h2 class="text-xl font-semibold mb-4">Bài tập luyện tập</h2>
                    
                    <?php if (empty($exercises)): ?>
                    <div class="text-center py-6 text-gray-500 dark:text-gray-400">
                        <i class="fas fa-pencil-alt text-4xl mb-3"></i>
                        <p>Chưa có bài tập nào cho chủ đề này</p>
                    </div>
                    <?php else: ?>
                    <div class="space-y-6">
                        <?php foreach ($exercises as $index => $exercise): ?>
                        <div class="border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden exercise-card">
                            <div class="p-4 bg-gray-50 dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 flex justify-between items-center">
                                <h3 class="font-medium">Bài tập #<?php echo $index + 1; ?></h3>
                                <span class="text-xs px-2 py-0.5 rounded-full 
                                    <?php 
                                    switch($exercise['difficulty']) {
                                        case 'easy': echo 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400'; break;
                                        case 'medium': echo 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400'; break;
                                        case 'hard': echo 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400'; break;
                                    }
                                    ?>">
                                    <?php 
                                    switch($exercise['difficulty']) {
                                        case 'easy': echo 'Dễ'; break;
                                        case 'medium': echo 'Trung bình'; break;
                                        case 'hard': echo 'Khó'; break;
                                    }
                                    ?>
                                </span>
                            </div>
                            <div class="p-4">
                                <p class="mb-4"><?php echo nl2br(htmlspecialchars($exercise['question'])); ?></p>
                                
                                <?php if (!empty($exercise['hint'])): ?>
                                <div class="mb-4">
                                    <button class="text-indigo-600 dark:text-indigo-400 text-sm flex items-center show-hint-btn">
                                        <i class="fas fa-lightbulb mr-1"></i> Xem gợi ý
                                    </button>
                                    <div class="hint-content mt-2 p-3 bg-yellow-50 dark:bg-yellow-900/20 text-yellow-800 dark:text-yellow-300 rounded-lg hidden">
                                        <?php echo nl2br(htmlspecialchars($exercise['hint'])); ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <div class="mt-4">
                                    <button class="text-indigo-600 dark:text-indigo-400 text-sm flex items-center show-answer-btn">
                                        <i class="fas fa-eye mr-1"></i> Xem lời giải
                                    </button>
                                    <div class="answer-content mt-2 p-3 bg-indigo-50 dark:bg-indigo-900/20 text-indigo-800 dark:text-indigo-300 rounded-lg hidden">
                                        <?php echo nl2br(htmlspecialchars($exercise['answer'])); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div id="flashcards-content" class="tab-content hidden">
                    <h2 class="text-xl font-semibold mb-4">Flashcards</h2>
                    
                    <?php if (empty($flashcards)): ?>
                    <div class="text-center py-6 text-gray-500 dark:text-gray-400">
                        <i class="fas fa-clone text-4xl mb-3"></i>
                        <p>Chưa có flashcard nào cho chủ đề này</p>
                    </div>
                    <?php else: ?>
                    <div class="flex justify-between items-center mb-4">
                        <p class="text-sm text-gray-500 dark:text-gray-400">Nhấp vào thẻ để lật</p>
                        <div>
                            <button id="prev-card" class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 disabled:opacity-50">
                                <i class="fas fa-chevron-left"></i>
                            </button>
                            <span id="card-counter" class="mx-2 text-sm">1 / <?php echo count($flashcards); ?></span>
                            <button id="next-card" class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800">
                                <i class="fas fa-chevron-right"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="flashcards-container">
                        <?php foreach ($flashcards as $index => $flashcard): ?>
                        <div class="flashcard <?php echo $index === 0 ? 'active' : 'hidden'; ?>" data-index="<?php echo $index; ?>">
                            <div class="flashcard-inner">
                                <div class="flashcard-front p-6 h-64 flex items-center justify-center text-lg font-medium text-center">
                                    <?php echo nl2br(htmlspecialchars($flashcard['front'])); ?>
                                </div>
                                <div class="flashcard-back p-6 h-64 bg-indigo-50 dark:bg-indigo-900/20 flex items-center justify-center">
                                    <?php echo nl2br(htmlspecialchars($flashcard['back'])); ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div id="assessments-content" class="tab-content hidden">
                    <h2 class="text-xl font-semibold mb-4">Bài kiểm tra</h2>
                    
                    <?php if (empty($assessments)): ?>
                    <div class="text-center py-6 text-gray-500 dark:text-gray-400">
                        <i class="fas fa-tasks text-4xl mb-3"></i>
                        <p>Chưa có bài kiểm tra nào cho chủ đề này</p>
                    </div>
                    <?php else: ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <?php foreach ($assessments as $assessment): ?>
                        <div class="border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden">
                            <div class="p-4">
                                <h3 class="font-semibold text-lg mb-1"><?php echo htmlspecialchars($assessment['title']); ?></h3>
                                <p class="text-sm text-gray-600 dark:text-gray-400 mb-3"><?php echo htmlspecialchars($assessment['description']); ?></p>
                                
                                <div class="flex items-center justify-between mb-4 text-sm">
                                    <div class="flex items-center">
                                        <i class="fas fa-clock mr-1 text-gray-500 dark:text-gray-400"></i>
                                        <span><?php echo $assessment['time_limit_minutes']; ?> phút</span>
                                    </div>
                                    <div class="flex items-center">
                                        <i class="fas fa-award mr-1 text-gray-500 dark:text-gray-400"></i>
                                        <span>Điểm đạt: <?php echo $assessment['passing_score']; ?></span>
                                    </div>
                                </div>
                                
                                <?php
                                $completed = false;
                                if (isLoggedIn()) {
                                    $user_id = $_SESSION['user_id'];
                                    $stmt = $conn->prepare("SELECT id, score, max_score, passed FROM assessment_results WHERE user_id = ? AND assessment_id = ? ORDER BY completed_at DESC LIMIT 1");
                                    $stmt->bind_param("ii", $user_id, $assessment['id']);
                                    $stmt->execute();
                                    $result = $stmt->get_result()->fetch_assoc();
                                    
                                    if ($result) {
                                        $completed = true;
                                        $score_percent = round(($result['score'] / $result['max_score']) * 100);
                                        $passed = $result['passed'];
                                    }
                                }
                                ?>
                                
                                <?php if ($completed): ?>
                                <div class="mt-2 mb-3">
                                    <div class="flex items-center justify-between mb-1">
                                        <span class="text-sm font-medium">Kết quả</span>
                                        <span class="text-sm font-medium"><?php echo $score_percent; ?>%</span>
                                    </div>
                                    <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2.5">
                                        <div class="h-2.5 rounded-full <?php echo $passed ? 'bg-green-600' : 'bg-red-600'; ?>" style="width: <?php echo $score_percent; ?>%"></div>
                                    </div>
                                </div>
                                
                                <div class="flex flex-col space-y-2">
                                    <a href="index.php?page=assessment&id=<?php echo $assessment['id']; ?>" class="btn btn-secondary py-2 text-center">
                                        <i class="fas fa-redo mr-2"></i>Làm lại
                                    </a>
                                    <a href="index.php?page=assessment_result&id=<?php echo $result['id']; ?>" class="text-indigo-600 dark:text-indigo-400 text-sm text-center">
                                        Xem chi tiết kết quả
                                    </a>
                                </div>
                                <?php else: ?>
                                <a href="index.php?page=assessment&id=<?php echo $assessment['id']; ?>" class="btn btn-primary py-2 text-center block">
                                    <i class="fas fa-play mr-2"></i>Bắt đầu làm bài
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php elseif ($current_subject): ?>
            <!-- Vista de temas de una asignatura -->
            <div class="card rounded-xl p-6 mb-6">
                <div class="flex items-center mb-6">
                    <a href="index.php?page=study" class="mr-3 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300">
                        <i class="fas fa-arrow-left"></i>
                    </a>
                    <div>
                        <h1 class="text-2xl font-bold"><?php echo htmlspecialchars($current_subject['name']); ?></h1>
                        <p class="text-gray-600 dark:text-gray-400 mt-1"><?php echo htmlspecialchars($current_subject['description']); ?></p>
                    </div>
                </div>
                
                <?php if (empty($topics)): ?>
                <div class="text-center py-6 text-gray-500 dark:text-gray-400">
                    <i class="fas fa-book-open text-4xl mb-3"></i>
                    <p>Chưa có chủ đề nào cho môn học này</p>
                    <p class="mt-2 text-sm">Vui lòng quay lại sau</p>
                </div>
                <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <?php foreach ($topics as $topic): ?>
                    <a href="index.php?page=study&topic=<?php echo $topic['id']; ?>" class="border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden hover:shadow-md transition">
                        <div class="p-4">
                            <h3 class="font-semibold text-lg mb-1"><?php echo htmlspecialchars($topic['name']); ?></h3>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mb-3 line-clamp-2"><?php echo htmlspecialchars($topic['description']); ?></p>
                            
                            <div class="flex justify-between items-center">
                                <span class="text-xs px-2 py-0.5 rounded-full 
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
                                </span>
                                
                                <?php
                                $progress = null;
                                if (isLoggedIn()) {
                                    $user_id = $_SESSION['user_id'];
                                    $topic_id = $topic['id'];
                                    $stmt = $conn->prepare("SELECT * FROM user_study_progress WHERE user_id = ? AND topic_id = ?");
                                    $stmt->bind_param("ii", $user_id, $topic_id);
                                    $stmt->execute();
                                    $progress = $stmt->get_result()->fetch_assoc();
                                }
                                ?>
                                
                                <?php if ($progress): ?>
                                <div class="flex items-center">
                                    <div class="w-24 bg-gray-200 dark:bg-gray-700 rounded-full h-2 mr-2">
                                        <div class="bg-indigo-600 h-2 rounded-full" style="width: <?php echo min(100, max(5, $progress['mastery_level'])); ?>%"></div>
                                    </div>
                                    <span class="text-xs text-gray-500 dark:text-gray-400"><?php echo $progress['mastery_level']; ?>%</span>
                                </div>
                                <?php else: ?>
                                <div class="text-xs text-gray-500 dark:text-gray-400">Chưa học</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php else: ?>
            <!-- Vista general de todas las asignaturas -->
            <div class="card rounded-xl p-6 mb-6">
                <h1 class="text-2xl font-bold mb-4">Hệ thống ôn tập kiến thức</h1>
                <p class="text-gray-600 dark:text-gray-400 mb-6">Chọn một môn học bên trái để bắt đầu ôn tập hoặc chọn từ danh sách dưới đây.</p>
                
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php foreach ($subjects as $subject): ?>
                    <a href="index.php?page=study&subject=<?php echo $subject['id']; ?>" class="border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden hover:shadow-md transition p-4">
                        <div class="flex items-center mb-3">
                            <div class="w-12 h-12 rounded-full bg-indigo-100 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-400 flex items-center justify-center">
                                <i class="fas fa-<?php echo htmlspecialchars($subject['icon']); ?> text-xl"></i>
                            </div>
                            <h3 class="font-semibold text-lg ml-3"><?php echo htmlspecialchars($subject['name']); ?></h3>
                        </div>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mb-3 line-clamp-2"><?php echo htmlspecialchars($subject['description']); ?></p>
                        
                        <?php
                        $stmt = $conn->prepare("SELECT COUNT(*) FROM study_topics WHERE subject_id = ?");
                        $stmt->bind_param("i", $subject['id']);
                        $stmt->execute();
                        $topic_count = $stmt->get_result()->fetch_row()[0];
                        ?>
                        
                        <div class="text-xs text-gray-500 dark:text-gray-400">
                            <?php echo $topic_count; ?> chủ đề
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Recomendaciones de estudio -->
            <div class="card rounded-xl p-6">
                <h2 class="text-xl font-bold mb-4">Gợi ý ôn tập</h2>
                
                <?php 
                $stmt = $conn->prepare("
                    SELECT t.id, t.name, t.difficulty, s.name as subject_name, s.icon
                    FROM study_topics t
                    JOIN study_subjects s ON t.subject_id = s.id
                    ORDER BY RAND()
                    LIMIT 6
                ");
                $stmt->execute();
                $recommended_topics = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                ?>
                
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    <?php foreach ($recommended_topics as $topic): ?>
                    <a href="index.php?page=study&topic=<?php echo $topic['id']; ?>" class="flex items-start p-3 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800 transition">
                        <div class="flex-shrink-0 w-8 h-8 rounded-full bg-indigo-100 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-400 flex items-center justify-center">
                            <i class="fas fa-<?php echo htmlspecialchars($topic['icon']); ?>"></i>
                        </div>
                        <div class="ml-3">
                            <h3 class="font-medium"><?php echo htmlspecialchars($topic['name']); ?></h3>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                <?php echo htmlspecialchars($topic['subject_name']); ?> • 
                                <?php 
                                switch($topic['difficulty']) {
                                    case 'beginner': echo 'Cơ bản'; break;
                                    case 'intermediate': echo 'Trung bình'; break;
                                    case 'advanced': echo 'Nâng cao'; break;
                                }
                                ?>
                            </p>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Xử lý dropdown trên thiết bị di động
    const subjectDropdown = document.getElementById('subjectDropdown');
    if (subjectDropdown) {
        subjectDropdown.addEventListener('change', function() {
            const subjectId = this.value;
            if (subjectId) {
                window.location.href = `index.php?page=study&subject=${subjectId}`;
            }
        });
    }

    // Navegación por pestañas
    const tabLinks = document.querySelectorAll('[data-target]');
    tabLinks.forEach(tab => {
        tab.addEventListener('click', function(e) {
            e.preventDefault();
            
            tabLinks.forEach(t => {
                t.classList.remove('border-indigo-600', 'text-indigo-600', 'dark:text-indigo-400', 'active-tab');
                t.classList.add('border-transparent', 'hover:border-gray-300', 'hover:text-gray-600', 'dark:hover:text-gray-300');
            });
            
            this.classList.add('border-indigo-600', 'text-indigo-600', 'dark:text-indigo-400', 'active-tab');
            this.classList.remove('border-transparent', 'hover:border-gray-300', 'hover:text-gray-600', 'dark:hover:text-gray-300');
            
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.add('hidden');
            });
            
            const target = this.getAttribute('data-target');
            document.getElementById(target).classList.remove('hidden');
        });
    });
    
    // Manejo de ejercicios - mostrar/ocultar pistas y respuestas
    document.querySelectorAll('.show-hint-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const hintContent = this.nextElementSibling;
            hintContent.classList.toggle('hidden');
            this.innerHTML = hintContent.classList.contains('hidden') 
                ? '<i class="fas fa-lightbulb mr-1"></i> Xem gợi ý'
                : '<i class="fas fa-times mr-1"></i> Ẩn gợi ý';
        });
    });
    
    document.querySelectorAll('.show-answer-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const answerContent = this.nextElementSibling;
            answerContent.classList.toggle('hidden');
            this.innerHTML = answerContent.classList.contains('hidden') 
                ? '<i class="fas fa-eye mr-1"></i> Xem lời giải'
                : '<i class="fas fa-times mr-1"></i> Ẩn lời giải';
        });
    });
    
    // Manejo de flashcards
    const flashcards = document.querySelectorAll('.flashcard');
    const prevBtn = document.getElementById('prev-card');
    const nextBtn = document.getElementById('next-card');
    const cardCounter = document.getElementById('card-counter');
    
    if (flashcards.length > 0) {
        let currentIndex = 0;
        
        flashcards.forEach(card => {
            card.addEventListener('click', function() {
                this.querySelector('.flashcard-inner').classList.toggle('flipped');
            });
        });
        
        function updateFlashcardNav() {
            cardCounter.textContent = `${currentIndex + 1} / ${flashcards.length}`;
            prevBtn.disabled = currentIndex === 0;
            nextBtn.disabled = currentIndex === flashcards.length - 1;
            
            if (prevBtn.disabled) {
                prevBtn.classList.add('opacity-50', 'cursor-not-allowed');
            } else {
                prevBtn.classList.remove('opacity-50', 'cursor-not-allowed');
            }
            
            if (nextBtn.disabled) {
                nextBtn.classList.add('opacity-50', 'cursor-not-allowed');
            } else {
                nextBtn.classList.remove('opacity-50', 'cursor-not-allowed');
            }
        }
        
        if (prevBtn) {
            prevBtn.addEventListener('click', function() {
                if (currentIndex > 0) {
                    flashcards[currentIndex].classList.add('hidden');
                    flashcards[currentIndex].classList.remove('active');
                    flashcards[currentIndex].querySelector('.flashcard-inner').classList.remove('flipped');
                    
                    currentIndex--;
                    
                    flashcards[currentIndex].classList.remove('hidden');
                    flashcards[currentIndex].classList.add('active');
                    
                    updateFlashcardNav();
                }
            });
        }
        
        if (nextBtn) {
            nextBtn.addEventListener('click', function() {
                if (currentIndex < flashcards.length - 1) {
                    flashcards[currentIndex].classList.add('hidden');
                    flashcards[currentIndex].classList.remove('active');
                    flashcards[currentIndex].querySelector('.flashcard-inner').classList.remove('flipped');
                    
                    currentIndex++;
                    
                    flashcards[currentIndex].classList.remove('hidden');
                    flashcards[currentIndex].classList.add('active');
                    
                    updateFlashcardNav();
                }
            });
        }
    }
});
</script>

<style>
/* Estilos para flashcards */
.flashcards-container {
    perspective: 1000px;
    min-height: 300px;
}

.flashcard {
    width: 100%;
    height: 100%;
    cursor: pointer;
}

.flashcard-inner {
    position: relative;
    width: 100%;
    height: 100%;
    text-align: center;
    transition: transform 0.6s;
    transform-style: preserve-3d;
    border: 1px solid var(--border-color);
    border-radius: 0.75rem;
}

.flashcard-inner.flipped {
    transform: rotateY(180deg);
}

.flashcard-front, .flashcard-back {
    position: absolute;
    width: 100%;
    height: 100%;
    -webkit-backface-visibility: hidden;
    backface-visibility: hidden;
    border-radius: 0.75rem;
}

.flashcard-back {
    transform: rotateY(180deg);
}
</style>