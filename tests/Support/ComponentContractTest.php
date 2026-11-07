<?php

declare(strict_types=1);

namespace Tests\Support;

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
