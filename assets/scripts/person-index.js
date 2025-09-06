// Manejar búsqueda con paginación
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('searchInput');
    
    if (searchInput) {
        // Solo enfocar si no hay valor previo (evita mover cursor)
        if (!searchInput.value.trim()) {
            searchInput.focus();
        }
        
        // Debounce para evitar demasiadas peticiones al servidor
        let searchTimeout;
        
        searchInput.addEventListener('input', function(e) {
            clearTimeout(searchTimeout);
            
            // Guardar posición del cursor
            const cursorPosition = e.target.selectionStart;
            
            searchTimeout = setTimeout(() => {
                const searchTerm = e.target.value.trim();
                
                // Crear URL con parámetros de búsqueda (resetear a página 1)
                const url = new URL(window.location.href);
                url.searchParams.set('page', '1');
                
                if (searchTerm) {
                    url.searchParams.set('search', searchTerm);
                } else {
                    url.searchParams.delete('search');
                }
                
                // Redirigir con los nuevos parámetros
                window.location.href = url.toString();
            }, 500);
        });
        
        // Manejar tecla Escape para limpiar búsqueda
        searchInput.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                if (searchInput.value.trim()) {
                    window.location.href = window.location.pathname;
                }
            }
        });
    }
});