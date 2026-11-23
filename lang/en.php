<?php

declare(strict_types=1);

// English catalogue. Same keys as `es.php`, same order: both files stay
// readable side by side, and I18nTest fails if one key is missing here.
//
// Demo keys are not here: each scaffold ships them in
// `framework/scaffolds/<demo>/lang/<locale>/<demo>.php` and the installer copies
// them to `lang/<locale>/<demo>.php` with the rest of the demo, so a project
// with no demos installed carries no unused translations (S1).
//
// Keys name the concept, values are the text. Parameters use `%name%`, as in
// `t('pagination.showing', {'%from%': 1})`.

return [
    'app.name' => 'My App',

    'field.optional' => '(optional)',

    'footer.environment' => 'environment %env%',

    'footer.apply'       => 'Change',
    'footer.language'    => 'Language',

    'locale.name' => 'English',

    'layout.brand_home'      => '%app%, go to the home page',
    'layout.breadcrumb'      => 'Breadcrumb',
    'layout.close_menu'      => 'Close menu',
    'layout.collapse_menu'   => 'Collapse menu',
    'layout.expand_menu'     => 'Expand menu',
    'layout.logout'          => 'Log out',
    'layout.main_navigation' => 'Main navigation',
    'layout.open_menu'       => 'Open menu',
    'layout.skip_to_content' => 'Skip to content',
    'layout.theme_toggle'    => 'Switch between light and dark theme',

    'nav.home' => 'Home',

    'modal.close' => 'Close',

    'pagination.apply'       => 'Apply',
    'pagination.first'       => 'First',
    'pagination.go_first'    => 'First page',
    'pagination.go_last'     => 'Last page',
    'pagination.go_next'     => 'Next page',
    'pagination.go_page'     => 'Page %page%',
    'pagination.go_previous' => 'Previous page',
    'pagination.label'       => 'records',
    'pagination.last'        => 'Last',
    'pagination.nav'         => 'Pagination',
    'pagination.next'        => 'Next',
    'pagination.per_page'    => '%label% per page',
    'pagination.previous'    => 'Previous',
    'pagination.showing'     => 'Showing %from% to %to% of %total% %label%',

    'toast.close' => 'Close notification',

    'home.intro'                   => 'A skeleton tuned for fast, AI-assisted development in constrained environments (Windows, Linux or 32-bit Termux offline). Clean architecture with server-side rendering, light reactivity without SPA overhead, and frontend assets served locally with no Node, no bundlers and no external CDN dependencies.',
    'home.open_demo'               => 'Open %name%',
    'home.sign_in'                 => 'Sign in',
    'home.tagline'                 => 'Full-stack PHP framework with server-side rendering',
    'home.features.title'          => 'Operational features',
    'home.feature.ssr.title'       => 'Server-side rendering',
    'home.feature.ssr.body'        => 'Twig templates resolved on the backend without SPA overhead.',
    'home.feature.node.title'      => 'No Node toolchain',
    'home.feature.node.body'       => 'Zero npm dependencies, no package.json and no JS compilers.',
    'home.feature.assets.title'    => 'Local assets, no CDN',
    'home.feature.assets.body'     => 'Tailwind and daisyUI 5 vendored and served with SRI.',
    'home.feature.offline.title'   => 'Runs 100% offline',
    'home.feature.offline.body'    => 'Works with no network, ideal for 32-bit Termux and isolated environments.',
    'home.feature.nobuild.title'   => 'Zero build steps',
    'home.feature.nobuild.body'    => 'Edit Twig or PHP templates and reload right away.',
    'home.feature.scaffolds.title' => 'Detachable scaffolds',
    'home.feature.scaffolds.body'  => 'Optional demos you can install or remove without leaving anything behind.',
    'home.stack.title'             => 'Architecture and stack',
    'home.stack.subtitle'          => 'Decoupled technologies, PSR standards and production-ready with no build steps.',
    'home.stack.backend'           => 'Core & Backend',
    'home.stack.frontend'          => 'Frontend & Reactivity',
    'home.stack.data'              => 'Data & Persistence',
    'home.stack.quality'           => 'Quality & Tooling',
    'home.tech.php'      => 'Base language and modern runtime',
    'home.tech.slim'     => 'HTTP router, middlewares and PSR-7/15 pipeline',
    'home.tech.phpdi'    => 'PSR-11 dependency injection container',
    'home.tech.csrf'     => 'Cross-site request forgery protection',
    'home.tech.dotenv'   => 'Configuration through environment variables',
    'home.tech.respect'  => 'Declarative rules for input validation',
    'home.tech.monolog'  => 'Structured log of system events',
    'home.tech.twig'     => 'Server-rendered templates through slim/twig-view',
    'home.tech.tailwind' => 'Utility styles run locally, no Node',
    'home.tech.daisyui'  => 'Vendored, themeable interface components',
    'home.tech.htmx'     => 'Reactive navigation and HTML swaps without a SPA',
    'home.tech.alpine'   => 'Interactive state handling in the browser',
    'home.tech.eloquent' => 'Object-relational mapping (illuminate/database)',
    'home.tech.sqlite'   => 'Embedded database with zero maintenance',
    'home.tech.phinx'    => 'Idempotent migrations and schema versioning',
    'home.tech.phpunit'  => 'Unit, functional and security tests',
    'home.tech.phpstan'  => 'Static type analysis, level 5 (PHAR)',
    'home.tech.whoops'   => 'Detailed error handler for development',
    'home.tech.composer' => 'Package manager and verification scripts',
    'home.modules.title'    => 'Modules and demos',
    'home.modules.subtitle' => 'Optional scaffolds to explore the stack or use as a starting point.',
    'home.demo.title'       => '%name% demo',
    'home.demo_available'   => '%name% demo: not installed',
    'home.demo.installed'   => 'Installed',
    'home.demo.optional'    => 'Optional',
    'home.demo.uninstall'   => 'Uninstall module:',
    'home.demo.install'     => 'Install command:',
    'home.requirements.title'      => 'Requirements and compatibility',
    'home.requirements.envs'       => 'Environments',
    'home.requirements.envs_value' => 'Termux (ARM 32/64b), Windows, Linux, Mac',
    'home.requirements.php'        => 'PHP',
    'home.requirements.extensions' => 'Extensions',
    'home.requirements.composer'   => 'Composer',
    'home.requirements.browsers'   => 'Browsers',
    'home.requirements.note'       => 'No Node.js, no build step and no CDN at runtime.',

    'login.email'      => 'Email',
    'login.email_hint' => 'you@example.com',
    'login.password'   => 'Password',
    'login.remember'   => 'Remember me for 30 days',
    'login.submit'     => 'Log in',
    'login.subtitle'   => 'Enter your email and password to continue.',
    'login.title'      => 'Sign in',

    'error.back_home'          => 'Back to home',
    'error.badge'              => 'Error %code%',
    'error.bad_request'        => 'The request is not valid.',
    'error.forbidden'          => 'You do not have permission to view this.',
    'error.method_not_allowed' => 'That method is not allowed.',
    'error.not_found'          => 'We could not find that page.',
    'error.server'             => 'Something went wrong on our side.',
    'error.title'              => '%code% · %app%',

    'validation.email'    => 'This field must be a valid email address.',
    'validation.max'      => 'Must not be longer than %max% characters.',
    'validation.min'      => 'Must be at least %min% characters long.',
    'validation.numeric'  => 'This field must be a number.',
    'validation.required' => 'This field is required.',
    'validation.string'   => 'This field must be text.',

    'auth.csrf_login'          => 'Your session expired. Sign in again.',
    'auth.csrf_reload'         => 'Your session expired. Reload the page.',
    'auth.invalid_credentials' => 'Invalid credentials.',
    'auth.login_required'      => 'Sign in to continue.',
    'auth.logged_out'          => 'Signed out.',
];
