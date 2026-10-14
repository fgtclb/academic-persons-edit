<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Tests\Functional\Service;

use FGTCLB\AcademicPersons\Settings\AcademicPersonsSettings;
use FGTCLB\AcademicPersonsEdit\Service\ContractContactFields;
use FGTCLB\AcademicPersonsEdit\Service\Exception\EditorRequestException;
use FGTCLB\AcademicPersonsEdit\Tests\Functional\AbstractAcademicPersonsEditTestCase;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Http\Uri;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Extbase\Mvc\ExtbaseRequestParameters;
use TYPO3\CMS\Extbase\Mvc\Request;

/**
 * What a contact request may write, checked against the `emailAddresses`
 * contact section the persons settings ship.
 *
 * A functional test for the same reason as {@see DocumentFieldNormalizerTest}:
 * the field definitions carry translated labels.
 */
final class ContractContactFieldsTest extends AbstractAcademicPersonsEditTestCase
{
    /**
     * The Extbase request of a frontend plugin in the default language, which the
     * label lookup reads on TYPO3 v14.
     */
    private function createRequest(): Request
    {
        return new Request(
            (new ServerRequest('https://www.acme.com/'))
                ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_FE)
                ->withAttribute('language', new SiteLanguage(0, 'en_US.UTF-8', new Uri('https://www.acme.com/'), []))
                ->withAttribute('extbase', new ExtbaseRequestParameters()),
        );
    }

    /**
     * @param array<string, mixed> $fields
     * @return array<string, string>
     */
    private function normalize(array $fields, bool $creating): array
    {
        $section = $this->get(AcademicPersonsSettings::class)->getContractContactSection('emailAddresses');
        $this->assertNotNull($section);
        return $this->get(ContractContactFields::class)->normalizeAndValidateContractContactFields(
            $this->createRequest(),
            $section,
            $fields,
            $creating,
        );
    }

    /**
     * @param array<string, mixed> $fields
     * @return array<string, list<string>>
     */
    private function refuse(array $fields, bool $creating): array
    {
        try {
            $this->normalize($fields, $creating);
        } catch (EditorRequestException $exception) {
            $this->assertSame('validation_failed', $exception->error);
            $this->assertSame(422, $exception->statusCode);
            $this->assertSame('The submitted Contract contact data is invalid.', $exception->responseMessage);
            return $exception->fieldErrors;
        }
        $this->fail('The fields were accepted.');
    }

    #[Test]
    public function aCreationIsAcceptedWithItsRequiredField(): void
    {
        $this->assertSame(
            ['email' => 'office@example.org'],
            $this->normalize(['email' => ' office@example.org '], true),
        );
    }

    #[Test]
    public function aRequiredFieldIsRefusedWhenACreationLacksIt(): void
    {
        $this->assertSame(
            ['email' => ['This field is required.']],
            $this->refuse([], true),
        );
    }

    /**
     * Unlike a document field, a required contact field counts as given when it
     * was submitted, even if its value is then refused. The answer names the
     * refusal alone, not a missing value on top of it.
     */
    #[Test]
    public function aRequiredFieldIsCheckedAgainstTheSubmittedFields(): void
    {
        $this->assertSame(
            ['email' => ['The value must be a string.']],
            $this->refuse(['email' => 123], true),
        );
    }

    #[Test]
    public function aTypeTheInstallationDoesNotOfferIsRefused(): void
    {
        $this->assertSame(
            ['type' => ['The selected value is not available.']],
            $this->refuse(['email' => 'office@example.org', 'type' => 'nonsense'], true),
        );
    }

    #[Test]
    public function aFieldTheSectionDoesNotHaveIsRefused(): void
    {
        $this->assertSame(
            ['street' => ['This field cannot be changed.']],
            $this->refuse(['street' => 'Main street'], false),
        );
    }
}
