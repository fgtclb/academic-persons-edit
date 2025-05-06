<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'FGTCLB: Academic Persons Edit',
    'description' => 'dds the option to assign frontend users to academic persons and allow editing the profiles in frontend.',
    'category' => 'plugin',
    'author' => 'Tim Schreiner',
    'author_email' => 'tim.schreiner@km2.de',
    'author_company' => 'FGTCLB',
    'state' => 'beta',
    'version' => '2.0.2',
    'clearCacheOnLoad' => true,
    'constraints' => [
        'depends' => [
            'typo3' => '12.4.0-13.4.99',
            'academic_persons' => '2.0.2',
        ],
        'conflicts' => [
        ],
        'suggests' => [
        ],
    ],
];
