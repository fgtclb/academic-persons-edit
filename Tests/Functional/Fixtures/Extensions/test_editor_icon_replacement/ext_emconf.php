<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'TESTS: Academic Persons Edit editor icon replacement',
    'description' => 'A site package that replaces profile editing icons, one in the right file and one in the wrong one, for tests',
    'version' => '3.0.0',
    'category' => 'misc',
    'state' => 'beta',
    'author' => 'FGTCLB GmbH',
    'author_email' => 'hello@fgtclb.com',
    'author_company' => 'FGTCLB GmbH',
    'constraints' => [
        'depends' => [
            'typo3' => '13.4.35-14.3.99',
            'core' => '13.4.35-14.3.99',
            'academic_persons_edit' => '3.0.0',
        ],
    ],
];
