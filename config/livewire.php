<?php

$defaults = require __DIR__.'/../vendor/livewire/livewire/config/livewire.php';

return array_replace_recursive($defaults, [
    /*
    |---------------------------------------------------------------------------
    | Payload Guards
    |---------------------------------------------------------------------------
    |
    | RepoView sends a serialized repository snapshot with each Livewire
    | interaction. Keep PHP's request limit above this application guard while
    | the component applies bounded history and diff data of its own.
    |
    */
    'payload' => [
        'max_size' => 8 * 1024 * 1024,
        'max_nesting_depth' => 10,
        'max_calls' => 50,
        'max_components' => 200,
    ],
]);
