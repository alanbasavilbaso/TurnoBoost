// Funcionalidad para la tabla de profesionales
document.addEventListener('DOMContentLoaded', function() {
    console.log("tablee loaded");
    const searchInput = document.getElementById('searchInput');
    const sortBy = document.getElementById('sortBy');
    const clearButton = document.getElementById('clearSearch');
    const tableBody = document.getElementById('professionalsTableBody');
    const searchResults = document.getElementById('searchResults');
    const resultsCount = document.getElementById('resultsCount');
    
    let allRows = [];
    
    // Inicializar filas
    if (tableBody) {
        allRows = Array.from(tableBody.querySelectorAll('.professional-row'));
    }
    
    // Función de búsqueda
    function performSearch() {
        const searchTerm = searchInput.value.toLowerCase().trim();
        let visibleCount = 0;
        
        allRows.forEach(row => {
            const name = row.dataset.name || '';
            const specialty = row.dataset.specialty || '';
            const email = row.dataset.email || '';
            const status = row.dataset.status || '';
            
            // Verificar coincidencia de búsqueda
            const matchesSearch = !searchTerm || 
                name.includes(searchTerm) || 
                specialty.includes(searchTerm) || 
                email.includes(searchTerm);
            
            if (matchesSearch) {
                row.style.display = '';
                row.classList.remove('hidden');
                visibleCount++;
                
                // Resaltar términos de búsqueda
                if (searchTerm) {
                    highlightSearchTerm(row, searchTerm);
                } else {
                    clearHighlight();
                }
            } else {
                row.style.display = 'none';
                row.classList.add('hidden');
            }
        });
        
        // Actualizar contador de resultados
        updateResultsCount(visibleCount, searchTerm);
    }
    
    // Función de ordenamiento
    function sortTable() {
        const sortValue = sortBy.value;
        const tbody = tableBody;
        
        const sortedRows = allRows.sort((a, b) => {
            let aValue, bValue;
            
            switch(sortValue) {
                case 'name':
                    aValue = a.dataset.name || '';
                    bValue = b.dataset.name || '';
                    break;
                case 'specialty':
                    aValue = a.dataset.specialty || '';
                    bValue = b.dataset.specialty || '';
                    break;
                case 'services':
                    aValue = parseInt(a.dataset.services) || 0;
                    bValue = parseInt(b.dataset.services) || 0;
                    return bValue - aValue; // Orden descendente para números
                case 'created':
                    aValue = a.dataset.created || '';
                    bValue = b.dataset.created || '';
                    break;
                default:
                    return 0;
            }
            
            return aValue.localeCompare(bValue);
        });
        
        // Reordenar en el DOM
        sortedRows.forEach(row => tbody.appendChild(row));
        
        // Aplicar búsqueda después del ordenamiento
        performSearch();
    }
    
    // Resaltar términos de búsqueda
    function highlightSearchTerm(row, term) {
        // Remover resaltados previos
        row.querySelectorAll('.search-highlight').forEach(el => {
            el.outerHTML = el.innerHTML;
        });
        
        // Aplicar nuevos resaltados
        const textNodes = getTextNodes(row);
        textNodes.forEach(node => {
            if (node.textContent.toLowerCase().includes(term)) {
                const regex = new RegExp(`(${term})`, 'gi');
                const highlighted = node.textContent.replace(regex, '<span class="search-highlight">$1</span>');
                const wrapper = document.createElement('span');
                wrapper.innerHTML = highlighted;
                node.parentNode.replaceChild(wrapper, node);
            }
        });
    }
    
    // Obtener nodos de texto (excluyendo badges de estado)
    function getTextNodes(element) {
        const textNodes = [];
        const walker = document.createTreeWalker(
            element,
            NodeFilter.SHOW_TEXT,
            {
                acceptNode: function(node) {
                    // Excluir nodos de texto dentro de badges
                    const parent = node.parentElement;
                    if (parent && (
                        parent.classList.contains('badge') ||
                        parent.closest('.badge')
                    )) {
                        return NodeFilter.FILTER_REJECT;
                    }
                    return NodeFilter.FILTER_ACCEPT;
                }
            },
            false
        );
        
        let node;
        while (node = walker.nextNode()) {
            if (node.textContent.trim()) {
                textNodes.push(node);
            }
        }
        return textNodes;
    }
    
    // Actualizar contador de resultados
    function updateResultsCount(count, hasFilter) {
        if (hasFilter) {
            resultsCount.textContent = count;
            searchResults.style.display = 'block';
        } else {
            searchResults.style.display = 'none';
        }
    }
    
    // Limpiar búsqueda y filtros
    function clearAll() {
        searchInput.value = '';
        // statusFilter.value = '';
        sortBy.value = 'name';
        
        allRows.forEach(row => {
            row.style.display = '';
            row.classList.remove('hidden');
            // Remover resaltados
            row.querySelectorAll('.search-highlight').forEach(el => {
                el.outerHTML = el.innerHTML;
            });
        });
        
        searchResults.style.display = 'none';
        sortTable();
    }

    function clearHighlight() {
        allRows.forEach(row => {
            row.style.display = '';
            row.classList.remove('hidden');
            // Remover resaltados
            row.querySelectorAll('.search-highlight').forEach(el => {
                el.outerHTML = el.innerHTML;
            });
        });
    }
    
    // Event listeners
    if (searchInput) {
        searchInput.addEventListener('input', performSearch);
    }
    
    // if (statusFilter) {
    //     statusFilter.addEventListener('change', performSearch);
    // }
    
    if (sortBy) {
        sortBy.addEventListener('change', sortTable);
    }
    
    if (clearButton) {
        clearButton.addEventListener('click', clearAll);
    }
    
    // Modal de eliminación
    const deleteModal = document.getElementById('deleteModal');
    if (deleteModal) {
        deleteModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const professionalId = button.getAttribute('data-professional-id');
            const professionalName = button.getAttribute('data-professional-name');
            const deleteUrl = button.getAttribute('data-delete-url');
            const csrfToken = button.getAttribute('data-csrf-token');
            
            document.getElementById('professionalName').textContent = professionalName;
            document.getElementById('deleteForm').action = deleteUrl;
            document.getElementById('csrfToken').value = csrfToken;
        });
    }
    
    // Inicializar ordenamiento por defecto
    sortTable();
});