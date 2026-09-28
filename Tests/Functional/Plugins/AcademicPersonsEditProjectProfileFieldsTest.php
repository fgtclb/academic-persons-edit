<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Tests\Functional\Plugins;

use FGTCLB\AcademicPersons\Service\DataHandlerExecutionContext;
use FGTCLB\AcademicPersonsEdit\Event\BeforeProfileEditingWriteEvent;
use FGTCLB\AcademicPersonsEdit\Profile\ProfileTranslator;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ResponseInterface;
use TESTS\TestEditorWriteListener\EventListener\EditorWriteListener;
use TESTS\TestProjectProfileFields\EventListener\RecordNamePrefixOnUpdate;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Http\Stream;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

/**
 * Project fields in the profile editor. The fixture extension
 * `test_project_profile_fields` adds columns to the profile table and declares
 * them as project fields: `namePrefix` (`tx_test_prefix`), a required text,
 * `guestLecturer` (`tx_test_guest`), a checkbox, `lockedCode` (`tx_test_code`),
 * locked with `readonly`, `syncedCode` (`tx_test_synced`), a managed field,
 * `noteText` (`tx_test_note`), rich text, `sharedCode` (`tx_test_shared`), a
 * column with `l10n_mode: exclude`, `ownCode` (`tx_test_own`), a column with
 * `allowLanguageSynchronization`, `contactMail` (`tx_test_mail`), an e-mail
 * column, and `webLink` (`tx_test_web`), a link column taking web addresses.
 * The write listener of `test_editor_write_listener` is loaded to replace a
 * value.
 */
final class AcademicPersonsEditProjectProfileFieldsTest extends AbstractFrontendProfilePluginTestCase
{
    private const TABLE = 'tx_academicpersons_domain_model_profile';

    protected const LANGUAGE_PRESETS = [
        'EN' => ['id' => 0, 'title' => 'English', 'locale' => 'en_US.UTF8', 'iso' => 'en', 'hrefLang' => 'en-US', 'direction' => ''],
        'DE' => ['id' => 1, 'title' => 'Deutsch', 'locale' => 'de_DE.UTF8', 'iso' => 'de', 'hrefLang' => 'de-DE', 'direction' => ''],
    ];

    protected function setUp(): void
    {
        $this->addTestExtensionsToLoad('tests/test-project-profile-fields');
        $this->addTestExtensionsToLoad('tests/test-editor-write-listener');
        parent::setUp();
        EditorWriteListener::reset();
        RecordNamePrefixOnUpdate::$announcements = [];
    }

    protected function tearDown(): void
    {
        EditorWriteListener::reset();
        RecordNamePrefixOnUpdate::$announcements = [];
        parent::tearDown();
    }

    #[Test]
    public function theEditorShowsAProjectFieldWithItsStoredValue(): void
    {
        $this->setUpProfileEditingTestCase();
        $this->updateProfileRow(self::PROFILE_ID, ['tx_test_prefix' => 'von', 'tx_test_guest' => 1]);

        $content = $this->withoutProfileEditingPrototypes($this->renderProfileEditingPage());

        $prefix = $this->control($content, 'input', 'profile-editing-1-namePrefix');
        $this->assertStringContainsString('name="namePrefix"', $prefix);
        $this->assertStringContainsString('value="von"', $prefix);
        $this->assertStringContainsString('required="required"', $prefix);
        $guest = $this->control($content, 'input', 'profile-editing-1-guestLecturer');
        $this->assertStringContainsString('type="checkbox"', $guest);
        $this->assertMatchesRegularExpression('@\\schecked[\\s>=]@', $guest);
        $this->assertStringContainsString('data-pe-checked-label="Yes"', $guest);
        $this->assertMatchesRegularExpression(
            '@data-pe-field-preview\s+data-pe-for="profile-editing-1-guestLecturer".*?data-pe-field-preview-content>\s*Yes\s*</div>@s',
            $content,
        );
    }

    #[Test]
    public function aSubmittedProjectFieldIsStoredInItsColumn(): void
    {
        $this->setUpProfileEditingTestCase();
        $content = $this->renderProfileEditingPage();

        $response = $this->postJson($this->extractUrl($content, 'data-update-url'), [
            'profile' => self::PROFILE_ID,
            'data' => ['namePrefix' => 'van der', 'guestLecturer' => true, 'title' => 'Dr.'],
        ]);

        $this->assertSame(200, $response->getStatusCode(), (string)$response->getBody());
        $this->assertSame(
            ['namePrefix' => 'van der', 'guestLecturer' => true, 'title' => 'Dr.'],
            $this->decode($response)['data'],
        );
        $this->assertSame(
            ['tx_test_prefix' => 'van der', 'tx_test_guest' => 1, 'title' => 'Dr.'],
            $this->profileRow(self::PROFILE_ID, ['tx_test_prefix', 'tx_test_guest', 'title']),
        );
        $this->assertGreaterThan(0, $this->historyEntries(self::PROFILE_ID));
    }

    #[Test]
    public function anInvalidProjectFieldValueStoresNothing(): void
    {
        $this->setUpProfileEditingTestCase();
        $this->updateProfileRow(self::PROFILE_ID, ['tx_test_prefix' => 'von']);
        $content = $this->renderProfileEditingPage();

        $response = $this->postJson($this->extractUrl($content, 'data-update-url'), [
            'profile' => self::PROFILE_ID,
            'data' => ['namePrefix' => '', 'title' => 'Dr.'],
        ]);

        $this->assertSame(422, $response->getStatusCode(), (string)$response->getBody());
        $body = $this->decode($response);
        $this->assertSame('validation_failed', $body['error']);
        $this->assertArrayHasKey('namePrefix', $body['errors']);
        $this->assertSame(
            ['tx_test_prefix' => 'von', 'title' => ''],
            $this->profileRow(self::PROFILE_ID, ['tx_test_prefix', 'title']),
        );
        $this->assertSame(0, $this->historyEntries(self::PROFILE_ID));
    }

    #[Test]
    public function aCheckboxProjectFieldTakesABooleanOnly(): void
    {
        $this->setUpProfileEditingTestCase();
        $content = $this->renderProfileEditingPage();

        $response = $this->postJson($this->extractUrl($content, 'data-update-url'), [
            'profile' => self::PROFILE_ID,
            'data' => ['guestLecturer' => 'yes'],
        ]);

        $this->assertSame(422, $response->getStatusCode(), (string)$response->getBody());
        $body = $this->decode($response);
        $this->assertSame('invalid_profile_data', $body['error']);
        $this->assertSame('Invalid boolean value for profile property "guestLecturer".', $body['message']);
        $this->assertSame(['tx_test_guest' => 0], $this->profileRow(self::PROFILE_ID, ['tx_test_guest']));
    }

    /**
     * The update is announced once, and the listeners of the announcement read the
     * stored project field: it is written before the announcement.
     */
    #[Test]
    public function theUpdateIsAnnouncedOnceWithTheStoredProjectField(): void
    {
        $this->setUpProfileEditingTestCase();
        $content = $this->renderProfileEditingPage();

        $response = $this->postJson($this->extractUrl($content, 'data-update-url'), [
            'profile' => self::PROFILE_ID,
            'data' => ['namePrefix' => 'von'],
        ]);

        $this->assertSame(200, $response->getStatusCode(), (string)$response->getBody());
        $this->assertSame(
            [['profile' => self::PROFILE_ID, 'origin' => 'frontend-editing', 'namePrefix' => 'von']],
            RecordNamePrefixOnUpdate::$announcements,
        );
    }

    /**
     * The DataHandler applies the TCA of the column, here its `max` of 30, and the
     * answer carries what it stored.
     */
    #[Test]
    public function theAnswerCarriesTheStoredValue(): void
    {
        $this->setUpProfileEditingTestCase();
        $content = $this->renderProfileEditingPage();

        $response = $this->postJson($this->extractUrl($content, 'data-update-url'), [
            'profile' => self::PROFILE_ID,
            'data' => ['namePrefix' => str_repeat('a', 35)],
        ]);

        $this->assertSame(200, $response->getStatusCode(), (string)$response->getBody());
        $this->assertSame(['namePrefix' => str_repeat('a', 30)], $this->decode($response)['data']);
        $this->assertSame(['tx_test_prefix' => str_repeat('a', 30)], $this->profileRow(self::PROFILE_ID, ['tx_test_prefix']));
    }

    #[Test]
    public function aLockedProjectFieldKeepsItsValue(): void
    {
        $this->setUpProfileEditingTestCase();
        $this->updateProfileRow(self::PROFILE_ID, ['tx_test_code' => 'kept']);
        $content = $this->renderProfileEditingPage();
        $this->assertMatchesRegularExpression(
            '@\\sreadonly[\\s>=]@',
            $this->control($this->withoutProfileEditingPrototypes($content), 'input', 'profile-editing-1-lockedCode'),
        );

        $response = $this->postJson($this->extractUrl($content, 'data-update-url'), [
            'profile' => self::PROFILE_ID,
            'data' => ['lockedCode' => 'changed', 'title' => 'Dr.'],
        ]);

        $this->assertSame(200, $response->getStatusCode(), (string)$response->getBody());
        $this->assertSame(['title' => 'Dr.'], $this->decode($response)['data']);
        $this->assertSame(
            ['tx_test_code' => 'kept', 'title' => 'Dr.'],
            $this->profileRow(self::PROFILE_ID, ['tx_test_code', 'title']),
        );
    }

    /**
     * The fixture manages `syncedCode`, and the profile carries an import identifier.
     */
    #[Test]
    public function aManagedProjectFieldKeepsItsValue(): void
    {
        $this->setUpProfileEditingTestCase();
        $this->updateProfileRow(self::PROFILE_ID, ['import_identifier' => 'fe_users:1', 'skip_sync' => 0, 'tx_test_synced' => 'synced']);
        $content = $this->renderProfileEditingPage();
        $this->assertMatchesRegularExpression('@data-pe-field-managed\s+data-pe-for="profile-editing-1-syncedCode"@', $content);

        $response = $this->postJson($this->extractUrl($content, 'data-update-url'), [
            'profile' => self::PROFILE_ID,
            'data' => ['syncedCode' => 'mine'],
        ]);

        $this->assertSame(200, $response->getStatusCode(), (string)$response->getBody());
        $this->assertSame([], $this->decode($response)['data']);
        $this->assertSame(['tx_test_synced' => 'synced'], $this->profileRow(self::PROFILE_ID, ['tx_test_synced']));
    }

    #[Test]
    public function aRichTextProjectFieldIsSanitised(): void
    {
        $this->setUpProfileEditingTestCase();
        $content = $this->renderProfileEditingPage();

        $response = $this->postJson($this->extractUrl($content, 'data-update-url'), [
            'profile' => self::PROFILE_ID,
            'data' => ['noteText' => '<p>Office hours</p><script>alert(1)</script>'],
        ]);

        $this->assertSame(200, $response->getStatusCode(), (string)$response->getBody());
        $stored = (string)$this->profileRow(self::PROFILE_ID, ['tx_test_note'])['tx_test_note'];
        $this->assertStringContainsString('<p>Office hours</p>', $stored);
        $this->assertStringNotContainsString('<script', $stored);
    }

    /**
     * An e-mail column takes an invalid address from nobody: the validator the
     * column needs refuses it before anything of the request is stored.
     */
    #[Test]
    public function anInvalidEmailOfAProjectFieldStoresNothing(): void
    {
        $this->setUpProfileEditingTestCase();
        $this->updateProfileRow(self::PROFILE_ID, ['tx_test_mail' => 'office@example.org']);
        $content = $this->renderProfileEditingPage();

        $response = $this->postJson($this->extractUrl($content, 'data-update-url'), [
            'profile' => self::PROFILE_ID,
            'data' => ['contactMail' => 'no address', 'title' => 'Dr.'],
        ]);

        $this->assertSame(422, $response->getStatusCode(), (string)$response->getBody());
        $this->assertArrayHasKey('contactMail', $this->decode($response)['errors']);
        $this->assertSame(
            ['tx_test_mail' => 'office@example.org', 'title' => ''],
            $this->profileRow(self::PROFILE_ID, ['tx_test_mail', 'title']),
        );
    }

    /**
     * The URL validator accepts a link of TYPO3 too, which the DataHandler would
     * resolve and empty after the other fields are stored: a project field takes web
     * addresses only.
     */
    #[Test]
    public function aLinkProjectFieldTakesWebAddressesOnly(): void
    {
        $this->setUpProfileEditingTestCase();
        $content = $this->renderProfileEditingPage();

        $response = $this->postJson($this->extractUrl($content, 'data-update-url'), [
            'profile' => self::PROFILE_ID,
            'data' => ['webLink' => 't3://page?uid=1', 'title' => 'Dr.'],
        ]);

        $this->assertSame(422, $response->getStatusCode(), (string)$response->getBody());
        $this->assertSame('Invalid web address for profile property "webLink".', $this->decode($response)['message']);
        $this->assertSame(['tx_test_web' => '', 'title' => ''], $this->profileRow(self::PROFILE_ID, ['tx_test_web', 'title']));

        $response = $this->postJson($this->extractUrl($content, 'data-update-url'), [
            'profile' => self::PROFILE_ID,
            'data' => ['webLink' => 'https://example.org/team'],
        ]);

        $this->assertSame(200, $response->getStatusCode(), (string)$response->getBody());
        $this->assertSame(['tx_test_web' => 'https://example.org/team'], $this->profileRow(self::PROFILE_ID, ['tx_test_web']));
    }

    /**
     * A listener of the write event replaces a project value like any other field,
     * and the replaced value is validated again.
     */
    #[Test]
    public function aListenerReplacesAProjectFieldValue(): void
    {
        $this->setUpProfileEditingTestCase();
        $content = $this->renderProfileEditingPage();
        EditorWriteListener::$behaviour = static function (BeforeProfileEditingWriteEvent $event): void {
            $event->setFields(['namePrefix' => 'zu']);
        };

        $response = $this->postJson($this->extractUrl($content, 'data-update-url'), [
            'profile' => self::PROFILE_ID,
            'data' => ['namePrefix' => 'von'],
        ]);

        $this->assertSame(200, $response->getStatusCode(), (string)$response->getBody());
        $this->assertSame(['namePrefix' => 'von'], EditorWriteListener::$calls[0]['fields']);
        $this->assertSame(['tx_test_prefix' => 'zu'], $this->profileRow(self::PROFILE_ID, ['tx_test_prefix']));

        EditorWriteListener::$behaviour = static function (BeforeProfileEditingWriteEvent $event): void {
            $event->setFields(['namePrefix' => '']);
        };
        $response = $this->postJson($this->extractUrl($content, 'data-update-url'), [
            'profile' => self::PROFILE_ID,
            'data' => ['namePrefix' => 'von'],
        ]);

        $this->assertSame(422, $response->getStatusCode(), (string)$response->getBody());
        $this->assertSame(['tx_test_prefix' => 'zu'], $this->profileRow(self::PROFILE_ID, ['tx_test_prefix']));
    }

    /**
     * A column all languages share is written to the default-language record, and
     * the DataHandler carries it into the translation.
     */
    #[Test]
    public function aSharedProjectColumnIsWrittenForEveryLanguage(): void
    {
        $this->setUpGermanEditor();
        $translationUid = $this->translationUid();
        $content = $this->renderGermanEditor();

        $response = $this->postJson($this->extractUrl($content, 'data-update-url'), [
            'profile' => self::PROFILE_ID,
            'data' => ['sharedCode' => 'all'],
        ]);

        $this->assertSame(200, $response->getStatusCode(), (string)$response->getBody());
        $this->assertSame(['sharedCode' => 'all'], $this->decode($response)['data']);
        $this->assertSame(['tx_test_shared' => 'all'], $this->profileRow(self::PROFILE_ID, ['tx_test_shared']));
        $this->assertSame(['tx_test_shared' => 'all'], $this->profileRow($translationUid, ['tx_test_shared']));
    }

    /**
     * A hidden translation of a visible profile is a record the visitor may not see:
     * the write is refused before anything of it is stored.
     */
    #[Test]
    public function aHiddenTranslationIsNotWritten(): void
    {
        $this->setUpGermanEditor();
        $translationUid = $this->translationUid();
        $content = $this->renderGermanEditor();
        $this->updateProfileRow($translationUid, ['hidden' => 1]);

        $response = $this->postJson($this->extractUrl($content, 'data-update-url'), [
            'profile' => self::PROFILE_ID,
            'data' => ['namePrefix' => 'zu', 'title' => 'Dr.'],
        ]);

        $this->assertSame(404, $response->getStatusCode(), (string)$response->getBody());
        $this->assertSame('profile_not_found', $this->decode($response)['error']);
        $this->assertSame(['tx_test_prefix' => '', 'title' => ''], $this->profileRow($translationUid, ['tx_test_prefix', 'title']));
        $this->assertSame(['tx_test_prefix' => '', 'title' => ''], $this->profileRow(self::PROFILE_ID, ['tx_test_prefix', 'title']));
    }

    /**
     * A column a translation may take from its default-language record keeps the
     * value the translation was given, as the backend keeps one an editor typed.
     */
    #[Test]
    public function aTranslationKeepsItsOwnValueOfASynchronisedColumn(): void
    {
        $this->setUpGermanEditor();
        $translationUid = $this->translationUid();
        $content = $this->renderGermanEditor();

        $response = $this->postJson($this->extractUrl($content, 'data-update-url'), [
            'profile' => self::PROFILE_ID,
            'data' => ['ownCode' => 'mine'],
        ]);

        $this->assertSame(200, $response->getStatusCode(), (string)$response->getBody());
        $this->assertSame(['ownCode' => 'mine'], $this->decode($response)['data']);
        $this->assertSame(['tx_test_own' => 'mine'], $this->profileRow($translationUid, ['tx_test_own']));
        $this->assertSame(['tx_test_own' => ''], $this->profileRow(self::PROFILE_ID, ['tx_test_own']));
        $state = json_decode((string)$this->profileRow($translationUid, ['l10n_state'])['l10n_state'], true);
        $this->assertSame('custom', $state['tx_test_own'] ?? null);
    }

    /**
     * The German editor holds the translation overlay, and the value belongs to the
     * translation row.
     */
    #[Test]
    public function aProjectFieldIsStoredOnTheEditedTranslation(): void
    {
        $this->setUpGermanEditor();
        $translationUid = $this->translationUid();
        $this->updateProfileRow(self::PROFILE_ID, ['tx_test_prefix' => 'von']);
        $this->updateProfileRow($translationUid, ['tx_test_prefix' => 'de']);
        $content = $this->renderGermanEditor();
        $this->assertStringContainsString(
            'value="de"',
            $this->control($this->withoutProfileEditingPrototypes($content), 'input', 'profile-editing-1-namePrefix'),
        );

        $response = $this->postJson($this->extractUrl($content, 'data-update-url'), [
            'profile' => self::PROFILE_ID,
            'data' => ['namePrefix' => 'zu'],
        ]);

        $this->assertSame(200, $response->getStatusCode(), (string)$response->getBody());
        $this->assertSame(['tx_test_prefix' => 'zu'], $this->profileRow($translationUid, ['tx_test_prefix']));
        $this->assertSame(['tx_test_prefix' => 'von'], $this->profileRow(self::PROFILE_ID, ['tx_test_prefix']));
    }

    private function setUpGermanEditor(): void
    {
        $this->setUpProfileEditingTestCase();
        $this->getConnectionPool()->getConnectionForTable('pages')->insert('pages', [
            'uid' => 4,
            'pid' => 1,
            'doktype' => 1,
            'sys_language_uid' => 1,
            'l10n_parent' => 2,
            'slug' => '/home',
            'title' => 'Home (DE)',
        ]);
        $this->getConnectionPool()->getConnectionForTable('tt_content')->insert('tt_content', [
            'uid' => 3,
            'pid' => 2,
            'sys_language_uid' => 1,
            'l18n_parent' => 1,
            'CType' => 'academicpersonsedit_profileediting',
        ]);
        $this->writeFrontendPluginTestSite([
            $this->buildDefaultLanguageConfiguration(identifier: 'EN', base: '/'),
            $this->buildLanguageConfiguration(identifier: 'DE', base: '/de/'),
        ]);
        $this->get(DataHandlerExecutionContext::class)->runAsBackendUser(
            function (): void {
                $this->assertGreaterThan(self::PROFILE_ID, $this->get(ProfileTranslator::class)->translateTo(self::PROFILE_ID, 1));
            },
        );
    }

    private function renderGermanEditor(): string
    {
        return $this->getPageAsFrontendUser(
            $this->extractPluginActionLink(
                $this->getPageAsFrontendUser('https://www.acme.com/de/home'),
                'tx_academicpersonsedit_profileediting',
                'index',
                'profileUid',
                self::PROFILE_ID,
            ),
        );
    }

    private function translationUid(): int
    {
        $queryBuilder = $this->getConnectionPool()->getQueryBuilderForTable(self::TABLE);
        $queryBuilder->getRestrictions()->removeAll();
        $uid = (int)$queryBuilder
            ->select('uid')
            ->from(self::TABLE)
            ->where(
                $queryBuilder->expr()->eq('l10n_parent', $queryBuilder->createNamedParameter(self::PROFILE_ID, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('sys_language_uid', $queryBuilder->createNamedParameter(1, Connection::PARAM_INT)),
            )
            ->executeQuery()
            ->fetchOne();
        $this->assertGreaterThan(self::PROFILE_ID, $uid);
        return $uid;
    }

    /**
     * @param array<string, mixed> $values
     */
    private function updateProfileRow(int $uid, array $values): void
    {
        $this->getConnectionPool()->getConnectionForTable(self::TABLE)->update(self::TABLE, $values, ['uid' => $uid]);
    }

    /**
     * @param list<string> $columns
     * @return array<string, mixed>
     */
    private function profileRow(int $uid, array $columns): array
    {
        $queryBuilder = $this->getConnectionPool()->getQueryBuilderForTable(self::TABLE);
        $queryBuilder->getRestrictions()->removeAll();
        $row = $queryBuilder
            ->select(...$columns)
            ->from(self::TABLE)
            ->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($uid, Connection::PARAM_INT)))
            ->executeQuery()
            ->fetchAssociative();
        $this->assertIsArray($row);
        foreach (['tx_test_guest'] as $integerColumn) {
            if (array_key_exists($integerColumn, $row)) {
                $row[$integerColumn] = (int)$row[$integerColumn];
            }
        }
        return $row;
    }

    private function historyEntries(int $uid): int
    {
        $queryBuilder = $this->getConnectionPool()->getQueryBuilderForTable('sys_history');
        return (int)$queryBuilder
            ->count('uid')
            ->from('sys_history')
            ->where(
                $queryBuilder->expr()->eq('tablename', $queryBuilder->createNamedParameter(self::TABLE)),
                $queryBuilder->expr()->eq('recuid', $queryBuilder->createNamedParameter($uid, Connection::PARAM_INT)),
            )
            ->executeQuery()
            ->fetchOne();
    }

    private function control(string $content, string $tag, string $id): string
    {
        $this->assertSame(
            1,
            preg_match('@<' . $tag . '\b(?=[^>]*\bid="' . preg_quote($id, '@') . '")[^>]*>@', $content, $match),
            $id,
        );
        return $match[0];
    }

    private function extractUrl(string $content, string $attribute): string
    {
        $this->assertSame(1, preg_match('@\b' . preg_quote($attribute, '@') . '="([^"]+)"@', $content, $match), $attribute);
        $url = html_entity_decode($match[1]);
        return str_starts_with($url, '/') ? 'https://www.acme.com' . $url : $url;
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(ResponseInterface $response): array
    {
        return json_decode((string)$response->getBody(), true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function postJson(string $url, array $payload): ResponseInterface
    {
        $body = new Stream('php://temp', 'rw');
        $body->write(json_encode($payload, JSON_THROW_ON_ERROR));
        $body->rewind();
        return $this->requestAsFrontendUser(
            (new InternalRequest($url))
                ->withMethod('POST')
                ->withAddedHeader('Content-Type', 'application/json')
                ->withAddedHeader('X-Requested-With', 'XMLHttpRequest')
                ->withBody($body),
        );
    }
}
