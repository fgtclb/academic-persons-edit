<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Service;

use FGTCLB\AcademicPersons\Domain\Model\Profile;
use FGTCLB\AcademicPersons\Domain\Repository\ProfileRepository;
use FGTCLB\AcademicPersonsEdit\Domain\Model\Dto\ProfileUpdateRequestResult;
use FGTCLB\AcademicPersonsEdit\Domain\Parser\ProfileUpdatePayloadParser;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Context\Context;

final readonly class ProfileUpdateRequestService
{
    public function __construct(
        private Context $context,
        private ProfileRepository $profileRepository,
        private ProfileUpdatePayloadParser $payloadParser,
    ) {}

    public function validate(ServerRequestInterface $request): ProfileUpdateRequestResult
    {
        if ($request->getMethod() !== 'POST') {
            return ProfileUpdateRequestResult::failure(
                'method_not_allowed',
                405,
            );
        }
        $contentType = strtolower(trim(explode(';', $request->getHeaderLine('Content-Type'), 2)[0]));
        if ($contentType !== 'application/json') {
            return ProfileUpdateRequestResult::failure(
                'unsupported_media_type',
                415,
            );
        }

        try {
            $payload = $this->payloadParser->parse(
                (string)$request->getBody()
            );
        } catch (\JsonException) {
            return ProfileUpdateRequestResult::failure(
                'invalid_json',
                400,
            );
        } catch (\UnexpectedValueException) {
            return ProfileUpdateRequestResult::failure(
                'invalid_payload',
                400,
            );
        }

        if (
            $this->context->getPropertyFromAspect(
                'frontend.user',
                'isLoggedIn',
                false,
            ) !== true
        ) {
            return ProfileUpdateRequestResult::failure(
                'authentication_required',
                401,
            );
        }

        $profile = $this->findEditableProfile(
            $payload->getProfileUid()
        );

        if ($profile === null) {
            return ProfileUpdateRequestResult::failure(
                'profile_not_editable',
                403,
            );
        }

        return ProfileUpdateRequestResult::success(
            $payload,
            $profile,
        );
    }

    public function findEditableProfile(
        int $profileUid,
    ): ?Profile {
        if ($profileUid <= 0) {
            return null;
        }

        $frontendUserId = (int)$this->context->getPropertyFromAspect(
            'frontend.user',
            'id',
            0,
        );

        foreach (
            $this->profileRepository->findByFrontendUser($frontendUserId) as $profile
        ) {
            if (
                $profile instanceof Profile
                && $profile->getUid() === $profileUid
            ) {
                return $profile;
            }
        }

        return null;
    }
}
