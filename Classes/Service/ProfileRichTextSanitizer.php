<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Service;

use FGTCLB\AcademicPersons\Settings\AcademicPersonsSettings;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use TYPO3\HtmlSanitizer\InitiatorInterface;

#[AsAlias(id: ProfileRichTextSanitizerInterface::class)]
final readonly class ProfileRichTextSanitizer implements ProfileRichTextSanitizerInterface, InitiatorInterface
{
    public function __construct(
        private ProfileRichTextSanitizerBuilder $builder,
        private AcademicPersonsSettings $academicPersonsSettings,
    ) {}

    public function supports(string $propertyName): bool
    {
        return strtolower($this->academicPersonsSettings->getProfileField($propertyName)?->renderType ?? '')
            === 'ckeditor';
    }

    public function sanitize(string $value): string
    {
        return trim($this->builder->build()->sanitize($value, $this));
    }

    public function __toString(): string
    {
        return self::class;
    }
}
