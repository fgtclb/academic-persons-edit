<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Tests\Functional\Plugins;

use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Http\Stream;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

/**
 * The `hide` action of a document section writes the record's own `hidden`
 * column, and a hidden record stays in the editor.
 *
 * Contracts and profile information rows declare `enablecolumns.disabled` in
 * TCA like the contact records do, and the editor is where a hidden record is
 * shown again - so it lists them, dimmed and badged, while the public views
 * keep reading the relations that respect the enable fields. The flag is sent
 * explicitly, not toggled, so a request that arrives twice stores the state
 * the visitor saw. The action is one of the section's `actions` list and is
 * refused like any other action a section does not list.
 */
final class AcademicPersonsEditDocumentVisibilityTest extends AbstractFrontendProfilePluginTestCase
{
    private const CONTRACT_ID = 1;
    private const COOPERATION_ID = 1;

    private string $toggleUrl = '';

    #[Test]
    public function hidesAndShowsAContractAndKeepsItInTheEditor(): void
    {
        $this->setUpVisibilityTestCase();
        $this->assertSame(0, $this->getHiddenFlag('tx_academicpersons_domain_model_contract', self::CONTRACT_ID));

        $hidden = $this->toggle('contracts', self::CONTRACT_ID, true);

        $this->assertSame(200, $hidden->getStatusCode(), (string)$hidden->getBody());
        $body = json_decode((string)$hidden->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertTrue($body['success'] ?? null);
        $this->assertSame('contracts', $body['section'] ?? null);
        $this->assertSame(self::CONTRACT_ID, $body['item']['uid'] ?? null);
        $this->assertTrue($body['item']['hidden'] ?? null);
        $this->assertSame(1, $this->getHiddenFlag('tx_academicpersons_domain_model_contract', self::CONTRACT_ID));

        // The editor still lists the hidden contract, marked as such, and its
        // toggle offers to show it; the visible one is neither.
        $content = $this->renderProfileEditingPage();
        $document = new \DOMDocument();
        $this->assertTrue(@$document->loadHTML($content));
        $xpath = new \DOMXPath($document);
        $this->assertSame('1', $this->attributeOfRow($xpath, 'contracts', 1, 'data-item-hidden'));
        $this->assertSame(1, $this->countBelowRow($xpath, 'contracts', 1, './/*[@data-pe-document-hidden-badge][not(@hidden)]'));
        $this->assertSame(1, $this->countBelowRow($xpath, 'contracts', 1, './/*[@data-pe-document-hide][@aria-label="' . $this->translate('actions.show') . '"][not(@aria-pressed)]'));
        $this->assertNull($this->attributeOfRow($xpath, 'contracts', 2, 'data-item-hidden'));
        $this->assertSame(1, $this->countBelowRow($xpath, 'contracts', 2, './/*[@data-pe-document-hidden-badge][@hidden]'));
        $this->assertSame(1, $this->countBelowRow($xpath, 'contracts', 2, './/*[@data-pe-document-hide][@aria-label="' . $this->translate('actions.hide') . '"][not(@aria-pressed)]'));

        // The hidden record is still found by the endpoints, so it can be shown again.
        $shown = $this->toggle('contracts', self::CONTRACT_ID, false);

        $this->assertSame(200, $shown->getStatusCode(), (string)$shown->getBody());
        $body = json_decode((string)$shown->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertFalse($body['item']['hidden'] ?? null);
        $this->assertSame(0, $this->getHiddenFlag('tx_academicpersons_domain_model_contract', self::CONTRACT_ID));
    }

    #[Test]
    public function hidesAndShowsAProfileInformationRecord(): void
    {
        $this->setUpVisibilityTestCase();

        $hidden = $this->toggle('cooperation', self::COOPERATION_ID, true);

        $this->assertSame(200, $hidden->getStatusCode(), (string)$hidden->getBody());
        $body = json_decode((string)$hidden->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertTrue($body['item']['hidden'] ?? null);
        $this->assertSame(
            1,
            $this->getHiddenFlag('tx_academicpersons_domain_model_profile_information', self::COOPERATION_ID),
        );
        $document = new \DOMDocument();
        $this->assertTrue(@$document->loadHTML($this->renderProfileEditingPage()));
        $xpath = new \DOMXPath($document);
        $this->assertSame('1', $this->attributeOfRow($xpath, 'cooperation', 1, 'data-item-hidden'));
        $this->assertNull($this->attributeOfRow($xpath, 'cooperation', 2, 'data-item-hidden'));

        $shown = $this->toggle('cooperation', self::COOPERATION_ID, false);

        $this->assertSame(200, $shown->getStatusCode(), (string)$shown->getBody());
        $this->assertSame(
            0,
            $this->getHiddenFlag('tx_academicpersons_domain_model_profile_information', self::COOPERATION_ID),
        );
    }

    /**
     * A flag that is not a boolean is refused, and a record of a section
     * that does not exist is not found - the same answers every other
     * document endpoint gives.
     */
    #[Test]
    public function aFlagThatIsNotABooleanIsRefused(): void
    {
        $this->setUpVisibilityTestCase();

        $response = $this->postJson($this->toggleUrl, [
            'profile' => self::PROFILE_ID,
            'data' => ['section' => 'contracts', 'record' => self::CONTRACT_ID, 'hidden' => '1'],
        ]);

        $this->assertSame(400, $response->getStatusCode(), (string)$response->getBody());
        $body = json_decode((string)$response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('invalid_payload', $body['error'] ?? null);
        $this->assertSame(0, $this->getHiddenFlag('tx_academicpersons_domain_model_contract', self::CONTRACT_ID));
    }

    #[Test]
    public function aRecordThatIsNotInTheSectionIsNotFound(): void
    {
        $this->setUpVisibilityTestCase();

        $response = $this->toggle('cooperation', 99, true);

        $this->assertSame(404, $response->getStatusCode(), (string)$response->getBody());
        $body = json_decode((string)$response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('document_not_found', $body['error'] ?? null);
    }

    private function setUpVisibilityTestCase(): void
    {
        $this->setUpProfileEditingTestCase();
        $this->importCSVDataSet(
            __DIR__ . '/Fixtures/AcademicPersonsEditProfileEditing/structuredDocumentSections.csv',
        );
        $this->getConnectionPool()
            ->getConnectionForTable('tx_academicpersons_domain_model_profile')
            ->update(
                'tx_academicpersons_domain_model_profile',
                ['contracts' => 2, 'cooperation' => 2],
                ['uid' => self::PROFILE_ID],
            );
        $this->toggleUrl = $this->extractDataUrl(
            $this->renderProfileEditingPage(),
            'data-toggle-document-visibility-url',
        );
    }

    private function toggle(string $section, int $record, bool $hidden): ResponseInterface
    {
        return $this->postJson($this->toggleUrl, [
            'profile' => self::PROFILE_ID,
            'data' => ['section' => $section, 'record' => $record, 'hidden' => $hidden],
        ]);
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

    private function extractDataUrl(string $content, string $attribute): string
    {
        $pattern = sprintf('@\b%s="([^"]+)"@', preg_quote($attribute, '@'));
        $this->assertSame(
            1,
            preg_match($pattern, $content, $match),
            sprintf('The rendered component has no "%s" URL.', $attribute),
        );
        $url = html_entity_decode($match[1]);
        return str_starts_with($url, '/') ? 'https://www.acme.com' . $url : $url;
    }

    private function rowNode(\DOMXPath $xpath, string $section, int $uid): \DOMNode
    {
        $rows = $xpath->query(sprintf(
            '//*[@data-section-key="%s"]//*[@data-pe-document-item][@data-item-uid="%d"]',
            $section,
            $uid,
        ));
        $this->assertNotFalse($rows);
        $this->assertCount(1, $rows, sprintf('The editor does not list record %d of "%s" exactly once.', $uid, $section));
        $row = $rows->item(0);
        $this->assertInstanceOf(\DOMNode::class, $row);
        return $row;
    }

    private function attributeOfRow(\DOMXPath $xpath, string $section, int $uid, string $attribute): ?string
    {
        $attributes = $this->rowNode($xpath, $section, $uid)->attributes;
        $this->assertNotNull($attributes);
        return $attributes->getNamedItem($attribute)?->nodeValue;
    }

    private function countBelowRow(\DOMXPath $xpath, string $section, int $uid, string $query): int
    {
        $nodes = $xpath->query($query, $this->rowNode($xpath, $section, $uid));
        $this->assertNotFalse($nodes);
        return $nodes->length;
    }

    private function getHiddenFlag(string $table, int $uid): int
    {
        return (int)$this->getConnectionPool()
            ->getConnectionForTable($table)
            ->executeQuery(sprintf('SELECT hidden FROM %s WHERE uid = ?', $table), [$uid])
            ->fetchOne();
    }
}
