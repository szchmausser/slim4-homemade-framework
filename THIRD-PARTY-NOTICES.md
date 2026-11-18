# Third-party notices

Este proyecto distribuye código y assets de terceros. Las licencias de esos
componentes se aplican a sus archivos, no al código propio del proyecto.

## Assets vendorizados

| Componente | Versión | Licencia | Copyright / origen |
|---|---|---|---|
| Alpine.js | 3.17.4 | MIT | Caleb Porzio y colaboradores — https://github.com/alpinejs/alpine |
| htmx | 2.0.11 | BSD-2-Clause | Big Sky Software — https://github.com/bigskysoftware/htmx |
| Tailwind CSS (browser build) | 4.3.3 | MIT | Tailwind Labs, Inc. — https://github.com/tailwindlabs/tailwindcss |
| daisyUI | 5.7.46 | MIT | Pouya Saadeghi — https://github.com/saadeghi/daisyui |
| Tabulator | 6.7.1 | MIT | Oli Folkerd — https://github.com/tabulator-tables/Tabulator |
| Iconos SVG (`components/ui/icon.twig`) | — | ISC / MIT | Basados en Lucide (ISC) y Feather (MIT) — https://github.com/lucide-icons/lucide |

Los assets viven en `public/assets/` y, para el demo de contactos, en
`framework/scaffolds/contacts/public/assets/vendor/tabulator/6.7.1/`. Ese
directorio incluye el archivo `LICENSE` de Tabulator.

Los archivos PHP de dependencias se instalan con Composer y conservan sus
propias licencias en `vendor/`. Las dependencias directas son:

| Paquete | Licencia |
|---|---|
| `slim/slim`, `slim/psr7`, `slim/twig-view`, `slim/csrf` | MIT |
| `php-di/php-di` | MIT |
| `illuminate/database` | MIT |
| `twig/twig` | BSD-3-Clause |
| `vlucas/phpdotenv` | BSD-3-Clause |
| `respect/validation` | MIT |
| `monolog/monolog` | MIT |
| `robmorgan/phinx` | MIT |
| `phpunit/phpunit` | BSD-3-Clause |
| `filp/whoops` | MIT |

## MIT License

The MIT License applies to Alpine.js, Tailwind CSS, daisyUI, Tabulator y los
paquetes marcados como MIT en la tabla anterior.

Copyright (c) de cada componente según la tabla.

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.

## BSD 2-Clause License

Aplica a htmx.

Copyright (c) Big Sky Software.

Redistribution and use in source and binary forms, with or without
modification, are permitted provided that the following conditions are met:

1. Redistributions of source code must retain the above copyright notice, this
   list of conditions and the following disclaimer.
2. Redistributions in binary form must reproduce the above copyright notice,
   this list of conditions and the following disclaimer in the documentation
   and/or other materials provided with the distribution.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE
FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL
DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR
SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER
CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY,
OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.

## ISC License

Aplica a los paths de iconos basados en Lucide.

Copyright (c) for portions of Lucide are held by Cole Bemis 2013-2022 as part
of Feather (MIT). All other copyright (c) for Lucide are held by Lucide
Contributors 2022.

Permission to use, copy, modify, and/or distribute this software for any
purpose with or without fee is hereby granted, provided that the above
copyright notice and this permission notice appear in all copies.

THE SOFTWARE IS PROVIDED "AS IS" AND THE AUTHOR DISCLAIMS ALL WARRANTIES WITH
REGARD TO THIS SOFTWARE INCLUDING ALL IMPLIED WARRANTIES OF MERCHANTABILITY
AND FITNESS. IN NO EVENT SHALL THE AUTHOR BE LIABLE FOR ANY SPECIAL, DIRECT,
INDIRECT, OR CONSEQUENTIAL DAMAGES OR ANY DAMAGES WHATSOEVER RESULTING FROM
LOSS OF USE, DATA OR PROFITS, WHETHER IN AN ACTION OF CONTRACT, NEGLIGENCE OR
OTHER TORTIOUS ACTION, ARISING OUT OF OR IN CONNECTION WITH THE USE OR
PERFORMANCE OF THIS SOFTWARE.
