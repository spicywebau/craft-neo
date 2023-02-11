<?php

return [
    [
        'id' => null,
        'sectionId' => '1',
        'typeId' => '1',
        'authorId' => '1',
        'uid' => 'entry-000000000000000000000000000001',
        'title' => 'Block Children Test',
        'slug' => 'block-children-test',
        'neoField1' => [
            'new1' => [
                'level' => 1,
                'type' => 'plainText',
                'enabled' => true,
                'fields' => [
                    'plainTextField' => '1',
                ],
            ],
            'new2' => [
                'level' => 2,
                'type' => 'plainText',
                'enabled' => true,
                'fields' => [
                    'plainTextField' => '2',
                ],
            ],
            'new3' => [
                'level' => 2,
                'type' => 'plainText',
                'enabled' => true,
                'fields' => [
                    'plainTextField' => '2',
                ],
            ],
            'new4' => [
                'level' => 3,
                'type' => 'plainText',
                'enabled' => true,
                'fields' => [
                    'plainTextField' => '3',
                ],
            ],
            'new5' => [
                'level' => 1,
                'type' => 'plainText',
                'enabled' => true,
                'fields' => [
                    'plainTextField' => '1',
                ],
            ],
            'new6' => [
                'level' => 2,
                'type' => 'plainText',
                'enabled' => false,
                'fields' => [
                    'plainTextField' => '2',
                ],
            ],
            'new7' => [
                'level' => 3,
                'type' => 'plainText',
                'enabled' => true,
                'collapsed' => false,
                'fields' => [
                    'plainTextField' => '3',
                ],
            ],
            'new8' => [
                'level' => 2,
                'type' => 'plainText',
                'enabled' => true,
                'collapsed' => false,
                'fields' => [
                    'plainTextField' => '2',
                ],
            ],
        ],
    ],
    [
        'id' => null,
        'sectionId' => '1',
        'typeId' => '1',
        'authorId' => '1',
        'uid' => 'entry-000000000000000000000000000002',
        'title' => 'Block query for ID gets only live block test',
        'slug' => 'block-query-for-id-gets-only-live-block-test',
        'neoField1' => [
            'new1' => [
                'level' => 1,
                'type' => 'plainText',
                'enabled' => true,
                'fields' => [
                    'plainTextField' => '1',
                ],
            ],
            'new2' => [
                'level' => 2,
                'type' => 'plainText',
                'enabled' => false,
                'fields' => [
                    'plainTextField' => '2',
                ],
            ],
            'new3' => [
                'level' => 2,
                'type' => 'plainText',
                'enabled' => true,
                'fields' => [
                    'plainTextField' => '3',
                ],
            ],
        ]
    ]
];
