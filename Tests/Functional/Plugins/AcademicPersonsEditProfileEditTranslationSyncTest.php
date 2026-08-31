<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Tests\Functional\Plugins;

use PHPUnit\Framework\Attributes\Test;

/**
 * Drives a profile edit through a real frontend request of the
 * `academicpersonsedit_profileediting` plugin and asserts the edit triggers the
 * translation synchronisation (ACE-485): with `profile.allowedLanguages = '1'`,
 * `ProfileController::updateAction()` persists the change and dispatches
 * `AfterProfileUpdateEvent` through
 * `AbstractActionController::persistAndDispatchProfileUpdate()`, the
 * `SyncChangesToTranslations` listener runs, and the `RecordSynchronizer` of
 * `EXT:academic_persons` creates the translated profile row.
 *
 * One coarse assertion on the translated row is deliberate: what a synchronisation
 * writes in depth (inline children, `l10n_mode=exclude` columns, file references) is
 * covered by the synchroniser's own functional tests in `EXT:academic_persons`, and
 * the listener behaviour by the `EventListener/SyncChangesToTranslations*` tests.
 * This test pins the piece neither of them can: the dispatch on the frontend editing
 * persistence path, observed end to end through the value it produces.
 */
final class AcademicPersonsEditProfileEditTranslationSyncTest extends AbstractProfileEditingPluginTestCase
{
    protected const LANGUAGE_PRESETS = [
        'EN' => ['id' => 0, 'title' => 'English', 'locale' => 'en_US.UTF8', 'iso' => 'en', 'hrefLang' => 'en-US', 'direction' => ''],
        'DE' => ['id' => 1, 'title' => 'Deutsch', 'locale' => 'de_DE.UTF8', 'iso' => 'de', 'hrefLang' => 'de-DE', 'direction' => ''],
    ];

    /**
     * Allows translating profiles into site language 1 - the gate the listener reads
     * through `ProfileTranslator::getAllowedLanguageIds()`.
     *
     * @param array<string, mixed> $additionalConfiguration
     * @return array<string, mixed>
     */
    protected function frontendPluginTestConfiguration(array $additionalConfiguration = []): array
    {
        return parent::frontendPluginTestConfiguration(array_replace_recursive([
            'EXTENSIONS' => [
                'academic_persons_edit' => [
                    'profile' => [
                        'allowedLanguages' => '1',
                    ],
                ],
            ],
        ], $additionalConfiguration));
    }

    /**
     * @return list<array<string, int|string>>
     */
    private function getProfileRows(): array
    {
        $rows = $this->getConnectionPool()
            ->getConnectionForTable('tx_academicpersons_domain_model_profile')
            ->executeQuery(
                'SELECT uid, pid, sys_language_uid, l10n_parent, l10n_source, first_name, last_name, website'
                . ' FROM tx_academicpersons_domain_model_profile WHERE deleted = 0 ORDER BY uid',
            )
            ->fetchAllAssociative();
        return array_values($rows);
    }

    #[Test]
    public function profileEditThroughThePluginCreatesTheConfiguredTranslation(): void
    {
        $this->setUpTestCase();
        // The site the shared harness writes carries only the default language; the
        // synchroniser resolves its target languages against the site of the request,
        // so language 1 has to exist there. Rewriting the `acme` site is enough - the
        // configuration is read per request.
        $this->writeFrontendPluginTestSite([
            $this->buildDefaultLanguageConfiguration(identifier: 'EN', base: '/'),
            $this->buildLanguageConfiguration(identifier: 'DE', base: '/de/'),
        ]);

        $this->assertCount(1, $this->getProfileRows(), 'Precondition: only the default language profile exists.');

        $this->submitProfileForm($this->getProfileEditFormUrl(), [
            'website' => 'https://submitted.example.org',
        ]);

        $rows = $this->getProfileRows();
        $this->assertCount(2, $rows, 'The frontend edit creates exactly one translated profile row.');
        $this->assertSame('https://submitted.example.org', (string)$rows[0]['website'], 'The edit itself was persisted.');
        $translatedRow = $rows[1];
        $this->assertSame(1, (int)$translatedRow['sys_language_uid']);
        $this->assertSame(self::PROFILE_ID, (int)$translatedRow['l10n_parent']);
        $this->assertSame(self::PROFILE_ID, (int)$translatedRow['l10n_source']);
        $this->assertSame(self::PROFILE_PAGE_ID, (int)$translatedRow['pid']);
        $this->assertSame('Max', (string)$translatedRow['first_name']);
    }
}
