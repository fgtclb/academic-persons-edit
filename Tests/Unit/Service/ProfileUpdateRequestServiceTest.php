<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Tests\Unit\Service;

use FGTCLB\AcademicPersons\Domain\Model\Profile;
use FGTCLB\AcademicPersons\Domain\Repository\ProfileRepository;
use FGTCLB\AcademicPersonsEdit\Domain\Model\Dto\ProfileUpdateRequestResult;
use FGTCLB\AcademicPersonsEdit\Domain\Parser\ProfileUpdatePayloadParser;
use FGTCLB\AcademicPersonsEdit\Service\ProfileUpdateRequestService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Http\Stream;
use TYPO3\CMS\Extbase\Persistence\QueryResultInterface;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class ProfileUpdateRequestServiceTest extends UnitTestCase
{
    #[Test]
    public function nonPostRequestIsRejectedBeforeParsingOrAuthentication(): void
    {
        $context = $this->createMock(Context::class);
        $context->expects($this->never())->method('getPropertyFromAspect');
        $profileRepository = $this->createMock(ProfileRepository::class);
        $profileRepository->expects($this->never())->method('findByFrontendUser');

        $result = $this->createSubject($context, $profileRepository)->validate(
            $this->createRequest('GET', 'not json'),
        );

        $this->assertFailure($result, 'method_not_allowed', 405);
    }

    #[Test]
    public function malformedJsonIsRejectedBeforeAuthentication(): void
    {
        $context = $this->createMock(Context::class);
        $context->expects($this->never())->method('getPropertyFromAspect');
        $profileRepository = $this->createMock(ProfileRepository::class);
        $profileRepository->expects($this->never())->method('findByFrontendUser');

        $result = $this->createSubject($context, $profileRepository)->validate(
            $this->createRequest('POST', '{"profile": 123,'),
        );

        $this->assertFailure($result, 'invalid_json', 400);
    }

    #[Test]
    public function nonJsonContentTypeIsRejectedBeforeParsingOrAuthentication(): void
    {
        $context = $this->createMock(Context::class);
        $context->expects($this->never())->method('getPropertyFromAspect');
        $profileRepository = $this->createMock(ProfileRepository::class);
        $profileRepository->expects($this->never())->method('findByFrontendUser');
        $result = $this->createSubject($context, $profileRepository)->validate(
            $this->createRequest('POST', '{"profile":123,"data":[]}', 'text/plain'),
        );
        $this->assertFailure($result, 'unsupported_media_type', 415);
    }

    #[Test]
    public function structurallyInvalidPayloadIsRejectedBeforeAuthentication(): void
    {
        $context = $this->createMock(Context::class);
        $context->expects($this->never())->method('getPropertyFromAspect');
        $profileRepository = $this->createMock(ProfileRepository::class);
        $profileRepository->expects($this->never())->method('findByFrontendUser');

        $result = $this->createSubject($context, $profileRepository)->validate(
            $this->createJsonRequest([
                'profile' => 123,
            ]),
        );

        $this->assertFailure($result, 'invalid_payload', 400);
    }

    #[Test]
    public function validPayloadRequiresAuthenticatedFrontendUser(): void
    {
        $context = $this->createContext(false);
        $profileRepository = $this->createMock(ProfileRepository::class);
        $profileRepository->expects($this->never())->method('findByFrontendUser');

        $result = $this->createSubject($context, $profileRepository)->validate(
            $this->createValidRequest(),
        );

        $this->assertFailure($result, 'authentication_required', 401);
    }

    #[Test]
    public function profileNotAssignedToCurrentFrontendUserIsRejected(): void
    {
        $context = $this->createContext(true, 37);
        $profileRepository = $this->createMock(ProfileRepository::class);
        $profileRepository
            ->expects($this->once())
            ->method('findByFrontendUser')
            ->with(37)
            ->willReturn($this->createQueryResult([
                $this->createProfile(456),
            ]));

        $result = $this->createSubject($context, $profileRepository)->validate(
            $this->createValidRequest(),
        );

        $this->assertFailure($result, 'profile_not_editable', 403);
    }

    #[Test]
    public function assignedProfileProducesSuccessfulResult(): void
    {
        $context = $this->createContext(true, 37);
        $requestedProfile = $this->createProfile(123);
        $profileRepository = $this->createMock(ProfileRepository::class);
        $profileRepository
            ->expects($this->once())
            ->method('findByFrontendUser')
            ->with(37)
            ->willReturn($this->createQueryResult([
                $this->createProfile(456),
                $requestedProfile,
            ]));

        $result = $this->createSubject($context, $profileRepository)->validate(
            $this->createValidRequest(),
        );

        $this->assertTrue($result->isValid());
        $this->assertSame($requestedProfile, $result->getProfile());
        $payload = $result->getPayload();
        $this->assertNotNull($payload);
        $this->assertSame(123, $payload->getProfileUid());
        $this->assertSame(
            ['firstName' => 'Jane'],
            $payload->getData(),
        );
        $this->assertNull($result->getError());
        $this->assertSame(200, $result->getStatusCode());
    }

    private function createSubject(
        Context $context,
        ProfileRepository $profileRepository,
    ): ProfileUpdateRequestService {
        return new ProfileUpdateRequestService(
            $context,
            $profileRepository,
            new ProfileUpdatePayloadParser(),
        );
    }

    private function createContext(
        bool $isLoggedIn,
        int $frontendUserId = 0,
    ): Context&MockObject {
        $context = $this->createMock(Context::class);
        $context
            ->method('getPropertyFromAspect')
            ->willReturnCallback(
                static fn(string $aspect, string $property, mixed $default): mixed => match ($property) {
                    'isLoggedIn' => $isLoggedIn,
                    'id' => $frontendUserId,
                    default => $default,
                },
            );
        return $context;
    }

    private function createValidRequest(): ServerRequest
    {
        return $this->createJsonRequest([
            'profile' => 123,
            'data' => [
                'firstName' => 'Jane',
            ],
        ]);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function createJsonRequest(array $payload): ServerRequest
    {
        return $this->createRequest(
            'POST',
            json_encode($payload, JSON_THROW_ON_ERROR),
        );
    }

    private function createRequest(
        string $method,
        string $body,
        string $contentType = 'application/json',
    ): ServerRequest {
        $stream = new Stream('php://temp', 'rw');
        $stream->write($body);
        $stream->rewind();

        return (new ServerRequest())
            ->withMethod($method)
            ->withBody($stream)
            ->withHeader('Content-Type', $contentType);
    }

    private function createProfile(int $uid): Profile
    {
        $profile = new Profile();
        $profile->_setProperty('uid', $uid);
        return $profile;
    }

    /**
     * @param list<Profile> $profiles
     * @return QueryResultInterface<int, Profile>
     */
    private function createQueryResult(array $profiles): QueryResultInterface
    {
        $position = 0;
        $queryResult = $this->createMock(QueryResultInterface::class);
        $queryResult
            ->method('rewind')
            ->willReturnCallback(
                static function () use (&$position): void {
                    $position = 0;
                },
            );
        $queryResult
            ->method('valid')
            ->willReturnCallback(
                static function () use (&$position, $profiles): bool {
                    return array_key_exists($position, $profiles);
                },
            );
        $queryResult
            ->method('current')
            ->willReturnCallback(
                static function () use (&$position, $profiles): ?Profile {
                    return $profiles[$position] ?? null;
                },
            );
        $queryResult
            ->method('key')
            ->willReturnCallback(
                static function () use (&$position): int {
                    return $position;
                },
            );
        $queryResult
            ->method('next')
            ->willReturnCallback(
                static function () use (&$position): void {
                    ++$position;
                },
            );
        return $queryResult;
    }

    private function assertFailure(
        ProfileUpdateRequestResult $result,
        string $error,
        int $statusCode,
    ): void {
        $this->assertFalse($result->isValid());
        $this->assertNull($result->getPayload());
        $this->assertNull($result->getProfile());
        $this->assertSame($error, $result->getError());
        $this->assertSame($statusCode, $result->getStatusCode());
    }
}
