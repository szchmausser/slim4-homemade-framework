<?php

declare(strict_types=1);

namespace Tests\Support;

use Framework\Validation\Validator;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ValidatorTest extends TestCase
{
    /** @return array<string, array{0: array, 1: array, 2: bool}> */
    public static function cases(): array
    {
        return [
            // nombre                       datos                      reglas                    falla
            'required, valor presente'      => [['title' => 'Comprar pan'], ['title' => 'required'], false],
            'required, string vacío'       => [['title' => ''],           ['title' => 'required'], true],
            'required, null'               => [['title' => null],        ['title' => 'required'], true],
            'required, campo ausente'      => [[],                       ['title' => 'required'], true],
            'required, solo espacios'      => [['title' => '   '],       ['title' => 'required'], true],
            'max, en el límite'            => [['title' => str_repeat('a', 120)], ['title' => 'max:120'], false],
            'max, un carácter de más'      => [['title' => str_repeat('a', 121)], ['title' => 'max:120'], true],
            'max, string vacío'            => [['title' => ''],           ['title' => 'max:120'], false],
            'required + max, ambos ok'     => [['title' => 'Pan'],        ['title' => 'required|max:120'], false],
            'required + max, falla max'    => [['title' => str_repeat('a', 200)], ['title' => 'required|max:120'], true],
            'campo sin reglas ni valor'    => [[],                       ['otro' => 'required'], true],
        ];
    }

    #[DataProvider('cases')]
    public function test_valida(array $data, array $rules, bool $shouldFail): void
    {
        $validator = Validator::make($data, $rules);
        self::assertSame($shouldFail, $validator->fails());
    }

    public function test_first_error_devuelve_el_mensaje_del_primer_campo(): void
    {
        $validator = Validator::make(['title' => ''], ['title' => 'required|max:120']);

        self::assertTrue($validator->fails());
        // `errors()` es campo => mensaje, no campo => lista de mensajes.
        self::assertSame(['title' => 'Este campo es obligatorio.'], $validator->errors());
        self::assertSame('Este campo es obligatorio.', $validator->firstError());
    }

    /** @return array<string, array{0: array, 1: array, 2: string, 3: string}> */
    public static function mensajes(): array
    {
        return [
            // caso          datos                            reglas                   campo      mensaje
            'required' => [['title' => ''],                ['title' => 'required'],    'title',    'Este campo es obligatorio.'],
            'string'   => [['title' => 123],               ['title' => 'string'],      'title',    'Este campo debe ser texto.'],
            'email'    => [['email' => 'no-es-email'],     ['email' => 'email'],       'email',    'Este campo debe ser un email válido.'],
            'numeric'  => [['priority' => 'alta'],         ['priority' => 'numeric'],  'priority', 'Este campo debe ser un número.'],
            'max'      => [['title' => str_repeat('a', 51)], ['title' => 'max:50'],    'title',    'No puede superar los 50 caracteres.'],
            'min'      => [['password' => 'corta'],        ['password' => 'min:8'],    'password', 'Debe tener al menos 8 caracteres.'],
        ];
    }

    /**
     * El mensaje que ve el usuario es castellano y viene por campo: es lo que
     * la vista pinta al lado del input (S3), no el texto en inglés de Respect.
     */
    #[DataProvider('mensajes')]
    public function test_los_mensajes_son_en_castellano(array $data, array $rules, string $field, string $expected): void
    {
        $validator = Validator::make($data, $rules);

        self::assertTrue($validator->fails(), 'El caso tenía que fallar.');
        self::assertSame([$field => $expected], $validator->errors());
        self::assertSame($expected, $validator->firstError());
    }

    /**
     * S2: un typo en la regla (`requird`) no puede validar en silencio. La
     * excepción nombra la regla, el campo y el string completo, que es lo que
     * hace falta para encontrar la regla rota sin abrir el template.
     */
    public function test_una_regla_desconocida_explota_nombrando_regla_campo_y_regla_completa(): void
    {
        try {
            Validator::make(['title' => 'Comprar pan'], ['title' => 'requird|max:120']);
        } catch (InvalidArgumentException $e) {
            self::assertStringContainsString('requird', $e->getMessage(), 'El mensaje no nombra la regla.');
            self::assertStringContainsString('title', $e->getMessage(), 'El mensaje no nombra el campo.');
            self::assertStringContainsString('requird|max:120', $e->getMessage(), 'El mensaje no trae la regla completa.');

            return;
        }

        self::fail('Una regla desconocida validó en silencio.');
    }

    /** @return array<string, array{0: string}> */
    public static function longitudesSinNumero(): array
    {
        return [
            'max sin parámetro' => ['max'],
            'min sin parámetro' => ['min'],
            'max con texto'     => ['max:muchos'],
            'min con texto'     => ['min:ocho'],
        ];
    }

    /**
     * `max`/`min` sin número es una regla rota: antes `(int) null` la volvía
     * `length(0)` y el campo nunca podía pasar, sin decir por qué.
     */
    #[DataProvider('longitudesSinNumero')]
    public function test_una_longitud_sin_numero_explota(string $rule): void
    {
        try {
            Validator::make(['title' => 'Comprar pan'], ['title' => $rule]);
        } catch (InvalidArgumentException $e) {
            self::assertStringContainsString('title', $e->getMessage(), 'El mensaje no nombra el campo.');
            self::assertStringContainsString($rule, $e->getMessage(), 'El mensaje no trae la regla completa.');

            return;
        }

        self::fail('Un "' . $rule . '" sin número validó en silencio.');
    }

    /**
     * Un solo mensaje por campo: gana la primera regla que falla, en el orden
     * en que están escritas. Sin eso el texto dependía del orden interno de
     * Respect y el aviso podía hablar de la regla equivocada.
     */
    public function test_la_primera_regla_que_falla_es_la_que_se_reporta(): void
    {
        // 'zzzzz' no es un email y además pasa el máximo: gana `email`.
        $primera = Validator::make(['email' => 'zzzzz'], ['email' => 'email|max:3']);
        self::assertSame(['email' => 'Este campo debe ser un email válido.'], $primera->errors());

        // Al revés: `max` gana aunque `email` también falle después.
        $segunda = Validator::make(['email' => str_repeat('a', 50)], ['email' => 'max:3|email']);
        self::assertSame(['email' => 'No puede superar los 3 caracteres.'], $segunda->errors());
    }
}
