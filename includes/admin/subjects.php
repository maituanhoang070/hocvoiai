<?php
// File: includes/admin/subjects.php
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
$subject_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$message = '';
$error = '';

// Datos iniciales para editar/añadir
$subject = [
    'name' => '',
    'icon' => 'book',
    'description' => ''
];

// Manejar acciones CRUD
if ($action === 'delete' && $subject_id) {
    // Verificar si hay temas asociados
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM study_topics WHERE subject_id = ?");
    $stmt->bind_param("i", $subject_id);
    $stmt->execute();
    $topic_count = $stmt->get_result()->fetch_assoc()['count'];
    
    if ($topic_count > 0) {
        $error = "Không thể xóa môn học này vì có $topic_count chủ đề liên quan!";
    } else {
        $stmt = $conn->prepare("DELETE FROM study_subjects WHERE id = ?");
        $stmt->bind_param("i", $subject_id);
        
        if ($stmt->execute()) {
            $message = 'Đã xóa môn học thành công!';
        } else {
            $error = 'Lỗi: ' . $conn->error;
        }
    }
} else if (($action === 'edit' && $subject_id) || $action === 'add') {
    // Cargar datos para editar
    if ($action === 'edit' && $subject_id) {
        $stmt = $conn->prepare("SELECT * FROM study_subjects WHERE id = ?");
        $stmt->bind_param("i", $subject_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            $subject = $row;
        } else {
            $error = 'Không tìm thấy môn học!';
        }
    }
    
    // Procesar formulario
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $subject['name'] = trim($_POST['name'] ?? '');
        $subject['icon'] = trim($_POST['icon'] ?? 'book');
        $subject['description'] = trim($_POST['description'] ?? '');
        
        if (empty($subject['name'])) {
            $error = 'Tên môn học không được để trống!';
        } else {
            // Verificar que no exista otro con el mismo nombre
            $stmt = $conn->prepare("SELECT id FROM study_subjects WHERE name = ? AND id != ?");
            $stmt->bind_param("si", $subject['name'], $subject_id);
            $stmt->execute();
            $exists = $stmt->get_result()->num_rows > 0;
            
            if ($exists) {
                $error = 'Môn học với tên này đã tồn tại!';
            } else {
                if ($action === 'edit') {
                    $stmt = $conn->prepare("UPDATE study_subjects SET name = ?, icon = ?, description = ? WHERE id = ?");
                    $stmt->bind_param("sssi", $subject['name'], $subject['icon'], $subject['description'], $subject_id);
                } else {
                    $stmt = $conn->prepare("INSERT INTO study_subjects (name, icon, description) VALUES (?, ?, ?)");
                    $stmt->bind_param("sss", $subject['name'], $subject['icon'], $subject['description']);
                }
                
                if ($stmt->execute()) {
                    $message = $action === 'edit' ? 'Đã cập nhật môn học thành công!' : 'Đã thêm môn học thành công!';
                    if ($action === 'add') {
                        $subject_id = $conn->insert_id;
                    }
                    
                    // Redirigir después de procesar
                    header('Location: subjects.php?message=' . urlencode($message));
                    exit;
                } else {
                    $error = 'Lỗi: ' . $conn->error;
                }
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
$limit = 20;
$offset = ($page - 1) * $limit;

$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Construir la consulta
$query = "SELECT s.*, COUNT(t.id) as topic_count 
          FROM study_subjects s 
          LEFT JOIN study_topics t ON s.id = t.subject_id 
          WHERE 1=1";
$count_query = "SELECT COUNT(*) as total FROM study_subjects WHERE 1=1";

$query_params = [];
$param_types = "";

if (!empty($search)) {
    $query .= " AND (s.name LIKE ? OR s.description LIKE ?)";
    $count_query .= " AND (name LIKE ? OR description LIKE ?)";
    $search_param = "%$search%";
    $query_params[] = $search_param;
    $query_params[] = $search_param;
    $param_types .= "ss";
}

$query .= " GROUP BY s.id";

// Ejecutar consulta de conteo
$stmt = $conn->prepare($count_query);
if (!empty($param_types)) {
    $stmt->bind_param($param_types, ...$query_params);
}
$stmt->execute();
$total_records = $stmt->get_result()->fetch_assoc()['total'];

$total_pages = ceil($total_records / $limit);

// Añadir límites para paginación
$query .= " ORDER BY s.name LIMIT ?, ?";
$query_params[] = $offset;
$query_params[] = $limit;
$param_types .= "ii";

// Ejecutar consulta principal
$stmt = $conn->prepare($query);
if (!empty($param_types)) {
    $stmt->bind_param($param_types, ...$query_params);
}
$stmt->execute();
$subjects = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Incluir el encabezado admin
$admin_page = 'subjects';
include 'header.php';
?>

<div class="flex-1 p-6 overflow-auto">
    <div class="max-w-7xl mx-auto">
        <?php if ($action === 'edit' || $action === 'add'): ?>
        <!-- Formulario de edición/adición -->
        <div class="flex items-center mb-6">
            <a href="subjects.php" class="mr-3 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300">
                <i class="fas fa-arrow-left"></i>
            </a>
            <h1 class="text-2xl font-bold"><?php echo $action === 'edit' ? 'Chỉnh sửa môn học' : 'Thêm môn học mới'; ?></h1>
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
            <form method="post" action="subjects.php?action=<?php echo $action; ?><?php echo $action === 'edit' ? '&id=' . $subject_id : ''; ?>">
                <div class="mb-4">
                    <label for="name" class="block text-sm font-medium mb-1">Tên môn học <span class="text-red-500">*</span></label>
                    <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($subject['name']); ?>" class="w-full" required>
                </div>
                
                <div class="mb-4">
                    <label for="icon" class="block text-sm font-medium mb-1">Icon <a href="https://fontawesome.com/icons?d=gallery&m=free" target="_blank" class="text-indigo-600 dark:text-indigo-400">(Xem danh sách)</a></label>
                    <input type="text" id="icon" name="icon" value="<?php echo htmlspecialchars($subject['icon']); ?>" class="w-full" placeholder="Ví dụ: book, calculator, atom...">
                    <div class="mt-2 flex items-center">
                        <div class="w-10 h-10 rounded-full bg-indigo-100 dark:bg-indigo-900/30 flex items-center justify-center text-indigo-600 dark:text-indigo-400 mr-3">
                            <i id="icon-preview" class="fas fa-<?php echo htmlspecialchars($subject['icon']); ?>"></i>
                        </div>
                        <div class="text-sm text-gray-500 dark:text-gray-400">
                            Preview Icon
                        </div>
                    </div>
                </div>
                
                <div class="mb-6">
                    <label for="description" class="block text-sm font-medium mb-1">Mô tả</label>
                    <textarea id="description" name="description" rows="4" class="w-full"><?php echo htmlspecialchars($subject['description']); ?></textarea>
                </div>
                
                <div class="flex justify-end">
                    <a href="subjects.php" class="btn btn-secondary mr-3">
                        Hủy
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <?php echo $action === 'edit' ? 'Cập nhật' : 'Thêm mới'; ?>
                    </button>
                </div>
            </form>
        </div>
        <?php else: ?>
        <!-- Lista de materias -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
            <h1 class="text-2xl font-bold mb-4 md:mb-0">Quản lý môn học</h1>
            
            <div>
                <a href="subjects.php?action=add" class="btn btn-primary">
                    <i class="fas fa-plus mr-2"></i>Thêm môn học
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
            <form action="subjects.php" method="get" class="flex flex-col md:flex-row gap-4">
                <div class="flex-1">
                    <label for="search" class="block text-sm font-medium mb-1">Tìm kiếm</label>
                    <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Tên môn học, mô tả..." class="w-full">
                </div>
                
                <div class="flex items-end">
                    <button type="submit" class="btn btn-primary h-10 px-4">
                        <i class="fas fa-search mr-2"></i>Lọc
                    </button>
                </div>
            </form>
        </div>
        
        <!-- Subject List -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php foreach ($subjects as $subject_item): ?>
            <div class="card rounded-lg overflow-hidden shadow-sm hover:shadow-md transition-shadow">
                <div class="p-6">
                    <div class="flex items-center mb-4">
                        <div class="w-12 h-12 rounded-full bg-indigo-100 dark:bg-indigo-900/30 flex items-center justify-center text-indigo-600 dark:text-indigo-400 mr-4">
                            <i class="fas fa-<?php echo htmlspecialchars($subject_item['icon']); ?> text-xl"></i>
                        </div>
                        <div>
                            <h2 class="text-lg font-semibold"><?php echo htmlspecialchars($subject_item['name']); ?></h2>
                            <div class="text-sm text-gray-500 dark:text-gray-400">
                                <?php echo $subject_item['topic_count']; ?> chủ đề
                            </div>
                        </div>
                    </div>
                    
                    <?php if (!empty($subject_item['description'])): ?>
                    <p class="text-sm mb-4 line-clamp-2"><?php echo htmlspecialchars($subject_item['description']); ?></p>
                    <?php else: ?>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mb-4 italic">Không có mô tả</p>
                    <?php endif; ?>
                    
                    <div class="flex justify-between pt-3 border-t border-gray-200 dark:border-gray-700">
                        <a href="topics.php?subject_id=<?php echo $subject_item['id']; ?>" class="text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-300 text-sm">
                            <i class="fas fa-clipboard-list mr-1"></i>Xem chủ đề
                        </a>
                        
                        <div>
                            <a href="subjects.php?action=edit&id=<?php echo $subject_item['id']; ?>" class="text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 text-sm">
                                <i class="fas fa-edit mr-1"></i>Sửa
                            </a>
                            
                            <?php if ($subject_item['topic_count'] == 0): ?>
                            <a href="subjects.php?action=delete&id=<?php echo $subject_item['id']; ?>" class="text-red-600 dark:text-red-400 hover:text-red-800 dark:hover:text-red-300 text-sm ml-4 confirm-delete">
                                <i class="fas fa-trash-alt mr-1"></i>Xóa
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            
            <?php if (empty($subjects)): ?>
            <div class="col-span-full text-center py-12 text-gray-500 dark:text-gray-400">
                <i class="fas fa-search text-5xl mb-4"></i>
                <p>Không tìm thấy môn học nào</p>
                <a href="subjects.php?action=add" class="btn btn-primary mt-4">
                    <i class="fas fa-plus mr-2"></i>Thêm môn học mới
                </a>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="mt-6 flex justify-center">
            <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                <?php if ($page > 1): ?>
                <a href="?page_num=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>" class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm font-medium text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-600">
                    <i class="fas fa-chevron-left"></i>
                </a>
                <?php endif; ?>
                
                <?php
                $start_page = max(1, $page - 2);
                $end_page = min($total_pages, $page + 2);
                
                if ($start_page > 1): ?>
                <a href="?page_num=1&search=<?php echo urlencode($search); ?>" class="relative inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600">
                    1
                </a>
                <?php if ($start_page > 2): ?>
                <span class="relative inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm font-medium text-gray-700 dark:text-gray-300">
                    ...
                </span>
                <?php endif; ?>
                <?php endif; ?>
                
                <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                <a href="?page_num=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>" class="relative inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm font-medium <?php echo $page === $i ? 'text-indigo-600 dark:text-indigo-500 bg-indigo-50 dark:bg-indigo-900/20' : 'text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600'; ?>">
                    <?php echo $i; ?>
                </a>
                <?php endfor; ?>
                
                <?php if ($end_page < $total_pages): ?>
                <?php if ($end_page < $total_pages - 1): ?>
                <span class="relative inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm font-medium text-gray-700 dark:text-gray-300">
                    ...
                </span>
                <?php endif; ?>
                <a href="?page_num=<?php echo $total_pages; ?>&search=<?php echo urlencode($search); ?>" class="relative inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600">
                    <?php echo $total_pages; ?>
                </a>
                <?php endif; ?>
                
                <?php if ($page < $total_pages): ?>
                <a href="?page_num=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>" class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm font-medium text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-600">
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
    // Preview de iconos
    const iconInput = document.getElementById('icon');
    const iconPreview = document.getElementById('icon-preview');
    
    if (iconInput && iconPreview) {
        iconInput.addEventListener('input', function() {
            iconPreview.className = 'fas fa-' + this.value;
        });
    }
</script>

<?php
// Incluir el pie de página admin
include 'footer.php';
?>