<?php
// File: includes/admin/users.php
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
$user_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$message = '';
$error = '';

// Manejar acciones CRUD
if ($action === 'delete' && $user_id) {
    // No permitir eliminar el propio usuario administrador
    if ($user_id == $_SESSION['user_id']) {
        $error = 'Không thể xóa tài khoản của chính bạn!';
    } else {
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        
        if ($stmt->execute()) {
            $message = 'Đã xóa người dùng thành công!';
        } else {
            $error = 'Lỗi: ' . $conn->error;
        }
    }
} else if ($action === 'toggle_admin' && $user_id) {
    // No permitir cambiar el rol del propio usuario administrador
    if ($user_id == $_SESSION['user_id']) {
        $error = 'Không thể thay đổi quyền của chính bạn!';
    } else {
        // Obtener rol actual
        $stmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            $new_role = ($row['role'] === 'admin') ? 'user' : 'admin';
            
            $stmt = $conn->prepare("UPDATE users SET role = ? WHERE id = ?");
            $stmt->bind_param("si", $new_role, $user_id);
            
            if ($stmt->execute()) {
                $message = 'Đã cập nhật quyền người dùng thành công!';
            } else {
                $error = 'Lỗi: ' . $conn->error;
            }
        } else {
            $error = 'Không tìm thấy người dùng!';
        }
    }
} else if ($action === 'add_points' && $user_id && isset($_POST['points'])) {
    $points = intval($_POST['points']);
    $reason = trim($_POST['reason'] ?? '');
    
    if ($points <= 0) {
        $error = 'Số điểm phải lớn hơn 0!';
    } else {
        // Añadir puntos al usuario
        if (addPoints($user_id, $points, 'admin_add', $reason)) {
            $message = "Đã thêm $points điểm cho người dùng thành công!";
        } else {
            $error = 'Lỗi khi thêm điểm!';
        }
    }
}

// Paginación y filtrado
$page = isset($_GET['page_num']) ? intval($_GET['page_num']) : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$role_filter = isset($_GET['role']) ? trim($_GET['role']) : '';

// Construir la consulta
$query = "SELECT * FROM users WHERE 1=1";
$count_query = "SELECT COUNT(*) as total FROM users WHERE 1=1";

$query_params = [];
$param_types = "";

if (!empty($search)) {
    $query .= " AND (username LIKE ? OR email LIKE ? OR full_name LIKE ?)";
    $count_query .= " AND (username LIKE ? OR email LIKE ? OR full_name LIKE ?)";
    $search_param = "%$search%";
    $query_params[] = $search_param;
    $query_params[] = $search_param;
    $query_params[] = $search_param;
    $param_types .= "sss";
}

if (!empty($role_filter)) {
    $query .= " AND role = ?";
    $count_query .= " AND role = ?";
    $query_params[] = $role_filter;
    $param_types .= "s";
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
$query .= " ORDER BY created_at DESC LIMIT ?, ?";
$query_params[] = $offset;
$query_params[] = $limit;
$param_types .= "ii";

// Ejecutar consulta principal
$stmt = $conn->prepare($query);
if (!empty($param_types)) {
    $stmt->bind_param($param_types, ...$query_params);
}
$stmt->execute();
$users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Incluir el encabezado admin
$admin_page = 'users';
include 'header.php';
?>

<div class="flex-1 p-6 overflow-auto">
    <div class="max-w-7xl mx-auto">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
            <h1 class="text-2xl font-bold mb-4 md:mb-0">Quản lý người dùng</h1>
            
            <div class="flex flex-col sm:flex-row gap-3">
                <a href="users.php?action=add" class="btn btn-primary">
                    <i class="fas fa-user-plus mr-2"></i>Thêm người dùng
                </a>
                <a href="users.php?action=export" class="btn btn-secondary">
                    <i class="fas fa-file-export mr-2"></i>Xuất danh sách
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
            <form action="users.php" method="get" class="flex flex-col md:flex-row gap-4">
                <div class="flex-1">
                    <label for="search" class="block text-sm font-medium mb-1">Tìm kiếm</label>
                    <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Tên đăng nhập, email..." class="w-full">
                </div>
                
                <div class="w-full md:w-48">
                    <label for="role" class="block text-sm font-medium mb-1">Quyền</label>
                    <select id="role" name="role" class="w-full">
                        <option value="">Tất cả</option>
                        <option value="admin" <?php echo $role_filter === 'admin' ? 'selected' : ''; ?>>Admin</option>
                        <option value="user" <?php echo $role_filter === 'user' ? 'selected' : ''; ?>>Người dùng</option>
                    </select>
                </div>
                
                <div class="flex items-end">
                    <button type="submit" class="btn btn-primary h-10 px-4">
                        <i class="fas fa-search mr-2"></i>Lọc
                    </button>
                </div>
            </form>
        </div>
        
        <!-- User List -->
        <div class="card rounded-lg overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-800">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Tài khoản</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Thông tin</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Điểm</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Trạng thái</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        <?php foreach ($users as $user_row): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div class="w-10 h-10 rounded-full bg-indigo-100 dark:bg-indigo-900/30 flex items-center justify-center text-indigo-600 dark:text-indigo-400 font-medium">
                                        <?php echo strtoupper(substr($user_row['username'], 0, 1)); ?>
                                    </div>
                                    <div class="ml-4">
                                        <div class="text-sm font-medium"><?php echo htmlspecialchars($user_row['username']); ?></div>
                                        <div class="text-sm text-gray-500 dark:text-gray-400">
                                            ID: <?php echo $user_row['id']; ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm"><?php echo htmlspecialchars($user_row['email']); ?></div>
                                <?php if (!empty($user_row['full_name'])): ?>
                                <div class="text-sm text-gray-500 dark:text-gray-400"><?php echo htmlspecialchars($user_row['full_name']); ?></div>
                                <?php endif; ?>
                                <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                    Đăng ký: <?php echo date('d/m/Y', strtotime($user_row['created_at'])); ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div class="text-sm font-medium"><?php echo number_format($user_row['points']); ?></div>
                                    <button type="button" class="ml-2 text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-300 add-points-btn" data-user-id="<?php echo $user_row['id']; ?>" data-username="<?php echo htmlspecialchars($user_row['username']); ?>">
                                        <i class="fas fa-plus-circle"></i>
                                    </button>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php if ($user_row['role'] === 'admin'): ?>
                                <span class="inline-flex px-2 py-1 text-xs rounded-full bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-400">
                                    Admin
                                </span>
                                <?php else: ?>
                                <span class="inline-flex px-2 py-1 text-xs rounded-full bg-gray-100 text-gray-800 dark:bg-gray-900/30 dark:text-gray-400">
                                    Người dùng
                                </span>
                                <?php endif; ?>
                                
                                <?php if ($user_row['last_login']): ?>
                                <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                    Đăng nhập: <?php echo date('d/m/Y H:i', strtotime($user_row['last_login'])); ?>
                                </div>
                                <?php else: ?>
                                <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                    Chưa đăng nhập
                                </div>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                <div class="flex flex-col space-y-2">
                                    <a href="users.php?action=edit&id=<?php echo $user_row['id']; ?>" class="text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-300">
                                        <i class="fas fa-edit mr-1"></i>Sửa
                                    </a>
                                    
                                    <?php if ($user_row['id'] != $_SESSION['user_id']): ?>
                                    <a href="users.php?action=toggle_admin&id=<?php echo $user_row['id']; ?>" class="text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300">
                                        <i class="fas fa-user-shield mr-1"></i><?php echo $user_row['role'] === 'admin' ? 'Hủy quyền admin' : 'Cấp quyền admin'; ?>
                                    </a>
                                    
                                    <a href="users.php?action=delete&id=<?php echo $user_row['id']; ?>" class="text-red-600 dark:text-red-400 hover:text-red-800 dark:hover:text-red-300 confirm-delete">
                                        <i class="fas fa-trash-alt mr-1"></i>Xóa
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        
                        <?php if (empty($users)): ?>
                        <tr>
                            <td colspan="5" class="px-6 py-4 text-center text-gray-500 dark:text-gray-400">
                                Không tìm thấy người dùng nào
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="bg-gray-50 dark:bg-gray-800 px-4 py-3 flex items-center justify-between border-t border-gray-200 dark:border-gray-700 sm:px-6">
                <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                    <div>
                        <p class="text-sm text-gray-700 dark:text-gray-400">
                            Hiển thị <span class="font-medium"><?php echo count($users); ?></span> trên tổng số <span class="font-medium"><?php echo $total_records; ?></span> người dùng
                        </p>
                    </div>
                    <div>
                        <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                            <?php if ($page > 1): ?>
                            <a href="?page_num=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo urlencode($role_filter); ?>" class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm font-medium text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-600">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                            <?php endif; ?>
                            
                            <?php
                            $start_page = max(1, $page - 2);
                            $end_page = min($total_pages, $page + 2);
                            
                            if ($start_page > 1): ?>
                            <a href="?page_num=1&search=<?php echo urlencode($search); ?>&role=<?php echo urlencode($role_filter); ?>" class="relative inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600">
                                1
                            </a>
                            <?php if ($start_page > 2): ?>
                            <span class="relative inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm font-medium text-gray-700 dark:text-gray-300">
                                ...
                            </span>
                            <?php endif; ?>
                            <?php endif; ?>
                            
                            <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                            <a href="?page_num=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo urlencode($role_filter); ?>" class="relative inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm font-medium <?php echo $page === $i ? 'text-indigo-600 dark:text-indigo-500 bg-indigo-50 dark:bg-indigo-900/20' : 'text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600'; ?>">
                                <?php echo $i; ?>
                            </a>
                            <?php endfor; ?>
                            
                            <?php if ($end_page < $total_pages): ?>
                            <?php if ($end_page < $total_pages - 1): ?>
                            <span class="relative inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm font-medium text-gray-700 dark:text-gray-300">
                                ...
                            </span>
                            <?php endif; ?>
                            <a href="?page_num=<?php echo $total_pages; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo urlencode($role_filter); ?>" class="relative inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600">
                                <?php echo $total_pages; ?>
                            </a>
                            <?php endif; ?>
                            
                            <?php if ($page < $total_pages): ?>
                            <a href="?page_num=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo urlencode($role_filter); ?>" class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm font-medium text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-600">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                            <?php endif; ?>
                        </nav>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Modal para añadir puntos -->
    <div id="add-points-modal" class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center hidden">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6 w-full max-w-md">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-medium">Thêm điểm cho <span id="modal-username"></span></h3>
                <button type="button" class="text-gray-500 hover:text-gray-700 focus:outline-none" id="close-modal">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <form action="users.php" method="post" id="add-points-form">
                <input type="hidden" name="action" value="add_points">
                <input type="hidden" name="id" id="modal-user-id">
                
                <div class="mb-4">
                    <label for="points" class="block text-sm font-medium mb-1">Số điểm</label>
                    <input type="number" id="points" name="points" min="1" value="10" class="w-full" required>
                </div>
                
                <div class="mb-6">
                    <label for="reason" class="block text-sm font-medium mb-1">Lý do (không bắt buộc)</label>
                    <input type="text" id="reason" name="reason" placeholder="Ví dụ: Thưởng hoạt động tích cực" class="w-full">
                </div>
                
                <div class="flex justify-end">
                    <button type="button" class="btn btn-secondary mr-3" id="cancel-modal">
                        Hủy
                    </button>
                    <button type="submit" class="btn btn-primary">
                        Thêm điểm
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // Manejar modal para añadir puntos
    const addPointsModal = document.getElementById('add-points-modal');
    const addPointsButtons = document.querySelectorAll('.add-points-btn');
    const closeModalBtn = document.getElementById('close-modal');
    const cancelModalBtn = document.getElementById('cancel-modal');
    const modalUsername = document.getElementById('modal-username');
    const modalUserId = document.getElementById('modal-user-id');
    const addPointsForm = document.getElementById('add-points-form');
    
    addPointsButtons.forEach(button => {
        button.addEventListener('click', function() {
            const userId = this.getAttribute('data-user-id');
            const username = this.getAttribute('data-username');
            
            modalUserId.value = userId;
            modalUsername.textContent = username;
            
            addPointsModal.classList.remove('hidden');
        });
    });
    
    function closeModal() {
        addPointsModal.classList.add('hidden');
    }
    
    if (closeModalBtn) closeModalBtn.addEventListener('click', closeModal);
    if (cancelModalBtn) cancelModalBtn.addEventListener('click', closeModal);
    
    // Cerrar modal haciendo clic fuera
    addPointsModal.addEventListener('click', function(e) {
        if (e.target === this) {
            closeModal();
        }
    });
    
    // Manejar formulario
    addPointsForm.addEventListener('submit', function(e) {
        const points = parseInt(document.getElementById('points').value);
        
        if (isNaN(points) || points <= 0) {
            e.preventDefault();
            alert('Vui lòng nhập số điểm hợp lệ lớn hơn 0.');
            return;
        }
        
        this.action = `users.php?action=add_points&id=${modalUserId.value}`;
    });
</script>

<?php
// Incluir el pie de página admin
include 'footer.php';
?>