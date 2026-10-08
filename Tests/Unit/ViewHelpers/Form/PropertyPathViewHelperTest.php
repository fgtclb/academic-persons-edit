<?php

declare(strict_types=1);

/*
 * This file is part of the "academic_persons_edit" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace FGTCLB\AcademicPersonsEdit\Tests\Unit\ViewHelpers\Form;

use FGTCLB\AcademicPersonsEdit\ViewHelpers\Form\PropertyPathViewHelper;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Fluid\ViewHelpers\FormViewHelper;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;
use TYPO3Fluid\Fluid\Core\ViewHelper\ViewHelperVariableContainer;

/**
 * Pins where the ViewHelper takes the object name from: the entry `f:form` stores for its
 * form fields, `FormViewHelper::class` / `formObjectName`. Should the core move it, the
 * messages of the editor fields disappear without any error, and this test turns red.
 */
final class PropertyPathViewHelperTest extends UnitTestCase
{
    private function renderPropertyPath(ViewHelperVariableContainer $viewHelperVariableContainer, string $property): string
    {
        $renderingContext = $this->createMock(RenderingContextInterface::class);
        $renderingContext->method('getViewHelperVariableContainer')->willReturn($viewHelperVariableContainer);

        $subject = new PropertyPathViewHelper();
        $subject->setRenderingContext($renderingContext);
        $subject->setArguments(['property' => $property]);

        return $subject->render();
    }

    #[Test]
    public function prefixesThePropertyWithTheObjectNameOfTheSurroundingForm(): void
    {
        $viewHelperVariableContainer = new ViewHelperVariableContainer();
        $viewHelperVariableContainer->add(FormViewHelper::class, 'formObjectName', 'emailAddressFormData');

        $this->assertSame('emailAddressFormData.email', $this->renderPropertyPath($viewHelperVariableContainer, 'email'));
    }

    #[Test]
    public function returnsThePropertyOutsideOfAForm(): void
    {
        $this->assertSame('email', $this->renderPropertyPath(new ViewHelperVariableContainer(), 'email'));
    }

    #[Test]
    public function returnsThePropertyInAFormWithoutObjectName(): void
    {
        $viewHelperVariableContainer = new ViewHelperVariableContainer();
        $viewHelperVariableContainer->add(FormViewHelper::class, 'formObjectName', '');

        $this->assertSame('email', $this->renderPropertyPath($viewHelperVariableContainer, 'email'));
    }
}
