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

    const table = new Tabulator(root, {
        ajaxURL: url,
        layout: 'fitColumns',
        // En móvil no hay ancho para cuatro columnas de texto. `false` (lo que
        // había) dejaba que la tabla scrolleara en horizontal dentro de la
        // tarjeta y Teléfono/Acciones quedaban fuera de vista sin pista de que
        // existían. `collapse` mueve las columnas que no entran a una sección
        // expandible de la fila: todo sigue alcanzable, un toque más abajo.
        responsiveLayout: 'collapse',
        // El bundle 6.7.1 trae `collapseStartOpen` en true: sin esto cada fila
        // nace abierta y la lista se vuelve una columna de pares campo/valor
        // en vez de una tabla. Cerrada por defecto, la fila se lee de un
        // vistazo y el detalle está a un toque.
        responsiveLayoutCollapseStartOpen: false,
        responsiveLayoutCollapseFormatter: (collapsed) => {
            const list = document.createElement('dl');
            list.className = 'grid grid-cols-[auto_1fr] items-start gap-x-3 gap-y-1.5 py-2 text-sm';

            collapsed.forEach((item) => {
                if (item.value === '' || item.value === null || item.value === undefined) {
                    return;
                }

                const term = document.createElement('dt');
                term.className = 'font-medium opacity-70';
                term.textContent = item.title;

                const detail = document.createElement('dd');
                detail.className = 'min-w-0 break-words';

                if (item.field === 'actions') {
                    // Único innerHTML del archivo: el formatter de acciones ya
                    // escapa cada valor con esc() y es markup propio, no texto
                    // que venga de la base sin escapar.
                    detail.innerHTML = item.value;
                } else {
                    detail.textContent = String(item.value);
                }

                list.append(term, detail);
            });

            return list;
        },
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
            // Afordancia para expandir la fila en móvil. `responsive: 0` la
            // excluye del colapso: si ella misma se ocultara, no habría forma
            // de abrir la sección que muestra el resto de los datos. Tabulator
            // la esconde sola en escritorio, cuando no hay nada colapsado.
            {
                formatter: 'responsiveCollapse',
                width: 40,
                minWidth: 40,
                hozAlign: 'center',
                headerSort: false,
                responsive: 0,
            },
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

    // El toggle de colapso que dibuja Tabulator es un <div> sin nombre ni
    // teclado. Se le da rol, foco y estado, y se sincroniza `aria-expanded`
    // cada vez que la fila se abre o se cierra (incluido después de cada
    // página remota).
    const decorateCollapseToggles = () => {
        root.querySelectorAll('.tabulator-responsive-collapse-toggle').forEach((toggle) => {
            if (!toggle.hasAttribute('role')) {
                toggle.setAttribute('role', 'button');
                toggle.setAttribute('tabindex', '0');
                toggle.setAttribute('aria-label', 'Mostrar u ocultar los datos de la fila');
            }
            toggle.setAttribute('aria-expanded', toggle.classList.contains('open') ? 'true' : 'false');
        });
    };

    table.on('tableBuilt', decorateCollapseToggles);
    table.on('dataProcessed', decorateCollapseToggles);

    // Tabulator alterna la fila desde un handler propio sobre el toggle y no
    // garantiza que el click llegue a este contenedor, así que escuchar el
    // evento era frágil: el `aria-expanded` quedaba clavado en false. Se
    // observa la clase `open` del propio toggle, que es la fuente de verdad, y
    // se copia al atributo ARIA.
    new MutationObserver(decorateCollapseToggles).observe(root, {
        attributes: true,
        attributeFilter: ['class'],
        subtree: true,
    });

    root.addEventListener('keydown', (event) => {
        const toggle = event.target.closest('.tabulator-responsive-collapse-toggle');
        if (!toggle || (event.key !== 'Enter' && event.key !== ' ')) {
            return;
        }
        event.preventDefault();
        toggle.click();
    });
})();
