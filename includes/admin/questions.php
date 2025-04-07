<?php
// File: includes/admin/questions.php
require_once '../config.php';
require_once '../functions.php';

// Verificar que el usuario haya iniciado sesión y sea administrador
if (!isLoggedIn()) {
    header('Location: ../../index.php?page=login');
    exit;
}

$user = getUserInfo($_SESSION['user_id']);
if (!$user || $user['role'] !== 'admin') {
    header('Location: ../../index.php');
    exit;
}

// Procesar acciones
$action = isset($_GET['action']) ? $_GET['action'] : '';
$question_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$assessment_id = isset($_GET['assessment_id']) ? intval($_GET['assessment_id']) : 0;
$message = '';
$error = '';

// Datos iniciales para editar/añadir
$question = [
    'assessment_id' => $assessment_id,
    'question' => '',
    'question_type' => 'multiple_choice',
    'options' => '["Option 1", "Option 2", "Option 3", "Option 4"]',
    'correct_answer' => '',
    'points' => 1
];

// Manejar acciones CRUD
if ($action === 'delete' && $question_id) {
    $stmt = $conn->prepare("DELETE FROM assessment_questions WHERE id = ?");
    $stmt->bind_param("i", $question_id);
    
    if ($stmt->execute()) {
        $message = 'Đã xóa câu hỏi thành công!';
    } else {
        $error = 'Lỗi: ' . $conn->error;
    }
} else if (($action === 'edit' && $question_id) || $action === 'add') {
    // Cargar datos para editar
    if ($action === 'edit' && $question_id) {
        $stmt = $conn->prepare("SELECT * FROM assessment_questions WHERE id = ?");
        $stmt->bind_param("i", $question_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            $question = $row;
            $assessment_id = $question['assessment_id'];
        } else {
            $error = 'Không tìm thấy câu hỏi!';
        }
    }
    
    // Procesar formulario
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $question['assessment_id'] = intval($_POST['assessment_id'] ?? 0);
        $question['question'] = trim($_POST['question'] ?? '');
        $question['question_type'] = $_POST['question_type'] ?? 'multiple_choice';
        $question['correct_answer'] = trim($_POST['correct_answer'] ?? '');
        $question['points'] = intval($_POST['points'] ?? 1);
        
        // Procesar opciones para preguntas de opción múltiple
        if ($question['question_type'] === 'multiple_choice') {
            $options = isset($_POST['options']) ? $_POST['options'] : [];
            $options = array_map('trim', $options);
            $options = array_filter($options, function($option) {
                return !empty($option);
            });
            
            if (count($options) < 2) {
                $error = 'Phải có ít nhất 2 lựa chọn!';
            } else {
                $question['options'] = json_encode(array_values($options));
            }
        } else {
            $question['options'] = '[]';
        }
        
        if (empty($question['question'])) {
            $error = 'Nội dung câu hỏi không được để trống!';
        } else if (empty($question['correct_answer'])) {
            $error = 'Đáp án đúng không được để trống!';
        } else if (empty($question['assessment_id'])) {
            $error = 'Vui lòng chọn bài kiểm tra!';
        } else if ($question['points'] < 1) {
            $error = 'Điểm số phải ít nhất là 1!';
        } else if ($question['question_type'] === 'multiple_choice' && !in_array($question['correct_answer'], json_decode($question['options'], true))) {
            $error = 'Đáp án đúng phải là một trong các lựa chọn!';
        } else {
            if ($action === 'edit') {
                $stmt = $conn->prepare("UPDATE assessment_questions SET assessment_id = ?, question = ?, question_type = ?, options = ?, correct_answer = ?, points = ? WHERE id = ?");
                $stmt->bind_param("issssii", $question['assessment_id'], $question['question'], $question['question_type'], $question['options'], $question['correct_answer'], $question['points'], $question_id);
            } else {
                $stmt = $conn->prepare("INSERT INTO assessment_questions (assessment_id, question, question_type, options, correct_answer, points) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("issssi", $question['assessment_id'], $question['question'], $question['question_type'], $question['options'], $question['correct_answer'], $question['points']);
            }
            
            if ($stmt->execute()) {
                if ($action === 'add') {
                    $question_id = $conn->insert_id;
                }
                
                $message = $action === 'edit' ? 'Đã cập nhật câu hỏi thành công!' : 'Đã thêm câu hỏi thành công!';
                
                // Determinar redirección
                $redirect_url = 'questions.php?assessment_id=' . $question['assessment_id'] . '&message=' . urlencode($message);
                
                // Añadir más preguntas si se solicita
                if (isset($_POST['add_another']) && $_POST['add_another'] == '1') {
                    $redirect_url = 'questions.php?action=add&assessment_id=' . $question['assessment_id'] . '&message=' . urlencode($message);
                }
                
                header('Location: ' . $redirect_url);
                exit;
            } else {
                $error = 'Lỗi: ' . $conn->error;
            }
        }
    }
}

// Obtener mensaje de la URL
if (isset($_GET['message'])) {
    $message = $_GET['message'];
}

// Paginación y filtrado
$page = isset($_GET['page_num']) ? intval($_GET['page_num']) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$type = isset($_GET['type']) ? $_GET['type'] : '';

// Obtener todas las evaluaciones para el formulario
$assessments = [];
$stmt = $conn->query("SELECT a.id, a.title, t.name as topic_name, s.name as subject_name 
                       FROM self_assessments a 
                       JOIN study_topics t ON a.topic_id = t.id 
                       JOIN study_subjects s ON t.subject_id = s.id 
                       ORDER BY s.name, t.name, a.title");
while ($row = $stmt->fetch_assoc()) {
    $assessments[] = $row;
}

// Construir la consulta
$query = "SELECT q.*, a.title as assessment_title, t.name as topic_name, s.name as subject_name
          FROM assessment_questions q
          JOIN self_assessments a ON q.assessment_id = a.id
          JOIN study_topics t ON a.topic_id = t.id
          JOIN study_subjects s ON t.subject_id = s.id
          WHERE 1=1";
$count_query = "SELECT COUNT(*) as total FROM assessment_questions q WHERE 1=1";

$query_params = [];
$param_types = "";

if (!empty($search)) {
    $query .= " AND (q.question LIKE ? OR q.correct_answer LIKE ?)";
    $count_query .= " AND (question LIKE ? OR correct_answer LIKE ?)";
    $search_param = "%$search%";
    $query_params[] = $search_param;
    $query_params[] = $search_param;
    $param_types .= "ss";
}

if (!empty($type)) {
    $query .= " AND q.question_type = ?";
    $count_query .= " AND question_type = ?";
    $query_params[] = $type;
    $param_types .= "s";
}

if ($assessment_id) {
    $query .= " AND q.assessment_id = ?";
    $count_query .= " AND assessment_id = ?";
    $query_params[] = $assessment_id;
    $param_types .= "i";
    
    // Obtener información de la evaluación
    $stmt = $conn->prepare("SELECT a.title, a.topic_id, t.name as topic_name, s.name as subject_name 
                             FROM self_assessments a
                             JOIN study_topics t ON a.topic_id = t.id
                             JOIN study_subjects s ON t.subject_id = s.id
                             WHERE a.id = ?");
    $stmt->bind_param("i", $assessment_id);
    $stmt->execute();
    $current_assessment = $stmt->get_result()->fetch_assoc();
}

// Ejecutar consulta de conteo
$stmt = $conn->prepare($count_query);
if (!empty($param_types)) {
    $stmt->bind_param($param_types, ...$query_params);
}
$stmt->execute();
$total_records = $stmt->get_result()->fetch_assoc()['total'];

$total_pages = ceil($total_records / $limit);

// Añadir límites para paginación
$query .= " ORDER BY q.id LIMIT ?, ?";
$query_params[] = $offset;
$query_params[] = $limit;
$param_types .= "ii";

// Ejecutar consulta principal
$stmt = $conn->prepare($query);
if (!empty($param_types)) {
    $stmt->bind_param($param_types, ...$query_params);
}
$stmt->execute();
$questions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Incluir el encabezado admin
$admin_page = 'questions';
include 'header.php';
?>

<div class="flex-1 p-6 overflow-auto">
    <div class="max-w-7xl mx-auto">
        <?php if ($action === 'edit' || $action === 'add'): ?>
        <!-- Formulario de edición/adición -->
        <div class="flex items-center mb-6">
            <a href="questions.php<?php echo $assessment_id ? '?assessment_id=' . $assessment_id : ''; ?>" class="mr-3 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300">
                <i class="fas fa-arrow-left"></i>
            </a>
            <h1 class="text-2xl font-bold"><?php echo $action === 'edit' ? 'Chỉnh sửa câu hỏi' : 'Thêm câu hỏi mới'; ?></h1>
        </div>
        
        <?php if (!empty($error)): ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6">
            <div class="flex">
                <div class="flex-shrink-0">
                    <i class="fas fa-exclamation-circle"></i>
                </div>
                <div class="ml-3">
                    <p><?php echo $error; ?></p>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="card rounded-lg p-6 mb-6">
            <form method="post" action="questions.php?action=<?php echo $action; ?><?php echo $action === 'edit' ? '&id=' . $question_id : ''; ?>">
                <div class="mb-4">
                    <label for="assessment_id" class="block text-sm font-medium mb-1">Bài kiểm tra <span class="text-red-500">*</span></label>
                    <select id="assessment_id" name="assessment_id" class="w-full" required>
                        <option value="">-- Chọn bài kiểm tra --</option>
                        <?php foreach ($assessments as $assessment): ?>
                        <option value="<?php echo $assessment['id']; ?>" <?php echo $question['assessment_id'] == $assessment['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($assessment['subject_name'] . ' - ' . $assessment['topic_name'] . ' - ' . $assessment['title']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="mb-4">
                    <label for="question" class="block text-sm font-medium mb-1">Nội dung câu hỏi <span class="text-red-500">*</span></label>
                    <textarea id="question" name="question" rows="3" class="w-full" required><?php echo htmlspecialchars($question['question']); ?></textarea>
                </div>
                
                <div class="mb-4">
                    <label for="question_type" class="block text-sm font-medium mb-1">Loại câu hỏi</label>
                    <select id="question_type" name="question_type" class="w-full">
                        <option value="multiple_choice" <?php echo $question['question_type'] === 'multiple_choice' ? 'selected' : ''; ?>>Trắc nghiệm</option>
                        <option value="true_false" <?php echo $question['question_type'] === 'true_false' ? 'selected' : ''; ?>>Đúng/Sai</option>
                        <option value="short_answer" <?php echo $question['question_type'] === 'short_answer' ? 'selected' : ''; ?>>Trả lời ngắn</option>
                    </select>
                </div>
                
                <div id="options-container" class="mb-4 <?php echo $question['question_type'] !== 'multiple_choice' ? 'hidden' : ''; ?>">
                    <label class="block text-sm font-medium mb-2">Các lựa chọn <span class="text-red-500">*</span></label>
                    <div id="options-list" class="space-y-2">
                        <?php 
                        $options = json_decode($question['options'], true) ?: [];
                        foreach ($options as $index => $option): 
                        ?>
                        <div class="flex items-center">
                            <input type="text" name="options[]" value="<?php echo htmlspecialchars($option); ?>" class="w-full mr-2" placeholder="Lựa chọn <?php echo $index + 1; ?>">
                            <button type="button" class="remove-option text-red-600 dark:text-red-400 hover:text-red-800 dark:hover:text-red-300">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        <?php endforeach; ?>
                        <?php if (empty($options)): ?>
                        <div class="flex items-center">
                            <input type="text" name="options[]" class="w-full mr-2" placeholder="Lựa chọn 1">
                            <button type="button" class="remove-option text-red-600 dark:text-red-400 hover:text-red-800 dark:hover:text-red-300">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        <div class="flex items-center">
                            <input type="text" name="options[]" class="w-full mr-2" placeholder="Lựa chọn 2">
                            <button type="button" class="remove-option text-red-600 dark:text-red-400 hover:text-red-800 dark:hover:text-red-300">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        <?php endif; ?>
                    </div>
                    <button type="button" id="add-option" class="mt-2 text-sm text-indigo-600 dark:text-indigo-400">
                        <i class="fas fa-plus mr-1"></i>Thêm lựa chọn
                    </button>
                </div>
                
                <div id="true-false-container" class="mb-4 <?php echo $question['question_type'] !== 'true_false' ? 'hidden' : ''; ?>">
                    <label class="block text-sm font-medium mb-2">Đáp án đúng <span class="text-red-500">*</span></label>
                    <div class="flex space-x-4">
                        <label class="flex items-center">
                            <input type="radio" name="tf_answer" value="true" <?php echo $question['correct_answer'] === 'true' ? 'checked' : ''; ?> class="mr-2">
                            <span>Đúng</span>
                        </label>
                        <label class="flex items-center">
                            <input type="radio" name="tf_answer" value="false" <?php echo $question['correct_answer'] === 'false' ? 'checked' : ''; ?> class="mr-2">
                            <span>Sai</span>
                        </label>
                    </div>
                </div>
                
                <div id="short-answer-container" class="mb-4 <?php echo $question['question_type'] !== 'short_answer' ? 'hidden' : ''; ?>">
                    <label for="short_answer" class="block text-sm font-medium mb-1">Đáp án đúng <span class="text-red-500">*</span></label>
                    <input type="text" id="short_answer" name="short_answer" value="<?php echo $question['question_type'] === 'short_answer' ? htmlspecialchars($question['correct_answer']) : ''; ?>" class="w-full" placeholder="Nhập đáp án (có thể nhập nhiều câu trả lời cách nhau bằng dấu |)">
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                        Nếu có nhiều đáp án đúng, hãy ngăn cách bằng dấu | (ví dụ: Hà Nội|Ha Noi|Hanoi)
                    </p>
                </div>
                
                <div id="multiple-choice-answer" class="mb-4 <?php echo $question['question_type'] !== 'multiple_choice' ? 'hidden' : ''; ?>">
                    <label for="mc_answer" class="block text-sm font-medium mb-1">Đáp án đúng <span class="text-red-500">*</span></label>
                    <input type="text" id="mc_answer" name="mc_answer" value="<?php echo $question['question_type'] === 'multiple_choice' ? htmlspecialchars($question['correct_answer']) : ''; ?>" class="w-full" placeholder="Chọn một lựa chọn từ danh sách">
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                        Đáp án đúng phải khớp chính xác với một trong các lựa chọn đã nhập
                    </p>
                </div>
                
                <!-- Campo oculto para almacenar la respuesta correcta final -->
                <input type="hidden" name="correct_answer" id="correct_answer" value="<?php echo htmlspecialchars($question['correct_answer']); ?>">
                
                <div class="mb-6">
                    <label for="points" class="block text-sm font-medium mb-1">Điểm số</label>
                    <input type="number" id="points" name="points" value="<?php echo intval($question['points']); ?>" min="1" class="w-full md:w-32">
                </div>
                
                <div class="flex justify-between">
                    <?php if ($action === 'add'): ?>
                    <div>
                        <label class="flex items-center">
                            <input type="checkbox" name="add_another" value="1" class="mr-2">
                            <span class="text-sm">Thêm câu hỏi khác sau khi lưu</span>
                        </label>
                    </div>
                    <?php else: ?>
                    <div></div>
                    <?php endif; ?>
                    
                    <div>
                        <a href="questions.php<?php echo $assessment_id ? '?assessment_id=' . $assessment_id : ''; ?>" class="btn btn-secondary mr-3">
                            Hủy
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <?php echo $action === 'edit' ? 'Cập nhật' : 'Thêm mới'; ?>
                        </button>
                    </div>
                </div>
            </form>
        </div>
        <?php else: ?>
        <!-- Lista de preguntas -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
            <?php if ($assessment_id && isset($current_assessment)): ?>
            <div>
                <div class="flex items-center mb-2">
                    <a href="assessments.php?topic_id=<?php echo $current_assessment['topic_id']; ?>" class="mr-3 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300">
                        <i class="fas fa-arrow-left"></i>
                    </a>
                    <h1 class="text-2xl font-bold">Câu hỏi: <?php echo htmlspecialchars($current_assessment['title']); ?></h1>
                </div>
                <p class="text-gray-600 dark:text-gray-400">
                    <?php echo htmlspecialchars($current_assessment['subject_name'] . ' - ' . $current_assessment['topic_name']); ?>
                </p>
            </div>
            <?php else: ?>
            <div>
                <h1 class="text-2xl font-bold mb-2">Quản lý câu hỏi</h1>
                <p class="text-gray-600 dark:text-gray-400">Quản lý tất cả các câu hỏi kiểm tra trong hệ thống</p>
            </div>
            <?php endif; ?>
            
            <div class="mt-4 md:mt-0">
                <a href="questions.php?action=add<?php echo $assessment_id ? '&assessment_id=' . $assessment_id : ''; ?>" class="btn btn-primary">
                    <i class="fas fa-plus mr-2"></i>Thêm câu hỏi
                </a>
            </div>
        </div>
        
        <?php if (!empty($message)): ?>
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6">
            <div class="flex">
                <div class="flex-shrink-0">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="ml-3">
                    <p><?php echo $message; ?></p>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($error)): ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6">
            <div class="flex">
                <div class="flex-shrink-0">
                    <i class="fas fa-exclamation-circle"></i>
                </div>
                <div class="ml-3">
                    <p><?php echo $error; ?></p>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Filters -->
        <div class="card rounded-lg p-4 mb-6">
            <form action="questions.php" method="get" class="flex flex-col md:flex-row gap-4">
                <?php if ($assessment_id): ?>
                <input type="hidden" name="assessment_id" value="<?php echo $assessment_id; ?>">
                <?php else: ?>
                <div class="w-full md:w-64">
                    <label for="assessment_id" class="block text-sm font-medium mb-1">Bài kiểm tra</label>
                    <select id="assessment_id" name="assessment_id" class="w-full">
                        <option value="">Tất cả bài kiểm tra</option>
                        <?php foreach ($assessments as $assessment): ?>
                        <option value="<?php echo $assessment['id']; ?>" <?php echo $assessment_id == $assessment['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($assessment['title'] . ' (' . $assessment['topic_name'] . ')'); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                
                <div class="flex-1">
                    <label for="search" class="block text-sm font-medium mb-1">Tìm kiếm</label>
                    <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Nội dung câu hỏi, đáp án..." class="w-full">
                </div>
                
                <div class="w-full md:w-48">
                    <label for="type" class="block text-sm font-medium mb-1">Loại câu hỏi</label>
                    <select id="type" name="type" class="w-full">
                        <option value="">Tất cả</option>
                        <option value="multiple_choice" <?php echo $type === 'multiple_choice' ? 'selected' : ''; ?>>Trắc nghiệm</option>
                        <option value="true_false" <?php echo $type === 'true_false' ? 'selected' : ''; ?>>Đúng/Sai</option>
                        <option value="short_answer" <?php echo $type === 'short_answer' ? 'selected' : ''; ?>>Trả lời ngắn</option>
                    </select>
                </div>
                
                <div class="flex items-end">
                    <button type="submit" class="btn btn-primary h-10 px-4">
                        <i class="fas fa-search mr-2"></i>Lọc
                    </button>
                </div>
            </form>
        </div>
        
        <!-- Questions List -->
        <div class="space-y-6">
            <?php foreach ($questions as $question_item): ?>
            <div class="card rounded-lg overflow-hidden shadow-sm p-6">
                <div class="flex justify-between items-start mb-3">
                    <div class="flex items-center">
                        <div class="w-10 h-10 rounded-full 
                            <?php 
                            switch($question_item['question_type']) {
                                case 'multiple_choice': echo 'bg-indigo-100 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-400'; break;
                                case 'true_false': echo 'bg-green-100 dark:bg-green-900/30 text-green-600 dark:text-green-400'; break;
                                case 'short_answer': echo 'bg-yellow-100 dark:bg-yellow-900/30 text-yellow-600 dark:text-yellow-400'; break;
                            }
                            ?> flex items-center justify-center mr-3">
                            <i class="fas fa-<?php 
                                switch($question_item['question_type']) {
                                    case 'multiple_choice': echo 'list'; break;
                                    case 'true_false': echo 'check-circle'; break;
                                    case 'short_answer': echo 'font'; break;
                                }
                            ?>"></i>
                        </div>
                        <div>
                            <span class="inline-flex px-2 py-1 text-xs rounded-full 
                                <?php 
                                switch($question_item['question_type']) {
                                    case 'multiple_choice': echo 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/30 dark:text-indigo-400'; break;
                                    case 'true_false': echo 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400'; break;
                                    case 'short_answer': echo 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400'; break;
                                }
                                ?>">
                                <?php 
                                switch($question_item['question_type']) {
                                    case 'multiple_choice': echo 'Trắc nghiệm'; break;
                                    case 'true_false': echo 'Đúng/Sai'; break;
                                    case 'short_answer': echo 'Trả lời ngắn'; break;
                                }
                                ?>
                            </span>
                            
                            <div class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                                <?php if ($assessment_id == 0): ?>
                                <?php echo htmlspecialchars($question_item['assessment_title'] . ' (' . $question_item['subject_name'] . ' - ' . $question_item['topic_name'] . ')'); ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="text-sm flex items-center">
                        <span class="font-medium"><?php echo $question_item['points']; ?> điểm</span>
                    </div>
                </div>
                
                <div class="p-4 bg-gray-50 dark:bg-gray-800 rounded-lg mb-4">
                    <div class="font-medium mb-2">Câu hỏi:</div>
                    <div><?php echo nl2br(htmlspecialchars($question_item['question'])); ?></div>
                </div>
                
                <?php if ($question_item['question_type'] === 'multiple_choice'): ?>
                <div class="mb-4">
                    <div class="font-medium mb-2">Các lựa chọn:</div>
                    <ul class="space-y-1 pl-6 list-disc">
                        <?php foreach (json_decode($question_item['options'], true) as $option): ?>
                        <li class="<?php echo $option === $question_item['correct_answer'] ? 'font-medium text-green-600 dark:text-green-400' : ''; ?>">
                            <?php echo htmlspecialchars($option); ?>
                            <?php if ($option === $question_item['correct_answer']): ?>
                            <span class="ml-2 text-green-600 dark:text-green-400"><i class="fas fa-check"></i> Đáp án đúng</span>
                            <?php endif; ?>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php elseif ($question_item['question_type'] === 'true_false'): ?>
                <div class="mb-4">
                    <div class="font-medium mb-2">Đáp án đúng:</div>
                    <div class="text-green-600 dark:text-green-400">
                        <?php echo $question_item['correct_answer'] === 'true' ? 'Đúng' : 'Sai'; ?>
                    </div>
                </div>
                <?php else: ?>
                <div class="mb-4">
                    <div class="font-medium mb-2">Đáp án đúng:</div>
                    <div class="text-green-600 dark:text-green-400">
                        <?php echo htmlspecialchars($question_item['correct_answer']); ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="flex justify-end mt-4 pt-3 border-t border-gray-200 dark:border-gray-700">
                    <a href="questions.php?action=edit&id=<?php echo $question_item['id']; ?>" class="text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 text-sm">
                        <i class="fas fa-edit mr-1"></i>Sửa
                    </a>
                    
                    <a href="questions.php?action=delete&id=<?php echo $question_item['id']; ?><?php echo $assessment_id ? '&assessment_id=' . $assessment_id : ''; ?>" class="text-red-600 dark:text-red-400 hover:text-red-800 dark:hover:text-red-300 text-sm ml-4 confirm-delete">
                        <i class="fas fa-trash-alt mr-1"></i>Xóa
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
            
            <?php if (empty($questions)): ?>
            <div class="text-center py-12 text-gray-500 dark:text-gray-400">
                <i class="fas fa-search text-5xl mb-4"></i>
                <p>Không tìm thấy câu hỏi nào</p>
                <a href="questions.php?action=add<?php echo $assessment_id ? '&assessment_id=' . $assessment_id : ''; ?>" class="btn btn-primary mt-4">
                    <i class="fas fa-plus mr-2"></i>Thêm câu hỏi mới
                </a>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="mt-6 flex justify-center">
            <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                <?php if ($page > 1): ?>
                <a href="?page_num=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&type=<?php echo urlencode($type); ?><?php echo $assessment_id ? '&assessment_id=' . $assessment_id : ''; ?>" class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm font-medium text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-600">
                    <i class="fas fa-chevron-left"></i>
                </a>
                <?php endif; ?>
                
                <?php
                $start_page = max(1, $page - 2);
                $end_page = min($total_pages, $page + 2);
                
                if ($start_page > 1): ?>
                <a href="?page_num=1&search=<?php echo urlencode($search); ?>&type=<?php echo urlencode($type); ?><?php echo $assessment_id ? '&assessment_id=' . $assessment_id : ''; ?>" class="relative inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600">
                    1
                </a>
                <?php if ($start_page > 2): ?>
                <span class="relative inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm font-medium text-gray-700 dark:text-gray-300">
                    ...
                </span>
                <?php endif; ?>
                <?php endif; ?>
                
                <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                <a href="?page_num=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&type=<?php echo urlencode($type); ?><?php echo $assessment_id ? '&assessment_id=' . $assessment_id : ''; ?>" class="relative inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm font-medium <?php echo $page === $i ? 'text-indigo-600 dark:text-indigo-500 bg-indigo-50 dark:bg-indigo-900/20' : 'text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600'; ?>">
                    <?php echo $i; ?>
                </a>
                <?php endfor; ?>
                
                <?php if ($end_page < $total_pages): ?>
                <?php if ($end_page < $total_pages - 1): ?>
                <span class="relative inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm font-medium text-gray-700 dark:text-gray-300">
                    ...
                </span>
                <?php endif; ?>
                <a href="?page_num=<?php echo $total_pages; ?>&search=<?php echo urlencode($search); ?>&type=<?php echo urlencode($type); ?><?php echo $assessment_id ? '&assessment_id=' . $assessment_id : ''; ?>" class="relative inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600">
                    <?php echo $total_pages; ?>
                </a>
                <?php endif; ?>
                
                <?php if ($page < $total_pages): ?>
                <a href="?page_num=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&type=<?php echo urlencode($type); ?><?php echo $assessment_id ? '&assessment_id=' . $assessment_id : ''; ?>" class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm font-medium text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-600">
                    <i class="fas fa-chevron-right"></i>
                </a>
                <?php endif; ?>
            </nav>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<script>
    // Manejar los tipos de pregunta y las opciones de respuesta
    const questionTypeSelect = document.getElementById('question_type');
    const optionsContainer = document.getElementById('options-container');
    const trueFalseContainer = document.getElementById('true-false-container');
    const shortAnswerContainer = document.getElementById('short-answer-container');
    const multipleChoiceAnswer = document.getElementById('multiple-choice-answer');
    const correctAnswerInput = document.getElementById('correct_answer');
    
    // Función para actualizar los campos según el tipo de pregunta
    function updateQuestionType() {
        if (!questionTypeSelect) return;
        
        const questionType = questionTypeSelect.value;
        
        // Ocultar todos los contenedores
        optionsContainer.classList.add('hidden');
        trueFalseContainer.classList.add('hidden');
        shortAnswerContainer.classList.add('hidden');
        multipleChoiceAnswer.classList.add('hidden');
        
        // Mostrar el contenedor correspondiente
        if (questionType === 'multiple_choice') {
            optionsContainer.classList.remove('hidden');
            multipleChoiceAnswer.classList.remove('hidden');
        } else if (questionType === 'true_false') {
            trueFalseContainer.classList.remove('hidden');
        } else if (questionType === 'short_answer') {
            shortAnswerContainer.classList.remove('hidden');
        }
    }
    
    // Actualizar al cargar la página
    updateQuestionType();
    
    // Actualizar al cambiar el tipo de pregunta
    if (questionTypeSelect) {
        questionTypeSelect.addEventListener('change', updateQuestionType);
    }
    
    // Gestionar opciones de respuesta múltiple
    const addOptionBtn = document.getElementById('add-option');
    const optionsList = document.getElementById('options-list');
    
    if (addOptionBtn && optionsList) {
        // Añadir nueva opción
        addOptionBtn.addEventListener('click', function() {
            const optionsCount = optionsList.querySelectorAll('input[name="options[]"]').length;
            
            const optionDiv = document.createElement('div');
            optionDiv.className = 'flex items-center';
            optionDiv.innerHTML = `
                <input type="text" name="options[]" class="w-full mr-2" placeholder="Lựa chọn ${optionsCount + 1}">
                <button type="button" class="remove-option text-red-600 dark:text-red-400 hover:text-red-800 dark:hover:text-red-300">
                    <i class="fas fa-times"></i>
                </button>
            `;
            
            optionsList.appendChild(optionDiv);
            
            // Asignar evento a nuevo botón de eliminar
            optionDiv.querySelector('.remove-option').addEventListener('click', function() {
                if (optionsList.querySelectorAll('input[name="options[]"]').length > 2) {
                    optionDiv.remove();
                }
            });
        });
        
        // Eliminar opción existente
        document.querySelectorAll('.remove-option').forEach(button => {
            button.addEventListener('click', function() {
                if (optionsList.querySelectorAll('input[name="options[]"]').length > 2) {
                    this.parentElement.remove();
                }
            });
        });
    }
    
    // Preprocesar formulario antes de enviar
    const questionForm = document.querySelector('form');
    if (questionForm) {
        questionForm.addEventListener('submit', function(e) {
            if (!questionTypeSelect) return;
            
            const questionType = questionTypeSelect.value;
            
            if (questionType === 'multiple_choice') {
                const mcAnswer = document.getElementById('mc_answer').value;
                correctAnswerInput.value = mcAnswer;
            } else if (questionType === 'true_false') {
                const tfAnswer = document.querySelector('input[name="tf_answer"]:checked');
                if (tfAnswer) {
                    correctAnswerInput.value = tfAnswer.value;
                }
            } else if (questionType === 'short_answer') {
                const shortAnswer = document.getElementById('short_answer').value;
                correctAnswerInput.value = shortAnswer;
            }
        });
    }
</script>

<?php
// Incluir el pie de página admin
include 'footer.php';
?>