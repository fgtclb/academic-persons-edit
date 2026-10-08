<?php

declare(strict_types=1);

/*
 * This file is part of the "academic_persons_edit" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace FGTCLB\AcademicPersonsEdit\ViewHelpers\Form;

use TYPO3\CMS\Fluid\ViewHelpers\FormViewHelper;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 * Returns the property path of a form field, prefixed with the object name of
 * the surrounding `f:form`, the path the validation results of that field are
 * stored under.
 *
 * The form field ViewHelpers of the core resolve their `property` argument the
 * same way, so a field rendered in a partial is bound to the right object. The
 * result is meant for `f:form.validationResults`, which expects the complete
 * path, for example `emailAddressFormData.email`. Outside of a form, or in a
 * form without an object name, the property is returned unchanged.
 *
 * Usage:
 *
 * ::
 *
 *      <f:form.validationResults for="{pe:form.propertyPath(property: element.identifier)}">
 *          …
 *      </f:form.validationResults>
 *
 * @internal to be used only in `EXT:academic_persons_edit` and not part of public API.
 */
final class PropertyPathViewHelper extends AbstractViewHelper
{
    public function initializeArguments(): void
    {
        $this->registerArgument('property', 'string', 'Name of the form object property', true);
    }

    public function render(): string
    {
        $property = (string)$this->arguments['property'];
        $viewHelperVariableContainer = $this->renderingContext->getViewHelperVariableContainer();
        $formObjectName = $viewHelperVariableContainer->get(FormViewHelper::class, 'formObjectName');
        if (!is_string($formObjectName) || $formObjectName === '') {
            return $property;
        }
        return $formObjectName . '.' . $property;
    }
}
