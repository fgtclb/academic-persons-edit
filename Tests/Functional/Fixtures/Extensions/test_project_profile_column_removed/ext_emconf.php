<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'TESTS: Academic Persons Edit project column removed',
    'description' => 'Removes a project column from the TCA after the persons settings are applied',
    'version' => '3.0.0',
    'category' => 'plugin',
    'state' => 'beta',
    'author' => 'FGTCLB GmbH',
    'author_email' => 'hello@fgtclb.com',
    'author_company' => 'FGTCLB GmbH',
    'constraints' => [
        'depends' => [
            'typo3' => '13.4.0-14.3.99',
            'test_project_profile_fields' => '3.0.0',
            'academic_persons' => '3.0.0',
        ],
    ],
];
