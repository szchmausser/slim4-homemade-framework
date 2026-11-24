<?php

declare(strict_types=1);

// English catalogue for the Contacts demo. It ships with the scaffold, so
// the demo itself installs and removes it: without
// `composer demo:contacts:install` these keys do not exist in the project.
// `nav.contacts` lives here because the module name is the demo's text.
//
// Same keys as `lang/es/contacts.php`.

return [
    // Module label in the sidebar and on the home page.
    'nav.contacts' => 'Contacts',

    // Screen, form, modals, table and flashes.
    'contacts.title'           => 'Contacts',
    'contacts.subtitle'        => 'Contact CRUD with a Tabulator listing: numbering, columns and row actions over SQLite.',
    'contacts.add'             => 'Add contact',
    'contacts.list_heading'    => 'List',
    'contacts.table_label'     => 'Contact list',
    'contacts.needs_js'        => 'Enable JavaScript to see the interactive table.',
    'contacts.create_title'    => 'New contact',
    'contacts.create_subtitle' => 'Fill in the details to add it to the list.',
    'contacts.create_submit'   => 'Create contact',
    'contacts.delete_title'    => 'Delete contact',
    'contacts.delete_question' => 'Delete %name%?',
    'contacts.delete_confirm'  => 'Delete',
    'contacts.edit'            => 'Edit',
    'contacts.delete'          => 'Delete',
    'contacts.edit_title'      => 'Edit contact',
    'contacts.cancel'          => 'Cancel',
    'contacts.save'            => 'Save',
    'contacts.save_changes'    => 'Save changes',
    'contacts.back'            => 'Back to contacts',
    'contacts.edit_subtitle'   => 'Update the details and save the changes.',
    'contacts.field_name'      => 'Name',
    'contacts.field_email'     => 'Email',
    'contacts.field_phone'     => 'Phone',
    'contacts.empty'           => 'No contacts yet.',
    'contacts.loading'         => 'Loading...',
    'contacts.load_error'      => 'The form could not be loaded.',
    'contacts.collapse_toggle' => 'Show or hide the row data',
    'contacts.column_number'   => '#',
    'contacts.column_name'     => 'Name',
    'contacts.column_email'    => 'Email',
    'contacts.column_phone'    => 'Phone',
    'contacts.column_actions'  => 'Actions',
    'contacts.filter_name'     => 'Search by name',
    'contacts.filter_email'    => 'Search by email',
    'contacts.filter_phone'    => 'Search by phone',
    'contacts.rows_per_page'   => 'Rows',
    'contacts.page'            => 'Page',
    'contacts.all'             => 'All',
    'contacts.counter_showing' => 'Showing',
    'contacts.counter_of'      => 'of',
    'contacts.counter_rows'    => 'rows',
    'contacts.counter_pages'   => 'pages',
    'contacts.summary'         => 'Remote-mode Tabulator grid: the server paginates and sorts, not the browser.',
    'contacts.why'             => 'For an advanced table instead of a list: data endpoint, sort whitelist, create and delete in modals and editing on its own page.',
    'contacts.created'         => 'Contact created.',
    'contacts.updated'         => 'Contact updated.',
    'contacts.deleted'         => 'Contact deleted.',
    'contacts.not_found'       => 'The contact does not exist.',
    'contacts.edit_crumb'      => 'Edit',
];
