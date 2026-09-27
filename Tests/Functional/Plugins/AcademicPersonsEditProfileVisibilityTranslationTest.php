<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Tests\Functional\Plugins;

use FGTCLB\AcademicPersons\Service\DataHandlerExecutionContext;
use FGTCLB\AcademicPersonsEdit\Profile\ProfileTranslator;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Http\Stream;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

/**
 * The owner's visibility switch on a translated profile.
 *
 * `hidden` is shared by every language of a profile, so the switch has to hide the
 * default record and each translation, from whichever site language the owner uses.
 * The editor holds the translation overlay in a translated site language, which is the
 * case an Extbase write gets wrong: it would write the translation row and leave the
 * default language public. `profile/allowedLanguages` stays empty on purpose, so the
 * translation synchronisation of the editor does not run and cannot be what hides
 * the translation.
 */
final class AcademicPersonsEditProfileVisibilityTranslationTest extends AbstractFrontendProfilePluginTestCase
{
    private int $translationUid = 0;

    protected const LANGUAGE_PRESETS = [
        'EN' => ['id' => 0, 'title' => 'English', 'locale' => 'en_US.UTF8', 'iso' => 'en', 'hrefLang' => 'en-US', 'direction' => ''],
        'DE' => ['id' => 1, 'title' => 'Deutsch', 'locale' => 'de_DE.UTF8', 'iso' => 'de', 'hrefLang' => 'de-DE', 'direction' => ''],
    ];

    private function setUpTranslationTestCase(): void
    {
        $this->setUpFrontendProfileTestCase(
            __DIR__ . '/Fixtures/AcademicPersonsEditProfileEditing/profileEditingPage.csv',
        );
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
        // Translated the way the backend and the editor translate a profile, which
        // carries the frontend user relation into the translation.
        $this->get(DataHandlerExecutionContext::class)->runAsBackendUser(
            function (): void {
                $this->translationUid = $this->get(ProfileTranslator::class)->translateTo(self::PROFILE_ID, 1);
            },
        );
        $this->assertGreaterThan(self::PROFILE_ID, $this->translationUid);
        $this->getConnectionPool()
            ->getConnectionForTable('tx_academicpersons_domain_model_profile')
            ->update(
                'tx_academicpersons_domain_model_profile',
                ['last_name' => 'Müllermann (DE)'],
                ['uid' => $this->translationUid],
            );
    }

    private function renderGermanProfileEditingPage(): string
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

    private function postVisibility(string $content, bool $hidden): ResponseInterface
    {
        $this->assertSame(1, preg_match('@\bdata-visibility-url="([^"]+)"@', $content, $match));
        $url = html_entity_decode($match[1]);
        if (str_starts_with($url, '/')) {
            $url = 'https://www.acme.com' . $url;
        }
        $body = new Stream('php://temp', 'rw');
        $body->write(json_encode(['profile' => self::PROFILE_ID, 'data' => ['hidden' => $hidden]], JSON_THROW_ON_ERROR));
        $body->rewind();
        return $this->requestAsFrontendUser(
            (new InternalRequest($url))
                ->withMethod('POST')
                ->withAddedHeader('Content-Type', 'application/json')
                ->withAddedHeader('X-Requested-With', 'XMLHttpRequest')
                ->withBody($body),
        );
    }

    /**
     * @return array<int, int> the hidden flag by profile uid
     */
    private function getHiddenByUid(): array
    {
        $rows = $this->getConnectionPool()
            ->getConnectionForTable('tx_academicpersons_domain_model_profile')
            ->executeQuery('SELECT uid, hidden FROM tx_academicpersons_domain_model_profile ORDER BY uid')
            ->fetchAllAssociative();
        $hiddenByUid = [];
        foreach ($rows as $row) {
            $hiddenByUid[(int)$row['uid']] = (int)$row['hidden'];
        }
        return $hiddenByUid;
    }

    #[Test]
    public function switchedOffInTheTranslatedLanguageTheWholeProfileIsHidden(): void
    {
        $this->setUpTranslationTestCase();
        $content = $this->renderGermanProfileEditingPage();
        $this->assertStringContainsString('Müllermann (DE)', $content);

        $response = $this->postVisibility($content, true);

        $this->assertSame(200, $response->getStatusCode(), (string)$response->getBody());
        $this->assertSame([self::PROFILE_ID => 1, $this->translationUid => 1], $this->getHiddenByUid());
    }

    #[Test]
    public function switchedOffInTheDefaultLanguageTheTranslationIsHiddenToo(): void
    {
        $this->setUpTranslationTestCase();

        $response = $this->postVisibility($this->renderProfileEditingPage(), true);

        $this->assertSame(200, $response->getStatusCode(), (string)$response->getBody());
        $this->assertSame([self::PROFILE_ID => 1, $this->translationUid => 1], $this->getHiddenByUid());
    }

    /**
     * The way back: the owner opens the hidden profile in the translated language and
     * shows it again, in every language.
     */
    #[Test]
    public function switchedOnAgainInTheTranslatedLanguageTheWholeProfileIsPublic(): void
    {
        $this->setUpTranslationTestCase();
        $this->getConnectionPool()
            ->getConnectionForTable('tx_academicpersons_domain_model_profile')
            ->executeStatement('UPDATE tx_academicpersons_domain_model_profile SET hidden = 1');
        $content = $this->renderGermanProfileEditingPage();
        $this->assertStringContainsString('Müllermann (DE)', $content);

        $response = $this->postVisibility($content, false);

        $this->assertSame(200, $response->getStatusCode(), (string)$response->getBody());
        $this->assertSame([self::PROFILE_ID => 0, $this->translationUid => 0], $this->getHiddenByUid());
    }
}
