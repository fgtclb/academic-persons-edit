<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Tests\Functional\Domain\Factory;

use FGTCLB\AcademicBase\Settings\Validation;
use FGTCLB\AcademicBase\Settings\ValidationSet;
use FGTCLB\AcademicPersonsEdit\Domain\Model\Dto\AbstractFormData;
use FGTCLB\AcademicPersonsEdit\Tests\Functional\AbstractAcademicPersonsEditTestCase;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;
use TYPO3\CMS\Extbase\Persistence\PersistenceManagerInterface;
use TYPO3\CMS\Extbase\Property\PropertyMapper;
use TYPO3\CMS\Extbase\Property\PropertyMappingConfiguration;
use TYPO3\CMS\Extbase\Property\TypeConverter\DateTimeConverter;

/**
 * Shared scaffolding for the factory tests.
 *
 * The factories decide per property whether a submitted JSON value reaches the domain model.
 * The helper maps realistic scalar input through Extbase and marks exactly those mapped values
 * as overrides, mirroring the profile editing controller's request handling.
 */
abstract class AbstractFactoryTestCase extends AbstractAcademicPersonsEditTestCase
{
    /**
     * Maps one action argument of a request to its form data object.
     *
     * @template T of AbstractFormData
     * @param class-string<T> $formDataClassName
     * @param array<string, mixed> $requestArguments complete raw arguments of the request, not only the mapped one
     * @param array<non-empty-string, string> $dateFormats property name to date format
     * @return T
     */
    protected function mapFormData(
        string $formDataClassName,
        string $argumentName,
        array $requestArguments,
        array $dateFormats = [],
    ): AbstractFormData {
        /** @var array<string, mixed> $source */
        $source = $requestArguments[$argumentName] ?? [];
        $configuration = new PropertyMappingConfiguration();
        $configuration->allowAllProperties();
        foreach ($dateFormats as $propertyName => $format) {
            $configuration
                ->forProperty($propertyName)
                ->setTypeConverterOption(
                    DateTimeConverter::class,
                    DateTimeConverter::CONFIGURATION_DATE_FORMAT,
                    $format,
                );
        }
        $formData = $this->get(PropertyMapper::class)->convert($source, $formDataClassName, $configuration);
        $this->assertInstanceOf($formDataClassName, $formData);
        foreach (array_keys($source) as $propertyName) {
            $formData->setPropertyOverride($propertyName, $formData->_getProperty($propertyName));
        }
        return $formData;
    }

    protected function createExtbaseRequest(): ServerRequestInterface
    {
        return new ServerRequest();
    }

    /**
     * A validation set marking a single property read only or disabled, which is how an
     * integrator takes a field out of the editable set.
     */
    protected function createValidationSet(
        string $identifier,
        string $propertyName = '',
        bool $disabled = false,
        bool $readOnly = false,
    ): ValidationSet {
        if ($propertyName === '') {
            return new ValidationSet($identifier, []);
        }
        return new ValidationSet($identifier, [
            $propertyName => new Validation($propertyName, $propertyName, false, $disabled, $readOnly, [], []),
        ]);
    }

    protected function persistenceManager(): PersistenceManager
    {
        $persistenceManager = $this->get(PersistenceManagerInterface::class);
        $this->assertInstanceOf(PersistenceManager::class, $persistenceManager);
        return $persistenceManager;
    }
}
