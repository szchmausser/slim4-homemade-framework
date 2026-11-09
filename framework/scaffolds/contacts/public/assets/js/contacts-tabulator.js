(() => {
    const root = document.querySelector('[data-tabulator-table]');

    if (!root || !window.Tabulator) {
        return;
    }

    // El endpoint viaja en el markup: sin URL no hay de dónde traer las filas,
    // así que la tabla no se inicializa (mejor ninguna tabla que una vacía que
    // parece "no hay contactos").
    const url = root.dataset.url;

    if (!url) {
        return;
    }

    const esc = (value) => String(value).replace(/[&<>"']/g, (character) => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#39;',
    }[character]));

    const actions = (contact) => `
        <div class="contact-actions">
            <a class="btn btn-square btn-ghost btn-sm size-9"
               href="${esc(contact.edit_url)}"
               aria-label="Editar"
               title="Editar">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4" aria-hidden="true"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4z"/></svg>
            </a>
            <button type="button" class="btn btn-square btn-ghost btn-sm size-9 icon-error"
                    data-contact-delete
                    data-delete-url="${esc(contact.delete_url)}"
                    data-contact-name="${esc(contact.name)}"
                    aria-label="Eliminar"
                    title="Eliminar">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4" aria-hidden="true"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
            </button>
        </div>`;

    const deleteModal = document.getElementById('contact-delete-modal');
    if (deleteModal) {
        // Delegado en el contenedor, no en cada botón: las filas llegan por
        // AJAX y se reemplazan al cambiar de página, así que un listener por
        // fila moriría en el primer swap y el borrado dejaría de abrir el modal.
        root.addEventListener('click', (event) => {
            const trigger = event.target.closest('[data-contact-delete]');
            if (!trigger) {
                return;
            }

            const name = deleteModal.querySelector('[data-contact-delete-name]');
            const form = deleteModal.querySelector('[data-contact-delete-form]');
            if (name) {
                name.textContent = trigger.dataset.contactName || '';
            }
            if (form) {
                form.action = trigger.dataset.deleteUrl || '';
            }
            deleteModal.showModal();
        });
    }

    new Tabulator(root, {
        ajaxURL: url,
        layout: 'fitColumns',
        responsiveLayout: false,
        placeholder: 'No hay contactos todavía.',
        // Remoto en las dos direcciones: la página y el orden los resuelve el
        // servidor sobre la tabla completa. Paginar u ordenar acá las diez
        // filas que llegaron sería un cliente simulando lo que el server ya
        // hizo, y con datos reales mostraría un orden que no es el de nadie.
        pagination: true,
        paginationMode: 'remote',
        sortMode: 'remote',
        paginationSize: 10,
        paginationSizeSelector: [5, 10, 25, 50, 100],
        // El bundle 6.7.1 manda el tamaño como `size` y el nombre del query
        // string se fija con `dataSendParams` (el viejo `paginationSizeParameter`
        // ya no existe en esta versión). Así el cliente y el servidor hablan el
        // mismo vocabulario del resto de la app: `page` y `per_page`.
        dataSendParams: {size: 'per_page'},
        paginationCounter: 'rows',
        paginationButtonCount: 5,
        locale: 'es',
        langs: {
            es: {
                pagination: {
                    page_size: 'Filas',
                    page_title: 'Página',
                    first: 'Primera',
                    first_title: 'Primera página',
                    last: 'Última',
                    last_title: 'Última página',
                    prev: 'Anterior',
                    prev_title: 'Página anterior',
                    next: 'Siguiente',
                    next_title: 'Página siguiente',
                    all: 'Todas',
                    counter: {
                        showing: 'Mostrando',
                        of: 'de',
                        rows: 'filas',
                        pages: 'páginas',
                    },
                },
            },
        },
        columns: [
            // `#` lo numera el servidor: es la posición en el orden visible, así
            // que no se puede ordenar (el id no es lo que se ve en la columna).
            {title: '#', field: 'number', hozAlign: 'center', headerHozAlign: 'center', width: 60, headerSort: false},
            {title: 'Nombre', field: 'name', minWidth: 140},
            {title: 'Email', field: 'email', minWidth: 180},
            {title: 'Teléfono', field: 'phone', minWidth: 140},
            {
                title: 'Acciones',
                field: 'actions',
                hozAlign: 'right',
                headerHozAlign: 'right',
                width: 104,
                minWidth: 100,
                maxWidth: 104,
                headerSort: false,
                // Las acciones se pintan desde los datos de la fila: en modo
                // remoto el JSON trae el contacto, no un campo `actions`
                // precalculado por el cliente.
                formatter: (cell) => actions(cell.getRow().getData()),
            },
        ],
    });
})();
