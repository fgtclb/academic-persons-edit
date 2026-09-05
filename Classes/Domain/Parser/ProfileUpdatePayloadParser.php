<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Domain\Parser;

use FGTCLB\AcademicPersonsEdit\Domain\Model\Dto\ProfileUpdatePayload;

final class ProfileUpdatePayloadParser
{
    /**
     * @throws \JsonException
     * @throws \UnexpectedValueException
     */
    public function parse(string $body): ProfileUpdatePayload
    {
        $decoded = json_decode(
            $body,
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
        if (!is_array($decoded)) {
            throw new \UnexpectedValueException('The JSON root must be an object.');
        }
        $profileUid = $decoded['profile'] ?? null;
        $data = $decoded['data'] ?? null;
        if (!is_int($profileUid) || $profileUid <= 0 || !is_array($data)) {
            throw new \UnexpectedValueException('The payload must contain a positive integer profile and a data object.');
        }
        return new ProfileUpdatePayload(
            profileUid: $profileUid,
            data: $data,
        );
    }
}
