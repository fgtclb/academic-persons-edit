<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Domain\Model\Dto;

/**
 * @internal to be used only in `EXT:academic_persons_edit` and not part of public API. May change at any time.
 */
abstract class AbstractFormData
{
    /**
     * Override values explicitly selected for the DTO to domain model transformation.
     *
     * @var array<string, mixed>
     */
    private array $propertyOverrides = [];

    // =================================================================================================================
    // Magic methods
    // =================================================================================================================

    public function _getProperty(string $propertyName): mixed
    {
        return $this->_hasProperty($propertyName) && isset($this->{$propertyName})
            ? $this->{$propertyName}
            : null;
    }

    public function _hasProperty(string $propertyName): bool
    {
        return property_exists($this, $propertyName);
    }

    /**
     * Register an override value for a property. This is the intended extension
     * point for the JSON request handler and for PSR-14 event listeners replacing
     * data before the transformation runs.
     */
    final public function setPropertyOverride(string $propertyName, mixed $value): void
    {
        $this->propertyOverrides[$propertyName] = $value;
    }

    final public function hasPropertyOverride(string $propertyName): bool
    {
        return array_key_exists($propertyName, $this->propertyOverrides);
    }

    final public function getPropertyOverride(string $propertyName): mixed
    {
        return $this->propertyOverrides[$propertyName] ?? null;
    }

    /**
     * A property value is applied only when the JSON request handler registered it.
     */
    final public function shouldApplyProperty(string $propertyName): bool
    {
        return $this->hasPropertyOverride($propertyName);
    }
}
