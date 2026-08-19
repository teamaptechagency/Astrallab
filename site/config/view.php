<?php

return [

    'paths' => [
        resource_path('views'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Where compiled Blade goes
    |--------------------------------------------------------------------------
    |
    | Normally storage/framework/views, which is fine anywhere the application
    | owns its own disk.
    |
    | It is not fine on a read-only deployment. Blade compiles a template the
    | first time it is rendered and writes the result here, so a read-only
    | storage/ turns the first request for any page into a 500. There, this
    | points at /tmp — the one writable place such platforms give you, lasting
    | one invocation — and each cold start recompiles what it needs.
    |
    | See DEPLOY-VERCEL.md.
    |
    */
    'compiled' => env(
        'VIEW_COMPILED_PATH',
        realpath(storage_path('framework/views')) ?: storage_path('framework/views'),
    ),

];
