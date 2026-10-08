<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Tests\Functional\Plugins;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Http\Stream;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

/**
 * The postcode and the street number of an address are text (ACE-841).
 *
 * The shipped validation settings listed the `number` flag for both, which
 * rendered them as number inputs: a browser refuses `12a` or `SW1A 1AA` there
 * before the form is ever sent. The backend half, the TCA type and what the
 * DataHandler stores, is covered in `academic_persons`.
 */
final class AcademicPersonsEditPhysicalAddressTextFieldsTest extends AbstractProfileEditingPluginTestCase
{
    private const ADDRESS_ID = 1;

    protected function setUpTestCase(): void
    {
        parent::setUpTestCase();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/AcademicPersonsEditPhysicalAddress/contractWithAddress.csv');
    }

    /**
     * Walks from the profile to its contract and from there to the edit form of
     * the address, taking every link from the page that renders it.
     */
    private function getAddressEditFormUrl(): string
    {
        $contractShowUrl = $this->findPluginLink($this->getProfileShowPage(), 'Contract', 'show');

        return $this->findPluginLink($this->getPageAsFrontendUser($contractShowUrl), 'PhysicalAddress', 'edit');
    }

    private function findPluginLink(string $content, string $controller, string $action): string
    {
        preg_match_all('@href="([^"]+)"@', $content, $matches);
        foreach ($matches[1] as $href) {
            $href = html_entity_decode($href);
            if (!str_contains($href, urlencode('[controller]') . '=' . $controller . '&')
                || !str_contains($href, urlencode('[action]') . '=' . $action . '&')
            ) {
                continue;
            }
            return str_starts_with($href, '/') ? 'https://www.acme.com' . $href : $href;
        }
        $this->fail(sprintf('No link to "%s::%s" found in the rendered page.', $controller, $action));
    }

    /**
     * @param array<string, string> $submittedProperties
     */
    private function submitAddressForm(string $formUrl, array $submittedProperties): ResponseInterface
    {
        $submitData = $this->renderEditFormAndExtractSubmitData($formUrl);

        $parsedBody = $this->pluginArgumentsOfFormAction($submitData['action']);
        foreach ($submitData['fields'] as $name => $value) {
            $this->addFormValue($parsedBody, $name, $value);
        }
        foreach ($submittedProperties as $propertyName => $value) {
            $this->addFormValue(
                $parsedBody,
                sprintf('tx_academicpersonsedit_profileediting[addressFormData][%s]', $propertyName),
                $value,
            );
        }

        $body = new Stream('php://temp', 'rw');
        $body->write(http_build_query($parsedBody));
        $body->rewind();

        $request = (new InternalRequest('https://www.acme.com/home'))
            ->withMethod('POST')
            ->withAddedHeader('Content-Type', 'application/x-www-form-urlencoded')
            ->withBody($body)
            ->withParsedBody($parsedBody);

        return $this->requestAsFrontendUser($request);
    }

    /**
     * @return \Generator<string, array{property: string}>
     */
    public static function textPropertyDataSets(): \Generator
    {
        yield 'zip' => ['property' => 'zip'];
        yield 'streetNumber' => ['property' => 'streetNumber'];
    }

    #[DataProvider('textPropertyDataSets')]
    #[Test]
    public function thePropertyIsRenderedAsTextInput(string $property): void
    {
        $this->setUpTestCase();

        $content = $this->getPageAsFrontendUser($this->getAddressEditFormUrl());

        $this->assertSame(
            1,
            preg_match(
                '@<input\b(?=[^>]*\bname="[^"]*\[addressFormData\]\[' . $property . '\]")[^>]*>@',
                $content,
                $control,
            ),
            sprintf('The "%s" control is not rendered.', $property),
        );
        $this->assertStringContainsString('type="text"', $control[0]);
        $this->assertStringContainsString('required="required"', $control[0]);
    }

    /**
     * A regression guard, not a proof of the defect: it passed before the change
     * as well, the editor never cast the value on the server. The browser refused
     * it, which is what the rendering test covers.
     */
    #[Test]
    public function aSubmittedPostcodeAndStreetNumberAreStoredAsEntered(): void
    {
        $this->setUpTestCase();

        $this->submitAddressForm($this->getAddressEditFormUrl(), [
            'street' => 'Stored Street',
            'streetNumber' => '12a',
            'zip' => '01067',
            'city' => 'Stored City',
            'country' => 'Stored Country',
            'type' => 'office',
        ]);

        $row = $this->getConnectionPool()
            ->getConnectionForTable('tx_academicpersons_domain_model_address')
            ->select(['street_number', 'zip'], 'tx_academicpersons_domain_model_address', ['uid' => self::ADDRESS_ID])
            ->fetchAssociative();
        $this->assertSame(['street_number' => '12a', 'zip' => '01067'], $row);
    }
}
