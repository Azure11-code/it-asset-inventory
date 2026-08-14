<?php

/**
 * Permission catalog. Each entry declares a resource (page/area) and the
 * actions available on it. Admin users bypass this catalog and get everything;
 * non-admin users must be granted each (resource, action) pair explicitly.
 *
 * Adding a new resource or action here also adds it to the user permission
 * matrix UI automatically.
 */
return [
    'action_labels' => [
        'view'   => 'View',
        'create' => 'Create',
        'edit'   => 'Edit',
        'delete' => 'Delete',
        'print'  => 'Print',
        'export' => 'Export',
        'import' => 'Import',
    ],

    'resources' => [
        ['key' => 'assets',           'label' => 'Assets',            'actions' => ['view', 'create', 'edit', 'delete', 'print', 'export', 'import']],
        ['key' => 'scan',             'label' => 'Scan',              'actions' => ['view']],
        ['key' => 'employees',        'label' => 'Employees',         'actions' => ['view', 'create', 'edit', 'delete', 'export', 'import']],
        ['key' => 'accountability',   'label' => 'Accountability',    'actions' => ['view', 'edit', 'print']],
        ['key' => 'permits',          'label' => 'Permits',           'actions' => ['view', 'create', 'edit', 'delete', 'print']],
        ['key' => 'incidents',        'label' => 'Incident Reports',  'actions' => ['view', 'create', 'edit', 'delete', 'print']],
        ['key' => 'recommendations',  'label' => 'Recommendations',   'actions' => ['view', 'create', 'edit', 'delete', 'print']],
        ['key' => 'signatories',      'label' => 'Signatories',       'actions' => ['view', 'create', 'edit', 'delete']],
        ['key' => 'asset_code_rules', 'label' => 'Asset Code Rules',  'actions' => ['view', 'create', 'edit', 'delete']],
        ['key' => 'brands',           'label' => 'Brands',            'actions' => ['view', 'create', 'edit', 'delete']],
        ['key' => 'categories',       'label' => 'Categories',        'actions' => ['view', 'create', 'edit', 'delete']],
        ['key' => 'conditions',       'label' => 'Conditions',        'actions' => ['view', 'create', 'edit', 'delete']],
        ['key' => 'departments',      'label' => 'Departments',       'actions' => ['view', 'create', 'edit', 'delete']],
        ['key' => 'locations',        'label' => 'Locations',         'actions' => ['view', 'create', 'edit', 'delete']],
    ],
];
