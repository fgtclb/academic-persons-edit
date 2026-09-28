<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'TESTS: Academic Persons Edit project fields',
    'description' => 'Project columns of the profile, editable in the profile editor',
    'version' => '3.0.0',
    'category' => 'plugin',
    'state' => 'beta',
    'author' => 'FGTCLB GmbH',
    'author_email' => 'hello@fgtclb.com',
    'author_company' => 'FGTCLB GmbH',
    'constraints' => [
        'depends' => [
            'typo3' => '13.4.0-14.3.99',
            'academic_persons' => '3.0.0',
        ],
    ],
];
