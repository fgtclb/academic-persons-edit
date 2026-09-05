<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Tests\Functional\Plugins;

use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Http\Stream;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

/**
 * The visibility toggle of a Contract contact writes the record's own
 * `hidden` column and nothing else.
 *
 * The previous editor offered the switch for exactly the three contact kinds -
 * physical addresses, email addresses and phone numbers - and it flipped the
 * TCA `enablecolumns.disabled` column of their tables. The inline editor
 * carries it as the `toggleContractContactVisibility` endpoint: the flag is
 * sent explicitly rather than toggled, so a request that arrives twice stores
 * the state the visitor saw, not its opposite. What the endpoint answers is the
 * serialised record, `hidden` included, because that is what the row is
 * rebuilt from.
 */
final class AcademicPersonsEditContractContactVisibilityTest extends AbstractFrontendProfilePluginTestCase
{
    private const CONTRACT_ID = 1;
    private const ADDRESS_ID = 1;
    private const EMAIL_OF_OTHER_CONTRACT_ID = 2;

    private string $toggleUrl = '';

    #[Test]
    public function hidesAndShowsAnAddressAndAnswersTheStoredRecord(): void
    {
        $this->setUpVisibilityTestCase();
        $this->assertSame(0, $this->getHiddenFlag('tx_academicpersons_domain_model_address', self::ADDRESS_ID));

        $hidden = $this->toggle('physicalAddresses', self::ADDRESS_ID, true);

        $this->assertSame(200, $hidden->getStatusCode(), (string)$hidden->getBody());
        $body = json_decode((string)$hidden->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertTrue($body['success'] ?? null);
        $this->assertSame(self::CONTRACT_ID, $body['contract'] ?? null);
        $this->assertSame('physicalAddresses', $body['section'] ?? null);
        $this->assertSame(self::ADDRESS_ID, $body['item']['uid'] ?? null);
        $this->assertTrue($body['item']['hidden'] ?? null);
        $this->assertSame('Campus Road', $body['item']['values']['street'] ?? null);
        $this->assertSame(1, $this->getHiddenFlag('tx_academicpersons_domain_model_address', self::ADDRESS_ID));
        $this->assertSame('Campus Road', $this->getAddressStreet(self::ADDRESS_ID));

        $shown = $this->toggle('physicalAddresses', self::ADDRESS_ID, false);

        $this->assertSame(200, $shown->getStatusCode(), (string)$shown->getBody());
        $body = json_decode((string)$shown->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertFalse($body['item']['hidden'] ?? null);
        $this->assertSame(0, $this->getHiddenFlag('tx_academicpersons_domain_model_address', self::ADDRESS_ID));
    }

    /**
     * Sending the state the record already has is accepted and changes
     * nothing - the switch is idempotent, which is what makes a double press
     * harmless.
     */
    #[Test]
    public function storingTheStateTheRecordAlreadyHasIsANoOp(): void
    {
        $this->setUpVisibilityTestCase();

        $response = $this->toggle('physicalAddresses', self::ADDRESS_ID, false);

        $this->assertSame(200, $response->getStatusCode(), (string)$response->getBody());
        $this->assertSame(0, $this->getHiddenFlag('tx_academicpersons_domain_model_address', self::ADDRESS_ID));
    }

    /**
     * A contact is resolved through the contract the request names, exactly as
     * every other contact endpoint resolves it: a record below another contract
     * is not found, whether or not the visitor may edit that contract too.
     */
    #[Test]
    public function aContactOfAnotherContractIsNotFound(): void
    {
        $this->setUpVisibilityTestCase();

        $response = $this->toggle('emailAddresses', self::EMAIL_OF_OTHER_CONTRACT_ID, true);

        $this->assertSame(404, $response->getStatusCode(), (string)$response->getBody());
        $body = json_decode((string)$response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('contract_contact_not_found', $body['error'] ?? null);
        $this->assertSame(0, $this->getHiddenFlag('tx_academicpersons_domain_model_email', self::EMAIL_OF_OTHER_CONTRACT_ID));
    }

    #[Test]
    public function aFlagThatIsNotABooleanIsRefused(): void
    {
        $this->setUpVisibilityTestCase();

        $response = $this->postJson($this->toggleUrl, [
            'profile' => self::PROFILE_ID,
            'data' => [
                'contract' => self::CONTRACT_ID,
                'section' => 'physicalAddresses',
                'record' => self::ADDRESS_ID,
                'hidden' => '1',
            ],
        ]);

        $this->assertSame(400, $response->getStatusCode(), (string)$response->getBody());
        $body = json_decode((string)$response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('invalid_payload', $body['error'] ?? null);
        $this->assertSame(0, $this->getHiddenFlag('tx_academicpersons_domain_model_address', self::ADDRESS_ID));
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
                ['contracts' => 2],
                ['uid' => self::PROFILE_ID],
            );
        $this->toggleUrl = $this->extractDataUrl(
            $this->renderProfileEditingPage(),
            'data-toggle-contract-contact-visibility-url',
        );
    }

    private function toggle(string $section, int $record, bool $hidden): ResponseInterface
    {
        return $this->postJson($this->toggleUrl, [
            'profile' => self::PROFILE_ID,
            'data' => [
                'contract' => self::CONTRACT_ID,
                'section' => $section,
                'record' => $record,
                'hidden' => $hidden,
            ],
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

    private function getHiddenFlag(string $table, int $uid): int
    {
        return (int)$this->getConnectionPool()
            ->getConnectionForTable($table)
            ->executeQuery(sprintf('SELECT hidden FROM %s WHERE uid = ?', $table), [$uid])
            ->fetchOne();
    }

    private function getAddressStreet(int $addressUid): string
    {
        return (string)$this->getConnectionPool()
            ->getConnectionForTable('tx_academicpersons_domain_model_address')
            ->executeQuery(
                'SELECT street FROM tx_academicpersons_domain_model_address WHERE uid = ?',
                [$addressUid],
            )
            ->fetchOne();
    }
}
