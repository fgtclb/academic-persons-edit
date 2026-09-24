<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'TESTS: Academic Persons Profile Update Recorder',
    'description' => 'Extension recording the profile update announcements for tests',
    'version' => '3.0.0',
    'category' => 'misc',
    'state' => 'beta',
    'author' => 'Stefan Bürk',
    'author_email' => 'hello@fgtclb.com',
    'author_company' => 'FGTCLB GmbH',
    'constraints' => [
        'depends' => [
            'typo3' => '13.4.0-14.3.99',
            'academic_persons' => '3.0.0',
        ],
    ],
];
