<?php

declare(strict_types=1);

/*
 * This file is part of the "academic_persons_edit" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace FGTCLB\AcademicPersonsEdit\Service;

use FGTCLB\AcademicPersons\Domain\Model\FunctionType;
use FGTCLB\AcademicPersons\Domain\Model\Location;
use FGTCLB\AcademicPersons\Domain\Model\OrganisationalUnit;
use FGTCLB\AcademicPersons\Domain\Repository\FunctionTypeRepository;
use FGTCLB\AcademicPersons\Domain\Repository\LocationRepository;
use FGTCLB\AcademicPersons\Domain\Repository\OrganisationalUnitRepository;
use FGTCLB\AcademicPersons\Settings\DocumentSection;
use FGTCLB\AcademicPersonsEdit\Service\Exception\EditorRequestException;
use TYPO3\CMS\Extbase\Mvc\RequestInterface;

/**
 * Turns the field values a document request submits into the values a contract
 * or profile information entry stores, and refuses them with the errors per
 * field when one does not validate.
 *
 * @phpstan-import-type DocumentFieldDefinition from DocumentFieldDefinitions
 *
 * @internal
 */
final readonly class DocumentFieldNormalizer
{
    public function __construct(
        private DocumentFieldDefinitions $documentFieldDefinitions,
        private EditorFieldValidation $editorFieldValidation,
        private ProfileRichTextSanitizerInterface $profileRichTextSanitizer,
        private FunctionTypeRepository $functionTypeRepository,
        private OrganisationalUnitRepository $organisationalUnitRepository,
        private LocationRepository $locationRepository,
    ) {}

    /**
     * Normalize and validate submitted document field values for a section.
     *
     * The method checks whether each field exists in the section definition,
     * whether it is editable, normalizes the incoming values to the expected PHP
     * types, and applies the section's validation rules. Required fields are also
     * enforced when creating a new document.
     *
     * A field the section does not have is refused. A locked one, managed on
     * the record or locked with `readonly`, `frontendreadonly` or `disabled`,
     * is dropped and keeps its stored value, because the editor sends every
     * field of the open record, the locked ones included.
     *
     * @param DocumentSection $section The document section whose field definitions and validation rules apply.
     * @param array<string, mixed> $fields Raw field data submitted by the client, keyed by field name.
     * @param bool $creating Whether the document is currently being created.
     * @param list<string> $managedProperties The properties the synchronisation manages on the record.
     * @return array<string, mixed> Normalized and validated field values keyed by field name.
     */
    public function normalizeAndValidateDocumentFields(
        RequestInterface $request,
        DocumentSection $section,
        array $fields,
        bool $creating,
        array $managedProperties = [],
    ): array {
        $definitions = $this->documentFieldDefinitions->getDocumentFieldDefinitions($request, $section, null, $managedProperties);
        $definitionsByName = [];
        foreach ($definitions as $definition) {
            $definitionsByName[$definition['name']] = $definition;
        }
        $errors = [];
        $normalized = [];
        foreach ($fields as $name => $value) {
            $definition = $definitionsByName[$name] ?? null;
            if ($definition === null) {
                $errors[$name][] = 'This field cannot be changed.';
                continue;
            }
            if ($definition['readOnly'] || $definition['disabled']) {
                continue;
            }
            try {
                $normalized[$name] = $this->normalizeDocumentFieldValue(
                    $name,
                    $definition['type'],
                    $value,
                    $definition['richText'],
                    $definition['min'],
                    $definition['max'],
                );
            } catch (\UnexpectedValueException $exception) {
                $errors[$name][] = $exception->getMessage();
            }
        }
        foreach ($definitionsByName as $name => $definition) {
            if ($creating && $definition['required'] && !array_key_exists($name, $normalized)) {
                $errors[$name][] = 'This field is required.';
            }
            if (!array_key_exists($name, $normalized)) {
                continue;
            }
            $validation = $section->validationSet->get($name);
            if ($validation !== null) {
                $this->editorFieldValidation->validateDocumentField($name, $normalized[$name], $validation, $errors);
            }
        }
        if ($errors !== []) {
            throw new EditorRequestException(
                'validation_failed',
                422,
                'The submitted document data is invalid.',
                $errors,
                1792001169,
            );
        }
        return $normalized;
    }

    /**
     * Normalizes a submitted document field value according to its declared field type.
     *
     * @param string $name The field name used to resolve select-based entity lookups.
     * @param string $type The document field type (for example: checkbox, number, date, select, or text).
     * @param mixed $value The submitted raw value to normalize.
     * @param bool $richText Whether the value should be sanitized as rich text.
     * @param int|null $min Lower bound of a number field, null for none.
     * @param int|null $max Upper bound of a number field, null for none.
     * @return mixed The normalized value for storage or validation.
     * @throws \UnexpectedValueException If the value does not match the expected format for the given type.
     */
    private function normalizeDocumentFieldValue(
        string $name,
        string $type,
        mixed $value,
        bool $richText,
        ?int $min = null,
        ?int $max = null,
    ): mixed {
        if ($type === 'checkbox') {
            if (!is_bool($value)) {
                throw new \UnexpectedValueException('The value must be boolean.');
            }
            return $value;
        }
        if ($type === 'number') {
            if ($value === null || $value === '') {
                return null;
            }
            $number = null;
            if (is_int($value)) {
                $number = $value;
            } elseif (is_string($value) && preg_match('/^-?\d+$/', $value) === 1) {
                $number = (int)$value;
            }
            if ($number === null) {
                throw new \UnexpectedValueException('The value must be an integer.');
            }
            // The bounds of the control are enforced again here: a browser that
            // ignores them, and every client that is not one, reach the same
            // refusal instead of a clamped value.
            if (($min !== null && $number < $min) || ($max !== null && $number > $max)) {
                throw new \UnexpectedValueException(sprintf(
                    'The value must be between %d and %d.',
                    $min ?? PHP_INT_MIN,
                    $max ?? PHP_INT_MAX,
                ));
            }
            return $number;
        }
        if ($type === 'date') {
            if ($value === null || $value === '') {
                return null;
            }
            if (!is_string($value)) {
                throw new \UnexpectedValueException('The value must be a date.');
            }
            $date = $this->documentFieldDefinitions->parseDocumentDate($value);
            if (!$date instanceof \DateTime) {
                throw new \UnexpectedValueException('The value must be a valid date.');
            }
            return $date;
        }
        if ($type === 'select') {
            if ($value === null || $value === '' || $value === 0) {
                return null;
            }
            $uid = is_int($value)
                ? $value
                : (is_string($value) && preg_match('/^\d+$/', $value) === 1 ? (int)$value : 0);
            if ($uid <= 0) {
                throw new \UnexpectedValueException('The selected value is invalid.');
            }
            $entity = $this->findDocumentSelectEntity($name, $uid);
            if ($entity === null) {
                throw new \UnexpectedValueException('The selected value is not available.');
            }
            return $entity;
        }
        if (!is_string($value)) {
            throw new \UnexpectedValueException('The value must be a string.');
        }
        return ($richText || in_array($name, ['bodytext', 'officeHours'], true))
            ? $this->profileRichTextSanitizer->sanitize($value)
            : trim($value);
    }

    /**
     * Finds the matching document select entity for the given field name and UID.
     *
     * @param string $name The field name identifying the repository to search in.
     * @param int $uid The UID of the entity to look up.
     * @return FunctionType|OrganisationalUnit|Location|null The matching entity or null if no entity with the given UID exists.
     */
    private function findDocumentSelectEntity(
        string $name,
        int $uid,
    ): FunctionType|OrganisationalUnit|Location|null {
        $items = match ($name) {
            'functionType' => $this->functionTypeRepository->findAll(),
            'organisationalUnit' => $this->organisationalUnitRepository->findAll(),
            'location' => $this->locationRepository->findAll(),
            default => [],
        };
        foreach ($items as $item) {
            if ((int)$item->getUid() === $uid) {
                return $item;
            }
        }
        return null;
    }
}
