<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Tests\Functional\Plugins;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Http\Stream;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

/**
 * Submits the create forms of the `academicpersonsedit_profileediting` plugin with invalid
 * values through a real frontend request, and checks what the visitor gets back: the form
 * again, with a readable message at the rejected field and the values that were entered.
 *
 * The messages are matched in the rendered markup on purpose. The validation results are
 * stored under the name of the form object, `emailAddressFormData.email`, so a template
 * that asks for any other path renders the form without a message and still answers with
 * the same status code.
 */
final class AcademicPersonsEditFormValidationErrorsTest extends AbstractProfileEditingPluginTestCase
{
    private const PLUGIN_NAMESPACE = 'tx_academicpersonsedit_profileediting';

    protected function setUpTestCase(): void
    {
        parent::setUpTestCase();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/AcademicPersonsEditFormValidationErrors/contract.csv');
    }

    #[Test]
    public function invalidEmailAddressIsReportedAtItsFieldAndKeptInTheForm(): void
    {
        $this->setUpTestCase();
        $contractShowPage = $this->getPageAsFrontendUser(
            $this->extractControllerActionLink($this->getProfileShowPage(), 'Contract', 'show'),
        );

        $content = $this->submitCreateForm(
            $this->extractControllerActionLink($contractShowPage, 'EmailAddress', 'new'),
            'emailAddressFormData',
            ['email' => 'not-an-email', 'type' => 'business'],
        );

        $this->assertFieldErrorMessage($content, 'Please enter a valid email address.');
        $this->assertFormErrorSummary($content);
        $this->assertSubmittedValue($content, 'emailAddressFormData', 'email', 'not-an-email');
        $this->assertStringNotContainsString('emailAddressFormData.email', $content);
        $this->assertStringNotContainsString('Extbase Variable Dump', $content);
        $this->assertSame(0, $this->countRecords('tx_academicpersons_domain_model_email'));
    }

    #[Test]
    public function emptyRequiredFieldIsReportedAtItsFieldAndTheOtherValuesAreKept(): void
    {
        $this->setUpTestCase();

        $content = $this->submitCreateForm(
            $this->extractControllerActionLink($this->getProfileShowPage(), 'Contract', 'new'),
            'contractFormData',
            ['position' => '', 'room' => 'Room 42'],
        );

        $this->assertFieldErrorMessage($content, 'This field is required.');
        $this->assertFormErrorSummary($content);
        $this->assertSubmittedValue($content, 'contractFormData', 'room', 'Room 42');
        $this->assertSame(1, $this->countRecords('tx_academicpersons_domain_model_contract'));
    }

    /**
     * The extension ships a message of its own for this field and error code, keyed by the
     * property path the validation results use, which wins over the message for the code.
     */
    #[Test]
    public function messageOfTheFieldWinsOverTheMessageOfTheErrorCode(): void
    {
        $this->setUpTestCase();

        $content = $this->submitCreateForm(
            $this->extractControllerActionLink($this->getProfileShowPage(), 'Contract', 'new'),
            'contractFormData',
            ['position' => 'Tester', 'validTo' => 'not-a-date'],
        );

        $this->assertFieldErrorMessage($content, '&quot;Valid to&quot; must be a valid date. (Seperated by .)');
        $this->assertStringNotContainsString('Please enter a valid date', $content);
        $this->assertSubmittedValue($content, 'contractFormData', 'validTo', 'not-a-date');
        $this->assertSame(1, $this->countRecords('tx_academicpersons_domain_model_contract'));
    }

    private function assertFieldErrorMessage(string $content, string $message): void
    {
        $this->assertMatchesRegularExpression(
            '@<div class="mb-3 field--has-error [^"]*">\s*<ul>\s*<li class="notification notification--is-danger">\s*'
            . preg_quote($message, '@')
            . '\s*</li>@',
            $content,
            'The rejected field does not show its message.',
        );
    }

    private function assertFormErrorSummary(string $content): void
    {
        $this->assertStringContainsString(
            'The form could not be saved. Please correct the marked fields.',
            $content,
        );
    }

    private function assertSubmittedValue(string $content, string $objectName, string $property, string $value): void
    {
        $name = htmlspecialchars(sprintf('%s[%s][%s]', self::PLUGIN_NAMESPACE, $objectName, $property));
        $this->assertSame(
            1,
            preg_match('@<input[^>]+name="' . preg_quote($name, '@') . '"[^>]*>@', $content, $match),
            sprintf('The form does not render the field "%s" again.', $property),
        );
        $this->assertStringContainsString(
            'value="' . htmlspecialchars($value) . '"',
            $match[0],
            sprintf('The field "%s" lost the submitted value.', $property),
        );
    }

    /**
     * Renders the form at `$formUrl`, and posts it to the `create` action with its hidden
     * fields and the given properties of `$objectName`. Returns the page of the response,
     * the form rendered again when the values are rejected.
     *
     * @param array<string, string> $properties
     */
    private function submitCreateForm(string $formUrl, string $objectName, array $properties): string
    {
        $formPage = $this->getPageAsFrontendUser($formUrl);
        $this->assertSame(
            1,
            preg_match(
                '@<form [^>]*action="([^"]*' . urlencode('[action]') . '=create[^"]*)"(.*?)</form>@s',
                $formPage,
                $formMatch,
            ),
            'The page does not contain a form posting to the "create" action.',
        );

        $parsedBody = $this->pluginArgumentsOfFormAction(html_entity_decode($formMatch[1]));
        preg_match_all(
            '@<input[^>]+type="hidden"[^>]+name="([^"]+)"[^>]+value="([^"]*)"@',
            $formMatch[2],
            $hiddenFields,
            PREG_SET_ORDER,
        );
        $this->assertNotEmpty($hiddenFields, 'The create form contains no hidden fields.');
        foreach ($hiddenFields as $hiddenField) {
            $this->addFormValue($parsedBody, html_entity_decode($hiddenField[1]), html_entity_decode($hiddenField[2]));
        }
        foreach ($properties as $property => $value) {
            $this->addFormValue(
                $parsedBody,
                sprintf('%s[%s][%s]', self::PLUGIN_NAMESPACE, $objectName, $property),
                $value,
            );
        }

        // See `submitProfileForm()` for why the body is provided explicitly.
        $body = new Stream('php://temp', 'rw');
        $body->write(http_build_query($parsedBody));
        $body->rewind();
        $request = (new InternalRequest('https://www.acme.com/home'))
            ->withMethod('POST')
            ->withAddedHeader('Content-Type', 'application/x-www-form-urlencoded')
            ->withBody($body)
            ->withParsedBody($parsedBody);

        return (string)$this->requestAsFrontendUser($request)->getBody();
    }

    /**
     * `extractActionLink()` matches the action alone, while every controller of the plugin
     * has a `new` and a `show` action.
     */
    private function extractControllerActionLink(string $content, string $controller, string $action): string
    {
        preg_match_all('@href="([^"]+)"@', $content, $matches);
        foreach ($matches[1] as $href) {
            $href = html_entity_decode($href);
            if (!str_contains($href, urlencode('[controller]') . '=' . $controller . '&')
                && !str_ends_with($href, urlencode('[controller]') . '=' . $controller)
            ) {
                continue;
            }
            if (!str_contains($href, urlencode('[action]') . '=' . $action . '&')) {
                continue;
            }
            return str_starts_with($href, '/') ? 'https://www.acme.com' . $href : $href;
        }
        $this->fail(sprintf('No link to action "%s" of controller "%s" found in the rendered page.', $action, $controller));
    }

    private function countRecords(string $tableName): int
    {
        return (int)$this->getConnectionPool()
            ->getConnectionForTable($tableName)
            ->count('*', $tableName, ['deleted' => 0]);
    }
}
