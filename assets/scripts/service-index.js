document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('searchInput');
    const serviceRows = document.querySelectorAll('.service-row');
    const tableBody = document.getElementById('servicesTableBody');
    const noResultsMessage = document.getElementById('noResultsMessage');
    
    // Crear mensaje de "no hay resultados" si no existe
    if (!noResultsMessage) {
        const noResults = document.createElement('tr');
        noResults.id = 'noResultsMessage';
        noResults.style.display = 'none';
        noResults.innerHTML = `
            <td colspan="6" class="text-center py-5">
                <i class="fas fa-search fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">No se encontraron servicios</h5>
                <p class="text-muted">No hay servicios que coincidan con tu búsqueda.</p>
            </td>
        `;
        tableBody.appendChild(noResults);
    }
    
    function filterServices(searchTerm) {
        const term = searchTerm.toLowerCase().trim();
        let visibleCount = 0;
        
        serviceRows.forEach(row => {
            const name = row.dataset.name || '';
            const description = row.dataset.description || '';
            
            const matches = name.includes(term) || description.includes(term);
            
            if (matches || term === '') {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });
        
        // Mostrar/ocultar mensaje de resultados
        if (term === '') {
            document.getElementById('noResultsMessage').style.display = 'none';
        } else {
            // Mostrar mensaje de "no hay resultados" si es necesario
            if (visibleCount === 0) {
                document.getElementById('noResultsMessage').style.display = '';
            } else {
                document.getElementById('noResultsMessage').style.display = 'none';
            }
        }
    }
    
    // Filtrar mientras escribe (con debounce para mejor rendimiento)
    let debounceTimer;
    searchInput.addEventListener('input', function() {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => {
            filterServices(this.value);
        }, 150); // 150ms de delay
    });
    
    // Enfocar el campo de búsqueda al cargar
    searchInput.focus();
});