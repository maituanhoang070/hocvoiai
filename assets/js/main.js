// File: assets/js/main.js
document.addEventListener('DOMContentLoaded', function() {
    
    
    // Xử lý tooltips
    const tooltips = document.querySelectorAll('[data-tooltip]');
    tooltips.forEach(tooltip => {
        tooltip.addEventListener('mouseenter', function() {
            const tooltipText = this.getAttribute('data-tooltip');
            const tooltipEl = document.createElement('div');
            tooltipEl.classList.add('tooltip');
            tooltipEl.innerText = tooltipText;
            document.body.appendChild(tooltipEl);
            
            const rect = this.getBoundingClientRect();
            tooltipEl.style.top = rect.top - tooltipEl.offsetHeight - 10 + 'px';
            tooltipEl.style.left = rect.left + (rect.width / 2) - (tooltipEl.offsetWidth / 2) + 'px';
            tooltipEl.style.opacity = '1';
        });
        
        tooltip.addEventListener('mouseleave', function() {
            const tooltipEl = document.querySelector('.tooltip');
            if (tooltipEl) {
                tooltipEl.remove();
            }
        });
    });

    // Xử lý nhập mã code nhận điểm
    const redeemForm = document.getElementById('redeemCodeForm');
    if (redeemForm) {
        redeemForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const code = document.getElementById('redemptionCode').value;
            const submitBtn = this.querySelector('button[type="submit"]');
            const alertContainer = document.getElementById('redeemAlert');
            
            // Đổi trạng thái nút khi đang xử lý
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Đang xử lý...';
            alertContainer.innerHTML = '';
            
            // Gửi mã code
            fetch('includes/api/redeem_code.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    'code': code
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Nhập mã thành công
                    alertContainer.innerHTML = `
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle mr-2"></i>${data.message}
                        </div>
                    `;
                    
                    // Cập nhật số điểm hiện tại (nếu có)
                    const pointsDisplay = document.querySelector('.points-display');
                    if (pointsDisplay) {
                        const currentPoints = parseInt(pointsDisplay.innerText);
                        if (!isNaN(currentPoints)) {
                            pointsDisplay.innerText = currentPoints + data.points;
                        }
                    }
                    
                    // Reset form
                    document.getElementById('redemptionCode').value = '';
                } else {
                    // Nhập mã thất bại, hiển thị lỗi
                    alertContainer.innerHTML = `
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle mr-2"></i>${data.message}
                        </div>
                    `;
                }
                submitBtn.disabled = false;
                submitBtn.innerHTML = 'Nhận điểm';
            })
            .catch(error => {
                console.error('Error:', error);
                alertContainer.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle mr-2"></i>Có lỗi xảy ra, vui lòng thử lại sau
                    </div>
                `;
                submitBtn.disabled = false;
                submitBtn.innerHTML = 'Nhận điểm';
            });
        });
    }
    
    // Xử lý cập nhật thông tin người dùng
    const profileForm = document.getElementById('profileForm');
    if (profileForm) {
        profileForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const fullName = document.getElementById('fullName').value;
            const email = document.getElementById('email').value;
            
            const submitBtn = this.querySelector('button[type="submit"]');
            const alertContainer = document.getElementById('profileAlert');
            
            // Đổi trạng thái nút khi đang xử lý
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Đang xử lý...';
            alertContainer.innerHTML = '';
            
            // Gửi dữ liệu cập nhật
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
                    // Cập nhật thành công
                    alertContainer.innerHTML = `
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle mr-2"></i>Cập nhật thông tin thành công
                        </div>
                    `;
                } else {
                    // Cập nhật thất bại, hiển thị lỗi
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

    // Handle user dropdown menu (open/close on click)
    const dropdownToggle = document.querySelector('.dropdown-toggle');
    const dropdownMenu = document.querySelector('.dropdown-menu');

    if (dropdownToggle && dropdownMenu) {
        // Toggle dropdown on click
        dropdownToggle.addEventListener('click', function(e) {
            e.preventDefault();
            dropdownMenu.classList.toggle('hidden');
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            if (!dropdownToggle.contains(e.target) && !dropdownMenu.contains(e.target)) {
                dropdownMenu.classList.add('hidden');
            }
        });
    }
});