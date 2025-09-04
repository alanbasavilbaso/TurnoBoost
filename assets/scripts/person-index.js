document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('searchInput');
    const patientRows = document.querySelectorAll('.patient-row');
    const tableBody = document.getElementById('patientsTableBody');
    const noResultsMessage = document.getElementById('noResultsMessage');
    
    // Crear mensaje de "no hay resultados" si no existe
    if (!noResultsMessage) {
        const noResults = document.createElement('tr');
        noResults.id = 'noResultsMessage';
        noResults.style.display = 'none';
        noResults.innerHTML = `
            <td colspan="5" class="text-center py-5">
                <i class="fas fa-search fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">No se encontraron clientes</h5>
                <p class="text-muted">No hay clientes que coincidan con tu búsqueda.</p>
            </td>
        `;
        tableBody.appendChild(noResults);
    }
    
    function filterPatients() {
        const searchTerm = searchInput.value.toLowerCase().trim();
        const rows = document.querySelectorAll('.patient-row');
        let visibleCount = 0;
    
        rows.forEach(row => {
            const name = row.dataset.name || '';
            const email = row.dataset.email || '';
            const phone = row.dataset.phone || '';
            const document = row.dataset.document || '';
            
            const isVisible = name.includes(searchTerm) || 
                            email.includes(searchTerm) || 
                            phone.includes(searchTerm) ||
                            document.includes(searchTerm);
            
            if (isVisible) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });
    
        // Show/hide no results message
        updateNoResultsMessage(visibleCount === 0 && searchTerm !== '');
    }
    
    // Filtrar mientras escribe (con debounce para mejor rendimiento)
    let debounceTimer;
    searchInput.addEventListener('input', function() {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => {
            filterPatients(this.value);
        }, 150); // 150ms de delay
    });
    
    // Enfocar el campo de búsqueda al cargar
    searchInput.focus();
});