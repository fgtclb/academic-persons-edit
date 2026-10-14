<?php

declare(strict_types=1);

/*
 * This file is part of the "academic_persons_edit" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace FGTCLB\AcademicPersonsEdit\Service;

use FGTCLB\AcademicBase\Settings\Validation;
use TYPO3\CMS\Extbase\Validation\Validator\ValidatorInterface;
use TYPO3\CMS\Extbase\Validation\ValidatorResolver;

/**
 * Checks a value of a document or contact field against the character limit
 * and the validators the persons settings configure for it.
 *
 * @internal
 */
final readonly class EditorFieldValidation
{
    public function __construct(
        private ValidatorResolver $validatorResolver,
    ) {}

    /**
     * Validates a single document field against the configured character limit and custom validators.
     *
     * @param string $name The field name used as the validation error key.
     * @param mixed $value The submitted value to validate.
     * @param Validation $validation The validation configuration for the field.
     * @param array<string, list<string>> $errors Reference to the collected validation errors by field name.
     */
    public function validateDocumentField(
        string $name,
        mixed $value,
        Validation $validation,
        array &$errors,
    ): void {
        if (
            $validation->characterLimit > 0
            && is_string($value)
            && RichTextCharacterCounter::count($value) > $validation->characterLimit
        ) {
            $errors[$name][] = sprintf(
                'The text must not exceed %d characters.',
                $validation->characterLimit,
            );
        }
        foreach ($validation->validatorClassNames as $validatorClassName) {
            $validator = $this->validatorResolver->createValidator($validatorClassName);
            if (!$validator instanceof ValidatorInterface) {
                continue;
            }
            $validationResult = $validator->validate($value);
            foreach ($validationResult->getErrors() as $error) {
                $errors[$name][] = $error->getMessage();
            }
        }
    }
}
