<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Service;

interface ProfileRichTextSanitizerInterface
{
    public function supports(string $propertyName): bool;

    public function sanitize(string $value): string;
}
