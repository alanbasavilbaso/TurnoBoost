document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('searchInput');
    const tableBody = document.getElementById('locationsTableBody');
    const rows = tableBody ? tableBody.querySelectorAll('.location-row') : [];

    if (searchInput && rows.length > 0) {
        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase().trim();
            
            rows.forEach(row => {
                const name = row.dataset.name || '';
                const address = row.dataset.address || '';
                
                const matches = name.includes(searchTerm) || address.includes(searchTerm);
                
                if (matches || searchTerm === '') {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
            
            // Mostrar mensaje si no hay resultados
            const visibleRows = Array.from(rows).filter(row => row.style.display !== 'none');
            
            // Remover mensaje anterior si existe
            const existingMessage = tableBody.querySelector('.no-results-message');
            if (existingMessage) {
                existingMessage.remove();
            }
            
            if (visibleRows.length === 0 && searchTerm !== '') {
                const noResultsRow = document.createElement('tr');
                noResultsRow.className = 'no-results-message';
                noResultsRow.innerHTML = `
                    <td colspan="5" class="text-center py-4">
                        <div class="text-muted">
                            <i class="fas fa-search fa-2x mb-3"></i>
                            <h5>No se encontraron resultados</h5>
                            <p>No hay locales que coincidan con "${searchTerm}"</p>
                        </div>
                    </td>
                `;
                tableBody.appendChild(noResultsRow);
            }
        });
    }
});