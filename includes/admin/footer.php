<?php
// File: includes/admin/footer.php
?>
        </div>
    </div>

    <script>
        // Darkmode detection and switching
        if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
            document.documentElement.classList.add('dark');
        }
        
        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', event => {
            if (event.matches) {
                document.documentElement.classList.add('dark');
            } else {
                document.documentElement.classList.remove('dark');
            }
        });
        
        // Mobile menu functionality
        const mobileMenuButton = document.getElementById('mobile-menu-button');
        const closeMobileMenuButton = document.getElementById('close-mobile-menu');
        const mobileMenu = document.getElementById('mobile-menu');
        
        if (mobileMenuButton) {
            mobileMenuButton.addEventListener('click', function() {
                mobileMenu.classList.remove('-translate-x-full');
            });
        }
        
        if (closeMobileMenuButton) {
            closeMobileMenuButton.addEventListener('click', function() {
                mobileMenu.classList.add('-translate-x-full');
            });
        }
        
        // Close mobile menu when clicking outside
        document.addEventListener('click', function(event) {
            if (mobileMenu && !mobileMenu.contains(event.target) && event.target !== mobileMenuButton) {
                mobileMenu.classList.add('-translate-x-full');
            }
        });
        
        // Confirm delete
        document.querySelectorAll('.confirm-delete').forEach(button => {
            button.addEventListener('click', function(e) {
                if (!confirm('Bạn có chắc chắn muốn xóa?')) {
                    e.preventDefault();
                }
            });
        });
    </script>
</body>
</html>