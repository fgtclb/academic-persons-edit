<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Tests\Functional\Plugins;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ResponseInterface;
use TESTS\TestProfileUpdateRecorder\EventListener\RecordProfileUpdateListener;
use TYPO3\CMS\Core\Http\Stream;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

/**
 * The owner's "Show my profile publicly" switch, driven through the real plugin.
 *
 * The switch writes the profile's `hidden` column, which every public output already
 * honours, so what is asserted here is the part the editor owns: the owner shows and
 * hides the own profile, the owner still reaches a profile while it is hidden, and
 * nobody reaches anything beyond that. What the public plugins do with a hidden
 * profile is covered by their own tests, and the detail page is asserted once here as
 * the end-to-end case. It asserts the page-not-found content rather than the status:
 * on TYPO3 v13 the status of an Extbase plugin response does not reach the frontend
 * response, so the detail plugin's 404 arrives as a 200 there.
 */
final class AcademicPersonsEditProfileVisibilityTest extends AbstractFrontendProfilePluginTestCase
{
    private const FOREIGN_PROFILE_ID = 2;

    private const PUBLIC_LIST_URL = 'https://www.acme.com/academic-persons/list';

    protected function setUp(): void
    {
        $this->addTestExtensionsToLoad('tests/test-profile-update-recorder');
        parent::setUp();
    }

    protected function tearDown(): void
    {
        RecordProfileUpdateListener::$announcements = [];
        RecordProfileUpdateListener::$hiddenStates = [];
        parent::tearDown();
    }

    /**
     * The editor fixture plus a public list of all profiles, the page a visitor finds
     * the profile on.
     */
    private function setUpVisibilityTestCase(): void
    {
        $this->setUpProfileEditingTestCase();
        $this->getConnectionPool()->getConnectionForTable('pages')->insert('pages', [
            'uid' => 5,
            'pid' => 1,
            'doktype' => 1,
            'slug' => '/academic-persons/list',
            'title' => 'Profile list (EN)',
        ]);
        $this->getConnectionPool()->getConnectionForTable('tt_content')->insert('tt_content', [
            'uid' => 10,
            'pid' => 5,
            'CType' => 'academicpersons_list',
            'header' => 'Profile list',
            'pi_flexform' => '<?xml version="1.0" encoding="utf-8" standalone="yes" ?><T3FlexForms><data>'
                . '<sheet index="sDEF"><language index="lDEF">'
                . '<field index="settings.demand.profileList"><value index="vDEF"></value></field>'
                . '<field index="settings.fallbackForNonTranslated"><value index="vDEF">0</value></field>'
                . '</language></sheet></data></T3FlexForms>',
        ]);
    }

    private function setProfileHidden(int $profileUid, bool $hidden): void
    {
        $this->getConnectionPool()
            ->getConnectionForTable('tx_academicpersons_domain_model_profile')
            ->update(
                'tx_academicpersons_domain_model_profile',
                ['hidden' => $hidden ? 1 : 0],
                ['uid' => $profileUid],
            );
    }

    private function isProfileHidden(int $profileUid): bool
    {
        return (bool)$this->getConnectionPool()
            ->getConnectionForTable('tx_academicpersons_domain_model_profile')
            ->executeQuery(
                'SELECT hidden FROM tx_academicpersons_domain_model_profile WHERE uid = ?',
                [$profileUid],
            )
            ->fetchOne();
    }

    private function extractDataUrl(string $content, string $attribute): string
    {
        $this->assertSame(
            1,
            preg_match(sprintf('@\b%s="([^"]+)"@', preg_quote($attribute, '@')), $content, $match),
            sprintf('The rendered component has no "%s" URL.', $attribute),
        );
        $url = html_entity_decode($match[1]);
        return str_starts_with($url, '/') ? 'https://www.acme.com' . $url : $url;
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

    /**
     * @return array<string, mixed>
     */
    private function decode(ResponseInterface $response): array
    {
        $body = json_decode((string)$response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($body, (string)$response->getBody());
        return $body;
    }

    private function getVisibilityCheckbox(string $content): \DOMElement
    {
        $document = new \DOMDocument();
        $this->assertTrue($document->loadHTML($content, LIBXML_NOERROR | LIBXML_NOWARNING));
        $nodes = (new \DOMXPath($document))->query(
            '//form[@data-pe-visibility-form]//input[contains(concat(" ", normalize-space(@class), " "),'
            . ' " academic-persons-profile-editing__visibility-checkbox ")]',
        );
        $this->assertNotFalse($nodes);
        $this->assertCount(1, $nodes, 'The editor renders no visibility switch.');
        $checkbox = $nodes->item(0);
        $this->assertInstanceOf(\DOMElement::class, $checkbox);
        return $checkbox;
    }

    #[Test]
    public function theSwitchIsOnForAPublicProfileAndOffForAHiddenOne(): void
    {
        $this->setUpVisibilityTestCase();

        $checkbox = $this->getVisibilityCheckbox($this->renderProfileEditingPage());
        $this->assertTrue($checkbox->hasAttribute('checked'));
        $this->assertFalse($checkbox->hasAttribute('disabled'));

        $this->setProfileHidden(self::PROFILE_ID, true);

        $checkbox = $this->getVisibilityCheckbox($this->renderProfileEditingPage());
        $this->assertFalse($checkbox->hasAttribute('checked'));
        $this->assertFalse($checkbox->hasAttribute('disabled'));
    }

    /**
     * The owner lists a hidden profile, marked as not public and without the link to a
     * detail page that would answer 404, and opens it.
     */
    #[Test]
    public function theOwnerListsAndOpensAHiddenProfile(): void
    {
        $this->setUpVisibilityTestCase();
        $this->setProfileHidden(self::PROFILE_ID, true);

        $listPage = $this->getPageAsFrontendUser('https://www.acme.com/home');

        $this->assertStringContainsString('Müllermann', $listPage);
        $this->assertStringContainsString('data-pe-profile-hidden', $listPage);
        $this->assertStringContainsString('Not public', $listPage);
        $this->assertStringNotContainsString('tx_academicpersons_detail', $listPage);
        $editUrl = $this->extractPluginActionLink(
            $listPage,
            'tx_academicpersonsedit_profileediting',
            'index',
            'profileUid',
            self::PROFILE_ID,
        );
        $response = $this->requestAsFrontendUser(new InternalRequest($editUrl));
        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('data-profile-uid="1"', (string)$response->getBody());
    }

    #[Test]
    public function aPublicProfileIsListedWithoutTheMarkAndWithItsDetailLink(): void
    {
        $this->setUpVisibilityTestCase();

        $listPage = $this->getPageAsFrontendUser('https://www.acme.com/home');

        $this->assertStringNotContainsString('data-pe-profile-hidden', $listPage);
        $this->assertStringContainsString('tx_academicpersons_detail', $listPage);
    }

    /**
     * Hidden by the owner, the profile is gone from the public list and the detail
     * page, and shown again it is back: the switch works in both directions, which it
     * only does while the owner still reaches the hidden profile. The update is
     * announced with the stored state, so a listener reads what the owner chose.
     */
    #[Test]
    public function theOwnerHidesTheProfileAndShowsItAgain(): void
    {
        $this->setUpVisibilityTestCase();
        $detailUrl = $this->extractPluginActionLink(
            $this->getPageAsFrontendUser('https://www.acme.com/home'),
            'tx_academicpersons_detail',
            'detail',
            'profile',
            self::PROFILE_ID,
        );
        $this->assertStringContainsString('Müllermann', $this->getPageAsFrontendUser(self::PUBLIC_LIST_URL));
        $visibilityUrl = $this->extractDataUrl($this->renderProfileEditingPage(), 'data-visibility-url');

        $response = $this->postJson($visibilityUrl, ['profile' => self::PROFILE_ID, 'data' => ['hidden' => true]]);

        $this->assertSame(200, $response->getStatusCode(), (string)$response->getBody());
        $this->assertSame(['success' => true, 'profile' => self::PROFILE_ID, 'hidden' => true], $this->decode($response));
        $this->assertTrue($this->isProfileHidden(self::PROFILE_ID));
        $this->assertSame([[self::PROFILE_ID, 'acme', 'frontend-editing']], RecordProfileUpdateListener::$announcements);
        $this->assertSame([true], RecordProfileUpdateListener::$hiddenStates);
        $this->assertStringNotContainsString('Müllermann', $this->getPageAsFrontendUser(self::PUBLIC_LIST_URL));
        $detailPage = (string)$this->requestAsFrontendUser(new InternalRequest($detailUrl))->getBody();
        $this->assertStringContainsString('Page Not Found', $detailPage);
        $this->assertStringNotContainsString('Müllermann', $detailPage);

        $visibilityUrl = $this->extractDataUrl($this->renderProfileEditingPage(), 'data-visibility-url');
        $response = $this->postJson($visibilityUrl, ['profile' => self::PROFILE_ID, 'data' => ['hidden' => false]]);

        $this->assertSame(200, $response->getStatusCode(), (string)$response->getBody());
        $this->assertSame(['success' => true, 'profile' => self::PROFILE_ID, 'hidden' => false], $this->decode($response));
        $this->assertFalse($this->isProfileHidden(self::PROFILE_ID));
        $this->assertStringContainsString('Müllermann', $this->getPageAsFrontendUser(self::PUBLIC_LIST_URL));
        $detailResponse = $this->requestAsFrontendUser(new InternalRequest($detailUrl));
        $this->assertSame(200, $detailResponse->getStatusCode());
        $this->assertStringContainsString('Müllermann', (string)$detailResponse->getBody());
    }

    /**
     * @return \Generator<string, array{0: array<string, mixed>}>
     */
    public static function malformedPayloadProvider(): \Generator
    {
        yield 'no value' => [[]];
        yield 'a string instead of a boolean' => [['hidden' => '1']];
        yield 'an integer instead of a boolean' => [['hidden' => 1]];
        yield 'a second field' => [['hidden' => true, 'skipSync' => true]];
        yield 'the value the switch shows rather than the column' => [['visibility' => false]];
    }

    /**
     * @param array<string, mixed> $data
     */
    #[Test]
    #[DataProvider('malformedPayloadProvider')]
    public function aMalformedPayloadChangesNothing(array $data): void
    {
        $this->setUpVisibilityTestCase();
        $visibilityUrl = $this->extractDataUrl($this->renderProfileEditingPage(), 'data-visibility-url');

        $response = $this->postJson($visibilityUrl, ['profile' => self::PROFILE_ID, 'data' => $data]);

        $this->assertSame(400, $response->getStatusCode(), (string)$response->getBody());
        $this->assertSame('invalid_payload', $this->decode($response)['error'] ?? null);
        $this->assertFalse($this->isProfileHidden(self::PROFILE_ID));
    }

    /**
     * The switch has one write path. The general field endpoint does not know `hidden`
     * and refuses it, so it can neither bypass the switch's configuration nor write the
     * value into a translation row.
     */
    #[Test]
    public function theFieldEndpointRefusesTheHiddenValue(): void
    {
        $this->setUpVisibilityTestCase();
        $updateUrl = $this->extractDataUrl($this->renderProfileEditingPage(), 'data-update-url');

        $response = $this->postJson($updateUrl, ['profile' => self::PROFILE_ID, 'data' => ['hidden' => true]]);

        $this->assertSame(422, $response->getStatusCode(), (string)$response->getBody());
        $this->assertSame('invalid_profile_data', $this->decode($response)['error'] ?? null);
        $this->assertFalse($this->isProfileHidden(self::PROFILE_ID));
    }

    /**
     * Including hidden profiles is for the owner's own ones. A hidden profile of somebody
     * else stays out of the owner's list and out of reach of the switch.
     */
    #[Test]
    public function aHiddenProfileOfAnotherOwnerStaysRefused(): void
    {
        $this->setUpVisibilityTestCase();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/AcademicPersonsEditProfileEditing/foreignProfile.csv');
        $this->setProfileHidden(self::FOREIGN_PROFILE_ID, true);
        $listPage = $this->getPageAsFrontendUser('https://www.acme.com/home');
        $this->assertStringNotContainsString('Intruder', $listPage);
        $visibilityUrl = $this->extractDataUrl($this->renderProfileEditingPage(), 'data-visibility-url');

        $response = $this->postJson($visibilityUrl, ['profile' => self::FOREIGN_PROFILE_ID, 'data' => ['hidden' => false]]);

        $this->assertSame(403, $response->getStatusCode(), (string)$response->getBody());
        $this->assertSame('profile_not_editable', $this->decode($response)['error'] ?? null);
        $this->assertTrue($this->isProfileHidden(self::FOREIGN_PROFILE_ID));
    }

    /**
     * Only the hidden flag is lifted for the owner. A profile whose end time has passed
     * stays out of the editor, exactly as before.
     */
    #[Test]
    public function aProfileOutsideItsTimeWindowStaysUnlisted(): void
    {
        $this->setUpVisibilityTestCase();
        $this->getConnectionPool()
            ->getConnectionForTable('tx_academicpersons_domain_model_profile')
            ->update(
                'tx_academicpersons_domain_model_profile',
                ['endtime' => 946684800],
                ['uid' => self::PROFILE_ID],
            );

        $listPage = $this->getPageAsFrontendUser('https://www.acme.com/home');

        $this->assertStringNotContainsString('Müllermann', $listPage);
    }

    /**
     * The image endpoints resolve the persisted row of the profile, and that must not
     * turn the owner away while the profile is hidden. The deletion is driven here
     * because it runs on both core versions, the upload is covered by
     * `AcademicPersonsEditProfileImageUploadTest`.
     */
    #[Test]
    public function theOwnerDeletesTheImageOfAHiddenProfile(): void
    {
        $this->setUpVisibilityTestCase();
        $this->seedProfileImage();
        $this->setProfileHidden(self::PROFILE_ID, true);
        $deleteUrl = $this->extractDataUrl($this->renderProfileEditingPage(), 'data-delete-image-url');

        $response = $this->postJson($deleteUrl, ['profile' => self::PROFILE_ID, 'data' => []]);

        $this->assertSame(200, $response->getStatusCode(), (string)$response->getBody());
        $this->assertSame(0, $this->getPersistedProfileImageCount());
        $this->assertTrue($this->isProfileHidden(self::PROFILE_ID));
    }
}
