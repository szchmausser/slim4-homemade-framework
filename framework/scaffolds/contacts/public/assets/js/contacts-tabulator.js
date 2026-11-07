(() => {
    const root = document.querySelector('[data-tabulator-table]');

    if (!root || !window.Tabulator) {
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

    let contacts;
    try {
        contacts = JSON.parse(root.dataset.rows || '[]');
    } catch (error) {
        return;
    }

    const rows = contacts.map((contact, index) => ({
        ...contact,
        number: index + 1,
        actions: actions(contact),
    }));

    const deleteModal = document.getElementById('contact-delete-modal');
    if (deleteModal) {
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
        data: rows,
        layout: 'fitColumns',
        responsiveLayout: false,
        placeholder: 'No hay contactos todavía.',
        pagination: true,
        paginationMode: 'local',
        paginationSize: 10,
        paginationSizeSelector: [10, 25, 50, 100],
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
            {title: '#', field: 'number', hozAlign: 'right', width: 60, headerSort: false},
            {title: 'Nombre', field: 'name', minWidth: 140, headerSort: false},
            {title: 'Email', field: 'email', minWidth: 180, headerSort: false},
            {title: 'Teléfono', field: 'phone', minWidth: 140, headerSort: false},
            {
                title: 'Acciones',
                field: 'actions',
                formatter: 'html',
                hozAlign: 'right',
                minWidth: 110,
                headerSort: false,
            },
        ],
    });
})();
