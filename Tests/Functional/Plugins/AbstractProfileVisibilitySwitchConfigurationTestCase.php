<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Tests\Functional\Plugins;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Http\Stream;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

/**
 * An installation that keeps the "Show my profile publicly" switch from owners.
 *
 * Each concrete class loads a site package that changes the one special field of
 * `Settings.yaml`: read-only, disabled or removed. In every case a profile an editor
 * hid stays hidden whatever the owner sends, and backend editors keep the checkbox:
 * the setting is about the owner's switch, not about who else may hide a profile.
 */
abstract class AbstractProfileVisibilitySwitchConfigurationTestCase extends AbstractFrontendProfilePluginTestCase
{
    /**
     * The composer name of the site package that configures the switch.
     */
    abstract protected function getSwitchConfigurationPackage(): string;

    /**
     * Whether the editor still renders the switch, disabled, or not at all.
     */
    abstract protected function isSwitchRendered(): bool;

    protected function setUp(): void
    {
        $this->addTestExtensionsToLoad($this->getSwitchConfigurationPackage());
        parent::setUp();
    }

    private function setUpHiddenProfile(): string
    {
        $this->setUpProfileEditingTestCase();
        $this->getConnectionPool()
            ->getConnectionForTable('tx_academicpersons_domain_model_profile')
            ->update('tx_academicpersons_domain_model_profile', ['hidden' => 1], ['uid' => self::PROFILE_ID]);
        return $this->renderProfileEditingPage();
    }

    private function isProfileHidden(): bool
    {
        return (bool)$this->getConnectionPool()
            ->getConnectionForTable('tx_academicpersons_domain_model_profile')
            ->executeQuery(
                'SELECT hidden FROM tx_academicpersons_domain_model_profile WHERE uid = ?',
                [self::PROFILE_ID],
            )
            ->fetchOne();
    }

    #[Test]
    public function theOwnerCannotShowAProfileAnEditorHid(): void
    {
        $content = $this->setUpHiddenProfile();
        $this->assertSame(1, preg_match('@\bdata-visibility-url="([^"]+)"@', $content, $match));
        $url = html_entity_decode($match[1]);
        if (str_starts_with($url, '/')) {
            $url = 'https://www.acme.com' . $url;
        }
        $body = new Stream('php://temp', 'rw');
        $body->write(json_encode(['profile' => self::PROFILE_ID, 'data' => ['hidden' => false]], JSON_THROW_ON_ERROR));
        $body->rewind();

        $response = $this->requestAsFrontendUser(
            (new InternalRequest($url))
                ->withMethod('POST')
                ->withAddedHeader('Content-Type', 'application/json')
                ->withAddedHeader('X-Requested-With', 'XMLHttpRequest')
                ->withBody($body),
        );

        $this->assertSame(403, $response->getStatusCode(), (string)$response->getBody());
        $decoded = json_decode((string)$response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($decoded);
        $this->assertSame('visibility_not_editable', $decoded['error'] ?? null);
        $this->assertTrue($this->isProfileHidden());
    }

    /**
     * The owner still reaches the hidden profile to edit everything else, and sees the
     * switch disabled, or not at all when the installation removed it.
     */
    #[Test]
    public function theEditorRendersTheSwitchUnavailable(): void
    {
        $content = $this->setUpHiddenProfile();

        $this->assertStringContainsString('data-profile-uid="1"', $content);
        $matched = preg_match(
            '@<input\b[^>]*data-pe-visibility-checkbox[^>]*>@',
            $content,
            $checkbox,
        );
        if (!$this->isSwitchRendered()) {
            $this->assertSame(0, $matched, 'The removed switch is rendered.');
            $this->assertStringNotContainsString('data-pe-visibility-form', $content);
            return;
        }
        $this->assertSame(1, $matched, 'The switch is not rendered.');
        $this->assertStringContainsString('disabled="disabled"', $checkbox[0]);
        $this->assertStringNotContainsString('checked', $checkbox[0]);
    }

    #[Test]
    public function backendEditorsKeepTheCheckbox(): void
    {
        $hiddenColumn = $GLOBALS['TCA']['tx_academicpersons_domain_model_profile']['columns']['hidden'];

        $this->assertFalse((bool)($hiddenColumn['config']['readOnly'] ?? false));
    }
}
