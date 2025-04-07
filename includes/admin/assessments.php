<?php
// File: includes/admin/assessments.php
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
$assessment_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$topic_id = isset($_GET['topic_id']) ? intval($_GET['topic_id']) : 0;
$message = '';
$error = '';

// Datos iniciales para editar/añadir
$assessment = [
    'topic_id' => $topic_id,
    'title' => '',
    'description' => '',
    'time_limit_minutes' => 30,
    'passing_score' => 70
];

// Manejar acciones CRUD
if ($action === 'delete' && $assessment_id) {
    // Verificar si hay preguntas asociadas
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM assessment_questions WHERE assessment_id = ?");
    $stmt->bind_param("i", $assessment_id);
    $stmt->execute();
    $question_count = $stmt->get_result()->fetch_assoc()['count'];
    
    if ($question_count > 0) {
        $error = "Cần xóa $question_count câu hỏi trước khi xóa bài kiểm tra này!";
    } else {
        $stmt = $conn->prepare("DELETE FROM self_assessments WHERE id = ?");
        $stmt->bind_param("i", $assessment_id);
        
        if ($stmt->execute()) {
            $message = 'Đã xóa bài kiểm tra thành công!';
        } else {
            $error = 'Lỗi: ' . $conn->error;
        }
    }
} else if (($action === 'edit' && $assessment_id) || $action === 'add') {
    // Cargar datos para editar
    if ($action === 'edit' && $assessment_id) {
        $stmt = $conn->prepare("SELECT * FROM self_assessments WHERE id = ?");
        $stmt->bind_param("i", $assessment_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            $assessment = $row;
            $topic_id = $assessment['topic_id'];
        } else {
            $error = 'Không tìm thấy bài kiểm tra!';
        }
    }
    
    // Procesar formulario
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $assessment['topic_id'] = intval($_POST['topic_id'] ?? 0);
        $assessment['title'] = trim($_POST['title'] ?? '');
        $assessment['description'] = trim($_POST['description'] ?? '');
        $assessment['time_limit_minutes'] = intval($_POST['time_limit_minutes'] ?? 30);
        $assessment['passing_score'] = intval($_POST['passing_score'] ?? 70);
        
        if (empty($assessment['title'])) {
            $error = 'Tiêu đề bài kiểm tra không được để trống!';
        } else if (empty($assessment['topic_id'])) {
            $error = 'Vui lòng chọn chủ đề!';
        } else if ($assessment['time_limit_minutes'] < 1) {
            $error = 'Thời gian làm bài phải ít nhất 1 phút!';
        } else if ($assessment['passing_score'] < 1 || $assessment['passing_score'] > 100) {
            $error = 'Điểm đạt phải từ 1 đến 100!';
        } else {
            if ($action === 'edit') {
                $stmt = $conn->prepare("UPDATE self_assessments SET topic_id = ?, title = ?, description = ?, time_limit_minutes = ?, passing_score = ? WHERE id = ?");
                $stmt->bind_param("issiii", $assessment['topic_id'], $assessment['title'], $assessment['description'], $assessment['time_limit_minutes'], $assessment['passing_score'], $assessment_id);
            } else {
                $stmt = $conn->prepare("INSERT INTO self_assessments (topic_id, title, description, time_limit_minutes, passing_score, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
                $stmt->bind_param("issii", $assessment['topic_id'], $assessment['title'], $assessment['description'], $assessment['time_limit_minutes'], $assessment['passing_score']);
            }
            
            if ($stmt->execute()) {
                if ($action === 'add') {
                    $assessment_id = $conn->insert_id;
                }
                
                $message = $action === 'edit' ? 'Đã cập nhật bài kiểm tra thành công!' : 'Đã thêm bài kiểm tra thành công!';
                
                // Redirigir a preguntas si se solicita
                if (isset($_POST['redirect_to_questions']) && $_POST['redirect_to_questions'] == '1') {
                    header('Location: questions.php?assessment_id=' . $assessment_id . '&message=' . urlencode($message));
                    exit;
                }
                
                // Redirigir después de procesar
                header('Location: assessments.php?topic_id=' . $assessment['topic_id'] . '&message=' . urlencode($message));
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

// Obtener todos los temas para el formulario
$topics = [];
$stmt = $conn->query("SELECT t.id, t.name, s.name as subject_name FROM study_topics t JOIN study_subjects s ON t.subject_id = s.id ORDER BY s.name, t.name");
while ($row = $stmt->fetch_assoc()) {
    $topics[] = $row;
}

// Construir la consulta
$query = "SELECT a.*, t.name as topic_name, s.name as subject_name,
           (SELECT COUNT(*) FROM assessment_questions WHERE assessment_id = a.id) as question_count,
           (SELECT COUNT(*) FROM assessment_results WHERE assessment_id = a.id) as result_count
           FROM self_assessments a
           JOIN study_topics t ON a.topic_id = t.id
           JOIN study_subjects s ON t.subject_id = s.id
           WHERE 1=1";
$count_query = "SELECT COUNT(*) as total FROM self_assessments a WHERE 1=1";

$query_params = [];
$param_types = "";

if (!empty($search)) {
    $query .= " AND (a.title LIKE ? OR a.description LIKE ?)";
    $count_query .= " AND (title LIKE ? OR description LIKE ?)";
    $search_param = "%$search%";
    $query_params[] = $search_param;
    $query_params[] = $search_param;
    $param_types .= "ss";
}

if ($topic_id) {
    $query .= " AND a.topic_id = ?";
    $count_query .= " AND topic_id = ?";
    $query_params[] = $topic_id;
    $param_types .= "i";
    
    // Obtener información del tema
    $stmt = $conn->prepare("SELECT t.name, t.subject_id, s.name as subject_name FROM study_topics t JOIN study_subjects s ON t.subject_id = s.id WHERE t.id = ?");
    $stmt->bind_param("i", $topic_id);
    $stmt->execute();
    $current_topic = $stmt->get_result()->fetch_assoc();
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
$query .= " ORDER BY a.created_at DESC LIMIT ?, ?";
$query_params[] = $offset;
$query_params[] = $limit;
$param_types .= "ii";

// Ejecutar consulta principal
$stmt = $conn->prepare($query);
if (!empty($param_types)) {
    $stmt->bind_param($param_types, ...$query_params);
}
$stmt->execute();
$assessments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Incluir el encabezado admin
$admin_page = 'assessments';
include 'header.php';
?>

<div class="flex-1 p-6 overflow-auto">
    <div class="max-w-7xl mx-auto">
        <?php if ($action === 'edit' || $action === 'add'): ?>
        <!-- Formulario de edición/adición -->
        <div class="flex items-center mb-6">
            <a href="assessments.php<?php echo $topic_id ? '?topic_id=' . $topic_id : ''; ?>" class="mr-3 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300">
                <i class="fas fa-arrow-left"></i>
            </a>
            <h1 class="text-2xl font-bold"><?php echo $action === 'edit' ? 'Chỉnh sửa bài kiểm tra' : 'Thêm bài kiểm tra mới'; ?></h1>
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
            <form method="post" action="assessments.php?action=<?php echo $action; ?><?php echo $action === 'edit' ? '&id=' . $assessment_id : ''; ?>">
                <div class="mb-4">
                    <label for="topic_id" class="block text-sm font-medium mb-1">Chủ đề <span class="text-red-500">*</span></label>
                    <select id="topic_id" name="topic_id" class="w-full" required>
                        <option value="">-- Chọn chủ đề --</option>
                        <?php foreach ($topics as $topic): ?>
                        <option value="<?php echo $topic['id']; ?>" <?php echo $assessment['topic_id'] == $topic['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($topic['subject_name'] . ' - ' . $topic['name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="mb-4">
                    <label for="title" class="block text-sm font-medium mb-1">Tiêu đề <span class="text-red-500">*</span></label>
                    <input type="text" id="title" name="title" value="<?php echo htmlspecialchars($assessment['title']); ?>" class="w-full" required>
                </div>
                
                <div class="mb-4">
                    <label for="description" class="block text-sm font-medium mb-1">Mô tả</label>
                    <textarea id="description" name="description" rows="3" class="w-full"><?php echo htmlspecialchars($assessment['description']); ?></textarea>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                    <div>
                        <label for="time_limit_minutes" class="block text-sm font-medium mb-1">Thời gian làm bài (phút)</label>
                        <input type="number" id="time_limit_minutes" name="time_limit_minutes" value="<?php echo intval($assessment['time_limit_minutes']); ?>" min="1" class="w-full">
                    </div>
                    
                    <div>
                        <label for="passing_score" class="block text-sm font-medium mb-1">Điểm đạt (% điểm tối đa)</label>
                        <input type="number" id="passing_score" name="passing_score" value="<?php echo intval($assessment['passing_score']); ?>" min="1" max="100" class="w-full">
                    </div>
                </div>
                
                <div class="flex justify-end">
                    <?php if ($action === 'add'): ?>
                    <div class="mr-auto">
                        <label class="flex items-center">
                            <input type="checkbox" name="redirect_to_questions" value="1" class="mr-2">
                            <span class="text-sm">Thêm câu hỏi ngay sau khi tạo</span>
                        </label>
                    </div>
                    <?php endif; ?>
                    
                    <a href="assessments.php<?php echo $topic_id ? '?topic_id=' . $topic_id : ''; ?>" class="btn btn-secondary mr-3">
                        Hủy
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <?php echo $action === 'edit' ? 'Cập nhật' : 'Thêm mới'; ?>
                    </button>
                </div>
            </form>
        </div>
        <?php else: ?>
        <!-- Lista de evaluaciones -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
            <?php if ($topic_id && isset($current_topic)): ?>
            <div>
                <div class="flex items-center mb-2">
                    <a href="topics.php?subject_id=<?php echo $current_topic['subject_id']; ?>" class="mr-3 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300">
                        <i class="fas fa-arrow-left"></i>
                    </a>
                    <h1 class="text-2xl font-bold">Bài kiểm tra: <?php echo htmlspecialchars($current_topic['name']); ?></h1>
                </div>
                <p class="text-gray-600 dark:text-gray-400">
                    Môn học: <?php echo htmlspecialchars($current_topic['subject_name']); ?>
                </p>
            </div>
            <?php else: ?>
            <div>
                <h1 class="text-2xl font-bold mb-2">Quản lý bài kiểm tra</h1>
                <p class="text-gray-600 dark:text-gray-400">Quản lý tất cả các bài kiểm tra trong hệ thống</p>
            </div>
            <?php endif; ?>
            
            <div class="mt-4 md:mt-0">
                <a href="assessments.php?action=add<?php echo $topic_id ? '&topic_id=' . $topic_id : ''; ?>" class="btn btn-primary">
                    <i class="fas fa-plus mr-2"></i>Thêm bài kiểm tra
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
            <form action="assessments.php" method="get" class="flex flex-col md:flex-row gap-4">
                <?php if ($topic_id): ?>
                <input type="hidden" name="topic_id" value="<?php echo $topic_id; ?>">
                <?php else: ?>
                <div class="w-full md:w-64">
                    <label for="topic_id" class="block text-sm font-medium mb-1">Chủ đề</label>
                    <select id="topic_id" name="topic_id" class="w-full">
                        <option value="">Tất cả chủ đề</option>
                        <?php foreach ($topics as $topic): ?>
                        <option value="<?php echo $topic['id']; ?>" <?php echo $topic_id == $topic['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($topic['subject_name'] . ' - ' . $topic['name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                
                <div class="flex-1">
                    <label for="search" class="block text-sm font-medium mb-1">Tìm kiếm</label>
                    <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Tiêu đề, mô tả..." class="w-full">
                </div>
                
                <div class="flex items-end">
                    <button type="submit" class="btn btn-primary h-10 px-4">
                        <i class="fas fa-search mr-2"></i>Lọc
                    </button>
                </div>
            </form>
        </div>
        
        <!-- Assessments List -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php foreach ($assessments as $assessment_item): ?>
            <div class="card rounded-lg overflow-hidden shadow-sm hover:shadow-md transition-shadow">
                <div class="p-6">
                    <div class="flex justify-between items-start mb-4">
                        <div>
                            <h2 class="text-lg font-semibold"><?php echo htmlspecialchars($assessment_item['title']); ?></h2>
                            <div class="text-sm text-gray-500 dark:text-gray-400">
                                <?php echo htmlspecialchars($assessment_item['subject_name'] . ' - ' . $assessment_item['topic_name']); ?>
                            </div>
                        </div>
                        
                        <div class="flex">
                            <div class="text-center px-2">
                                <div class="text-lg font-semibold"><?php echo $assessment_item['question_count']; ?></div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">Câu hỏi</div>
                            </div>
                        </div>
                    </div>
                    
                    <?php if (!empty($assessment_item['description'])): ?>
                    <p class="text-sm mb-4"><?php echo htmlspecialchars($assessment_item['description']); ?></p>
                    <?php endif; ?>
                    
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center">
                            <i class="fas fa-clock text-gray-500 dark:text-gray-400 mr-2"></i>
                            <span class="text-sm"><?php echo $assessment_item['time_limit_minutes']; ?> phút</span>
                        </div>
                        <div class="flex items-center">
                            <i class="fas fa-award text-gray-500 dark:text-gray-400 mr-2"></i>
                            <span class="text-sm">Đạt: ≥ <?php echo $assessment_item['passing_score']; ?>%</span>
                        </div>
                    </div>
                    
                    <?php if ($assessment_item['result_count'] > 0): ?>
                    <div class="mb-4 text-sm text-gray-500 dark:text-gray-400">
                        Đã có <?php echo $assessment_item['result_count']; ?> lượt làm bài
                    </div>
                    <?php endif; ?>
                    
                    <div class="flex justify-between pt-3 border-t border-gray-200 dark:border-gray-700">
                        <a href="questions.php?assessment_id=<?php echo $assessment_item['id']; ?>" class="text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-300 text-sm">
                            <i class="fas fa-question-circle mr-1"></i>Quản lý câu hỏi
                        </a>
                        
                        <div>
                            <a href="assessments.php?action=edit&id=<?php echo $assessment_item['id']; ?>" class="text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 text-sm">
                                <i class="fas fa-edit mr-1"></i>Sửa
                            </a>
                            
                            <?php if ($assessment_item['question_count'] == 0): ?>
                            <a href="assessments.php?action=delete&id=<?php echo $assessment_item['id']; ?><?php echo $topic_id ? '&topic_id=' . $topic_id : ''; ?>" class="text-red-600 dark:text-red-400 hover:text-red-800 dark:hover:text-red-300 text-sm ml-4 confirm-delete">
                                <i class="fas fa-trash-alt mr-1"></i>Xóa
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            
            <?php if (empty($assessments)): ?>
            <div class="col-span-full text-center py-12 text-gray-500 dark:text-gray-400">
                <i class="fas fa-search text-5xl mb-4"></i>
                <p>Không tìm thấy bài kiểm tra nào</p>
                <a href="assessments.php?action=add<?php echo $topic_id ? '&topic_id=' . $topic_id : ''; ?>" class="btn btn-primary mt-4">
                    <i class="fas fa-plus mr-2"></i>Thêm bài kiểm tra mới
                </a>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="mt-6 flex justify-center">
            <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                <?php if ($page > 1): ?>
                <a href="?page_num=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?><?php echo $topic_id ? '&topic_id=' . $topic_id : ''; ?>" class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm font-medium text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-600">
                    <i class="fas fa-chevron-left"></i>
                </a>
                <?php endif; ?>
                
                <?php
                $start_page = max(1, $page - 2);
                $end_page = min($total_pages, $page + 2);
                
                if ($start_page > 1): ?>
                <a href="?page_num=1&search=<?php echo urlencode($search); ?><?php echo $topic_id ? '&topic_id=' . $topic_id : ''; ?>" class="relative inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600">
                    1
                </a>
                <?php if ($start_page > 2): ?>
                <span class="relative inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm font-medium text-gray-700 dark:text-gray-300">
                    ...
                </span>
                <?php endif; ?>
                <?php endif; ?>
                
                <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                <a href="?page_num=<?php echo $i; ?>&search=<?php echo urlencode($search); ?><?php echo $topic_id ? '&topic_id=' . $topic_id : ''; ?>" class="relative inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm font-medium <?php echo $page === $i ? 'text-indigo-600 dark:text-indigo-500 bg-indigo-50 dark:bg-indigo-900/20' : 'text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600'; ?>">
                    <?php echo $i; ?>
                </a>
                <?php endfor; ?>
                
                <?php if ($end_page < $total_pages): ?>
                <?php if ($end_page < $total_pages - 1): ?>
                <span class="relative inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm font-medium text-gray-700 dark:text-gray-300">
                    ...
                </span>
                <?php endif; ?>
                <a href="?page_num=<?php echo $total_pages; ?>&search=<?php echo urlencode($search); ?><?php echo $topic_id ? '&topic_id=' . $topic_id : ''; ?>" class="relative inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600">
                    <?php echo $total_pages; ?>
                </a>
                <?php endif; ?>
                
                <?php if ($page < $total_pages): ?>
                <a href="?page_num=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?><?php echo $topic_id ? '&topic_id=' . $topic_id : ''; ?>" class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm font-medium text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-600">
                    <i class="fas fa-chevron-right"></i>
                </a>
                <?php endif; ?>
            </nav>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php
// Incluir el pie de página admin
include 'footer.php';
?>