<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Tests\Functional\Plugins;

use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Http\Stream;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

/**
 * Fields an installation locks for profile owners with `frontendreadonly`.
 *
 * The fixture extension `test_frontend_readonly` of `academic_persons` marks the
 * profile title, the contract position and room and the address street, and
 * the middle name together with `readonly`. The editor shows them read-only
 * and refuses a submitted value, exactly as for `readonly`. That the backend
 * form keeps them editable is covered next to the TCA, in `academic_persons`.
 */
final class AcademicPersonsEditFrontendReadOnlyFieldTest extends AbstractFrontendProfilePluginTestCase
{
    protected function setUp(): void
    {
        $this->addTestExtensionsToLoad('tests/test-frontend-readonly');
        parent::setUp();
    }

    private function renderEditorWithContracts(): string
    {
        $this->setUpProfileEditingTestCase();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/AcademicPersonsEditProfileEditing/structuredDocumentSections.csv');
        $this->getConnectionPool()
            ->getConnectionForTable('tx_academicpersons_domain_model_profile')
            ->update('tx_academicpersons_domain_model_profile', ['contracts' => 2], ['uid' => self::PROFILE_ID]);
        return $this->renderProfileEditingPage();
    }

    private function extractDataUrl(string $content, string $attribute): string
    {
        $this->assertSame(1, preg_match('@\b' . preg_quote($attribute, '@') . '="([^"]+)"@', $content, $match), $attribute);
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

    private function storedValue(string $table, string $column, int $uid): mixed
    {
        return $this->getConnectionPool()
            ->getConnectionForTable($table)
            ->select([$column], $table, ['uid' => $uid])
            ->fetchOne();
    }

    #[Test]
    public function theTitleIsRenderedReadOnly(): void
    {
        $content = $this->renderEditorWithContracts();

        $this->assertSame(
            1,
            preg_match('@<input\b(?=[^>]*\bid="profile-editing-1-title")[^>]*>@', $content, $control),
            'The title control is not rendered.',
        );
        $this->assertStringContainsString('readonly="readonly"', $control[0]);
    }

    #[Test]
    public function aSubmittedTitleIsRefusedAndNotStored(): void
    {
        $updateUrl = $this->extractDataUrl($this->renderEditorWithContracts(), 'data-update-url');

        $response = $this->postJson($updateUrl, ['profile' => self::PROFILE_ID, 'data' => ['title' => 'Prof. Dr.']]);

        $this->assertSame(422, $response->getStatusCode(), (string)$response->getBody());
        $body = json_decode((string)$response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('Unknown profile property "title".', $body['message'] ?? null);
        $this->assertSame('', $this->storedValue('tx_academicpersons_domain_model_profile', 'title', self::PROFILE_ID));
    }

    #[Test]
    public function aSubmittedContractRoomIsRefusedAndNotStored(): void
    {
        $updateUrl = $this->extractDataUrl($this->renderEditorWithContracts(), 'data-update-document-url');

        $response = $this->postJson($updateUrl, [
            'profile' => self::PROFILE_ID,
            'data' => ['section' => 'contracts', 'record' => 1, 'fields' => ['room' => 'B 1.23']],
        ]);

        $this->assertSame(422, $response->getStatusCode(), (string)$response->getBody());
        $body = json_decode((string)$response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(['This field cannot be changed.'], $body['errors']['room'] ?? null);
        $this->assertSame('', $this->storedValue('tx_academicpersons_domain_model_contract', 'room', 1));
    }

    /**
     * The lock is per field: the office hours of the same contract stay
     * editable for the owner.
     */
    #[Test]
    public function anUnlockedFieldOfTheSameContractIsStillStored(): void
    {
        $updateUrl = $this->extractDataUrl($this->renderEditorWithContracts(), 'data-update-document-url');

        $response = $this->postJson($updateUrl, [
            'profile' => self::PROFILE_ID,
            'data' => ['section' => 'contracts', 'record' => 1, 'fields' => ['officeHours' => '<p>By appointment</p>']],
        ]);

        $this->assertSame(200, $response->getStatusCode(), (string)$response->getBody());
        $this->assertSame(
            '<p>By appointment</p>',
            $this->storedValue('tx_academicpersons_domain_model_contract', 'office_hours', 1),
        );
    }
}
