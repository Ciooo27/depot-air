/**
 * AquaDepot - Core Frontend & Dashboard JavaScript
 */

document.addEventListener('DOMContentLoaded', function () {
    // 0. Top Loading Bar Animation
    initPageLoader();

    // 1. Dark Mode / Light Mode Management
    initThemeToggle();

    // 2. Sidebar Mobile Toggle
    initSidebar();

    // 3. SweetAlert2 Flash Message Toast
    initFlashMessages();

    // 4. Initialize Tooltips & Popovers
    initBootstrapComponents();
});

/**
 * Top loading progress indicator
 */
function initPageLoader() {
    let bar = document.getElementById('page-progress-bar');
    if (!bar) {
        bar = document.createElement('div');
        bar.id = 'page-progress-bar';
        document.body.prepend(bar);
    }
    bar.style.width = '30%';
    setTimeout(() => { bar.style.width = '70%'; }, 100);
    setTimeout(() => { 
        bar.style.width = '100%'; 
        setTimeout(() => { bar.style.opacity = '0'; }, 200);
    }, 300);
}

/**
 * Theme toggle handler
 */
function initThemeToggle() {
    const savedTheme = localStorage.getItem('aquadepot_theme') || 'light';
    applyTheme(savedTheme);

    const themeToggles = document.querySelectorAll('.theme-toggle-btn');
    themeToggles.forEach(btn => {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            const currentTheme = document.documentElement.getAttribute('data-bs-theme') || 'light';
            const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
            applyTheme(newTheme);
            localStorage.setItem('aquadepot_theme', newTheme);
        });
    });
}

function applyTheme(theme) {
    document.documentElement.setAttribute('data-bs-theme', theme);
    const icons = document.querySelectorAll('.theme-toggle-icon');
    icons.forEach(icon => {
        if (theme === 'dark') {
            icon.classList.remove('bi-moon-stars-fill');
            icon.classList.add('bi-sun-fill');
        } else {
            icon.classList.remove('bi-sun-fill');
            icon.classList.add('bi-moon-stars-fill');
        }
    });
}

/**
 * Mobile Sidebar Drawer
 */
function initSidebar() {
    const toggleBtn = document.getElementById('sidebarToggleBtn');
    const sidebar = document.querySelector('.app-sidebar');
    
    if (toggleBtn && sidebar) {
        toggleBtn.addEventListener('click', function () {
            sidebar.classList.toggle('show');
            toggleBackdrop(sidebar.classList.contains('show'));
        });

        // Close when clicking outside
        document.addEventListener('click', function (e) {
            if (window.innerWidth < 992) {
                if (!sidebar.contains(e.target) && !toggleBtn.contains(e.target) && sidebar.classList.contains('show')) {
                    sidebar.classList.remove('show');
                    toggleBackdrop(false);
                }
            }
        });
    }
}

function toggleBackdrop(show) {
    let backdrop = document.getElementById('sidebar-backdrop');
    if (show) {
        if (!backdrop) {
            backdrop = document.createElement('div');
            backdrop.id = 'sidebar-backdrop';
            backdrop.className = 'modal-backdrop fade show';
            backdrop.style.zIndex = '1025';
            document.body.appendChild(backdrop);
            backdrop.addEventListener('click', function() {
                const sidebar = document.querySelector('.app-sidebar');
                if (sidebar) sidebar.classList.remove('show');
                toggleBackdrop(false);
            });
        }
    } else {
        if (backdrop) {
            backdrop.remove();
        }
    }
}

/**
 * SweetAlert2 confirmation dialog for delete
 */
function confirmDelete(url, itemTitle = 'data ini') {
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            title: 'Hapus Data?',
            text: `Apakah Anda yakin ingin menghapus ${itemTitle}? Tindakan ini tidak dapat dibatalkan!`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#E53935',
            cancelButtonColor: '#64748B',
            confirmButtonText: '<i class="bi bi-trash me-1"></i> Ya, Hapus!',
            cancelButtonText: 'Batal',
            reverseButtons: true,
            customClass: {
                popup: 'rounded-4'
            }
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = url;
            }
        });
    } else {
        if (confirm(`Apakah Anda yakin ingin menghapus ${itemTitle}?`)) {
            window.location.href = url;
        }
    }
}

/**
 * Flash messages renderer
 */
function initFlashMessages() {
    const flashElement = document.getElementById('flash-message-data');
    if (flashElement && typeof Swal !== 'undefined') {
        const type = flashElement.getAttribute('data-type') || 'info';
        const message = flashElement.getAttribute('data-message') || '';

        const Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 4000,
            timerProgressBar: true,
            didOpen: (toast) => {
                toast.addEventListener('mouseenter', Swal.stopTimer);
                toast.addEventListener('mouseleave', Swal.resumeTimer);
            }
        });

        Toast.fire({
            icon: type,
            title: message
        });
    }
}

/**
 * Tooltips initialization
 */
function initBootstrapComponents() {
    if (typeof bootstrap !== 'undefined') {
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    }
}

