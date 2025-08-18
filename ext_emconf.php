<?php

$EM_CONF[$_EXTKEY] = [
    'author' => 'FGTCLB',
    'author_company' => 'FGTCLB GmbH',
    'author_email' => 'hello@fgtclb.com',
    'category' => 'plugin',
    'constraints' => [
        'depends' => [
            'typo3' => '12.4.22-13.4.99',
            'install' => '12.4.22-13.4.99',
            'academic_base' => '2.0.2',
            'academic_persons' => '2.0.2',
        ],
        'conflicts' => [
        ],
        'suggests' => [
        ],
    ],
    'description' => 'Provides the option to assign frontend users to academic persons and allow editing the profiles in frontend.',
    'state' => 'beta',
    'title' => 'FGTCLB: Academic Persons Edit',
    'version' => '2.0.2',
];
