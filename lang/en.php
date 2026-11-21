<?php

declare(strict_types=1);

// English catalogue. Same keys as `es.php`, same order: both files stay
// readable side by side, and I18nTest fails if one key is missing here.
//
// Keys name the concept, values are the text. Parameters use `%name%`, as in
// `t('pagination.showing', {'%from%': 1})`.

return [
    'app.name' => 'My App',

    'field.optional' => '(optional)',

    'footer.environment' => 'environment %env%',

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
];
