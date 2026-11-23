(() => {
    const root = document.querySelector('[data-tabulator-table]');

    if (!root || !window.Tabulator) {
        return;
    }

    // El endpoint viaja en el markup: sin URL no hay de dónde traer las filas,
    // así que la tabla no se inicializa (mejor ninguna tabla que una vacía que
    // parece no tener contactos).
    const url = root.dataset.url;

    if (!url) {
        return;
    }

    // Los textos visibles los manda el servidor en el JSON que la página deja
    // al lado de la tabla (`index.twig`, armado por `ContactController`): este
    // archivo es un asset estático y no sabe en qué idioma pidieron la página.
    // Misma regla que la URL: sin textos no hay nombres accesibles ni paginador,
    // así que la tabla no se inicializa.
    const i18nNode = document.getElementById('contacts-table-i18n');

    if (!i18nNode) {
        return;
    }

    let i18n;

    try {
        i18n = JSON.parse(i18nNode.dataset.strings || '');
    } catch (error) {
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
            <button type="button" class="btn btn-square btn-ghost btn-sm size-9"
                    data-contact-edit
                    data-edit-url="${esc(contact.edit_url)}"
                    aria-label="${esc(i18n.actions.edit)}"
                    title="${esc(i18n.actions.edit)}">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4" aria-hidden="true"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4z"/></svg>
            </button>
            <button type="button" class="btn btn-square btn-ghost btn-sm size-9 icon-error"
                    data-contact-delete
                    data-delete-url="${esc(contact.delete_url)}"
                    data-contact-name="${esc(contact.name)}"
                    aria-label="${esc(i18n.actions.delete)}"
                    title="${esc(i18n.actions.delete)}">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4" aria-hidden="true"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
            </button>
        </div>`;

    const deleteModal = document.getElementById('contact-delete-modal');
    const editModal = document.getElementById('contact-edit-modal');
    const editBody = document.getElementById('contact-edit-body');

    // Delegado en el contenedor: las filas llegan por AJAX y se reemplazan al
    // cambiar de página, así que un listener por fila moriría en el primer swap.
    root.addEventListener('click', (event) => {
        const editTrigger = event.target.closest('[data-contact-edit]');
        if (editTrigger && editModal && editBody) {
            event.preventDefault();

            const editUrl = editTrigger.dataset.editUrl || '';
            if (editUrl === '') {
                return;
            }

            editBody.innerHTML = `<div class="py-6 text-center text-sm opacity-70">${esc(i18n.loading)}</div>`;
            editModal.showModal();

            if (window.htmx) {
                window.htmx.ajax('GET', editUrl, {
                    target: '#contact-edit-body',
                    swap: 'innerHTML',
                });
            } else {
                editBody.innerHTML = `<p class="py-4 text-sm text-error">${esc(i18n.load_error)}</p>`;
            }

            return;
        }

        const trigger = event.target.closest('[data-contact-delete]');
        if (!trigger || !deleteModal) {
            return;
        }

        const name = deleteModal.querySelector('[data-contact-delete-name]');
        const form = deleteModal.querySelector('[data-contact-delete-form]');
        if (name) {
            name.textContent = trigger.dataset.contactName || '';
        }
        if (form) {
            const deleteUrl = trigger.dataset.deleteUrl || '';
            form.action = deleteUrl;
            form.setAttribute('hx-post', deleteUrl);
            form.setAttribute('hx-target', 'this');
            form.setAttribute('hx-swap', 'none');
            form.setAttribute('hx-disabled-elt', "find button[type='submit']");
            if (window.htmx) {
                window.htmx.process(form);
            }
        }
        deleteModal.showModal();
    });

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
        placeholder: i18n.empty,
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
        // El paquete de textos de Tabulator también llega del servidor: la clave
        // del locale es un nombre propio del demo (`app`) y no el código de
        // idioma, así que no queda ninguna cadena visible incrustada acá.
        locale: 'app',
        langs: {
            app: {
                pagination: i18n.pagination,
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
            {title: i18n.columns.number, field: 'number', hozAlign: 'center', headerHozAlign: 'center', width: 60, headerSort: false},
            {title: i18n.columns.name, field: 'name', minWidth: 140},
            {title: i18n.columns.email, field: 'email', minWidth: 180},
            {title: i18n.columns.phone, field: 'phone', minWidth: 140},
            {
                title: i18n.columns.actions,
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

    // Una mutación htmx devuelve 204 + `contacts:changed`; no hay navegación.
    // Se cierran los diálogos, se limpia el alta y se recarga la página actual
    // de la tabla (replaceData conserva page/per_page y el sorter activo).
    document.body.addEventListener('contacts:changed', (event) => {
        document.querySelectorAll('dialog[open]').forEach((dialog) => dialog.close());

        const createForm = document.querySelector('#contact-create-modal form[hx-post]');
        if (createForm instanceof HTMLFormElement) {
            createForm.reset();
        }

        // replaceData vuelve a la pagina 1; se restaura la pagina actual (o la
        // ultima valida si el borrado dejo la anterior fuera de rango).
        const page = table.getPage();
        table.replaceData().then(() => {
            if (page > 1) {
                table.setPage(Math.min(page, table.getPageMax()));
            }
        }).catch(() => {});
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
                toggle.setAttribute('aria-label', i18n.collapse);
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
