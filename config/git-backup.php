<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Git repository
    |--------------------------------------------------------------------------
    |
    | Define the target directory including the Git repository here.
    |
    */
    'directory' => storage_path('app'),

    /*
    |--------------------------------------------------------------------------
    | Storage driver
    |--------------------------------------------------------------------------
    |
    | This package use Laravel Filesystem On-Demand Disks
    | See also: https://laravel.com/docs/9.x/filesystem#on-demand-disks
    | Define her the storage driver. Default: `local`
    |
    */
    'storage-driver' => 'local',

    /*
    |--------------------------------------------------------------------------
    | Git command
    |--------------------------------------------------------------------------
    |
    | This command is executed in the repository directory.
    | Use \Carbon\Carbon method between `{}`
    |
    */
    'command' => 'git add -A -f && git commit -m "{toDateTimeString}" && git push',

    /*
    |--------------------------------------------------------------------------
    | Database backup
    |--------------------------------------------------------------------------
    */
    'database' => [
        'connections' => [
            'mysql',
        ],
        // Excluding tables from the dump
        'exclude-tables' => [
            'mysql' => [],
        ],
        // Dump specific tables
        'include-tables' => [
            'mysql' => [],
        ],
        /*
        |--------------------------------------------------------------------------
        | Subdirectory for database backups
        |--------------------------------------------------------------------------
        */
        'directory' => 'database',
        /*
        |--------------------------------------------------------------------------
        | Filename include path (optional)
        |--------------------------------------------------------------------------
        |
        | * Use \Carbon\Carbon method between `{}`
        | * Or {database}, {username}, {driver}, {host} for database config
        | * Or {date-Y}, {date-Y-m-d} etc for date formatted
        | * Without file extension
        |
        */
        'filenames' => [
            'mysql' => '{driver}/{database}-{toDateTimeString}',
        ],
        /*
        |--------------------------------------------------------------------------
        | Optional archive encryption
        |--------------------------------------------------------------------------
        */
        'encryption' => [
            'algorithm' => 'default', # When set to 'default', we'll use ZipArchive::EM_AES_256 if it is
        ],
        'passwords' => [
            'mysql' => null,
        ]
    ],
];
