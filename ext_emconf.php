<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'FGTCLB: Academic Persons Edit',
    'description' => 'dds the option to assign frontend users to academic persons and allow editing the profiles in frontend.',
    'category' => 'plugin',
    'author' => 'Tim Schreiner',
    'author_email' => 'tim.schreiner@km2.de',
    'author_company' => 'FGTCLB',
    'state' => 'beta',
    'version' => '0.2.0',
    'clearCacheOnLoad' => true,
    'constraints' => [
        'depends' => [
            'typo3' => '11.5.0-11.5.99',
            // @todo Change this to '1.0.0-1.99.99' after academic_persons has been released.
            //       TYPO3 does not support dev-constraints like `2.*.*` as composer.
            'academic_persons' => '0.2.0 - 1.99.99',
        ],
        'conflicts' => [
        ],
        'suggests' => [
        ],
    ],
];
