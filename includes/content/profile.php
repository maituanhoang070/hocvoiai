<?php
// File: includes/content/profile.php
// Verificar que el usuario haya iniciado sesión
requireLogin();

// Obtener información del usuario
$user = getUserInfo($_SESSION['user_id']);
?>

<div class="max-w-4xl mx-auto">
    <h1 class="text-2xl font-bold mb-6">Hồ sơ cá nhân</h1>
    
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <!-- Sidebar -->
        <div class="card p-6 rounded-xl">
            <div class="text-center mb-6">
                <div class="w-24 h-24 rounded-full bg-indigo-500 flex items-center justify-center text-white text-3xl font-bold mx-auto">
                    <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
                </div>
                <h2 class="text-xl font-semibold mt-4"><?php echo htmlspecialchars($user['full_name'] ?: $user['username']); ?></h2>
                <p class="text-gray-500 dark:text-gray-400">@<?php echo htmlspecialchars($user['username']); ?></p>
            </div>
            
            <div class="border-t border-gray-200 dark:border-gray-700 pt-4">
                <div class="flex justify-between items-center mb-3">
                    <span>Điểm</span>
                    <span class="font-semibold flex items-center">
                        <i class="fas fa-coins text-yellow-500 mr-2"></i>
                        <span class="points-display"><?php echo $user['points']; ?></span>
                    </span>
                </div>
                <div class="flex justify-between items-center mb-3">
                    <span>Câu hỏi đã đặt</span>
                    <?php 
                    // Contar preguntas realizadas
                    $stmt = $conn->prepare("SELECT COUNT(*) FROM questions WHERE user_id = ?");
                    $stmt->bind_param("i", $_SESSION['user_id']);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $question_count = $result->fetch_row()[0];
                    ?>
                    <span class="font-semibold"><?php echo $question_count; ?></span>
                </div>
                <div class="flex justify-between items-center">
                    <span>Thành viên từ</span>
                    <span class="font-semibold"><?php echo date('d/m/Y', strtotime($user['created_at'])); ?></span>
                </div>
            </div>
            
            <div class="mt-6">
                <a href="index.php?page=redeem" class="btn btn-primary w-full justify-center">
                    <i class="fas fa-gift mr-2"></i>Nhập mã code
                </a>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="md:col-span-2">
            <!-- Formulario de perfil -->
            <div class="card p-6 rounded-xl mb-6">
                <h3 class="text-xl font-semibold mb-4">Thông tin cá nhân</h3>
                
                <div id="profileAlert" class="mb-4"></div>
                
                <form id="profileForm">
                    <div class="mb-4">
                        <label for="username" class="block text-sm font-medium mb-1">Tên đăng nhập</label>
                        <input type="text" id="username" value="<?php echo htmlspecialchars($user['username']); ?>" class="w-full bg-gray-100 dark:bg-gray-800" disabled>
                        <p class="text-xs text-gray-500 mt-1">Tên đăng nhập không thể thay đổi</p>
                    </div>
                    
                    <div class="mb-4">
                        <label for="fullName" class="block text-sm font-medium mb-1">Họ và tên</label>
                        <input type="text" id="fullName" name="full_name" value="<?php echo htmlspecialchars($user['full_name'] ?: ''); ?>" class="w-full">
                    </div>
                    
                    <div class="mb-6">
                        <label for="email" class="block text-sm font-medium mb-1">Email</label>
                        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" class="w-full">
                    </div>
                    
                    <button type="submit" class="btn btn-primary px-6 py-2">Cập nhật</button>
                </form>
            </div>
            
            <!-- Cambio de contraseña -->
            <div class="card p-6 rounded-xl">
                <h3 class="text-xl font-semibold mb-4">Đổi mật khẩu</h3>
                
                <div id="passwordAlert" class="mb-4"></div>
                
                <form id="passwordForm">
                    <div class="mb-4">
                        <label for="currentPassword" class="block text-sm font-medium mb-1">Mật khẩu hiện tại</label>
                        <input type="password" id="currentPassword" name="current_password" class="w-full" required>
                    </div>
                    
                    <div class="mb-4">
                        <label for="newPassword" class="block text-sm font-medium mb-1">Mật khẩu mới</label>
                        <input type="password" id="newPassword" name="new_password" class="w-full" required minlength="6">
                        <p class="text-xs text-gray-500 mt-1">Mật khẩu phải có ít nhất 6 ký tự</p>
                    </div>
                    
                    <div class="mb-6">
                        <label for="confirmPassword" class="block text-sm font-medium mb-1">Xác nhận mật khẩu mới</label>
                        <input type="password" id="confirmPassword" name="confirm_password" class="w-full" required minlength="6">
                    </div>
                    
                    <button type="submit" class="btn btn-primary px-6 py-2">Đổi mật khẩu</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Manejar formulario de actualización de perfil
    const profileForm = document.getElementById('profileForm');
    if (profileForm) {
        profileForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const fullName = document.getElementById('fullName').value;
            const email = document.getElementById('email').value;
            
            const submitBtn = this.querySelector('button[type="submit"]');
            const alertContainer = document.getElementById('profileAlert');
            
            // Cambiar estado del botón durante el procesamiento
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Đang xử lý...';
            alertContainer.innerHTML = '';
            
            // Enviar datos de actualización
            fetch('includes/api/update_profile.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    'full_name': fullName,
                    'email': email
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Actualización exitosa
                    alertContainer.innerHTML = `
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle mr-2"></i>Cập nhật thông tin thành công
                        </div>
                    `;
                } else {
                    // Actualización fallida
                    alertContainer.innerHTML = `
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle mr-2"></i>${data.message}
                        </div>
                    `;
                }
                submitBtn.disabled = false;
                submitBtn.innerHTML = 'Cập nhật';
            })
            .catch(error => {
                console.error('Error:', error);
                alertContainer.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle mr-2"></i>Có lỗi xảy ra, vui lòng thử lại sau
                    </div>
                `;
                submitBtn.disabled = false;
                submitBtn.innerHTML = 'Cập nhật';
            });
        });
    }
    
    // Manejar formulario de cambio de contraseña
    const passwordForm = document.getElementById('passwordForm');
    if (passwordForm) {
        passwordForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const currentPassword = document.getElementById('currentPassword').value;
            const newPassword = document.getElementById('newPassword').value;
            const confirmPassword = document.getElementById('confirmPassword').value;
            
            const submitBtn = this.querySelector('button[type="submit"]');
            const alertContainer = document.getElementById('passwordAlert');
            
            // Validar que las contraseñas coincidan
            if (newPassword !== confirmPassword) {
                alertContainer.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle mr-2"></i>Mật khẩu mới không khớp
                    </div>
                `;
                return;
            }
            
            // Cambiar estado del botón durante el procesamiento
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Đang xử lý...';
            alertContainer.innerHTML = '';
            
            // Enviar solicitud de cambio de contraseña
            fetch('includes/api/change_password.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    'current_password': currentPassword,
                    'new_password': newPassword,
                    'confirm_password': confirmPassword
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Cambio exitoso
                    alertContainer.innerHTML = `
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle mr-2"></i>Đổi mật khẩu thành công
                        </div>
                    `;
                    // Reiniciar formulario
                    passwordForm.reset();
                } else {
                    // Cambio fallido
                    alertContainer.innerHTML = `
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle mr-2"></i>${data.message}
                        </div>
                    `;
                }
                submitBtn.disabled = false;
                submitBtn.innerHTML = 'Đổi mật khẩu';
            })
            .catch(error => {
                console.error('Error:', error);
                alertContainer.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle mr-2"></i>Có lỗi xảy ra, vui lòng thử lại sau
                    </div>
                `;
                submitBtn.disabled = false;
                submitBtn.innerHTML = 'Đổi mật khẩu';
            });
        });
    }
});
</script>