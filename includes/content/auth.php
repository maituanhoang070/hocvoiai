<?php
// File: includes/content/auth.php
// Determinar qué página mostrar (login o registro)
$authPage = $page; // $page viene de index.php
?>

<div class="max-w-md mx-auto px-4 py-8">
    <?php if ($authPage == 'login'): ?>
    <!-- Formulario de Login -->
    <div id="loginContainer" class="card p-6 rounded-xl shadow-md">
        <h2 class="text-2xl font-bold mb-6 text-center">Đăng nhập</h2>
        
        <div id="loginAlert" class="mb-4"></div>
        
        <form id="loginForm">
            <div class="mb-4">
                <label for="username" class="block text-sm font-medium mb-1">Tên đăng nhập hoặc email</label>
                <input type="text" id="username" name="username" class="w-full" required>
            </div>
            
            <div class="mb-6">
                <div class="flex justify-between mb-1">
                    <label for="password" class="block text-sm font-medium">Mật khẩu</label>
                    <a href="index.php?page=reset_password" class="text-sm text-indigo-600 hover:underline">Quên mật khẩu?</a>
                </div>
                <input type="password" id="password" name="password" class="w-full" required>
            </div>
            
            <button type="submit" class="btn btn-primary w-full py-2">Đăng nhập</button>
        </form>
        
        <div class="mt-4 text-center">
            <p>Chưa có tài khoản? <a href="index.php?page=register" id="registerLink" class="text-indigo-600 hover:underline">Đăng ký</a></p>
        </div>
    </div>
    <?php elseif ($authPage == 'register'): ?>
    <!-- Formulario de Registro -->
    <div id="registerContainer" class="card p-6 rounded-xl shadow-md">
        <h2 class="text-2xl font-bold mb-6 text-center">Đăng ký tài khoản</h2>
        
        <div id="registerAlert" class="mb-4"></div>
        
        <form id="registerForm">
            <div class="mb-4">
                <label for="reg_username" class="block text-sm font-medium mb-1">Tên đăng nhập</label>
                <input type="text" id="reg_username" name="username" class="w-full" required>
            </div>
            
            <div class="mb-4">
                <label for="reg_email" class="block text-sm font-medium mb-1">Email</label>
                <input type="email" id="reg_email" name="email" class="w-full" required>
            </div>
            
            <div class="mb-4">
                <label for="reg_full_name" class="block text-sm font-medium mb-1">Họ và tên</label>
                <input type="text" id="reg_full_name" name="full_name" class="w-full">
            </div>
            
            <div class="mb-4">
                <label for="reg_password" class="block text-sm font-medium mb-1">Mật khẩu</label>
                <input type="password" id="reg_password" name="password" class="w-full" required minlength="6">
                <p class="text-xs text-gray-500 mt-1">Mật khẩu phải có ít nhất 6 ký tự</p>
            </div>
            
            <div class="mb-6">
                <label for="reg_confirm_password" class="block text-sm font-medium mb-1">Xác nhận mật khẩu</label>
                <input type="password" id="reg_confirm_password" name="confirm_password" class="w-full" required minlength="6">
            </div>
            
            <button type="submit" class="btn btn-primary w-full py-2">Đăng ký</button>
        </form>
        
        <div class="mt-4 text-center">
            <p>Đã có tài khoản? <a href="index.php?page=login" id="loginLink" class="text-indigo-600 hover:underline">Đăng nhập</a></p>
        </div>
    </div>
    <?php elseif ($authPage == 'reset_password'): ?>
    <!-- Formulario de Recuperación de Contraseña -->
    <div class="card p-6 rounded-xl shadow-md">
        <h2 class="text-2xl font-bold mb-6 text-center">Khôi phục mật khẩu</h2>
        
        <div id="resetAlert" class="mb-4"></div>
        
        <form id="resetForm">
            <div class="mb-6">
                <label for="reset_email" class="block text-sm font-medium mb-1">Email đăng ký</label>
                <input type="email" id="reset_email" name="email" class="w-full" required>
                <p class="text-xs text-gray-500 mt-1">Chúng tôi sẽ gửi hướng dẫn khôi phục mật khẩu qua email.</p>
            </div>
            
            <button type="submit" class="btn btn-primary w-full py-2">Gửi yêu cầu</button>
        </form>
        
        <div class="mt-4 text-center">
            <p><a href="index.php?page=login" class="text-indigo-600 hover:underline">
                <i class="fas fa-arrow-left mr-1"></i>Quay lại đăng nhập
            </a></p>
        </div>
    </div>
    <?php endif; ?>
</div>