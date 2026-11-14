/**
 * Foco después de un swap (S4). Toda acción de fila reemplaza #tareas-panel
 * entero (`hx-swap="outerHTML"`), así que el nodo que tenía el foco se va con
 * el swap y `document.activeElement` cae en <body>: la Tab siguiente arranca
 * en el sidebar y después de cada toggle, edición o borrado hay que recorrer
 * la página completa otra vez.
 *
 * Se mantiene el swap del panel (decisión D2(a)) y el foco se devuelve a mano:
 * 1. El control que dispara lleva `data-focus-key` (ver tasks/_list.twig) y lo
 *    publica en `pendingKey` durante htmx:beforeRequest, ANTES del request.
 * 2. En htmx:afterSettle se busca esa misma clave en el DOM recién
 *    intercambiado y se enfoca: es el mismo control, en la fila nueva.
 * 3. Si la clave no existe en el DOM nuevo (la fila se borró, o guardar y
 *    cancelar salieron del modo edición y esos botones ya no están), el foco
 *    va al panel, que tiene `tabindex="-1"`. Sigue en la zona de la fila, no
 *    en el shell, que es lo que pide S4.
 * 4. Sin `data-focus-key` no se toca el foco: paginar o cambiar el tamaño de
 *    página es navegación, y llevarse el foco ahí sería robarlo.
 */

function restoreFocusAfterSwap() {
    // Una sola clave viva: la intención dura lo que dura el request.
    let pendingKey = null;

    document.body.addEventListener('htmx:beforeRequest', (event) => {
        const elt = event.detail && event.detail.elt;
        // Se ASIGNA siempre, no se conserva el valor anterior cuando no hay
        // clave: un request que no llega a hacer swap (un 400 de CSRF, un 5xx)
        // dejaría su clave viva y el request siguiente la gastaría en el lugar
        // equivocado. `dataset` no existe si htmx dispara sobre un target que
        // no es un elemento (puede pasar), así que se lee con guarda.
        pendingKey = (elt && elt.dataset && elt.dataset.focusKey) || null;
    });

    document.body.addEventListener('htmx:afterSettle', () => {
        if (pendingKey === null) {
            return;
        }
        const key = pendingKey;
        pendingKey = null;

        const found = document.querySelector(selectorForKey(key));
        const focusTarget = found || document.getElementById('tareas-panel');
        if (!focusTarget) {
            return;
        }

        // `focus()` sin opciones a propósito: `preventScroll: true` dejaría el
        // foco en un nodo fuera de pantalla justo cuando el swap movió el
        // layout, y el siguiente Tab saldría de un lugar que el usuario no ve.
        // El default sólo scrollea si el destino no está a la vista.
        focusTarget.focus();
    });

    /**
     * La clave la arma Twig con el id de la tarea (un entero), pero el selector
     * no depende de eso: `CSS.escape` neutraliza comillas o backslashes si
     * alguna vez la clave deja de ser numérica, y el fallback cubre los
     * navegadores que no exponen `CSS.escape`.
     */
    function selectorForKey(key) {
        const escaped = (window.CSS && typeof window.CSS.escape === 'function')
            ? window.CSS.escape(key)
            : String(key).replace(/["\\]/g, '\\$&');
        return '[data-focus-key="' + escaped + '"]';
    }
}

// Los listeners van en document.body y no en el panel: el panel se reemplaza en
// cada swap, el body no. Se enganchan aquí, al final del body, cuando ya existe.
restoreFocusAfterSwap();

/**
 * Un 422 no es un fallo de transporte: es la respuesta con el panel o el
 * formulario re-renderizado y el mensaje de validación adentro. htmx 2.0.11 no
 * swapea NINGÚN 4xx por default (`responseHandling`:
 * `[{code:"204",swap:false},{code:"[23]..",swap:true},{code:"[45]..",swap:false,error:true}]`),
 * así que sin esta excepción el mensaje por campo no llega nunca a la
 * pantalla: el panel queda como estaba y lo único visible es el toast
 * genérico de notifyHtmxFailures().
 *
 * `isError = false` es la otra mitad: es el flag del que htmx deriva
 * `failed`/`successful` y el que decide si emite `htmx:responseError`. Con el
 * swap habilitado y el flag encendido, el toast genérico denunciaría un fallo
 * que el formulario ya explica campo por campo.
 */
function allowValidationSwap() {
    document.body.addEventListener('htmx:beforeSwap', (event) => {
        const detail = event.detail;
        const xhr = detail && detail.xhr;

        if (!xhr || xhr.status !== 422) {
            return;
        }

        detail.shouldSwap = true;
        detail.isError = false;
    });
}

/**
 * Un fallo de red o del servidor no puede quedar en silencio (S8). htmx NO
 * intercambia respuestas de error: el panel queda como estaba y, sin aviso, el
 * usuario ve una acción que "no hizo nada" y la repite.
 *
 * - `htmx:responseError`: el servidor contestó 4xx/5xx.
 * - `htmx:sendError`: no hubo respuesta (sin red, DNS, servidor caído).
 *
 * Se usa el MISMO evento `toasts` que ya emite Controller::render() para las
 * acciones htmx exitosas: un solo componente de avisos, y este script no toca
 * el estado de Alpine (`.toast-host` es su dueño, no un consumidor más).
 */
function notifyHtmxFailures() {
    const notify = (message) => window.dispatchEvent(new CustomEvent('toasts', {
        detail: { items: [{ message, type: 'error' }] }
    }));

    document.body.addEventListener('htmx:responseError', (event) => {
        const status = event.detail && event.detail.xhr ? event.detail.xhr.status : 0;

        // 400, 419 y 422 ya tienen su aviso: el failureHandler de slim/csrf
        // responde 400 con `HX-Trigger` y ese evento `csrf` lo muestra este
        // mismo stack; el 422 swapea (ver allowValidationSwap) y el mensaje
        // viaja en el formulario o en `HX-Trigger-After-Swap`. Avisar también
        // aquí daría DOS toasts del mismo fallo, y el de CSRF dice más
        // ("Tu sesión expiró") porque sabe por qué falló.
        if (status === 400 || status === 419 || status === 422) {
            return;
        }

        notify('No se pudo completar la acción. Intenta de nuevo.');
    });

    document.body.addEventListener('htmx:sendError', () => {
        notify('No hay conexión con el servidor.');
    });
}

/**
 * Dialogs por atributo, sin onclick inline:
 *   data-modal-open="id"  abre el <dialog> con showModal()
 *   data-modal-close      cierra el <dialog> que lo contiene
 * showModal() da top layer, Escape, focus trap y devuelve el foco al cerrar.
 * El `open` que renderiza el servidor tras un 422 se convierte a modal real.
 */
function wireDialogs() {
    const previousFocus = new WeakMap();

    document.addEventListener('click', (event) => {
        if (!(event.target instanceof Element)) return;

        const opener = event.target.closest('[data-modal-open]');
        if (opener) {
            const dialog = document.getElementById(opener.getAttribute('data-modal-open'));
            if (dialog instanceof HTMLDialogElement) {
                previousFocus.set(dialog, document.activeElement);
                if (dialog.open) dialog.close();
                dialog.showModal();
            }
            return;
        }

        const closer = event.target.closest('[data-modal-close]');
        if (closer) {
            const dialog = closer.closest('dialog');
            if (dialog instanceof HTMLDialogElement) dialog.close();
        }
    });

    document.addEventListener('close', (event) => {
        const dialog = event.target;
        if (!(dialog instanceof HTMLDialogElement)) return;

        const previous = previousFocus.get(dialog);
        if (previous instanceof HTMLElement && document.contains(previous)) {
            previous.focus();
        }
        previousFocus.delete(dialog);
    }, true);

    document.querySelectorAll('dialog[open]').forEach((dialog) => {
        dialog.removeAttribute('open');
        dialog.showModal();
    });
}

/**
 * Un formulario clasico no debe enviarse dos veces por doble click. Se
 * deshabilita el submitter un tick despues del submit para no perder su
 * name/value en la serializacion.
 */
function preventDoubleSubmit() {
    document.addEventListener('submit', (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || form.method === 'dialog') return;

        // htmx ya controla el estado con hx-disabled-elt; si además lo
        // deshabilitamos acá, el submitter queda con aria-disabled para
        // siempre y el snapshot de accesibilidad lo anuncia como muerto.
        if (
            form.hasAttribute('hx-post')
            || form.hasAttribute('hx-get')
            || form.hasAttribute('hx-put')
            || form.hasAttribute('hx-patch')
            || form.hasAttribute('hx-delete')
        ) {
            return;
        }

        const submitter = event.submitter
            || form.querySelector('button[type="submit"], button:not([type])');
        if (!(submitter instanceof HTMLButtonElement)) return;

        setTimeout(() => {
            submitter.disabled = true;
            submitter.setAttribute('aria-disabled', 'true');
        }, 0);
    });
}

allowValidationSwap();
notifyHtmxFailures();
wireDialogs();
preventDoubleSubmit();
