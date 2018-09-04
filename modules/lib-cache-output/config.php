<?php

return [
    '__name' => 'lib-cache-output',
    '__version' => '0.0.1',
    '__git' => 'git@github.com:getmim/lib-cache-output.git',
    '__license' => 'MIT',
    '__author' => [
        'name' => 'Iqbal Fauzi',
        'email' => 'iqbalfawz@gmail.com',
        'website' => 'http://iqbalfn.com/'
    ],
    '__files' => [
        'modules/lib-cache-output' => ['install','update','remove'],
        'etc/cache/output' => ['install','remove']
    ],
    '__dependencies' => [
        'required' => [],
        'optional' => [
            [
                'lib-compress' => NULL
            ]
        ]
    ],
    '__gitignore' => [],
    'autoload' => [
        'classes' => [
            'LibCacheOutput\\Library' => [
                'type' => 'file',
                'base' => 'modules/lib-cache-output/library'
            ]
        ],
        'files' => []
    ],
    'callback' => [
        'core' => [
            'ready' => [
                'LibCacheOutput\\Library\\Callback::coreReady' => true
            ],
            'printing' => [
                'LibCacheOutput\\Library\\Callback::corePrinting' => true
            ]
        ]
    ],
    'libCacheOutput' => [
        'query' => [
            'page' => 1
        ]
    ]
];