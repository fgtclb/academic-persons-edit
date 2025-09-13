<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Domain\Model\Dto;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Extbase\Mvc\ExtbaseRequestParameters;

/**
 * @internal to be used only in `EXT:academic_person_edit` and not part of public API. May change at any time.
 */
abstract class AbstractFormData
{
    private ?ServerRequestInterface $request = null;
    private ?string $argumentName = null;

    /**
     * Override values applied during the DTO to domain model transformation even
     * when the corresponding property was not sent within the current request.
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

    final public function setRequest(ServerRequestInterface $request): void
    {
        $this->request = $request;
    }

    final protected function getRequest(): ?ServerRequestInterface
    {
        return $this->request;
    }

    final public function setArgumentName(string $argumentName): void
    {
        $this->argumentName = $argumentName;
    }

    final public function getArgumentName(): ?string
    {
        return $this->argumentName;
    }

    protected function getExtbaseRequestParameters(): ?ExtbaseRequestParameters
    {
        return $this->request?->getAttribute('extbase') ?? null;
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function getActionRequestArguments(): ?array
    {
        return $this->getExtbaseRequestParameters()?->getArguments() ?? null;
    }

    /**
     * Determine whether the given property has been sent within the current request
     * for the argument this form data object has been mapped from. Only properties
     * really transferred with the request are considered, allowing the transformation
     * to skip values which have not been part of the submitted form.
     */
    public function wasPropertySentInRequest(string $propertyName): bool
    {
        $arguments = $this->getActionRequestArguments();
        $argumentName = $this->getArgumentName();
        if ($arguments === null || $argumentName === null || $argumentName === '') {
            return false;
        }
        $namespacedArguments = $arguments[$argumentName] ?? null;
        if (!is_array($namespacedArguments)) {
            return false;
        }
        return array_key_exists($propertyName, $namespacedArguments);
    }

    /**
     * Register an override value for a property. Registered overrides are applied
     * during the DTO to domain model transformation even if the property has not
     * been sent within the current request. This is the intended extension point
     * for PSR-14 event listeners filling up data from other sources before the
     * transformation runs.
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
     * A property value must be applied to the domain model when it has either been
     * sent within the current request, or has been registered as override value.
     */
    final public function shouldApplyProperty(string $propertyName): bool
    {
        return $this->wasPropertySentInRequest($propertyName)
            || $this->hasPropertyOverride($propertyName);
    }
}
