<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Domain\Model\Dto;

/**
 * @internal to be used only in `EXT:academic_person_edit` and not part of public API. May change at any time.
 */
class AbstractFormData
{
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
}
