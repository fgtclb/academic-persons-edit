<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Tests\Functional\Plugins;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Http\Stream;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

/**
 * An installation that runs the synchronisation and declares no managed
 * field keeps every action of the editor, on the same records
 * `AcademicPersonsEditManagedFieldsTest` locks.
 */
final class AcademicPersonsEditManagedFieldsNotDeclaredTest extends AbstractFrontendProfilePluginTestCase
{
    #[Test]
    public function aSynchronisedContactIsDeletedAsBefore(): void
    {
        $this->setUpProfileEditingTestCase();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/AcademicPersonsEditManagedFields/records.csv');
        $this->getConnectionPool()
            ->getConnectionForTable('tx_academicpersons_domain_model_profile')
            ->update(
                'tx_academicpersons_domain_model_profile',
                ['contracts' => 2, 'import_identifier' => 'fe_users:1', 'skip_sync' => 0],
                ['uid' => self::PROFILE_ID],
            );
        $content = $this->renderProfileEditingPage();
        $this->assertStringNotContainsString('data-pe-field-managed', $content);
        $this->assertStringNotContainsString('data-pe-document-managed-badge', $content);
        $this->assertSame(1, preg_match('@\bdata-delete-contract-contact-url="([^"]+)"@', $content, $match));
        $url = html_entity_decode($match[1]);

        $body = new Stream('php://temp', 'rw');
        $body->write(json_encode([
            'profile' => self::PROFILE_ID,
            'data' => ['contract' => 1, 'section' => 'phoneNumbers', 'record' => 1],
        ], JSON_THROW_ON_ERROR));
        $body->rewind();
        $response = $this->requestAsFrontendUser(
            (new InternalRequest(str_starts_with($url, '/') ? 'https://www.acme.com' . $url : $url))
                ->withMethod('POST')
                ->withAddedHeader('Content-Type', 'application/json')
                ->withAddedHeader('X-Requested-With', 'XMLHttpRequest')
                ->withBody($body),
        );

        $this->assertSame(200, $response->getStatusCode(), (string)$response->getBody());
        $this->assertSame(
            0,
            (int)$this->getConnectionPool()
                ->getConnectionForTable('tx_academicpersons_domain_model_phone_number')
                ->count('uid', 'tx_academicpersons_domain_model_phone_number', ['uid' => 1, 'deleted' => 0]),
        );
    }
}
