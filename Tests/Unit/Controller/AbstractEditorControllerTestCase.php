<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Tests\Unit\Controller;

use FGTCLB\AcademicPersons\Domain\Repository\ProfileRepository;
use FGTCLB\AcademicPersons\Profile\ManagedFieldResolver;
use FGTCLB\AcademicPersons\Settings\AcademicPersonsSettings;
use FGTCLB\AcademicPersonsEdit\Domain\Parser\ProfileUpdatePayloadParser;
use FGTCLB\AcademicPersonsEdit\Service\EditorEndpoint;
use FGTCLB\AcademicPersonsEdit\Service\EditorWrites;
use FGTCLB\AcademicPersonsEdit\Service\ManagedRecordLocks;
use FGTCLB\AcademicPersonsEdit\Service\ProfileUpdateRequestService;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\EventDispatcher\NoopEventDispatcher;
use TYPO3\CMS\Core\Http\PropagateResponseException;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Http\Stream;
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\Schema\TcaSchemaFactory;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Mvc\ExtbaseRequestParameters;
use TYPO3\CMS\Extbase\Mvc\Request;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * What the unit tests of the three controllers of the profile editor share:
 * the request they hand an action, the collaborators every controller takes,
 * and the reading of the JSON answer an action propagates.
 */
abstract class AbstractEditorControllerTestCase extends UnitTestCase
{
    protected function createAcademicPersonsSettings(): AcademicPersonsSettings
    {
        return new AcademicPersonsSettings(
            profileSections: [],
            documentSections: [],
            raw: [],
        );
    }

    protected function createManagedRecordLocks(AcademicPersonsSettings $academicPersonsSettings): ManagedRecordLocks
    {
        return new ManagedRecordLocks(
            new ManagedFieldResolver(
                $academicPersonsSettings,
                $this->createStub(ConnectionPool::class),
                $this->createStub(TcaSchemaFactory::class),
            ),
            $academicPersonsSettings,
        );
    }

    protected function createProfileUpdateRequestService(ProfileRepository $profileRepository): ProfileUpdateRequestService
    {
        return new ProfileUpdateRequestService(
            new Context(),
            $profileRepository,
            new ProfileUpdatePayloadParser(),
        );
    }

    protected function createEditorEndpoint(): EditorEndpoint
    {
        return new EditorEndpoint($this->createStub(LogManager::class));
    }

    protected function createEditorWrites(): EditorWrites
    {
        return new EditorWrites(new NoopEventDispatcher(), $this->createStub(PersistenceManager::class));
    }

    /**
     * Hands the request to the controller the way the Extbase dispatcher does.
     */
    protected function setRequest(ActionController $subject, Request $request): void
    {
        (new \ReflectionProperty(ActionController::class, 'request'))->setValue($subject, $request);
    }

    protected function createRequest(
        string $method,
        string $body = '',
    ): Request {
        $stream = new Stream('php://temp', 'rw');
        $stream->write($body);
        $stream->rewind();

        $serverRequest = (new ServerRequest())
            ->withMethod($method)
            ->withBody($stream)
            ->withHeader('Content-Type', 'application/json')
            ->withAttribute(
                'extbase',
                new ExtbaseRequestParameters(),
            );

        return new Request($serverRequest);
    }

    /**
     * @param callable(): ResponseInterface $action
     */
    protected function getPropagatedResponse(callable $action): ResponseInterface
    {
        try {
            $action();
        } catch (PropagateResponseException $exception) {
            return $exception->getResponse();
        }
        $this->fail('The JSON error response was not propagated to the middleware stack.');
    }

    /**
     * @param array<string, mixed> $expectedBody
     */
    protected function assertJsonResponse(
        ResponseInterface $response,
        int $expectedStatusCode,
        array $expectedBody,
    ): void {
        $this->assertSame(
            $expectedStatusCode,
            $response->getStatusCode(),
        );

        $this->assertSame(
            $expectedBody,
            json_decode(
                (string)$response->getBody(),
                true,
                512,
                JSON_THROW_ON_ERROR,
            ),
        );
    }
}
