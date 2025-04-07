// File: assets/js/auth.js
document.addEventListener('DOMContentLoaded', function() {
    // Xử lý đăng nhập
    const loginForm = document.getElementById('loginForm');
    if (loginForm) {
        loginForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const username = document.getElementById('username').value;
            const password = document.getElementById('password').value;
            const submitBtn = this.querySelector('button[type="submit"]');
            const alertContainer = document.getElementById('loginAlert');
            
            // Đổi trạng thái nút khi đang xử lý
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Đang xử lý...';
            alertContainer.innerHTML = '';
            
            // Gửi dữ liệu đăng nhập
            fetch('includes/auth/login.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    'username': username,
                    'password': password
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Đăng nhập thành công
                    window.location.href = data.redirect || 'index.php';
                } else {
                    // Đăng nhập thất bại, hiển thị lỗi
                    alertContainer.innerHTML = `
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle mr-2"></i>${data.message}
                        </div>
                    `;
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = 'Đăng nhập';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alertContainer.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle mr-2"></i>Có lỗi xảy ra, vui lòng thử lại sau
                    </div>
                `;
                submitBtn.disabled = false;
                submitBtn.innerHTML = 'Đăng nhập';
            });
        });
    }
    
    // Xử lý đăng ký
    const registerForm = document.getElementById('registerForm');
    if (registerForm) {
        registerForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const username = document.getElementById('reg_username').value;
            const email = document.getElementById('reg_email').value;
            const password = document.getElementById('reg_password').value;
            const confirmPassword = document.getElementById('reg_confirm_password').value;
            const fullName = document.getElementById('reg_full_name').value;
            
            const submitBtn = this.querySelector('button[type="submit"]');
            const alertContainer = document.getElementById('registerAlert');
            
            // Đổi trạng thái nút khi đang xử lý
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Đang xử lý...';
            alertContainer.innerHTML = '';
            
            // Kiểm tra mật khẩu và xác nhận mật khẩu
            if (password !== confirmPassword) {
                alertContainer.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle mr-2"></i>Mật khẩu xác nhận không khớp
                    </div>
                `;
                submitBtn.disabled = false;
                submitBtn.innerHTML = 'Đăng ký';
                return;
            }
            
            // Gửi dữ liệu đăng ký
            fetch('includes/auth/register.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    'username': username,
                    'email': email,
                    'password': password,
                    'confirm_password': confirmPassword,
                    'full_name': fullName
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Đăng ký thành công
                    window.location.href = data.redirect || 'index.php';
                } else {
                    // Đăng ký thất bại, hiển thị lỗi
                    alertContainer.innerHTML = `
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle mr-2"></i>${data.message}
                        </div>
                    `;
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = 'Đăng ký';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alertContainer.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle mr-2"></i>Có lỗi xảy ra, vui lòng thử lại sau
                    </div>
                `;
                submitBtn.disabled = false;
                submitBtn.innerHTML = 'Đăng ký';
            });
        });
    }
    
    // Xử lý chuyển đổi giữa đăng nhập và đăng ký
    const registerLink = document.getElementById('registerLink');
    const loginLink = document.getElementById('loginLink');
    const loginContainer = document.getElementById('loginContainer');
    const registerContainer = document.getElementById('registerContainer');
    
    if (registerLink && loginContainer && registerContainer) {
        registerLink.addEventListener('click', function(e) {
            e.preventDefault();
            loginContainer.classList.add('hidden');
            registerContainer.classList.remove('hidden');
        });
    }
    
    if (loginLink && loginContainer && registerContainer) {
        loginLink.addEventListener('click', function(e) {
            e.preventDefault();
            registerContainer.classList.add('hidden');
            loginContainer.classList.remove('hidden');
        });
    }
});