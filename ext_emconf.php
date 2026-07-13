<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'FGTCLB: Academic Persons Edit',
    'description' => 'Provides the option to assign frontend users to academic persons and allow editing the profiles in frontend.',
    'version' => '3.0.0',
    'category' => 'plugin',
    'state' => 'beta',
    'author' => 'FGTCLB',
    'author_email' => 'hello@fgtclb.com',
    'author_company' => 'FGTCLB GmbH',
    'constraints' => [
        'depends' => [
            'typo3' => '13.4.0-13.4.99',
            'install' => '13.4.0-13.4.99',
            'academic_base' => '3.0.0',
            'academic_persons' => '3.0.0',
        ],
    ],
];
