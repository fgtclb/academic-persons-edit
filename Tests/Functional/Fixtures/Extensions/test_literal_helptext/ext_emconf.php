<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'TESTS: Academic Persons literal help texts',
    'description' => 'Literal help texts for two fields of the contracts section, for the label tests of the profile editor',
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
