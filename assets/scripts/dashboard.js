document.addEventListener('DOMContentLoaded', function() {
    // Limpiar cualquier estilo residual del login
    document.body.style.display = '';
    document.body.classList.remove('login-page');
    
    // Inicializar dropdowns de Bootstrap explícitamente
    const dropdownElementList = document.querySelectorAll('.dropdown-toggle');
    const dropdownList = [...dropdownElementList].map(dropdownToggleEl => new bootstrap.Dropdown(dropdownToggleEl));
    
    // Usar requestAnimationFrame para el siguiente frame
    requestAnimationFrame(() => {
        const navbar = document.querySelector('.navbar');
        if (navbar) {
            // Mejor: identificar y solucionar la causa raíz del problema de render
            navbar.style.display = 'none';
            navbar.offsetHeight;
            navbar.style.display = '';
        }
    });
});