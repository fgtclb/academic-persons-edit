<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Domain\Model\Dto;

use FGTCLB\AcademicPersons\Domain\Model\Profile;

final readonly class ProfileUpdateRequestResult
{
    private function __construct(
        private bool $valid,
        private ?ProfileUpdatePayload $payload = null,
        private ?Profile $profile = null,
        private ?string $error = null,
        private int $statusCode = 200,
    ) {}

    public static function success(
        ProfileUpdatePayload $payload,
        Profile $profile,
    ): self {
        return new self(
            valid: true,
            payload: $payload,
            profile: $profile,
        );
    }

    public static function failure(
        string $error,
        int $statusCode,
    ): self {
        return new self(
            valid: false,
            error: $error,
            statusCode: $statusCode,
        );
    }

    public function isValid(): bool
    {
        return $this->valid;
    }

    public function getPayload(): ?ProfileUpdatePayload
    {
        return $this->payload;
    }

    public function getProfile(): ?Profile
    {
        return $this->profile;
    }

    public function getError(): ?string
    {
        return $this->error;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
}
