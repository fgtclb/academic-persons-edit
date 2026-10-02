<?php

declare(strict_types=1);

/*
 * This file is part of the "academic_persons_edit" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace FGTCLB\AcademicPersonsEdit\Tests\Functional\Import;

use FGTCLB\AcademicPersons\Import\ImportedContract;
use FGTCLB\AcademicPersons\Import\ImportedProfile;
use FGTCLB\AcademicPersons\Import\ProfileImportWriter;
use FGTCLB\AcademicPersonsEdit\Tests\Functional\AbstractAcademicPersonsEditTestCase;
use PHPUnit\Framework\Attributes\Test;
use SBUERK\TYPO3\Testing\SiteHandling\SiteBasedTestTrait;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * An import run from the command line: no request, no backend user. The
 * writer announces the person as an import, and the listener of this extension
 * synchronises the translations from it. The fixture extension
 * `test_managed_fields_editor` manages the website, a column all languages
 * share.
 */
final class ProfileImportWriterTranslationTest extends AbstractAcademicPersonsEditTestCase
{
    use SiteBasedTestTrait;

    private const PROFILE_TABLE = 'tx_academicpersons_domain_model_profile';

    protected const LANGUAGE_PRESETS = [
        'EN' => ['id' => 0, 'title' => 'English', 'locale' => 'en_US.UTF8', 'iso' => 'en', 'hrefLang' => 'en-US', 'direction' => ''],
        'DE' => ['id' => 1, 'title' => 'Deutsch', 'locale' => 'de_DE.UTF8', 'iso' => 'de', 'hrefLang' => 'de-DE', 'direction' => ''],
    ];

    protected function setUp(): void
    {
        $this->configurationToUseInTestInstance = [
            'EXTENSIONS' => [
                'academic_persons_edit' => [
                    'profile' => [
                        'allowedLanguages' => '1',
                    ],
                ],
            ],
        ];
        $this->testExtensionsToLoad[] = 'tests/test-managed-fields-editor';
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/importedProfile.csv');
        $this->writeSiteConfiguration(
            identifier: 'main',
            site: $this->buildSiteConfiguration(rootPageId: 1, base: 'https://www.acme.com/'),
            languages: [
                $this->buildDefaultLanguageConfiguration(identifier: 'EN', base: '/'),
                $this->buildLanguageConfiguration(identifier: 'DE', base: '/de/'),
            ],
        );
        unset($GLOBALS['TYPO3_REQUEST'], $GLOBALS['BE_USER']);
    }

    protected function tearDown(): void
    {
        GeneralUtility::rmdir($this->instancePath . '/typo3conf/sites', true);
        parent::tearDown();
    }

    /**
     * The core carries the website into the existing translation within the
     * write itself. The contract the import adds reaches the translation only
     * through the synchronisation the announcement starts.
     */
    #[Test]
    public function theTranslationFollowsAChangedPerson(): void
    {
        $result = $this->get(ProfileImportWriter::class)->write(new ImportedProfile(
            identifier: 'his:4711',
            pid: 2,
            fields: ['website' => 'https://new.example.org'],
            contracts: [new ImportedContract(identifier: 'his:4711-1', fields: ['position' => 'Professor'])],
        ));

        $this->assertSame([], $result->errors);
        $this->assertSame('https://new.example.org', $this->fetchProfile(1, 0)['website'] ?? null);
        $this->assertSame('https://new.example.org', $this->fetchProfile(1, 1)['website'] ?? null);
        $contractUid = (int)$result->getRecord('tx_academicpersons_domain_model_contract', 'his:4711-1')?->uid;
        $this->assertSame(
            [[2, 'Professor']],
            $this->fetchContractTranslations($contractUid),
            'The new contract is localized into the translation of the profile.',
        );
        $this->assertArrayNotHasKey('BE_USER', $GLOBALS, 'The writer leaves no backend user behind.');
    }

    #[Test]
    public function aNewPersonIsTranslated(): void
    {
        $result = $this->get(ProfileImportWriter::class)->write(new ImportedProfile(
            identifier: 'his:9000',
            pid: 2,
            fields: ['first_name' => 'Ada', 'last_name' => 'Lovelace'],
        ));

        $profileUid = (int)$result->getRecord(self::PROFILE_TABLE, 'his:9000')?->uid;
        $this->assertSame('Lovelace', $this->fetchProfile($profileUid, 1)['last_name'] ?? null);
    }

    /**
     * @return array<string, mixed>|null the default-language profile, or its translation
     */
    private function fetchProfile(int $profileUid, int $languageUid): ?array
    {
        $queryBuilder = $this->getConnectionPool()->getQueryBuilderForTable(self::PROFILE_TABLE);
        $queryBuilder->getRestrictions()->removeAll()->add(new DeletedRestriction());
        $row = $queryBuilder
            ->select('*')
            ->from(self::PROFILE_TABLE)
            ->where(
                $queryBuilder->expr()->eq(
                    $languageUid === 0 ? 'uid' : 'l10n_parent',
                    $queryBuilder->createNamedParameter($profileUid, Connection::PARAM_INT),
                ),
                $queryBuilder->expr()->eq('sys_language_uid', $queryBuilder->createNamedParameter($languageUid, Connection::PARAM_INT)),
            )
            ->executeQuery()
            ->fetchAssociative();
        return $row === false ? null : $row;
    }

    /**
     * @return list<array{int, string}> the profile and the position of each translation of the contract
     */
    private function fetchContractTranslations(int $contractUid): array
    {
        $queryBuilder = $this->getConnectionPool()->getQueryBuilderForTable('tx_academicpersons_domain_model_contract');
        $queryBuilder->getRestrictions()->removeAll()->add(new DeletedRestriction());
        $rows = $queryBuilder
            ->select('profile', 'position')
            ->from('tx_academicpersons_domain_model_contract')
            ->where(
                $queryBuilder->expr()->eq('l10n_parent', $queryBuilder->createNamedParameter($contractUid, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('sys_language_uid', $queryBuilder->createNamedParameter(1, Connection::PARAM_INT)),
            )
            ->orderBy('uid')
            ->executeQuery()
            ->fetchAllAssociative();
        return array_map(static fn(array $row): array => [(int)$row['profile'], (string)$row['position']], $rows);
    }
}
