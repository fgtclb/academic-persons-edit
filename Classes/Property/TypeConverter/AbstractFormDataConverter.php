<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Property\TypeConverter;

use FGTCLB\AcademicPersonsEdit\Controller\AbstractActionController;
use FGTCLB\AcademicPersonsEdit\Domain\Model\Dto\AbstractFormData;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Extbase\Property\PropertyMappingConfigurationInterface;
use TYPO3\CMS\Extbase\Property\TypeConverter\ObjectConverter;

/**
 * Provides type converter for argument types based on {@see AbstractFormData} to set
 * the request to the object instance using {@see AbstractFormData::setRequest()} as
 * long as provided within the configuration.
 *
 * {@see AbstractActionController::setCurrentRequestForAbstractFormDataBasedArguments()}
 * set the request as TypeConverter options for all {@see AbstractFormData} arguments,
 * called from {@see AbstractActionController::initializeAction()} and therefore for
 * all controller actions extending {@see AbstractActionController}. Sadly, there is
 * no better way currently.
 *
 * Note that fallback to `$GLOBALS['TYPO3_REQUEST']` is added for now, but may be
 * removed and is considered experimental for now.
 */
final class AbstractFormDataConverter extends ObjectConverter
{
    /**
     * @param mixed $source
     * @param array<string, mixed> $convertedChildProperties
     */
    public function convertFrom(
        $source,
        string $targetType,
        array $convertedChildProperties = [],
        ?PropertyMappingConfigurationInterface $configuration = null
    ): ?object {
        /** @var mixed $request */
        $request = $configuration?->getConfigurationValue(self::class, 'request') ?? $GLOBALS['TYPO3_REQUEST'] ?? null;
        $argumentName = $configuration?->getConfigurationValue(self::class, 'argumentName') ?? null;
        $object = parent::convertFrom($source, $targetType, $convertedChildProperties, $configuration);
        if (!($object instanceof AbstractFormData)) {
            return $object;
        }
        if ($request instanceof ServerRequestInterface) {
            $object->setRequest($request);
        }
        if (is_string($argumentName) && $argumentName !== '') {
            $object->setArgumentName($argumentName);
        }
        return $object;
    }
}
