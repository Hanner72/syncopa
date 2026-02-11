        </main>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/locales/de.global.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Sidebar Toggle
        const sidebar = document.getElementById('sidebar');
        const sidebarToggle = document.getElementById('sidebarToggle');
        const sidebarClose = document.getElementById('sidebarClose');
        const sidebarOverlay = document.getElementById('sidebarOverlay');
        
        function openSidebar() {
            sidebar?.classList.add('show');
            sidebarOverlay?.classList.add('show');
            document.body.style.overflow = 'hidden';
        }
        
        function closeSidebar() {
            sidebar?.classList.remove('show');
            sidebarOverlay?.classList.remove('show');
            document.body.style.overflow = '';
        }
        
        sidebarToggle?.addEventListener('click', openSidebar);
        sidebarClose?.addEventListener('click', closeSidebar);
        sidebarOverlay?.addEventListener('click', closeSidebar);
        
        // Theme Toggle
        const themeToggle = document.getElementById('themeToggle');
        const html = document.documentElement;
        
        // Load saved theme
        const savedTheme = localStorage.getItem('theme') || 'light';
        html.setAttribute('data-theme', savedTheme);
        updateThemeIcon(savedTheme);
        
        themeToggle?.addEventListener('click', function() {
            const current = html.getAttribute('data-theme');
            const next = current === 'light' ? 'dark' : 'light';
            html.setAttribute('data-theme', next);
            localStorage.setItem('theme', next);
            updateThemeIcon(next);
        });
        
        function updateThemeIcon(theme) {
            const icon = themeToggle?.querySelector('i');
            if (icon) {
                icon.className = theme === 'light' ? 'bi bi-moon' : 'bi bi-sun';
            }
        }
        
        // Close sidebar on window resize if open
        window.addEventListener('resize', function() {
            if (window.innerWidth > 991.98) {
                closeSidebar();
            }
        });
    });
    
    // DataTables Config
    $.extend(true, $.fn.dataTable.defaults, {
        language: { url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/de-DE.json' },
        pageLength: 25,
        responsive: true,
        dom: '<"row align-items-center mb-2"<"col-auto"l><"col"f>><"table-responsive"t><"row align-items-center mt-2"<"col-sm-5"i><"col-sm-7"p>>'
    });
    
    function confirmDelete(msg) {
        return confirm(msg || 'Wirklich löschen?');
    }
    
    function showToast(message, type = 'success') {
        const html = `<div class="toast align-items-center text-white bg-${type} border-0" role="alert">
            <div class="d-flex"><div class="toast-body">${message}</div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div></div>`;
        let container = document.getElementById('toastContainer');
        if (!container) {
            container = document.createElement('div');
            container.id = 'toastContainer';
            container.className = 'toast-container position-fixed top-0 end-0 p-2';
            container.style.zIndex = '9999';
            document.body.appendChild(container);
        }
        container.insertAdjacentHTML('beforeend', html);
        const el = container.lastElementChild;
        new bootstrap.Toast(el).show();
        el.addEventListener('hidden.bs.toast', () => el.remove());
    }
    
    // Form validation
    document.querySelectorAll('.needs-validation').forEach(form => {
        form.addEventListener('submit', e => {
            if (!form.checkValidity()) { e.preventDefault(); e.stopPropagation(); }
            form.classList.add('was-validated');
        });
    });
    
    // Auto-dismiss alerts
    setTimeout(() => {
        document.querySelectorAll('.alert:not(.alert-permanent)').forEach(el => {
            el.style.transition = 'opacity 0.5s';
            el.style.opacity = '0';
            setTimeout(() => el.remove(), 500);
        });
    }, 5000);
    </script>
</body>
</html>
