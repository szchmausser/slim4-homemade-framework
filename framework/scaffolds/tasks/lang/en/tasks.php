<?php

declare(strict_types=1);

// English catalogue for the Tasks demo. It ships with the scaffold, so the
// demo itself installs and removes it: without `composer demo:tasks:install`
// these keys do not exist in the project. `nav.tasks` lives here because the
// module name is the demo's text, not the shell's.
//
// Same keys as `lang/es/tasks.php`.

return [
    // Module label in the sidebar and on the home page.
    'nav.tasks' => 'Tasks',

    // Screen, row, status, modal and flashes.
    'tasks.title'            => 'Tasks',
    'tasks.heading'          => 'My tasks',
    'tasks.subtitle'         => 'Task CRUD with HTMX, inline editing and pagination over SQLite.',
    'tasks.new'              => 'New task',
    'tasks.add'              => 'Add',
    'tasks.empty'            => 'No tasks yet.',
    'tasks.field_title'      => 'Task title',
    'tasks.save'             => 'Save',
    'tasks.cancel'           => 'Cancel',
    'tasks.status_done'      => 'Completed',
    'tasks.status_done_at'   => 'Completed on %date%',
    'tasks.status_pending'   => 'Pending',
    'tasks.mark_done'        => 'Mark as done',
    'tasks.mark_pending'     => 'Mark as not done',
    'tasks.edit'             => 'Edit',
    'tasks.delete'           => 'Delete',
    'tasks.delete_title'     => 'Delete this task?',
    'tasks.delete_confirm'   => 'Delete',
    'tasks.pagination_label' => 'tasks',
    'tasks.summary'          => 'List with inline editing and status toggling: every action is swapped as an HTMX region.',
    'tasks.why'              => 'The demo that pushes the shell: HTMX region, server-side pagination, focus after the swap and notices inside the swap.',
    'tasks.created'          => 'Task created.',
    'tasks.updated'          => 'Task updated.',
    'tasks.deleted'          => 'Task deleted.',
    'tasks.not_found'        => 'The task does not exist or was already deleted.',
    'tasks.marked_pending'   => 'Task marked as pending.',
    'tasks.completed'        => 'Task completed.',
    'tasks.invalid_data'     => 'Invalid data.',

    // Listing: search, filter and order (ListQuery). The filter only accepts the
    // values declared in the controller and sorting only its two columns.
    'tasks.search'             => 'Search',
    'tasks.search_placeholder' => 'Search by title',
    'tasks.filter_status'      => 'Status',
    'tasks.filter_all'         => 'All statuses',
    'tasks.sort_label'         => 'Sort by',
    'tasks.sort_title'         => 'Title',
    'tasks.sort_created'       => 'Created',
];
