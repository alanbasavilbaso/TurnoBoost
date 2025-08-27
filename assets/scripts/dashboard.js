document.addEventListener('DOMContentLoaded', function() {
    // Limpiar cualquier estilo residual del login
    document.body.style.display = '';
    document.body.classList.remove('login-page');
    
    // Inicializar dropdowns de Bootstrap explícitamente
    const dropdownElementList = document.querySelectorAll('.dropdown-toggle');
    const dropdownList = [...dropdownElementList].map(dropdownToggleEl => new bootstrap.Dropdown(dropdownToggleEl));
    
    // Forzar re-render de la navbar
    setTimeout(() => {
        const navbar = document.querySelector('.navbar');
        if (navbar) {
            navbar.style.display = 'none';
            navbar.offsetHeight; // Trigger reflow
            navbar.style.display = '';
        }
    }, 100);
});