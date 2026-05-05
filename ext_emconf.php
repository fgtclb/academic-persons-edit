<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'FGTCLB: Academic Persons Edit',
    'description' => 'Provides the option to assign frontend users to academic persons and allow editing the profiles in frontend.',
    'version' => '2.3.2',
    'category' => 'plugin',
    'state' => 'beta',
    'author' => 'FGTCLB',
    'author_email' => 'hello@fgtclb.com',
    'author_company' => 'FGTCLB GmbH',
    'constraints' => [
        'depends' => [
            'typo3' => '12.4.22-13.4.99',
            'install' => '12.4.22-13.4.99',
            'academic_base' => '2.3.2',
            'academic_persons' => '2.3.2',
        ],
    ],
];
