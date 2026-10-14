<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Tests\Unit\Service;

use FGTCLB\AcademicPersonsEdit\Service\EditorEndpoint;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Http\PropagateResponseException;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Extbase\Mvc\ExtbaseRequestParameters;
use TYPO3\CMS\Extbase\Mvc\Request;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class EditorEndpointTest extends UnitTestCase
{
    private function createRequest(?string $requestedWith): Request
    {
        $serverRequest = (new ServerRequest())
            ->withMethod('POST')
            ->withAttribute('extbase', new ExtbaseRequestParameters());
        if ($requestedWith !== null) {
            $serverRequest = $serverRequest->withHeader('X-Requested-With', $requestedWith);
        }
        return new Request($serverRequest);
    }

    #[Test]
    public function aRequestTheEditorSentPasses(): void
    {
        (new EditorEndpoint($this->createStub(LogManager::class)))
            ->assertRequestWasSentByTheEditor($this->createRequest('XMLHttpRequest'));

        $this->addToAssertionCount(1);
    }

    /**
     * @return \Generator<string, array{0: string|null}>
     */
    public static function foreignRequestProvider(): \Generator
    {
        yield 'no header' => [null];
        yield 'another value' => ['fetch'];
        yield 'an empty value' => [''];
    }

    #[Test]
    #[DataProvider('foreignRequestProvider')]
    public function aRequestWithoutTheHeaderOfTheEditorIsRefused(?string $requestedWith): void
    {
        try {
            (new EditorEndpoint($this->createStub(LogManager::class)))
                ->assertRequestWasSentByTheEditor($this->createRequest($requestedWith));
        } catch (PropagateResponseException $exception) {
            $this->assertSame(1777046203, $exception->getCode());
            $this->assertSame(400, $exception->getResponse()->getStatusCode());
            $this->assertSame(
                [
                    'success' => false,
                    'error' => 'invalid_request',
                    'message' => 'The request must carry the "X-Requested-With: XMLHttpRequest" header.',
                ],
                json_decode((string)$exception->getResponse()->getBody(), true, 512, JSON_THROW_ON_ERROR),
            );
            return;
        }
        $this->fail('The request was not refused.');
    }
}
