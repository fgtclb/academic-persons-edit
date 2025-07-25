<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'FGTCLB: Academic Persons Edit',
    'description' => 'dds the option to assign frontend users to academic persons and allow editing the profiles in frontend.',
    'category' => 'plugin',
    'author' => 'Tim Schreiner',
    'author_email' => 'tim.schreiner@km2.de',
    'author_company' => 'FGTCLB',
    'state' => 'beta',
    'version' => '1.2.0',
    'clearCacheOnLoad' => true,
    'constraints' => [
        'depends' => [
            'typo3' => '11.5.0-12.4.99',
            'academic_persons' => '1.2.0',
        ],
        'conflicts' => [
        ],
        'suggests' => [
        ],
    ],
];
