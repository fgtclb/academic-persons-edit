<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Domain\Model\Dto;

/**
 * @internal to be used only in `EXT:academic_persons_edit` and not part of public API. May change at any time.
 *
 *  Properties:
 * - property missing from $data: do not change the persisted value
 * - property present with "": explicitly clear the persisted value
 * - property present with a value: update the persisted value
 */
final readonly class ProfileUpdatePayload
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        private int $profileUid,
        private array $data,
    ) {}

    public function getProfileUid(): int
    {
        return $this->profileUid;
    }

    /**
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        return $this->data;
    }

    public function hasProperty(string $propertyName): bool
    {
        return array_key_exists($propertyName, $this->data);
    }

    public function getProperty(string $propertyName): mixed
    {
        return $this->data[$propertyName] ?? null;
    }
}
