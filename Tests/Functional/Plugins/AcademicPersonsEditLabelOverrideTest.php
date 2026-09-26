<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Tests\Functional\Plugins;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Http\Stream;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

/**
 * Label overrides through `_LOCAL_LANG` reach every kind of translation the profile editor
 * renders or answers, on every supported core version: a label comes from `locallang.xlf`,
 * an override of the extension (`plugin.tx_academicpersonsedit`) replaces it, and an
 * override of the plugin (`plugin.tx_academicpersonsedit_profileediting`) replaces both.
 *
 * Up to TYPO3 v13 the core builds the TypoScript path from the extension name a translation
 * passes, only lowercased, so a name with underscores read `plugin.tx_academic_persons_edit`
 * instead. TYPO3 v14 strips the underscores itself, but reads the override of the plugin
 * only from the Extbase request, which a translation in PHP has to hand on.
 *
 * The kinds: a multi-line `f:translate` tag and the language column the controller
 * translates, both in the list; a call inline in an attribute, one nested, with escaped
 * quotes, in the arguments of a partial, a help text and the heading of a document
 * section, both full `LLL:` references into the files of `academic_persons`, and the
 * options of a select, which a service translates from such a reference, all in the
 * editor; and the labels the controller translates into the JSON answers of the document
 * and contact forms - a field label, a help text, the value of a checkbox, the label of a
 * contact section and one of a contact summary, and the name of a country, a reference into
 * the country list of the core. An override of a full reference is keyed by the id of the
 * label inside the file it points to.
 *
 * Without a name of its own, a full reference into another extension's file read the
 * overrides of the editor on TYPO3 v13 and those of `academic_persons` on v14, and in PHP no
 * override at all; every such call now names the editor.
 *
 * Up to TYPO3 v13 the core also keeps the labels of a language file, overrides included,
 * for the rest of the request: once one translation has read the right path, the ones after
 * it show its overrides whatever name they pass. A case therefore proves its own call on v13
 * only as the first translation of its file in the request, which only some of the cases
 * here are. On TYPO3 v14 an underscored name reads the same paths; there a case proves that
 * its call names the editor at all, which a full reference into the files of
 * `academic_persons` did not. Every override case was shown to fail before the change: the
 * help texts, the heading of a document section and the country option on v14, the other
 * cases on v13.
 *
 * The calls in PHP translate through one method that defaults to the name of the editor, and
 * the check of the extension names of all translations holds the templates.
 */
final class AcademicPersonsEditLabelOverrideTest extends AbstractFrontendProfilePluginTestCase
{
    private const LIST_URL = 'https://www.acme.com/home';

    protected function setUp(): void
    {
        // Literal help texts for the room of a contract and the street number of an address.
        $this->addTestExtensionsToLoad('tests/test-literal-helptext');
        parent::setUp();
    }

    private function setUpLabelTestCase(string $setup): void
    {
        $this->setUpProfileEditingTestCase();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/AcademicPersonsEditProfileEditing/structuredDocumentSections.csv');
        // A profile for every language, the one the list labels in PHP.
        $this->getConnectionPool()
            ->getConnectionForTable('tx_academicpersons_domain_model_profile')
            ->insert('tx_academicpersons_domain_model_profile', [
                'uid' => 2,
                'pid' => self::PROFILE_PAGE_ID,
                'sys_language_uid' => -1,
                'first_name' => 'Erika',
                'last_name' => 'Beispiel',
                'slug' => 'erika-beispiel',
                'frontend_users' => 1,
            ]);
        $this->getConnectionPool()
            ->getConnectionForTable('tx_academicpersons_feuser_mm')
            ->insert('tx_academicpersons_feuser_mm', [
                'uid_local' => 2,
                'uid_foreign' => self::FRONTEND_USER_ID,
                'sorting' => 2,
                'sorting_foreign' => 2,
            ]);
        $connection = $this->getConnectionPool()->getConnectionForTable('sys_template');
        $template = $connection->select(['uid', 'config'], 'sys_template', ['pid' => 1])->fetchAssociative();
        $this->assertIsArray($template);
        $connection->update('sys_template', ['config' => $template['config'] . LF . $setup], ['uid' => $template['uid']]);
    }

    /**
     * @param array<string, string> $overrides TypoScript path => label
     */
    private function localLang(string $key, array $overrides): string
    {
        $setup = '';
        foreach ($overrides as $path => $label) {
            $setup .= $path . '._LOCAL_LANG.default.' . $key . ' = ' . $label . LF;
        }
        return $setup;
    }

    /**
     * The label a case reads, from where the editor shows it.
     */
    private function renderedLabel(string $where, string $selector, string $setup): string
    {
        $this->setUpLabelTestCase($setup);

        return match ($where) {
            'list' => $this->normalizeWhitespace($this->getPageAsFrontendUser(self::LIST_URL)),
            'editor' => $this->normalizeWhitespace($this->renderProfileEditingPage()),
            'document form' => $this->selectFromJson($this->requestForm('data-document-form-url', [
                'section' => 'contracts',
                'record' => 1,
                'mode' => 'edit',
            ]), $selector),
            'contact form' => $this->selectFromJson($this->requestForm('data-contract-contact-form-url', [
                'contract' => 1,
                'section' => 'physicalAddresses',
                'mode' => 'add',
            ]), $selector),
            default => throw new \LogicException('Unknown place ' . $where, 1790406901),
        };
    }

    private function assertLabel(string $where, string $selector, string $expected, string $actual): void
    {
        if ($where === 'list' || $where === 'editor') {
            $this->assertStringContainsString(sprintf($selector, $expected), $actual);
            return;
        }
        $this->assertSame($expected, $actual);
    }

    private function normalizeWhitespace(string $content): string
    {
        return (string)preg_replace('/\s+/', ' ', $content);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function requestForm(string $urlAttribute, array $data): array
    {
        $editor = $this->renderProfileEditingPage();
        $this->assertSame(1, preg_match('@\b' . preg_quote($urlAttribute, '@') . '="([^"]+)"@', $editor, $match));
        $url = html_entity_decode($match[1]);
        $body = new Stream('php://temp', 'rw');
        $body->write(json_encode(['profile' => self::PROFILE_ID, 'data' => $data], JSON_THROW_ON_ERROR));
        $body->rewind();
        $response = $this->requestAsFrontendUser(
            (new InternalRequest(str_starts_with($url, '/') ? 'https://www.acme.com' . $url : $url))
                ->withMethod('POST')
                ->withAddedHeader('Content-Type', 'application/json')
                ->withAddedHeader('X-Requested-With', 'XMLHttpRequest')
                ->withBody($body),
        );
        $this->assertSame(200, $response->getStatusCode(), (string)$response->getBody());
        $decoded = json_decode((string)$response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($decoded);

        return $decoded;
    }

    /**
     * `field:<name>:<property>`, `option:<field>:<value>` or `section:<identifier>:<path>`.
     *
     * @param array<string, mixed> $answer
     */
    private function selectFromJson(array $answer, string $selector): string
    {
        $parts = explode(':', $selector);
        $type = array_shift($parts);
        $name = array_shift($parts);
        $list = $type === 'section' ? ($answer['contactSections'] ?? []) : ($answer['fields'] ?? []);
        $this->assertIsArray($list);
        $keyName = $type === 'section' ? 'identifier' : 'name';
        foreach ($list as $entry) {
            if (!is_array($entry) || ($entry[$keyName] ?? null) !== $name) {
                continue;
            }
            if ($type === 'option') {
                foreach ($entry['options'] ?? [] as $option) {
                    if (is_array($option) && ($option['value'] ?? null) === $parts[0]) {
                        return (string)($option['label'] ?? '');
                    }
                }
                break;
            }
            $value = $entry;
            foreach ($parts as $part) {
                $this->assertIsArray($value);
                $value = $value[$part] ?? null;
            }
            return (string)(is_scalar($value) ? $value : '');
        }
        $this->fail(sprintf('The answer holds nothing for "%s".', $selector));
    }

    /**
     * Every kind of translation of this extension's own labels: where it shows, how to find
     * it there, its key and the label of `locallang.xlf`.
     *
     * @return \Generator<string, array{0: string, 1: string, 2: string, 3: string}>
     */
    public static function translationDataProvider(): \Generator
    {
        yield 'list, multi-line tag' => [
            'list', '<h1 id="academic-persons-profile-editing-list-heading" class="h2 fw-normal mb-4"> %s </h1>',
            'list.profile.assigned', 'Assigned profiles',
        ];
        yield 'list, language column translated by the controller' => [
            'list', '<td class="text-nowrap">%s</td>',
            'list.language.all', 'All languages',
        ];
        yield 'editor, inline in an attribute' => [
            'editor', 'data-message-saving="%s"',
            'profileEditing.status.saving', 'Saving changes…',
        ];
        yield 'editor, nested with escaped quotes in the arguments of a partial' => [
            'editor', 'aria-label="Form actions: %s"',
            'profileEditing.section.personal', 'Personal data',
        ];
        yield 'editor, help text of a field, a full reference' => [
            'editor', 'data-bs-content="%s"',
            'helptext.title', 'Here you can change a title.',
        ];
        yield 'editor, heading of a document section, a full reference' => [
            'editor', 'document-section-contracts-heading" class="display-6 fw-normal text-start mb-0"> %s </h2>',
            'tx_academicpersons_domain_model_profile.columns.contracts.label', 'Employee Contracts',
        ];
        yield 'editor, select option translated by the options service with a full reference' => [
            'editor', '<option value="ms">%s</option>',
            'tx_academicpersons_domain_model_profile.columns.gender.items.ms', 'Ms.',
        ];
        yield 'document form, field label translated by the controller' => [
            'document form', 'field:position:label',
            'contract.position.label', 'Position',
        ];
        yield 'document form, checkbox value translated by the controller' => [
            'document form', 'field:publish:displayValue',
            'profileEditing.visibility.public', 'Public',
        ];
        yield 'document form, help text of a field translated by the controller, a full reference' => [
            'document form', 'field:position:helptext',
            'helptext.contracts.position', 'Enter the position or role associated with this employment contract.',
        ];
        yield 'contact form, help text of a field translated by the controller, a full reference' => [
            'contact form', 'field:street:helptext',
            'helptext.contractContact.street', 'Enter the street name without the building number.',
        ];
        yield 'contact form, country option translated by the controller, a full reference into the core' => [
            'contact form', 'option:country:DE',
            'DE.name', 'Germany',
        ];
        yield 'document form, contact section label translated by the controller' => [
            'document form', 'section:physicalAddresses:label',
            'contract.physicalAddresses', 'Physical addresses',
        ];
        yield 'document form, contact summary label translated by the controller' => [
            'document form', 'section:physicalAddresses:items:0:summary:0:label',
            'physicalAddress.street.label', 'Street',
        ];
    }

    #[DataProvider('translationDataProvider')]
    #[Test]
    public function theEditorRendersTheLabelOfTheLanguageFile(string $where, string $selector, string $key, string $label): void
    {
        $this->assertLabel($where, $selector, $label, $this->renderedLabel($where, $selector, ''));
    }

    #[DataProvider('translationDataProvider')]
    #[Test]
    public function theEditorRendersTheLabelOfTheExtension(string $where, string $selector, string $key, string $label): void
    {
        $actual = $this->renderedLabel($where, $selector, $this->localLang($key, [
            'plugin.tx_academicpersonsedit' => 'Extension label',
        ]));

        $this->assertLabel($where, $selector, 'Extension label', $actual);
    }

    #[DataProvider('translationDataProvider')]
    #[Test]
    public function theEditorRendersTheLabelOfThePlugin(string $where, string $selector, string $key, string $label): void
    {
        $actual = $this->renderedLabel($where, $selector, $this->localLang($key, [
            'plugin.tx_academicpersonsedit_profileediting' => 'Plugin label',
        ]));

        $this->assertLabel($where, $selector, 'Plugin label', $actual);
    }

    #[DataProvider('translationDataProvider')]
    #[Test]
    public function theLabelOfThePluginWinsOverTheOneOfTheExtension(string $where, string $selector, string $key, string $label): void
    {
        $actual = $this->renderedLabel($where, $selector, $this->localLang($key, [
            'plugin.tx_academicpersonsedit' => 'Extension label',
            'plugin.tx_academicpersonsedit_profileediting' => 'Plugin label',
        ]));

        $this->assertLabel($where, $selector, 'Plugin label', $actual);
        $this->assertStringNotContainsString('Extension label', $actual);
    }

    /**
     * @return \Generator<string, array{0: string, 1: string}>
     */
    public static function addressTypeDataProvider(): \Generator
    {
        yield 'label of the configuration' => ['', 'Business'];
        yield 'label of the extension' => ['plugin.tx_academicpersons._LOCAL_LANG.default.Business = Office', 'Office'];
    }

    /**
     * The types of an address are labelled by the configuration of `academic_persons`,
     * and the controller translates such a label with that extension's name. Its file has
     * no entry for them, so the configured label stands until a site overrides it, under the
     * path of `academic_persons`. On TYPO3 v14 its path for the plugin of the request,
     * `plugin.tx_academicpersons_profileediting`, is read too; that path belongs to no plugin
     * of `academic_persons` and is not tested.
     */
    #[DataProvider('addressTypeDataProvider')]
    #[Test]
    public function theTypeOfAnAddressReadsTheLabelOfItsExtension(string $setup, string $label): void
    {
        $this->assertSame($label, $this->renderedLabel('contact form', 'option:type:business', $setup));
    }

    /**
     * @return \Generator<string, array{0: string, 1: string, 2: string}>
     */
    public static function literalHelptextDataProvider(): \Generator
    {
        yield 'document form, room of a contract' => [
            'document form', 'field:room:helptext', 'Ask the front desk for the room number.',
        ];
        yield 'contact form, street number of an address' => [
            'contact form', 'field:streetNumber:helptext', 'Leave it empty if the building has no number.',
        ];
    }

    /**
     * A site may configure the help text of a contract or contact field as literal text
     * rather than as a label reference. Translated with the name of the editor, such a text
     * is an unknown key and is shown as it is; translated without a name, as before, the core
     * refused a short key and the form could not be opened.
     */
    #[DataProvider('literalHelptextDataProvider')]
    #[Test]
    public function aLiteralHelpTextOfTheFormsIsShownAsItIs(string $where, string $selector, string $text): void
    {
        $this->assertSame($text, $this->renderedLabel($where, $selector, ''));
    }
}
