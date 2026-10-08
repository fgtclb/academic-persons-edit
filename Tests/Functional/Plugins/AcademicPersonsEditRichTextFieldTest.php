<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Tests\Functional\Plugins;

use PHPUnit\Framework\Attributes\Test;

/**
 * The rich text fields of the profile and the contract form start an editor.
 *
 * The shipped `ckeditor.js` attaches the editor to every element with the class
 * `rich-text`, so the class is what makes a field a rich text field, and the page
 * has to load the editor and that script. The flag is passed down in a Fluid array
 * literal, where Fluid 2 (TYPO3 v12) reads an unquoted `true` as a variable of that
 * name and renders the field as a plain textarea. The contract forms did not load
 * the editor at all.
 */
final class AcademicPersonsEditRichTextFieldTest extends AbstractProfileEditingPluginTestCase
{
    private const CONTRACT_ID = 1;

    #[Test]
    public function richTextFieldsOfTheProfileFormCarryTheRichTextClass(): void
    {
        $this->setUpTestCase();

        $content = $this->getPageAsFrontendUser($this->getProfileEditFormUrl());

        foreach (['teachingArea', 'coreCompetences', 'supervisedThesis', 'supervisedDoctoralThesis', 'miscellaneous'] as $identifier) {
            $this->assertTextareaIsRichText($content, 'profile.' . $identifier);
        }
        $this->assertEditorIsLoaded($content);
    }

    #[Test]
    public function officeHoursFieldOfTheNewContractFormStartsAnEditor(): void
    {
        $this->setUpTestCase();

        $content = $this->getPageAsFrontendUser($this->getContractActionUrl('new'));

        $this->assertTextareaIsRichText($content, 'contract.officeHours');
        $this->assertEditorIsLoaded($content);
    }

    #[Test]
    public function officeHoursFieldOfTheContractEditFormStartsAnEditor(): void
    {
        $this->setUpTestCase();
        $this->addContract();

        $content = $this->getPageAsFrontendUser($this->getContractActionUrl('edit'));

        $this->assertTextareaIsRichText($content, 'contract.officeHours');
        $this->assertEditorIsLoaded($content);
    }

    private function addContract(): void
    {
        $this->getConnectionPool()
            ->getConnectionForTable('tx_academicpersons_domain_model_contract')
            ->insert(
                'tx_academicpersons_domain_model_contract',
                [
                    'uid' => self::CONTRACT_ID,
                    'pid' => self::PROFILE_PAGE_ID,
                    'profile' => self::PROFILE_ID,
                    'position' => 'Professor',
                    'sorting' => 1,
                ],
            );
        $this->getConnectionPool()
            ->getConnectionForTable('tx_academicpersons_domain_model_profile')
            ->update(
                'tx_academicpersons_domain_model_profile',
                ['contracts' => 1],
                ['uid' => self::PROFILE_ID],
            );
    }

    /**
     * `extractActionLink()` cannot be used: it does not look at the controller, and
     * the profile show page renders `new` and `edit` links for more than one of them.
     */
    private function getContractActionUrl(string $action): string
    {
        preg_match_all('@href="([^"]+)"@', $this->getProfileShowPage(), $matches);
        foreach ($matches[1] as $href) {
            $href = html_entity_decode($href);
            if (!str_contains($href, urlencode('[controller]') . '=Contract&')
                || !str_contains($href, urlencode('[action]') . '=' . $action . '&')
            ) {
                continue;
            }
            return str_starts_with($href, '/') ? 'https://www.acme.com' . $href : $href;
        }
        $this->fail(sprintf('No link to the "%s" action of the contract controller found on the profile show page.', $action));
    }

    private function assertTextareaIsRichText(string $content, string $elementId): void
    {
        $document = new \DOMDocument();
        $this->assertTrue($document->loadHTML($content, LIBXML_NOERROR | LIBXML_NOWARNING));
        $textareas = (new \DOMXPath($document))->query(sprintf('//textarea[@id="%s"]', $elementId));
        $this->assertNotFalse($textareas);
        $this->assertSame(1, $textareas->length, sprintf('Missing textarea "%s".', $elementId));
        $textarea = $textareas->item(0);
        $this->assertInstanceOf(\DOMElement::class, $textarea);
        $this->assertMatchesRegularExpression(
            '/(^|\s)rich-text(\s|$)/',
            $textarea->getAttribute('class'),
            sprintf('Textarea "%s" is not marked as a rich text field.', $elementId),
        );
    }

    /**
     * The editor itself and the script attaching it to the rich text fields, each
     * loaded once.
     */
    private function assertEditorIsLoaded(string $content): void
    {
        $this->assertSame(
            1,
            preg_match_all('@<script [^>]*src="https://cdn\.ckeditor\.com/[^"]+/ckeditor\.js"@', $content),
            'The page does not load the editor exactly once.',
        );
        $this->assertSame(
            1,
            preg_match_all('@<script [^>]*src="[^"]*academic_persons_edit/Resources/Public/JavaScript/frontend/ckeditor\.js[^"]*"@', $content),
            'The page does not load the script attaching the editor exactly once.',
        );
    }
}
