<?php

declare(strict_types=1);

// English catalogue for the Notes demo. It ships with the scaffold, so the
// demo itself installs and removes it: without `composer demo:notes:install`
// these keys do not exist in the project. `nav.notes` lives here because the
// module name is the demo's text, not the shell's.
//
// The wording follows the tutorial («Agregar nota», «Borrar nota», «Nota
// creada.»), which is the one reusing these keys.
//
// Same keys as `lang/es/notes.php`.

return [
    // Module label in the sidebar and on the home page.
    'nav.notes' => 'Notes',

    // Screen, modals and flashes.
    'notes.title'           => 'Notes',
    'notes.subtitle'        => 'Your notes, with creation and row actions.',
    'notes.add'             => 'Add note',
    'notes.list_heading'    => 'All notes',
    'notes.create_title'    => 'New note',
    'notes.create_subtitle' => 'Give it a title; the body is optional.',
    'notes.create_submit'   => 'Create note',
    'notes.edit_title'      => 'Edit note',
    'notes.save_changes'    => 'Save changes',
    'notes.empty'           => 'No notes yet.',
    'notes.edit_aria'       => 'Edit %title%',
    'notes.delete_aria'     => 'Delete %title%',
    'notes.delete_title'    => 'Delete note',
    'notes.delete_question' => 'Delete "%title%"? This cannot be undone.',
    'notes.cancel'          => 'Cancel',
    'notes.delete_confirm'  => 'Delete',
    'notes.save'            => 'Save',
    'notes.field_title'     => 'Title',
    'notes.field_body'      => 'Body (optional)',
    'notes.summary'         => 'A minimal, complete CRUD: create and edit in modals, confirmed delete and server-side pagination.',
    'notes.why'             => 'It is the tutorial pattern with an English domain (title and body columns) and the flat layout under app/Models and app/Http/Controllers: compare behaviour, not location.',
    'notes.created'         => 'Note created.',
    'notes.updated'         => 'Note updated.',
    'notes.deleted'         => 'Note deleted.',

    // Listing: search and order (ListQuery); order only accepts title and
    // created_at, search goes over title and body.
    'notes.search'             => 'Search',
    'notes.search_placeholder' => 'Search title and body',
    'notes.sort_label'         => 'Sort by',
    'notes.sort_title'         => 'Title',
    'notes.sort_created'       => 'Created',
];
