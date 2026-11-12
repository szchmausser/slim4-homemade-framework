<?php

declare(strict_types=1);

namespace Tests\Support;

use DOMDocument;
use DOMXPath;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

/**
 * Contratos mínimos de los componentes base. Los demos los ejercitan por HTTP,
 * pero estos tests fijan las props que un usuario puede reutilizar sin
 * depender de una ruta, un demo o un controlador.
 */
final class ComponentContractTest extends TestCase
{
    private Environment $twig;

    protected function setUp(): void
    {
        $views = dirname(__DIR__, 2) . '/resources/views';
        $this->twig = new Environment(new FilesystemLoader($views), [
            'autoescape' => 'html',
            'strict_variables' => false,
        ]);
    }

    public function test_button_expone_htmx_y_foco_opcionales(): void
    {
        $html = $this->twig->render('components/ui/button.twig', [
            'label' => 'Guardar',
            'type' => 'submit',
            'icon' => 'check',
            'size' => 'sm',
            'extra_class' => 'gap-1.5',
            'hx_post' => '/tareas/1',
            'hx_target' => '#tareas-panel',
            'hx_swap' => 'outerHTML',
            'hx_headers' => '{"csrf_name":"token"}',
            'data_focus_key' => 'save-1',
        ]);

        self::assertStringContainsString('type="submit"', $html);
        self::assertStringContainsString('btn btn-primary btn-sm gap-1.5', $html);
        self::assertStringContainsString('hx-post="/tareas/1"', $html);
        self::assertStringContainsString('hx-target="#tareas-panel"', $html);
        self::assertStringContainsString('hx-swap="outerHTML"', $html);
        self::assertStringContainsString('hx-headers=', $html);
        self::assertStringContainsString('data-focus-key="save-1"', $html);
        self::assertStringContainsString('<svg', $html);
    }

    public function test_field_permite_label_oculto_y_variante_compacta(): void
    {
        $html = $this->twig->render('components/form/field.twig', [
            'id' => 'task-title-3',
            'name' => 'title',
            'label' => 'Título de la tarea',
            'value' => 'Texto',
            'required' => true,
            'maxlength' => 120,
            'field_class' => 'contents',
            'label_class' => 'sr-only',
            'input_class' => 'input input-sm w-full',
            'data_focus_key' => 'edit-3',
        ]);

        self::assertStringContainsString('class="contents"', $html);
        self::assertStringContainsString('class="sr-only"', $html);
        self::assertStringContainsString('for="task-title-3"', $html);
        self::assertStringContainsString('id="task-title-3"', $html);
        self::assertStringContainsString('class="input input-sm w-full"', $html);
        self::assertStringContainsString('value="Texto"', $html);
        self::assertStringContainsString('required', $html);
        self::assertStringContainsString('data-focus-key="edit-3"', $html);
    }

    public function test_field_marca_el_campo_invalido_y_ata_el_mensaje(): void
    {
        $html = $this->twig->render('components/form/field.twig', [
            'id' => 'task-title-7',
            'name' => 'title',
            'label' => 'Título de la tarea',
            'error' => 'No puede superar los 120 caracteres.',
        ]);

        // `aria-invalid` declara el estado y `aria-describedby` ata el mensaje
        // al input: sin eso el lector de pantalla lee el error como texto
        // suelto y no como la razón del campo (S4).
        self::assertStringContainsString('aria-invalid="true"', $html);
        self::assertStringContainsString('aria-describedby="task-title-7-error"', $html);
        self::assertStringContainsString(
            '<p id="task-title-7-error" class="text-error text-sm" role="alert">No puede superar los 120 caracteres.</p>',
            $html
        );
    }

    public function test_field_sin_error_no_marca_el_campo_invalido(): void
    {
        // Contrato previo: un campo válido no anuncia estado de error ni crea
        // un mensaje vacío con `role="alert"` que hable de la nada.
        $html = $this->twig->render('components/form/field.twig', [
            'name' => 'title',
            'label' => 'Título de la tarea',
        ]);

        self::assertStringNotContainsString('aria-invalid', $html);
        self::assertStringNotContainsString('aria-describedby', $html);
        self::assertStringNotContainsString('role="alert"', $html);
    }

    public function test_alert_conserva_variante_y_rol_semantico(): void
    {
        $html = $this->twig->render('components/feedback/alert.twig', [
            'variant' => 'error',
            'message' => 'No se pudo guardar.',
        ]);

        self::assertStringContainsString('alert alert-error', $html);
        self::assertStringContainsString('role="alert"', $html);
        self::assertStringContainsString('No se pudo guardar.', $html);
        self::assertStringContainsString('icon-error', $html);
    }

    public function test_alert_con_variante_desconocida_conserva_rol_valido_y_clase(): void
    {
        // S3: `variants[...]` devuelve null para una variante que no existe, y
        // el componente quedaba con `role=""` (ARIA inválido) y el slot de
        // color vacío. Sin `strict_variables` Twig lo tapaba: aquí se fija que
        // una variante desconocida cae al default documentado (info/status).
        $html = $this->twig->render('components/feedback/alert.twig', [
            'variant' => 'warning',
            'message' => 'No se pudo guardar.',
        ]);

        self::assertMatchesRegularExpression('/\brole="(alert|status)"/', $html);
        self::assertMatchesRegularExpression('/\bclass="alert [a-z-]+"/', $html);
        self::assertStringContainsString('<span class="icon-info shrink-0" aria-hidden="true">', $html);
    }

    public function test_button_con_href_y_disabled_no_navega(): void
    {
        // S4: `disabled` sobre un enlace no puede ser decorativo. Si conserva
        // `href` navega igual; sin `href` deja de activar, `aria-disabled` lo
        // anuncia y `tabindex="-1"` lo saca del orden de tabulación.
        $html = $this->twig->render('components/ui/button.twig', [
            'label' => 'Volver a contactos',
            'href' => '/contactos',
            'disabled' => true,
        ]);

        self::assertStringContainsString('<a ', $html);
        self::assertStringNotContainsString('href=', $html);
        self::assertStringContainsString('aria-disabled="true"', $html);
        self::assertStringContainsString('tabindex="-1"', $html);
    }

    public function test_button_como_enlace_conserva_href_target_e_icono(): void
    {
        // Contrato previo de la rama `<a>`, que usa el demo de contactos:
        // href, target, aria-label, icono del registro compartido y atributos
        // HTMX. Un `<a>` no lleva `type`.
        $html = $this->twig->render('components/ui/button.twig', [
            'label' => 'Volver a contactos',
            'variant' => 'ghost',
            'size' => 'sm',
            'href' => '/contactos',
            'target' => '_blank',
            'aria_label' => 'Volver a contactos',
            'icon' => 'arrow-left',
            'hx_get' => '/contactos?page=2',
            'data_focus_key' => 'back-1',
        ]);

        self::assertStringContainsString('href="/contactos"', $html);
        self::assertStringContainsString('target="_blank"', $html);
        self::assertStringContainsString('aria-label="Volver a contactos"', $html);
        self::assertStringContainsString('btn btn-ghost btn-sm', $html);
        self::assertStringContainsString('hx-get="/contactos?page=2"', $html);
        self::assertStringContainsString('data-focus-key="back-1"', $html);
        self::assertStringContainsString('<svg', $html);
        self::assertStringNotContainsString('type=', $html);
    }

    public function test_icon_conserva_el_registro_y_el_fallback_vacio(): void
    {
        // S5: los 23 nombres del registro salen de una sola definición. Lo que
        // no cambió es el contrato: un `<svg>` por nombre, la clase por
        // defecto, y ningún fallback cuando el nombre no existe (un icono
        // equivocado que parece válido es peor que un hueco).
        $names = [
            'home', 'tasks', 'contacts', 'mail', 'lock', 'arrow-right', 'arrow-left',
            'alert-circle', 'info', 'plus', 'pencil', 'trash', 'check-circle', 'check',
            'x', 'rotate', 'list', 'layers', 'server', 'monitor', 'database', 'wrench', 'package',
        ];

        foreach ($names as $name) {
            $html = (string) $this->twig->render('partials/_icon.twig', ['name' => $name]);
            self::assertSame(1, substr_count($html, '<svg'), 'Icono duplicado o ausente: ' . $name);
            self::assertStringContainsString('class="h-5 w-5 shrink-0"', $html, $name);
        }

        self::assertSame('', trim((string) $this->twig->render('partials/_icon.twig', ['name' => 'hourse'])));
    }

    public function test_icon_del_lapiz_es_el_mismo_path_que_el_js_de_contactos(): void
    {
        // S5: el formatter de contactos pinta el lápiz a mano y no se puede
        // tocar (su JS va con SRI). Si el registro y el formatter divergen, el
        // mismo icono se ve distinto según quién lo pinte: el test compara el
        // markup exacto contra el del JS.
        $jsPath = dirname(__DIR__, 2) . '/framework/scaffolds/contacts/public/assets/js/contacts-tabulator.js';
        $js = is_file($jsPath) ? file_get_contents($jsPath) : false;

        if (!is_string($js)) {
            self::markTestSkipped('El scaffold de contactos no está en este árbol.');
        }

        $pencil = '~(<path d="M12 20h9"/><path d="[^"]+"/>)~';
        $jsPencil = preg_match($pencil, $js, $jsMatches) === 1 ? $jsMatches[1] : '';

        $icon = (string) $this->twig->render('partials/_icon.twig', ['name' => 'pencil']);
        $iconPencil = preg_match($pencil, $icon, $iconMatches) === 1 ? $iconMatches[1] : '';

        self::assertNotSame('', $jsPencil, 'El formatter de contactos ya no trae el lápiz esperado.');
        self::assertSame($jsPencil, $iconPencil);
    }

    public function test_modal_trae_control_de_cierre_y_descripcion(): void
    {
        // S6: un `<dialog open>` re-renderizado por el servidor no entra al top
        // layer, así que Escape no lo cierra. El modal trae su propio control
        // (`form method=dialog` dentro del `modal-box`) y anuncia el subtítulo
        // como descripción.
        $withSubtitle = $this->twig->createTemplate(
            "{% embed 'components/ui/modal.twig' with {id: 'demo-modal', title: 'Demo', subtitle: 'Detalle'} %}"
            . '{% block body %}<p>Contenido</p>{% endblock %}'
            . '{% endembed %}'
        )->render([]);

        $document = new DOMDocument();
        $document->loadHTML(
            '<!DOCTYPE html><html><head><meta charset="utf-8"></head><body>' . $withSubtitle . '</body></html>',
            LIBXML_NOERROR | LIBXML_NOWARNING
        );
        $closers = (new DOMXPath($document))->query(
            '//div[contains(concat(" ", normalize-space(@class), " "), " modal-box ")]'
            . '//form[@method="dialog"]/button[@aria-label="Cerrar"]'
        );

        self::assertNotFalse($closers);
        self::assertSame(1, $closers->length);
        self::assertStringContainsString('aria-describedby="demo-modal-subtitle"', $withSubtitle);
        self::assertStringContainsString('id="demo-modal-subtitle"', $withSubtitle);

        $withoutSubtitle = $this->twig->createTemplate(
            "{% embed 'components/ui/modal.twig' with {id: 'plain-modal', title: 'Demo'} %}"
            . '{% endembed %}'
        )->render([]);

        self::assertStringNotContainsString('aria-describedby', $withoutSubtitle);
    }

    public function test_modal_compone_titulo_cuerpo_y_backdrop(): void
    {
        $template = $this->twig->createTemplate(
            "{% embed 'components/ui/modal.twig' with {id: 'demo-modal', title: 'Demo'} %}"
            . '{% block body %}<p>Contenido</p>{% endblock %}'
            . '{% endembed %}'
        );

        $html = $template->render([]);

        self::assertStringContainsString('<dialog id="demo-modal"', $html);
        self::assertStringContainsString('aria-labelledby="demo-modal-title"', $html);
        self::assertStringContainsString('id="demo-modal-title"', $html);
        self::assertStringContainsString('Contenido', $html);
        self::assertStringContainsString('modal-backdrop', $html);
    }
}
