<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Tests\Functional\Domain\Factory;

use FGTCLB\AcademicPersons\Settings\Validation;
use FGTCLB\AcademicPersons\Settings\ValidationSet;
use FGTCLB\AcademicPersonsEdit\Controller\AbstractActionController;
use FGTCLB\AcademicPersonsEdit\Domain\Model\Dto\AbstractFormData;
use FGTCLB\AcademicPersonsEdit\Property\TypeConverter\AbstractFormDataConverter;
use FGTCLB\AcademicPersonsEdit\Tests\Functional\AbstractAcademicPersonsEditTestCase;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Extbase\Mvc\ExtbaseRequestParameters;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;
use TYPO3\CMS\Extbase\Persistence\PersistenceManagerInterface;
use TYPO3\CMS\Extbase\Property\PropertyMapper;
use TYPO3\CMS\Extbase\Property\PropertyMappingConfiguration;
use TYPO3\CMS\Extbase\Property\TypeConverter\DateTimeConverter;

/**
 * Shared scaffolding for the factory tests.
 *
 * The factories decide per property whether a submitted value reaches the domain model, and
 * that decision is read off the *request*: {@see AbstractFormData::wasPropertySentInRequest()}
 * looks into `$request->getAttribute('extbase')`. Building that array by hand next to a form
 * data object built by hand — as the unit test has to — allows the two to disagree, which is
 * exactly the state that can never occur in production.
 *
 * {@see mapFormData()} closes that gap: the form data object and the "was sent" information
 * come from one and the same raw argument array, mapped through the real Extbase
 * {@see PropertyMapper} and the extension's own {@see AbstractFormDataConverter}, configured
 * the way {@see AbstractActionController::initializeAction()} configures an action argument.
 */
abstract class AbstractFactoryTestCase extends AbstractAcademicPersonsEditTestCase
{
    /**
     * Maps one action argument of a request to its form data object.
     *
     * @template T of AbstractFormData
     * @param class-string<T> $formDataClassName
     * @param array<string, mixed> $requestArguments complete raw arguments of the request, not only the mapped one
     * @param array<non-empty-string, string> $dateFormats property name to date format, mirroring `AbstractActionController::DATETIME_ARGUMENTS`
     * @param string|null $boundArgumentName the name handed to the converter; defaults to `$argumentName`,
     *                                       and an empty string leaves the form data object without one
     * @return T
     */
    protected function mapFormData(
        string $formDataClassName,
        string $argumentName,
        array $requestArguments,
        array $dateFormats = [],
        ?string $boundArgumentName = null,
    ): AbstractFormData {
        $source = $requestArguments[$argumentName] ?? [];
        $configuration = new PropertyMappingConfiguration();
        $configuration->allowAllProperties();
        $configuration->setTypeConverterOptions(
            AbstractFormDataConverter::class,
            [
                'request' => $this->createExtbaseRequest($requestArguments),
                'argumentName' => $boundArgumentName ?? $argumentName,
            ],
        );
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
        return $formData;
    }

    /**
     * @param array<string, mixed> $requestArguments
     */
    protected function createExtbaseRequest(array $requestArguments): ServerRequestInterface
    {
        return (new ServerRequest())->withAttribute(
            'extbase',
            (new ExtbaseRequestParameters())->setArguments($requestArguments),
        );
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
