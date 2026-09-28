<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'TESTS: Academic Persons Edit Write Listener',
    'description' => 'Extension with a configurable listener of the profile editing write event for tests',
    'version' => '3.0.0',
    'category' => 'misc',
    'state' => 'beta',
    'author' => 'Stefan Bürk',
    'author_email' => 'hello@fgtclb.com',
    'author_company' => 'FGTCLB GmbH',
    'constraints' => [
        'depends' => [
            'typo3' => '13.4.0-14.3.99',
            'academic_persons_edit' => '3.0.0',
        ],
    ],
];
