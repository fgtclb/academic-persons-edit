<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Tests\Functional\Plugins;

use FGTCLB\AcademicPersons\Settings\AcademicPersonsSettingsFactory;
use FGTCLB\AcademicPersonsEdit\Controller\ProfileController;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Http\Stream;
use TYPO3\CMS\Core\Localization\DateFormatter;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Localization\Locale;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

/**
 * Covers the profile and structured-section AJAX contracts as well as the
 * image editor of the `academicpersonsedit_profileediting` content element.
 */
final class AcademicPersonsEditProfileEditingTest extends AbstractFrontendProfilePluginTestCase
{
    /**
     * The lecture title of the fixture, long enough to claim a row of its own.
     *
     * Lectures is the widest section the settings configure - three date
     * columns, five actions and the drag handle - so it is the one that shows
     * whether a long title takes the space away from the action group or gives
     * way to it.
     */
    private const LONG_LECTURE_TITLE = 'Interdisciplinary perspectives on community development, welfare policy and social work practice';

    private function seedStructuredDocumentSections(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/AcademicPersonsEditProfileEditing/structuredDocumentSections.csv');
        $this->getConnectionPool()
            ->getConnectionForTable('tx_academicpersons_domain_model_profile')
            ->update(
                'tx_academicpersons_domain_model_profile',
                [
                    'contracts' => 2,
                    'cooperation' => 2,
                    'lectures' => 1,
                    'memberships' => 2,
                    'press_media' => 0,
                    'publications' => 1,
                    'scientific_research' => 1,
                    'vita' => 2,
                ],
                ['uid' => self::PROFILE_ID],
            );
    }

    private function xpathOf(string $content): \DOMXPath
    {
        $document = new \DOMDocument();
        $this->assertTrue($document->loadHTML($content, LIBXML_NOERROR | LIBXML_NOWARNING));

        return new \DOMXPath($document);
    }

    /**
     * The number of nodes an expression matches.
     *
     * `DOMXPath::query()` returns `false` for an invalid expression, and a
     * `->length` read on that is a fatal error rather than a failed assertion,
     * so the two are separated here once instead of at every call site that
     * only wants the count.
     */
    private function nodeCount(\DOMXPath $xpath, string $expression, ?\DOMNode $context = null): int
    {
        $found = $xpath->query($expression, $context);
        $this->assertNotFalse($found, sprintf('"%s" is not a valid XPath expression.', $expression));

        return $found->length;
    }

    /**
     * The first element an expression matches, or a failed assertion.
     *
     * `DOMXPath::query()` answers `false` for an invalid expression and
     * `DOMNodeList::item()` answers `null` for an empty one, so a caller that
     * only wants the one node it is asserting on has three things to narrow
     * before it can. They are narrowed here once.
     */
    private function firstElement(
        \DOMXPath $xpath,
        string $expression,
        ?\DOMNode $context = null,
        string $message = '',
    ): \DOMElement {
        $found = $xpath->query($expression, $context);
        $this->assertNotFalse($found, sprintf('"%s" is not a valid XPath expression.', $expression));
        $node = $found->item(0);
        $this->assertInstanceOf(
            \DOMElement::class,
            $node,
            $message !== '' ? $message : sprintf('Nothing matches "%s".', $expression),
        );

        return $node;
    }

    /**
     * The label an assertion compares against, resolved the way the page does.
     *
     * Comparing rendered text against the translated label rather than against
     * the key is what makes an assertion behavioural: a partial that stops
     * rendering the label, or renders the key, fails.
     */
    private function translate(string $key): string
    {
        $label = $this->get(LanguageServiceFactory::class)->create('default')->sL(
            'LLL:EXT:academic_persons_edit/Resources/Private/Language/locallang.xlf:' . $key,
        );
        $this->assertNotSame('', $label, sprintf('The label "%s" is not translated.', $key));

        return $label;
    }

    /**
     * @return list<string>
     */
    private function renderedClassList(?\DOMNode $node): array
    {
        $this->assertInstanceOf(\DOMElement::class, $node);

        return array_values(array_filter(
            preg_split('@\s+@', trim($node->getAttribute('class'))) ?: [],
            static fn(string $class): bool => $class !== '',
        ));
    }

    private function getProfileEditingPartial(string $relativePath): string
    {
        $content = file_get_contents(
            __DIR__ . '/../../../Resources/Private/Partials/Profile/' . $relativePath . '.html',
        );
        $this->assertIsString($content);
        return $content;
    }

    private function getProfileEditingFluidSources(): string
    {
        $paths = [
            ...(glob(__DIR__ . '/../../../Resources/Private/Templates/Profile/*.html') ?: []),
            ...(glob(__DIR__ . '/../../../Resources/Private/Partials/Profile/*.html') ?: []),
            ...(glob(__DIR__ . '/../../../Resources/Private/Partials/Profile/*/*.html') ?: []),
            ...(glob(__DIR__ . '/../../../Resources/Private/Partials/Profile/*/*/*.html') ?: []),
        ];
        sort($paths);
        $sources = '';
        foreach ($paths as $path) {
            $content = file_get_contents($path);
            $this->assertIsString($content);
            $sources .= $content;
        }
        return $sources;
    }

    private function extractDataUrl(string $content, string $attribute): string
    {
        $pattern = sprintf('@\b%s="([^"]+)"@', preg_quote($attribute, '@'));
        $this->assertSame(
            1,
            preg_match($pattern, $content, $match),
            sprintf('The rendered component has no "%s" URL.', $attribute),
        );
        $url = html_entity_decode($match[1]);
        return str_starts_with($url, '/') ? 'https://www.acme.com' . $url : $url;
    }

    private function extractDeleteButtonOpeningTag(string $content): string
    {
        $this->assertSame(
            1,
            preg_match('@<button\b(?=[^>]*data-pe-delete-image)[^>]*>@s', $content, $match),
            'The image view has no delete button.',
        );
        return $match[0];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function postJson(string $url, array $payload): ResponseInterface
    {
        $body = new Stream('php://temp', 'rw');
        $body->write(json_encode($payload, JSON_THROW_ON_ERROR));
        $body->rewind();
        return $this->requestAsFrontendUser(
            (new InternalRequest($url))
                ->withMethod('POST')
                ->withAddedHeader('Content-Type', 'application/json')
                ->withAddedHeader('X-Requested-With', 'XMLHttpRequest')
                ->withBody($body),
        );
    }

    /**
     * The AJAX hooks of the image editor, on the page the visitor is served.
     *
     * `frontend/profile/image.ts` addresses every one of these by attribute, so
     * a renamed or dropped one is a broken editor and nothing else notices. The
     * editor is rendered inside the target of `Templates/Profile/Index.html`
     * rather than fetched, so all of it is on the first response.
     */
    #[Test]
    public function imageEditorExposesStableAjaxHooks(): void
    {
        $this->setUpProfileEditingTestCase();
        $content = $this->renderProfileEditingPage();
        $xpath = $this->xpathOf($content);

        $editor = $xpath->query(
            '//section[@data-pe-image-view-container]'
                . '[contains(concat(" ", normalize-space(@class), " "), '
                . '" academic-persons-profile-editing__image-editor ")]',
        );
        $this->assertNotFalse($editor);
        $this->assertSame(1, $editor->length, 'The image editor is not rendered exactly once.');
        $container = $editor->item(0);
        $this->assertInstanceOf(\DOMElement::class, $container);

        // Delivered closed, idle and without an error; every other state is
        // written by the element.
        $this->assertSame('hidden', $container->getAttribute('hidden'));
        $this->assertSame('false', $container->getAttribute('aria-busy'));

        foreach (
            [
                'academic-persons-profile-editing__image-editor-content',
                'data-pe-image-error',
                'data-pe-upload-image',
                'data-pe-delete-image',
                'data-pe-cancel-delete-image',
                'data-pe-confirm-delete-image',
                'data-pe-image-delete-actions',
            ] as $hook
        ) {
            $found = $xpath->query(
                str_starts_with($hook, 'data-')
                    ? sprintf('.//*[@%s]', $hook)
                    : sprintf(
                        './/*[contains(concat(" ", normalize-space(@class), " "), " %s ")]',
                        $hook,
                    ),
                $container,
            );
            $this->assertNotFalse($found);
            $this->assertSame(
                1,
                $found->length,
                sprintf('The image editor does not carry "%s" exactly once.', $hook),
            );
        }

        // Delete, cancel-delete, confirm-delete, close and save - and nothing
        // else. The editor this one replaced had an "add" and a "replace"
        // button beside them, and its confirmation was a browser dialog; the
        // labels below are the whole set, so neither can come back unnoticed.
        $buttons = $xpath->query('.//button', $container);
        $this->assertNotFalse($buttons);
        $this->assertSame(5, $buttons->length);
        $labels = [];
        foreach ($buttons as $button) {
            $labels[] = trim($button->textContent);
        }
        sort($labels);
        $expected = [
            $this->translate('actions.cancel'),
            $this->translate('actions.cancel'),
            $this->translate('actions.delete'),
            $this->translate('actions.delete'),
            $this->translate('actions.save'),
        ];
        sort($expected);
        $this->assertSame($expected, $labels);

        $this->assertStringContainsString(
            $this->translate('profileEditing.image.editor.deleteConfirm'),
            trim($container->textContent),
        );
        // The one <dialog> of the page is the unsaved-changes template of
        // Partials/Profile/UnsavedChanges.html, which is not the image
        // editor's and is asserted by name; the image editor itself has none.
        $this->assertSame(0, $this->nodeCount($xpath, '//dialog[not(@data-pe-unsaved-changes)]'));
        $this->assertSame(1, $this->nodeCount($xpath, '//template[@data-pe-dialog="unsaved-changes"]/dialog[@data-pe-unsaved-changes]'));
        $this->assertSame(0, $this->nodeCount($xpath, '//*[@data-add-label or @data-replace-label]'));
        // Extbase validation results are not rendered into the editor: the
        // upload answers with JSON and the element writes the message.
        $this->assertSame(
            0,
            $this->nodeCount(
                $xpath,
                './/*[contains(concat(" ", normalize-space(@class), " "), " typo3-messages ")]',
                $container,
            ),
        );
    }

    /**
     * The one control that opens the editor, and the accessible name it needs.
     *
     * It is rendered by `Partials/Profile/Image/Card.html` next to the preview
     * and is the only way into the editor without JavaScript state, so it
     * carries `aria-expanded`, points at the editor section by id and has both
     * a title and an `aria-label` - the icon alone names nothing.
     */
    #[Test]
    public function imageCardExposesAccessibleEditHook(): void
    {
        $this->setUpProfileEditingTestCase();
        $content = $this->renderProfileEditingPage();
        $xpath = $this->xpathOf($content);

        $this->assertStringContainsString($this->translate('profileEditing.image.heading'), $content);
        $this->assertSame(1, $this->nodeCount($xpath, '//*[@data-pe-image-preview]'));

        $buttons = $xpath->query('//button[@data-pe-open-image-view]');
        $this->assertNotFalse($buttons);
        $this->assertSame(1, $buttons->length);
        $button = $buttons->item(0);
        $this->assertInstanceOf(\DOMElement::class, $button);

        $label = $this->translate('profileEditing.image.edit');
        $this->assertSame($label, $button->getAttribute('title'));
        $this->assertSame($label, $button->getAttribute('aria-label'));
        $this->assertSame('false', $button->getAttribute('aria-expanded'));

        $controls = $button->getAttribute('aria-controls');
        $this->assertNotSame('', $controls);
        $target = $xpath->query(sprintf('//*[@id="%s"]', $controls));
        $this->assertNotFalse($target);
        $this->assertSame(
            1,
            $target->length,
            'The edit button points at an element that is not rendered.',
        );
        $this->assertSame('', $target->item(0)?->attributes?->getNamedItem('data-pe-image-view-container')?->nodeValue);

        $icon = $xpath->query(
            './/*[@data-identifier="academic-persons-edit-upload-image"]',
            $button,
        );
        $this->assertNotFalse($icon);
        $this->assertSame(1, $icon->length);

        // The card names the image and nothing else: the profile name is the
        // heading of the header partial, and this section must not repeat it.
        $this->assertSame(
            0,
            $this->nodeCount($xpath, '//*[@data-pe-image-preview]//*[@data-pe-profile-name]'),
        );
    }

    #[Test]
    public function stickyImageColumnCarriesItsFrontendHook(): void
    {
        $this->setUpProfileEditingTestCase();
        $xpath = $this->xpathOf($this->renderProfileEditingPage());

        $sticky = $xpath->query('//*[@data-pe-sticky-image]');
        $this->assertNotFalse($sticky);
        $this->assertSame(1, $sticky->length);
        $this->assertContains('sticky-top', $this->renderedClassList($sticky->item(0)));
    }

    /**
     * One toggle in the header, and no global footer actions anywhere.
     *
     * The editor this one replaced had a footer bar with a "save all" and a
     * "cancel all" button. The only global control left is the "edit all"
     * toggle, which opens full form editing; the bar that governs that state
     * is rendered inside each fields form and is asserted by
     * {@see self::everyFieldsFormRendersOneHiddenFormActionBar()}. The two
     * labels of the toggle are read by `frontend/profile/fields.ts` from the
     * attributes asserted here.
     *
     * The name says what is asserted and no more: nothing here toggles
     * anything, because no JavaScript runs in this suite.
     */
    #[Test]
    public function theEditAllToggleCarriesBothLabelsAndNoFooterActionsRemain(): void
    {
        $this->assertFileDoesNotExist(
            __DIR__ . '/../../../Resources/Private/Partials/Profile/Profile/FooterActions.html',
        );

        $this->setUpProfileEditingTestCase();
        $content = $this->renderProfileEditingPage();
        $xpath = $this->xpathOf($content);

        $toggles = $xpath->query('//button[@data-academic-persons-profile-editing-edit-all-btn]');
        $this->assertNotFalse($toggles);
        $this->assertSame(1, $toggles->length);
        $toggle = $toggles->item(0);
        $this->assertInstanceOf(\DOMElement::class, $toggle);

        $editAll = $toggle->getAttribute('data-pe-edit-all-label');
        $closeAll = $toggle->getAttribute('data-pe-close-all-label');
        $this->assertSame($this->translate('profileEditing.btnEditAll'), $editAll);
        $this->assertSame($this->translate('profileEditing.btnCloseAll'), $closeAll);
        $this->assertNotSame($editAll, $closeAll);
        $this->assertSame('false', $toggle->getAttribute('aria-pressed'));
        $this->assertSame(
            1,
            $this->nodeCount($xpath, './/*[@data-pe-edit-all-button-label]', $toggle),
        );

        // One header, rendered once, with the name and the synchronisation
        // switch in it - not repeated by the section partials.
        $this->assertSame(1, $this->nodeCount($xpath, '//*[@data-pe-profile-header]'));
        $this->assertSame(1, $this->nodeCount($xpath, '//*[@data-pe-profile-name]'));
        $this->assertSame(1, $this->nodeCount($xpath, '//*[@data-pe-sync-form]'));
        $this->assertStringContainsString(
            $this->translate('profileEditing.section.personal'),
            $content,
        );

        $this->assertSame(
            0,
            $this->nodeCount($xpath, '//*[@data-pe-footer-button-area or @data-pe-cancel-all]'),
        );
    }

    #[Test]
    public function contentFieldsRenderDirectRichTextPreviewsAndThreeFieldActions(): void
    {
        $this->setUpProfileEditingTestCase();
        $rendered = $this->renderProfileEditingPage();
        // Without the prototypes: the rich text control of a document field is
        // one of them, and it is markup no visitor is shown until an editor is
        // opened in the browser.
        $content = $this->withoutProfileEditingPrototypes($rendered);
        $this->assertSame(
            1,
            preg_match_all('@(?<!:)\bdata-pe-rich-text(?=[\s=>])@', $rendered)
                - preg_match_all('@(?<!:)\bdata-pe-rich-text(?=[\s=>])@', $content),
            'The control-rich-text prototype is rendered exactly once.',
        );
        $this->assertSame(
            5,
            preg_match_all('@(?<!:)\bdata-pe-rich-text(?=[\s=>])@', $content),
        );
        $this->assertSame(5, substr_count($content, 'data-pe-editor-container'));
        $this->assertSame(
            5,
            preg_match_all('@\bdata-pe-rich-text-preview(?=[\s>])@', $content),
        );
        $this->assertSame(
            5,
            preg_match_all('@\bdata-pe-rich-text-preview-content(?=[\s>])@', $content),
        );
        $fieldActionCount = substr_count($content, 'data-pe-field-actions');
        $autosaveControlCount = substr_count($content, 'data-pe-autosave-on-change');
        $autosaveUndoCount = substr_count($content, 'data-pe-autosave-undo');
        $this->assertGreaterThanOrEqual(5, $fieldActionCount);
        $this->assertSame($autosaveControlCount, $autosaveUndoCount);
        $this->assertSame($fieldActionCount, substr_count($content, 'data-pe-dismiss'));
        $this->assertSame($fieldActionCount, substr_count($content, 'data-pe-save'));
        $this->assertSame(
            $fieldActionCount + $autosaveUndoCount,
            preg_match_all('@\bdata-pe-cancel(?=[\s>])@', $content),
        );
        $this->assertStringContainsString('Delete content', $content);
        $this->assertStringContainsString('Cancel', $content);
        $this->assertStringContainsString('Save', $content);
        $this->assertStringContainsString('No content', $content);
        $field = $this->getProfileEditingPartial('Field/Editable');
        $fields = $this->getProfileEditingPartial('Profile/Fields');
        $preview = $this->getProfileEditingPartial('Field/Preview');
        $control = $this->getProfileEditingPartial('Field/Control');
        $this->assertStringContainsString('<f:form.textarea', $control);
        $this->assertStringContainsString('<f:form.textfield', $control);
        $this->assertStringContainsString('data-pe-rich-text-preview', $preview);
        $this->assertStringContainsString('data-pe-rich-text-heading', $field);
        $this->assertStringContainsString(
            'arguments="{elementId: elementId, besideHeading: true}"',
            $field,
        );
        $this->assertSame(1, substr_count($field, 'besideHeading: true'));
        $this->assertSame(1, substr_count($field, 'besideHeading: false'));
        $this->assertSame(1, substr_count($fields, 'richText: true'));
        $this->assertSame(2, substr_count($fields, 'textarea: true'));
        $document = new \DOMDocument();
        $this->assertTrue($document->loadHTML($content, LIBXML_NOERROR | LIBXML_NOWARNING));
        $xpath = new \DOMXPath($document);
        $richTextControls = $xpath->query('//*[@data-pe-rich-text]');
        $this->assertNotFalse($richTextControls);
        foreach ($richTextControls as $richTextControl) {
            $headings = $xpath->query(
                'ancestor::*[@data-pe-field-editor][1]/*[@data-pe-rich-text-heading]',
                $richTextControl,
            );
            $this->assertNotFalse($headings);
            $this->assertCount(1, $headings);
            $heading = $headings->item(0);
            $this->assertInstanceOf(\DOMElement::class, $heading);
            $headingActions = $xpath->query('.//*[@data-pe-field-actions]', $heading);
            $this->assertNotFalse($headingActions);
            $this->assertCount(1, $headingActions);
        }
        $limitedControl = $xpath->query('//*[@id="profile-editing-1-miscellaneous"]');
        $this->assertNotFalse($limitedControl);
        $this->assertCount(1, $limitedControl);
        $this->assertSame(
            '1000',
            $limitedControl->item(0)?->attributes?->getNamedItem('data-pe-character-limit')?->nodeValue,
        );
        $characterCounters = $xpath->query(
            '//*[@data-pe-character-counter and @data-pe-for="profile-editing-1-miscellaneous"]',
        );
        $this->assertNotFalse($characterCounters);
        $this->assertCount(1, $characterCounters);
        $this->assertSame('0 / 1000', trim($characterCounters->item(0)?->textContent ?? ''));
    }

    #[Test]
    public function profileFormsConsumeValidationFromTheirOwnVisualSection(): void
    {
        $this->setUpProfileEditingTestCase();
        $content = $this->renderProfileEditingPage();
        $template = file_get_contents(__DIR__ . '/../../../Resources/Private/Templates/Profile/Index.html');
        $profileForm = $this->getProfileEditingPartial('Profile/Personal');
        $contentForm = $this->getProfileEditingPartial('Profile/About');
        $this->assertIsString($template);
        $this->assertStringContainsString('section: profileSections.information', $template);
        $this->assertStringContainsString('section: profileSections.aboutme', $template);
        $this->assertStringContainsString('items: section.items', $profileForm);
        $this->assertStringContainsString('items: section.items', $contentForm);
        $this->assertStringNotContainsString('Profile/Sections/Personal', $profileForm);
        $this->assertStringNotContainsString('Profile/Sections/Content', $contentForm);
        $this->assertStringContainsString('data-profile-section="information"', $content);
        $this->assertStringContainsString('data-profile-section="aboutme"', $content);
    }

    #[Test]
    public function configuredProfileAndSpecialFieldsDriveTheRenderedControls(): void
    {
        $this->setUpProfileEditingTestCase();
        $content = $this->renderProfileEditingPage();
        $this->assertStringContainsString('id="profile-editing-1-firstName"', $content);
        $this->assertStringContainsString('id="profile-editing-1-website"', $content);
        $this->assertStringContainsString('type="url"', $content);
        $this->assertStringContainsString(
            'data-pe-profile-name-field-ids="title firstName middleName lastName"',
            $content,
        );
        $this->assertStringContainsString('data-pe-sync-form', $content);
        $this->assertStringContainsString('data-pe-image-preview', $content);
        $document = new \DOMDocument();
        $this->assertTrue($document->loadHTML($content, LIBXML_NOERROR | LIBXML_NOWARNING));
        $xpath = new \DOMXPath($document);
        $previewHelptexts = $xpath->query(
            '//*[@data-pe-field-preview or @data-pe-group-preview]//*[@data-pe-helptext]',
        );
        $this->assertNotFalse($previewHelptexts);
        $this->assertCount(0, $previewHelptexts);
        $languageService = $this->get(LanguageServiceFactory::class)->create('default');
        foreach ([
            'firstName' => 'helptext.firstName',
            'miscellaneous' => 'helptext.miscellaneous',
            'skipSync' => 'helptext.skipSync',
        ] as $identifier => $translationKey) {
            $helptexts = $xpath->query(sprintf(
                '//*[@data-pe-helptext and @data-pe-for="profile-editing-%d-%s"]',
                self::PROFILE_ID,
                $identifier,
            ));
            $this->assertNotFalse($helptexts);
            $this->assertGreaterThanOrEqual(1, $helptexts->length);
            foreach ($helptexts as $helptext) {
                $this->assertInstanceOf(\DOMElement::class, $helptext);
                $this->assertSame(
                    $languageService->sL(
                        'LLL:EXT:academic_persons/Resources/Private/Language/locallang.xlf:'
                            . $translationKey,
                    ),
                    $helptext->getAttribute('data-bs-content'),
                );
            }
        }
    }

    #[Test]
    public function profileNameAndGlobalControlsRenderAboveTheImageAndPersonalHeadings(): void
    {
        $this->setUpProfileEditingTestCase();
        $content = $this->renderProfileEditingPage();
        $document = new \DOMDocument();
        $this->assertTrue($document->loadHTML($content, LIBXML_NOERROR | LIBXML_NOWARNING));
        $xpath = new \DOMXPath($document);
        $headers = $xpath->query('//*[@data-pe-profile-header]');
        $this->assertNotFalse($headers);
        $this->assertCount(1, $headers);
        $header = $headers->item(0);
        $this->assertInstanceOf(\DOMElement::class, $header);
        foreach ([
            './/*[@data-pe-profile-name]',
            './/*[@data-pe-sync-form]',
            './/*[@data-academic-persons-profile-editing-edit-all-btn]',
        ] as $query) {
            $elements = $xpath->query($query, $header);
            $this->assertNotFalse($elements);
            $this->assertCount(1, $elements);
        }
        $imageHeading = $xpath->query('//*[@id="profile-editing-1-image-heading"]');
        $personalHeading = $xpath->query('//*[@id="profile-editing-1-personal-heading"]');
        $this->assertNotFalse($imageHeading);
        $this->assertNotFalse($personalHeading);
        $this->assertSame('Profile image', trim($imageHeading->item(0)?->textContent ?? ''));
        $this->assertSame('Personal data', trim($personalHeading->item(0)?->textContent ?? ''));
        $this->assertLessThan(
            strpos($content, 'profile-editing-1-image-heading'),
            strpos($content, 'data-pe-profile-header'),
        );
        $this->assertLessThan(
            strpos($content, 'profile-editing-1-personal-heading'),
            strpos($content, 'data-pe-profile-header'),
        );
        $this->assertStringContainsString('Back to profile overview', $content);
    }

    #[Test]
    public function editableSelectControlsUseExplicitFieldActions(): void
    {
        $this->setUpProfileEditingTestCase();
        $content = $this->renderProfileEditingPage();
        $document = new \DOMDocument();
        $this->assertTrue($document->loadHTML($content, LIBXML_NOERROR | LIBXML_NOWARNING));
        $xpath = new \DOMXPath($document);
        foreach (['gender'] as $identifier) {
            $elementId = 'profile-editing-' . self::PROFILE_ID . '-' . $identifier;
            $controls = $xpath->query(sprintf('//*[@id="%s"]', $elementId));
            $this->assertNotFalse($controls);
            $this->assertSame(
                1,
                $controls->length,
                sprintf('Missing unique control for "%s".', $identifier),
            );
            $control = $controls->item(0);
            $this->assertInstanceOf(\DOMElement::class, $control);
            $this->assertFalse(
                $control->hasAttribute('disabled'),
                sprintf('Editable control "%s" must not be rendered disabled.', $identifier),
            );
            $this->assertFalse(
                $control->hasAttribute('data-pe-autosave-on-change'),
                sprintf('Editable control "%s" must not opt into autosave-on-change.', $identifier),
            );
            $undoButtons = $xpath->query(sprintf(
                '//*[@data-pe-autosave-undo and @data-pe-cancel and @data-pe-for="%s"]',
                $elementId,
            ));
            $this->assertNotFalse($undoButtons);
            $this->assertSame(
                0,
                $undoButtons->length,
                sprintf('Unexpected autosave undo button for "%s".', $identifier),
            );
            $fieldActions = $xpath->query(sprintf(
                '//*[@data-pe-field-actions and @data-pe-for="%s"]',
                $elementId,
            ));
            $this->assertNotFalse($fieldActions);
            $this->assertSame(1, $fieldActions->length, sprintf('Missing field actions for "%s".', $identifier));
            foreach (['data-pe-dismiss', 'data-pe-cancel', 'data-pe-save'] as $actionAttribute) {
                $actionButtons = $xpath->query(sprintf(
                    '//*[@%s and @data-pe-for="%s"]',
                    $actionAttribute,
                    $elementId,
                ));
                $this->assertNotFalse($actionButtons);
                $this->assertSame(
                    1,
                    $actionButtons->length,
                    sprintf('Missing "%s" action for "%s".', $actionAttribute, $identifier),
                );
            }
            $editButtons = $xpath->query(sprintf(
                '//*[@data-academic-persons-profile-editing-activate-btn and @data-pe-for="%s"]',
                $elementId,
            ));
            $this->assertNotFalse($editButtons);
            $this->assertSame(1, $editButtons->length, sprintf('Missing edit button for "%s".', $identifier));
            $editButton = $editButtons->item(0);
            $this->assertInstanceOf(\DOMElement::class, $editButton);
            $this->assertSame($elementId . '-editor', $editButton->getAttribute('aria-controls'));
            $editors = $xpath->query(sprintf('//*[@id="%s-editor" and @data-pe-field-editor]', $elementId));
            $this->assertNotFalse($editors);
            $this->assertSame(1, $editors->length, sprintf('Missing editor for "%s".', $identifier));
        }
    }

    /**
     * The years of a compact row are printed as they are stored. Nothing formats them,
     * so nothing about them follows a locale, and a row whose own start year is empty
     * falls back to the single year of the record.
     */
    #[Test]
    public function documentRowYearsAreRenderedAsStored(): void
    {
        $this->setUpProfileEditingTestCase();
        $this->seedStructuredDocumentSections();

        $content = $this->renderProfileEditingPage();

        $document = new \DOMDocument();
        $this->assertTrue($document->loadHTML($content, LIBXML_NOERROR | LIBXML_NOWARNING));
        $xpath = new \DOMXPath($document);
        $yearValues = $xpath->query('//*[@data-pe-document-value="yearStart"]');
        $this->assertNotFalse($yearValues);
        $this->assertGreaterThan(0, $yearValues->length, 'No document row renders a start year.');
        $renderedYears = [];
        foreach ($yearValues as $yearValue) {
            $renderedYears[] = trim((string)$yearValue->textContent);
        }
        $this->assertContains('2025', $renderedYears);
        $this->assertNotContains('Jan 1, 2025', $renderedYears);
    }

    #[Test]
    public function structuredDocumentSectionsHonorConfiguredRowsActionsAndReadonlyState(): void
    {
        $this->setUpProfileEditingTestCase();
        $this->seedStructuredDocumentSections();
        $content = $this->renderProfileEditingPage();
        $aboutPosition = strpos($content, 'profile-editing-1-about-heading');
        $contractsPosition = strpos($content, 'data-section-key="contracts"');
        $this->assertIsInt($aboutPosition);
        $this->assertIsInt($contractsPosition);
        $this->assertGreaterThan($aboutPosition, $contractsPosition);
        $document = new \DOMDocument();
        $this->assertTrue($document->loadHTML($content, LIBXML_NOERROR | LIBXML_NOWARNING));
        $xpath = new \DOMXPath($document);
        $sections = $xpath->query('//*[@data-pe-document-section]');
        $this->assertNotFalse($sections);
        $documentSections = $this->get(AcademicPersonsSettingsFactory::class)->get()->documentSections;
        $this->assertCount(count($documentSections), $sections);
        $sectionKeys = [];
        $configuredSections = array_values($documentSections);
        foreach ($sections as $position => $section) {
            $sectionKeys[] = $section->attributes?->getNamedItem('data-section-key')?->nodeValue;
            $this->assertSame(
                (string)$position,
                $section->attributes?->getNamedItem('data-section-position')?->nodeValue,
            );
            $addButtons = $xpath->query('.//button[@data-pe-document-add]', $section);
            $this->assertNotFalse($addButtons);
            $sectionSettings = $configuredSections[$position];
            $this->assertCount($sectionSettings->allowsCreate() ? 1 : 0, $addButtons);
            $items = $xpath->query('.//*[@data-pe-document-items]/*[@data-pe-document-item]', $section);
            $this->assertNotFalse($items);
            $listHeaders = $xpath->query('.//*[@data-pe-document-list-header]', $section);
            $this->assertNotFalse($listHeaders);
            $this->assertCount(1, $listHeaders);
            foreach ($items as $item) {
                $actions = $xpath->query('.//*[@data-pe-document-actions]/button', $item);
                $this->assertNotFalse($actions);
                $this->assertCount(
                    count($sectionSettings->getAllowedActions()) + ($sectionSettings->allowsDragSorting() ? 1 : 0),
                    $actions,
                );
                $actualActions = [];
                foreach ($actions as $actionElement) {
                    $this->assertInstanceOf(\DOMElement::class, $actionElement);
                    $actualActions[] = match (true) {
                        $actionElement->hasAttribute('data-pe-document-drag') => 'drag',
                        $actionElement->hasAttribute('data-pe-document-view') => 'view',
                        $actionElement->hasAttribute('data-pe-document-sort') => $actionElement->getAttribute('data-pe-document-sort'),
                        $actionElement->hasAttribute('data-pe-document-delete') => 'delete',
                        $actionElement->hasAttribute('data-pe-document-edit') => 'edit',
                        default => 'unknown',
                    };
                }
                $expectedActions = $sectionSettings->allowsDragSorting() ? ['drag'] : [];
                array_push($expectedActions, ...$sectionSettings->getAllowedActions());
                $this->assertSame($expectedActions, $actualActions);
                $rowValueElements = $xpath->query('.//*[@data-pe-document-value or @data-pe-document-title]', $item);
                $this->assertNotFalse($rowValueElements);
                $actualRowFields = [];
                foreach ($rowValueElements as $rowValueElement) {
                    $this->assertInstanceOf(\DOMElement::class, $rowValueElement);
                    $actualRowFields[] = $rowValueElement->hasAttribute('data-pe-document-title')
                        ? 'title'
                        : $rowValueElement->getAttribute('data-pe-document-value');
                }
                $expectedRowFields = array_map(
                    static fn(string $field): string => $sectionSettings->isContractSection()
                        ? match ($field) {
                            'from' => 'validFrom',
                            'to' => 'validTo',
                            default => $field,
                        }
                    : match ($field) {
                        'from' => 'yearStart',
                        'to' => 'yearEnd',
                        'description' => 'bodytext',
                        default => $field,
                    },
                    $sectionSettings->rowFields,
                );
                $this->assertSame($expectedRowFields, $actualRowFields);
            }
            $this->assertSame(
                $sectionSettings->fieldName,
                $section->attributes->getNamedItem('data-section-field-name')?->nodeValue,
            );
            $this->assertSame(
                $sectionSettings->type,
                $section->attributes->getNamedItem('data-section-record-type')?->nodeValue,
            );
            $this->assertSame(
                $sectionSettings->readOnly ? '1' : '0',
                $section->attributes->getNamedItem('data-section-readonly')?->nodeValue,
            );
            $this->assertSame(
                $sectionSettings->allowsDragSorting() ? '1' : '0',
                $section->attributes->getNamedItem('data-section-sortable')?->nodeValue,
            );
        }
        $this->assertSame(array_keys($documentSections), $sectionKeys);
        $documentsPartial = $this->getProfileEditingPartial('Documents/Sections');
        $this->assertStringContainsString('key="{section.label}"', $documentsPartial);
        $this->assertStringNotContainsString('key="profile.{section.identifier}"', $documentsPartial);
        $this->assertStringContainsString('data-pe-document-add', $documentsPartial);
        $actionsPartial = $this->getProfileEditingPartial('Documents/Actions');
        foreach ([
            'academic-persons-edit-sort-handle',
            'academic-persons-edit-view',
            'academic-persons-edit-move-down',
            'academic-persons-edit-move-up',
            'academic-persons-edit-delete',
            'academic-persons-edit-edit',
            'academic-persons-edit-view-close',
        ] as $iconIdentifier) {
            $this->assertStringContainsString($iconIdentifier, $actionsPartial);
        }
        $actionPositions = array_map(
            static fn(string $hook): int|false => strpos($actionsPartial, $hook),
            [
                'data-pe-document-view',
                'data-pe-document-sort="down"',
                'data-pe-document-sort="up"',
                'data-pe-document-delete',
                'data-pe-document-edit',
            ],
        );
        foreach ($actionPositions as $actionPosition) {
            $this->assertIsInt($actionPosition);
        }
        $this->assertSame($actionPositions, array_values(array_unique($actionPositions)));
        $sortedActionPositions = $actionPositions;
        sort($sortedActionPositions);
        $this->assertSame($sortedActionPositions, $actionPositions);
        $contractItems = $xpath->query(
            '//*[@data-section-key="contracts"]//*[@data-pe-document-items]/*[@data-pe-document-item]',
        );
        $this->assertNotFalse($contractItems);
        $this->assertCount(2, $contractItems);
        foreach ($contractItems as $contractItem) {
            $contractActions = $xpath->query('.//*[@data-pe-document-actions]/button', $contractItem);
            $this->assertNotFalse($contractActions);
            $this->assertCount(6, $contractActions);
            $this->assertTrue($contractActions->item(0)?->attributes?->getNamedItem('data-pe-document-drag') !== null);
            $this->assertTrue($contractActions->item(1)?->attributes?->getNamedItem('data-pe-document-view') !== null);
            $this->assertSame('down', $contractActions->item(2)?->attributes?->getNamedItem('data-pe-document-sort')?->nodeValue);
            $this->assertSame('up', $contractActions->item(3)?->attributes?->getNamedItem('data-pe-document-sort')?->nodeValue);
            $this->assertTrue($contractActions->item(4)?->attributes?->getNamedItem('data-pe-document-delete') !== null);
            $this->assertTrue($contractActions->item(5)?->attributes?->getNamedItem('data-pe-document-edit') !== null);
        }
        $this->assertSame('10', $contractItems->item(0)?->attributes?->getNamedItem('data-item-sorting')?->nodeValue);
        $emptyPressMedia = $xpath->query('//*[@data-section-key="pressMedia"]//*[@data-pe-document-empty-state]');
        $this->assertNotFalse($emptyPressMedia);
        $this->assertCount(1, $emptyPressMedia);
        $expectedValues = [
            'Rectorate',
            'Professor',
            'Cooperation first',
            self::LONG_LECTURE_TITLE,
            'Biodiversity monitoring association',
            'Vita first',
            'Publication first',
            'Research first',
        ];
        foreach ($expectedValues as $expectedValue) {
            $this->assertStringContainsString($expectedValue, $content);
        }
        $this->assertStringContainsString('No press releases have been added yet.', $content);
    }

    /**
     * The action group of a row is drawn in one line, on every row and on the
     * header above it.
     *
     * A `.row` wraps, and the widest section - lectures, with three date
     * columns, five actions and the drag handle - used to spend three quarters
     * of the row on `col-md-3` date cells and push the group of six buttons
     * onto a line of its own as soon as a title was long enough to claim the
     * rest. The fixture therefore carries a lecture whose title is long, and
     * what is asserted is the three halves of the answer: narrower date cells,
     * text cells that may break a word rather than demand its width, and an
     * action group that shrinks for nothing.
     *
     * Removing `flex-shrink-0` or `justify-content-md-end` from
     * `Documents/Actions.html`, or `text-break` or the narrower `col-md-2` from
     * `ProfileInformationRow.html` or `Documents/Header.html`, turns this red.
     */
    #[Test]
    public function documentRowsDrawTheActionGroupInOneLineAndLetTheTextGiveWay(): void
    {
        $this->setUpProfileEditingTestCase();
        $this->seedStructuredDocumentSections();
        $content = $this->renderProfileEditingPage();
        $xpath = $this->xpathOf($content);

        $actionGroups = $xpath->query('//*[@data-pe-document-actions]');
        $this->assertNotFalse($actionGroups);
        $this->assertGreaterThan(0, $actionGroups->length, 'No document row renders an action group.');
        foreach ($actionGroups as $actionGroup) {
            $classes = $this->renderedClassList($actionGroup);
            foreach ([
                'col-12',
                'col-md-auto',
                'flex-shrink-0',
                'd-flex',
                'flex-nowrap',
                'justify-content-center',
                'justify-content-md-end',
                'ms-md-auto',
            ] as $class) {
                $this->assertContains(
                    $class,
                    $classes,
                    sprintf('An action group is rendered without "%s".', $class),
                );
            }
            // The two the group stacked centred below the text on a phone and
            // pushed right from "md" up replaced.
            $this->assertNotContains('justify-content-end', $classes);
            $this->assertNotContains('ms-auto', $classes);
        }

        $lectureRows = $xpath->query(
            '//*[@data-section-key="lectures"]//*[@data-pe-document-items]/*[@data-pe-document-item]',
        );
        $this->assertNotFalse($lectureRows);
        $this->assertCount(1, $lectureRows);
        $lectureRow = $lectureRows->item(0);
        $this->assertInstanceOf(\DOMElement::class, $lectureRow);
        $title = $this->firstElement($xpath, './/*[@data-pe-document-title]', $lectureRow);
        $this->assertSame(self::LONG_LECTURE_TITLE, trim((string)$title->textContent));
        $titleCell = $title->parentNode;
        $this->assertContains('col-md', $this->renderedClassList($titleCell));
        $this->assertContains('text-break', $this->renderedClassList($titleCell));
        foreach (['year', 'yearStart', 'yearEnd'] as $field) {
            $value = $this->firstElement(
                $xpath,
                sprintf('.//*[@data-pe-document-value="%s"]', $field),
                $lectureRow,
                sprintf('The lecture row has no "%s" cell.', $field),
            );
            $classes = $this->renderedClassList($value->parentNode);
            $this->assertContains('col-md-2', $classes);
            $this->assertNotContains('col-md-3', $classes);
            $this->assertContains('text-break', $classes);
        }

        // The header carries the same widths in the same order, or it stops
        // standing above the values it names.
        $header = $this->firstElement(
            $xpath,
            '//*[@data-section-key="lectures"]//*[@data-pe-document-list-header]',
        );
        $headerCells = $xpath->query('./*', $header);
        $this->assertNotFalse($headerCells);
        $this->assertCount(5, $headerCells);
        foreach ([0, 1, 2] as $position) {
            $classes = $this->renderedClassList($headerCells->item($position));
            $this->assertContains('col-md-2', $classes);
            $this->assertNotContains('col-md-3', $classes);
        }
        $this->assertContains('col', $this->renderedClassList($headerCells->item(3)));
        $actionsHeading = $this->renderedClassList($headerCells->item(4));
        $this->assertContains('col-md-auto', $actionsHeading);
        $this->assertContains('flex-shrink-0', $actionsHeading);
        $this->assertContains('ms-md-auto', $actionsHeading);
        $this->assertNotContains('ms-auto', $actionsHeading);
    }

    /**
     * Both eyes of the view control, and both of its labels.
     *
     * The control is a toggle - it opens the read view and closes it again -
     * and `aria-expanded` used to be the only thing that said so. It still is
     * the state; what the browser does with it now is swap the two icons and
     * the two labels Fluid renders, so the glyph above an open panel says
     * "close" rather than "show". None of that is markup the browser builds:
     * both icons stand in the button, one of them `hidden`, and both strings
     * ride on the button as data attributes.
     */
    #[Test]
    public function documentRowViewActionCarriesBothEyeStatesAndBothLabels(): void
    {
        $this->setUpProfileEditingTestCase();
        $this->seedStructuredDocumentSections();
        $content = $this->renderProfileEditingPage();
        $xpath = $this->xpathOf($content);
        $view = $this->translate('actions.view');
        $viewClose = $this->translate('actions.viewClose');
        $this->assertNotSame($view, $viewClose);

        $buttons = $xpath->query(
            '//*[@data-pe-document-view]|//*[@data-pe-contract-contact-view]',
        );
        $this->assertNotFalse($buttons);
        $this->assertGreaterThan(1, $buttons->length, 'The rendered page has no view control.');
        foreach ($buttons as $button) {
            $this->assertInstanceOf(\DOMElement::class, $button);
            $this->assertSame($view, $button->getAttribute('aria-label'));
            $this->assertSame($view, $button->getAttribute('title'));
            $this->assertSame($view, $button->getAttribute('data-pe-label-collapsed'));
            $this->assertSame($viewClose, $button->getAttribute('data-pe-label-expanded'));
            $collapsed = $this->firstElement($xpath, './/*[@data-pe-view-icon="collapsed"]', $button);
            $expanded = $this->firstElement($xpath, './/*[@data-pe-view-icon="expanded"]', $button);
            $this->assertFalse($collapsed->hasAttribute('hidden'));
            $this->assertTrue($expanded->hasAttribute('hidden'));
            $this->assertSame(
                1,
                $this->nodeCount($xpath, './/*[@data-identifier="academic-persons-edit-view"]', $collapsed),
            );
            $this->assertSame(
                1,
                $this->nodeCount($xpath, './/*[@data-identifier="academic-persons-edit-view-close"]', $expanded),
            );
        }
    }

    #[Test]
    public function profileInformationDocumentAjaxActionsCoverFormCreateUpdateSortAndDelete(): void
    {
        $this->setUpProfileEditingTestCase();
        $this->seedStructuredDocumentSections();
        $content = $this->renderProfileEditingPage();
        $formUrl = $this->extractDataUrl($content, 'data-document-form-url');
        $createUrl = $this->extractDataUrl($content, 'data-create-document-url');
        $updateUrl = $this->extractDataUrl($content, 'data-update-document-url');
        $sortUrl = $this->extractDataUrl($content, 'data-sort-document-url');
        $deleteUrl = $this->extractDataUrl($content, 'data-delete-document-url');
        $formResponse = $this->postJson($formUrl, [
            'profile' => self::PROFILE_ID,
            'data' => ['section' => 'cooperation', 'record' => 1, 'mode' => 'edit'],
        ]);
        $this->assertSame(200, $formResponse->getStatusCode(), (string)$formResponse->getBody());
        $formBody = json_decode((string)$formResponse->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertTrue($formBody['success'] ?? null);
        $this->assertSame(1, $formBody['record'] ?? null);
        $this->assertSame(
            ['title', 'link', 'year', 'yearStart', 'yearEnd', 'bodytext'],
            array_column($formBody['fields'] ?? [], 'name'),
        );
        $formFieldsByName = array_column($formBody['fields'] ?? [], null, 'name');
        $this->assertTrue($formFieldsByName['bodytext']['richText'] ?? false);
        $this->assertSame(500, $formFieldsByName['bodytext']['characterLimit'] ?? null);
        $this->assertTrue($formFieldsByName['year']['required'] ?? false);
        $this->assertFalse($formFieldsByName['yearStart']['required'] ?? true);
        $this->assertFalse($formFieldsByName['yearEnd']['required'] ?? true);
        // The three year fields are number controls and carry the bounds of the TCA
        // range; nothing else does.
        foreach (['year', 'yearStart', 'yearEnd'] as $yearField) {
            $this->assertSame('number', $formFieldsByName[$yearField]['type'] ?? null, $yearField);
            $this->assertSame(0, $formFieldsByName[$yearField]['min'] ?? null, $yearField);
            $this->assertSame(9999, $formFieldsByName[$yearField]['max'] ?? null, $yearField);
            $this->assertSame(1, $formFieldsByName[$yearField]['step'] ?? null, $yearField);
        }
        $this->assertSame(
            ['min' => null, 'max' => null, 'step' => null],
            array_intersect_key(
                (array)($formFieldsByName['title'] ?? []),
                ['min' => null, 'max' => null, 'step' => null],
            ),
            'a text field declares no bounds',
        );
        $languageService = $this->get(LanguageServiceFactory::class)->create('default');
        foreach ([
            'title' => 'title',
            'year' => 'year',
            'yearStart' => 'from',
            'yearEnd' => 'to',
            'bodytext' => 'description',
        ] as $helptextField => $translationKey) {
            $this->assertSame(
                $languageService->sL(
                    'LLL:EXT:academic_persons/Resources/Private/Language/locallang.xlf:'
                        . 'helptext.documentSections.' . $translationKey,
                ),
                $formFieldsByName[$helptextField]['helptext'] ?? null,
                sprintf('Missing translated helptext for document field "%s".', $helptextField),
            );
        }
        $this->assertSame('', $formFieldsByName['link']['helptext'] ?? null);
        $missingYearResponse = $this->postJson($createUrl, [
            'profile' => self::PROFILE_ID,
            'data' => [
                'section' => 'cooperation',
                'fields' => ['title' => 'Missing required year'],
            ],
        ]);
        $this->assertSame(422, $missingYearResponse->getStatusCode());
        $missingYearBody = json_decode(
            (string)$missingYearResponse->getBody(),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
        $this->assertArrayHasKey('year', $missingYearBody['errors'] ?? []);
        $this->assertArrayNotHasKey('yearStart', $missingYearBody['errors'] ?? []);
        $this->assertArrayNotHasKey('yearEnd', $missingYearBody['errors'] ?? []);
        // The bounds of the number control are enforced on the server as well.
        $outOfRangeResponse = $this->postJson($createUrl, [
            'profile' => self::PROFILE_ID,
            'data' => [
                'section' => 'cooperation',
                'fields' => ['title' => 'Year above the range', 'year' => '10000'],
            ],
        ]);
        $this->assertSame(422, $outOfRangeResponse->getStatusCode());
        $outOfRangeBody = json_decode(
            (string)$outOfRangeResponse->getBody(),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
        // The refused value never lands in the normalized set, so the required
        // check of the same request reports the field as missing as well.
        $this->assertSame(
            ['The value must be between 0 and 9999.', 'This field is required.'],
            $outOfRangeBody['errors']['year'] ?? null,
        );
        $overLimitResponse = $this->postJson($createUrl, [
            'profile' => self::PROFILE_ID,
            'data' => [
                'section' => 'cooperation',
                'fields' => [
                    'title' => 'Description over its configured limit',
                    'year' => 2027,
                    'bodytext' => '<p><strong>' . str_repeat('a', 501) . '</strong></p>',
                ],
            ],
        ]);
        $this->assertSame(422, $overLimitResponse->getStatusCode());
        $overLimitBody = json_decode(
            (string)$overLimitResponse->getBody(),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
        $this->assertSame(
            ['The text must not exceed 500 characters.'],
            $overLimitBody['errors']['bodytext'] ?? null,
        );
        $createResponse = $this->postJson($createUrl, [
            'profile' => self::PROFILE_ID,
            'data' => [
                'section' => 'cooperation',
                'fields' => [
                    'title' => 'AJAX cooperation',
                    'link' => 'https://example.com/ajax-cooperation',
                    'year' => 2027,
                    'bodytext' => '<p><strong>Created inline</strong></p>',
                ],
            ],
        ]);
        $this->assertSame(200, $createResponse->getStatusCode(), (string)$createResponse->getBody());
        $createBody = json_decode((string)$createResponse->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $createdUid = (int)($createBody['item']['uid'] ?? 0);
        $this->assertGreaterThan(0, $createdUid, (string)$createResponse->getBody());
        $this->assertSame('AJAX cooperation', $createBody['item']['display']['title'] ?? null);
        $storedCreatedRecord = $this->getConnectionPool()
            ->getConnectionForTable('tx_academicpersons_domain_model_profile_information')
            ->executeQuery(
                'SELECT profile, type, title, year, year_start, year_end, sorting'
                    . ' FROM tx_academicpersons_domain_model_profile_information'
                    . ' WHERE uid = ? AND deleted = 0',
                [$createdUid],
            )
            ->fetchAssociative();
        $this->assertIsArray($storedCreatedRecord);
        $this->assertSame(self::PROFILE_ID, (int)$storedCreatedRecord['profile']);
        $this->assertSame('cooperation', $storedCreatedRecord['type']);
        $this->assertSame('AJAX cooperation', $storedCreatedRecord['title']);
        $this->assertSame(2027, (int)$storedCreatedRecord['year']);
        $this->assertNull($storedCreatedRecord['year_start']);
        $this->assertNull($storedCreatedRecord['year_end']);
        $this->assertSame(30, (int)$storedCreatedRecord['sorting']);
        $updateResponse = $this->postJson($updateUrl, [
            'profile' => self::PROFILE_ID,
            'data' => [
                'section' => 'cooperation',
                'record' => $createdUid,
                'fields' => [
                    'title' => 'AJAX cooperation updated',
                    'year' => 2028,
                    'bodytext' => '<p><strong>Updated inline</strong><script>alert(1)</script></p>',
                ],
            ],
        ]);
        $this->assertSame(200, $updateResponse->getStatusCode(), (string)$updateResponse->getBody());
        $updateBody = json_decode((string)$updateResponse->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('AJAX cooperation updated', $updateBody['item']['display']['title'] ?? null);
        $this->assertSame('2028', $updateBody['item']['display']['year'] ?? null);
        $this->assertStringContainsString('<strong>Updated inline</strong>', $updateBody['item']['display']['bodytext'] ?? '');
        $this->assertStringNotContainsString('<script', $updateBody['item']['display']['bodytext'] ?? '');
        $sortResponse = $this->postJson($sortUrl, [
            'profile' => self::PROFILE_ID,
            'data' => [
                'section' => 'cooperation',
                'record' => $createdUid,
                'direction' => 'up',
            ],
        ]);
        $this->assertSame(200, $sortResponse->getStatusCode(), (string)$sortResponse->getBody());
        $sortBody = json_decode((string)$sortResponse->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame([1, $createdUid, 2], $sortBody['order'] ?? null);
        $dragSortResponse = $this->postJson($sortUrl, [
            'profile' => self::PROFILE_ID,
            'data' => [
                'section' => 'cooperation',
                'order' => [$createdUid, 2, 1],
            ],
        ]);
        $this->assertSame(200, $dragSortResponse->getStatusCode(), (string)$dragSortResponse->getBody());
        $dragSortBody = json_decode((string)$dragSortResponse->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame([$createdUid, 2, 1], $dragSortBody['order'] ?? null);
        $incompleteDragSortResponse = $this->postJson($sortUrl, [
            'profile' => self::PROFILE_ID,
            'data' => [
                'section' => 'cooperation',
                'order' => [$createdUid, 2],
            ],
        ]);
        $this->assertSame(400, $incompleteDragSortResponse->getStatusCode());
        $wrongSectionResponse = $this->postJson($updateUrl, [
            'profile' => self::PROFILE_ID,
            'data' => [
                'section' => 'publications',
                'record' => $createdUid,
                'fields' => ['title' => 'Must not be persisted'],
            ],
        ]);
        $this->assertSame(404, $wrongSectionResponse->getStatusCode());
        $deleteResponse = $this->postJson($deleteUrl, [
            'profile' => self::PROFILE_ID,
            'data' => ['section' => 'cooperation', 'record' => $createdUid],
        ]);
        $this->assertSame(200, $deleteResponse->getStatusCode(), (string)$deleteResponse->getBody());
        $deleteBody = json_decode((string)$deleteResponse->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame($createdUid, $deleteBody['deleted'] ?? null);
        $activeCount = (int)$this->getConnectionPool()
            ->getConnectionForTable('tx_academicpersons_domain_model_profile_information')
            ->executeQuery(
                'SELECT COUNT(*) FROM tx_academicpersons_domain_model_profile_information'
                    . ' WHERE uid = ? AND deleted = 0',
                [$createdUid],
            )
            ->fetchOne();
        $this->assertSame(0, $activeCount);
    }

    #[Test]
    public function contractDocumentAjaxActionsCoverFormCreateUpdateSortAndDelete(): void
    {
        $this->setUpProfileEditingTestCase();
        $this->seedStructuredDocumentSections();
        $content = $this->renderProfileEditingPage();
        $formUrl = $this->extractDataUrl($content, 'data-document-form-url');
        $createUrl = $this->extractDataUrl($content, 'data-create-document-url');
        $updateUrl = $this->extractDataUrl($content, 'data-update-document-url');
        $sortUrl = $this->extractDataUrl($content, 'data-sort-document-url');
        $deleteUrl = $this->extractDataUrl($content, 'data-delete-document-url');
        $formResponse = $this->postJson($formUrl, [
            'profile' => self::PROFILE_ID,
            'data' => ['section' => 'contracts', 'record' => 1, 'mode' => 'view'],
        ]);
        $this->assertSame(200, $formResponse->getStatusCode(), (string)$formResponse->getBody());
        $formBody = json_decode((string)$formResponse->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('contract', $formBody['kind'] ?? null);
        $this->assertSame(
            [
                'position',
                'organisationalUnit',
                'functionType',
                'validFrom',
                'validTo',
                'location',
                'room',
                'officeHours',
                'publish',
            ],
            array_column($formBody['fields'] ?? [], 'name'),
        );
        $contractFieldsByName = array_column($formBody['fields'] ?? [], null, 'name');
        $languageService = $this->get(LanguageServiceFactory::class)->create('default');
        foreach (array_keys($contractFieldsByName) as $fieldName) {
            $translationFieldName = match ($fieldName) {
                'validFrom' => 'validFrom',
                'validTo' => 'validTo',
                default => $fieldName,
            };
            $this->assertSame(
                $languageService->sL(
                    'LLL:EXT:academic_persons/Resources/Private/Language/locallang.xlf:'
                        . 'helptext.contracts.' . $translationFieldName,
                ),
                $contractFieldsByName[$fieldName]['helptext'] ?? null,
                sprintf('Missing translated helptext for Contract field "%s".', $fieldName),
            );
        }
        $this->assertTrue($contractFieldsByName['officeHours']['richText'] ?? false);
        // The two contract dates. The descriptor type stays "date" - it is
        // what the endpoint serializes, validates and displays a date by, and
        // what a date picker will one day be mounted on - while the control
        // itself is a plain text input carrying the format as its hint.
        foreach (['validFrom', 'validTo'] as $dateField) {
            $this->assertSame('date', $contractFieldsByName[$dateField]['type'] ?? null);
            $this->assertSame('dd.mm.yyyy', $contractFieldsByName[$dateField]['placeholder'] ?? null);
        }
        $this->assertMatchesRegularExpression(
            '@^\d{2}\.\d{2}\.\d{4}$@',
            (string)($contractFieldsByName['validFrom']['value'] ?? ''),
            'The value of a contract date is serialized in the format its control shows.',
        );
        $this->assertSame(
            ['physicalAddresses', 'emailAddresses', 'phoneNumbers'],
            array_column($formBody['contactSections'] ?? [], 'identifier'),
        );
        $this->assertSame(
            'Campus Road 14',
            $formBody['contactSections'][0]['items'][0]['summary'][0]['value'] ?? null,
        );
        $editFormResponse = $this->postJson($formUrl, [
            'profile' => self::PROFILE_ID,
            'data' => ['section' => 'contracts', 'record' => 1, 'mode' => 'edit'],
        ]);
        $this->assertSame(200, $editFormResponse->getStatusCode(), (string)$editFormResponse->getBody());
        $addFormResponse = $this->postJson($formUrl, [
            'profile' => self::PROFILE_ID,
            'data' => ['section' => 'contracts', 'record' => 0, 'mode' => 'add'],
        ]);
        $this->assertSame(200, $addFormResponse->getStatusCode(), (string)$addFormResponse->getBody());
        $createResponse = $this->postJson($createUrl, [
            'profile' => self::PROFILE_ID,
            'data' => [
                'section' => 'contracts',
                'fields' => [
                    'position' => 'Visiting professor',
                    'organisationalUnit' => '',
                    'functionType' => '',
                    'validFrom' => '2026-01-15',
                    'validTo' => '2026-12-15',
                    'location' => '',
                    'room' => 'B 1.23',
                    'officeHours' => '<p>By appointment</p>',
                    'publish' => true,
                ],
            ],
        ]);
        $this->assertSame(200, $createResponse->getStatusCode(), (string)$createResponse->getBody());
        $createBody = json_decode((string)$createResponse->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $createdUid = $createBody['item']['uid'] ?? null;
        $this->assertIsInt($createdUid);
        $this->assertGreaterThan(0, $createdUid);
        $updateResponse = $this->postJson($updateUrl, [
            'profile' => self::PROFILE_ID,
            'data' => [
                'section' => 'contracts',
                'record' => $createdUid,
                'fields' => ['position' => 'Guest professor'],
            ],
        ]);
        $this->assertSame(200, $updateResponse->getStatusCode(), (string)$updateResponse->getBody());
        $updateBody = json_decode((string)$updateResponse->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('Guest professor', $updateBody['item']['display']['position'] ?? null);
        $sortResponse = $this->postJson($sortUrl, [
            'profile' => self::PROFILE_ID,
            'data' => ['section' => 'contracts', 'record' => $createdUid, 'direction' => 'up'],
        ]);
        $this->assertSame(200, $sortResponse->getStatusCode(), (string)$sortResponse->getBody());
        $sortBody = json_decode((string)$sortResponse->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame([1, $createdUid, 2], $sortBody['order'] ?? null);
        $deleteResponse = $this->postJson($deleteUrl, [
            'profile' => self::PROFILE_ID,
            'data' => ['section' => 'contracts', 'record' => $createdUid],
        ]);
        $this->assertSame(200, $deleteResponse->getStatusCode(), (string)$deleteResponse->getBody());
        $this->assertSame(
            0,
            (int)$this->getConnectionPool()
                ->getConnectionForTable('tx_academicpersons_domain_model_contract')
                ->executeQuery(
                    'SELECT COUNT(*) FROM tx_academicpersons_domain_model_contract'
                        . ' WHERE uid = ? AND deleted = 0',
                    [$createdUid],
                )
                ->fetchOne(),
        );
    }

    /**
     * The two formats a contract date is accepted in, and the one it is shown
     * in.
     *
     * `d.m.Y` is what the control shows and submits. `Y-m-d` is what the
     * endpoint answered and accepted while the control was an
     * `<input type="date">`; it stays accepted, so a client written against
     * that shape is not broken by the control changing. Anything else is
     * refused with the error the endpoint always used.
     *
     * The read view is unaffected either way: both requests come back with the
     * same `MEDIUMDATE` of the site language, which is what the public views
     * render as well.
     */
    #[Test]
    public function contractDatesAreAcceptedInBothTheControlAndTheIsoFormat(): void
    {
        $this->setUpProfileEditingTestCase();
        $this->seedStructuredDocumentSections();
        $content = $this->renderProfileEditingPage();
        $createUrl = $this->extractDataUrl($content, 'data-create-document-url');
        $expectedDisplay = (new DateFormatter())->format(
            new \DateTime('2026-01-15 00:00:00'),
            'MEDIUMDATE',
            new Locale('en-US'),
        );
        $created = [];

        foreach (['iso' => '2026-01-15', 'control' => '15.01.2026'] as $shape => $submitted) {
            $response = $this->postJson($createUrl, [
                'profile' => self::PROFILE_ID,
                'data' => [
                    'section' => 'contracts',
                    'fields' => ['position' => 'Lecturer', 'validFrom' => $submitted],
                ],
            ]);
            $this->assertSame(
                200,
                $response->getStatusCode(),
                sprintf('The %s format was refused: %s', $shape, (string)$response->getBody()),
            );
            $body = json_decode((string)$response->getBody(), true, 512, JSON_THROW_ON_ERROR);
            $this->assertSame('15.01.2026', $body['item']['values']['validFrom'] ?? null);
            $this->assertSame($expectedDisplay, $body['item']['display']['validFrom'] ?? null);
            $uid = $body['item']['uid'] ?? null;
            $this->assertIsInt($uid);
            $created[$shape] = (int)$this->getConnectionPool()
                ->getConnectionForTable('tx_academicpersons_domain_model_contract')
                ->executeQuery(
                    'SELECT valid_from FROM tx_academicpersons_domain_model_contract WHERE uid = ?',
                    [$uid],
                )
                ->fetchOne();
        }

        $this->assertSame(
            $created['iso'],
            $created['control'],
            'The two accepted formats have to store the same date.',
        );

        $refused = $this->postJson($createUrl, [
            'profile' => self::PROFILE_ID,
            'data' => [
                'section' => 'contracts',
                'fields' => ['position' => 'Lecturer', 'validFrom' => '15/01/2026'],
            ],
        ]);
        $this->assertSame(422, $refused->getStatusCode());
        $refusedBody = json_decode((string)$refused->getBody(), true, 512, JSON_THROW_ON_ERROR);
        // The unreadable value is reported as such. `validFrom` is a required
        // field, so it is also reported as missing - a value the endpoint
        // could not read is no value - and the assertion is on the message
        // that is about the format rather than on the whole list.
        $this->assertIsArray($refusedBody['errors']['validFrom'] ?? null);
        $this->assertContains(
            'The value must be a valid date.',
            $refusedBody['errors']['validFrom'],
        );
    }

    #[Test]
    public function contractContactAjaxActionsCoverAddressEmailAndPhoneNumberCrud(): void
    {
        $this->setUpProfileEditingTestCase();
        $this->seedStructuredDocumentSections();
        $content = $this->renderProfileEditingPage();
        $formUrl = $this->extractDataUrl($content, 'data-contract-contact-form-url');
        $createUrl = $this->extractDataUrl($content, 'data-create-contract-contact-url');
        $updateUrl = $this->extractDataUrl($content, 'data-update-contract-contact-url');
        $deleteUrl = $this->extractDataUrl($content, 'data-delete-contract-contact-url');
        $sortUrl = $this->extractDataUrl($content, 'data-sort-contract-contact-url');
        $languageService = $this->get(LanguageServiceFactory::class)->create('default');
        /** @var array<string, array{table: string, field: string, fields: array<string, string>, updatedValue: string}> $cases */
        $cases = [
            'physicalAddresses' => [
                'table' => 'tx_academicpersons_domain_model_address',
                'field' => 'street',
                'fields' => [
                    'street' => 'New Road',
                    'streetNumber' => '12a',
                    'additional' => 'Third floor',
                    'zip' => '41061',
                    'city' => 'Mönchengladbach',
                    'state' => 'NRW',
                    'country' => 'DE',
                    'type' => 'business',
                ],
                'updatedValue' => 'Updated Road',
            ],
            'emailAddresses' => [
                'table' => 'tx_academicpersons_domain_model_email',
                'field' => 'email',
                'fields' => ['email' => 'new@example.com', 'type' => 'business'],
                'updatedValue' => 'updated@example.com',
            ],
            'phoneNumbers' => [
                'table' => 'tx_academicpersons_domain_model_phone_number',
                'field' => 'phoneNumber',
                'fields' => ['phoneNumber' => '+49 2161 654321', 'type' => 'mobile'],
                'updatedValue' => '+49 2161 999999',
            ],
        ];
        foreach ($cases as $section => $case) {
            $addFormResponse = $this->postJson($formUrl, [
                'profile' => self::PROFILE_ID,
                'data' => [
                    'contract' => 1,
                    'section' => $section,
                    'record' => 0,
                    'mode' => 'add',
                ],
            ]);
            $this->assertSame(200, $addFormResponse->getStatusCode(), (string)$addFormResponse->getBody());
            $addFormBody = json_decode(
                (string)$addFormResponse->getBody(),
                true,
                512,
                JSON_THROW_ON_ERROR,
            );
            $contactFieldsByName = array_column($addFormBody['fields'] ?? [], null, 'name');
            foreach ($contactFieldsByName as $fieldName => $field) {
                $this->assertNotSame(
                    '',
                    $field['helptext'] ?? '',
                    sprintf('Missing translated helptext for %s field "%s".', $section, $fieldName),
                );
            }
            if ($section === 'physicalAddresses') {
                $this->assertSame('select', $contactFieldsByName['country']['type'] ?? null);
                $countryOptions = array_column(
                    $contactFieldsByName['country']['options'] ?? [],
                    null,
                    'value',
                );
                $this->assertSame(
                    $languageService->sL('LLL:EXT:core/Resources/Private/Language/Iso/countries.xlf:DE.name'),
                    $countryOptions['DE']['label'] ?? null,
                );
                $invalidCountryFields = $case['fields'];
                $invalidCountryFields['country'] = 'ZZ';
                $invalidCountryResponse = $this->postJson($createUrl, [
                    'profile' => self::PROFILE_ID,
                    'data' => [
                        'contract' => 1,
                        'section' => $section,
                        'fields' => $invalidCountryFields,
                    ],
                ]);
                $this->assertSame(422, $invalidCountryResponse->getStatusCode());
                $invalidCountryBody = json_decode(
                    (string)$invalidCountryResponse->getBody(),
                    true,
                    512,
                    JSON_THROW_ON_ERROR,
                );
                $this->assertSame(
                    ['The selected value is not available.'],
                    $invalidCountryBody['errors']['country'] ?? null,
                );
            }
            $createResponse = $this->postJson($createUrl, [
                'profile' => self::PROFILE_ID,
                'data' => [
                    'contract' => 1,
                    'section' => $section,
                    'fields' => $case['fields'],
                ],
            ]);
            $this->assertSame(200, $createResponse->getStatusCode(), (string)$createResponse->getBody());
            $createBody = json_decode((string)$createResponse->getBody(), true, 512, JSON_THROW_ON_ERROR);
            $createdUid = $createBody['item']['uid'] ?? null;
            $this->assertIsInt($createdUid);
            $updatedFields = $case['fields'];
            $updatedFields[$case['field']] = $case['updatedValue'];
            $updateResponse = $this->postJson($updateUrl, [
                'profile' => self::PROFILE_ID,
                'data' => [
                    'contract' => 1,
                    'section' => $section,
                    'record' => $createdUid,
                    'fields' => $updatedFields,
                ],
            ]);
            $this->assertSame(200, $updateResponse->getStatusCode(), (string)$updateResponse->getBody());
            $updateBody = json_decode((string)$updateResponse->getBody(), true, 512, JSON_THROW_ON_ERROR);
            $this->assertSame($case['updatedValue'], $updateBody['item']['values'][$case['field']] ?? null);
            $viewResponse = $this->postJson($formUrl, [
                'profile' => self::PROFILE_ID,
                'data' => [
                    'contract' => 1,
                    'section' => $section,
                    'record' => $createdUid,
                    'mode' => 'view',
                ],
            ]);
            $this->assertSame(200, $viewResponse->getStatusCode(), (string)$viewResponse->getBody());
            $viewBody = json_decode((string)$viewResponse->getBody(), true, 512, JSON_THROW_ON_ERROR);
            $this->assertSame($case['updatedValue'], $viewBody['fields'][0]['value'] ?? null);
            $sortResponse = $this->postJson($sortUrl, [
                'profile' => self::PROFILE_ID,
                'data' => [
                    'contract' => 1,
                    'section' => $section,
                    'record' => $createdUid,
                    'direction' => 'up',
                ],
            ]);
            $this->assertSame(200, $sortResponse->getStatusCode(), (string)$sortResponse->getBody());
            $sortBody = json_decode((string)$sortResponse->getBody(), true, 512, JSON_THROW_ON_ERROR);
            $this->assertSame([$createdUid, 1], $sortBody['order'] ?? null);
            $deleteResponse = $this->postJson($deleteUrl, [
                'profile' => self::PROFILE_ID,
                'data' => [
                    'contract' => 1,
                    'section' => $section,
                    'record' => $createdUid,
                ],
            ]);
            $this->assertSame(200, $deleteResponse->getStatusCode(), (string)$deleteResponse->getBody());
            $this->assertSame(
                0,
                (int)$this->getConnectionPool()
                    ->getConnectionForTable($case['table'])
                    ->executeQuery(
                        sprintf('SELECT COUNT(*) FROM %s WHERE uid = ? AND deleted = 0', $case['table']),
                        [$createdUid],
                    )
                    ->fetchOne(),
            );
        }
        $otherContractContactResponse = $this->postJson($formUrl, [
            'profile' => self::PROFILE_ID,
            'data' => [
                'contract' => 1,
                'section' => 'emailAddresses',
                'record' => 2,
                'mode' => 'view',
            ],
        ]);
        $this->assertSame(404, $otherContractContactResponse->getStatusCode());
    }

    #[Test]
    public function richTextAjaxUpdatePersistsAndReturnsOnlySanitizedMarkup(): void
    {
        $this->setUpProfileEditingTestCase();
        $updateUrl = $this->extractDataUrl(
            $this->renderProfileEditingPage(),
            'data-update-url',
        );
        $response = $this->postJson(
            $updateUrl,
            [
                'profile' => self::PROFILE_ID,
                'data' => [
                    'coreCompetences' => '<script>alert(1)</script>'
                        . '<p onclick="alert(2)"><strong>Secure content</strong></p>'
                        . '<a href="javascript:alert(3)">Unsafe link</a>',
                ],
            ],
        );
        $this->assertSame(200, $response->getStatusCode());
        $body = json_decode((string)$response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($body, (string)$response->getBody());
        $this->assertTrue($body['success'] ?? null, (string)$response->getBody());
        $this->assertIsArray($body['data'] ?? null, (string)$response->getBody());
        $storedValue = (string)$this->getConnectionPool()
            ->getConnectionForTable('tx_academicpersons_domain_model_profile')
            ->executeQuery(
                'SELECT core_competences FROM tx_academicpersons_domain_model_profile WHERE uid = ?',
                [self::PROFILE_ID],
            )
            ->fetchOne();
        $this->assertSame($storedValue, $body['data']['coreCompetences']);
        $this->assertStringContainsString('<p><strong>Secure content</strong></p>', $storedValue);
        $this->assertStringNotContainsString('<script', $storedValue);
        $this->assertStringNotContainsString('onclick', $storedValue);
        $this->assertStringNotContainsString('javascript:', $storedValue);
    }

    #[Test]
    public function richTextAjaxUpdateRejectsConfiguredProfileCharacterLimit(): void
    {
        $this->setUpProfileEditingTestCase();
        $connection = $this->getConnectionPool()
            ->getConnectionForTable('tx_academicpersons_domain_model_profile');
        $persistedValue = (string)$connection->executeQuery(
            'SELECT miscellaneous FROM tx_academicpersons_domain_model_profile WHERE uid = ?',
            [self::PROFILE_ID],
        )->fetchOne();
        $updateUrl = $this->extractDataUrl($this->renderProfileEditingPage(), 'data-update-url');
        $response = $this->postJson(
            $updateUrl,
            [
                'profile' => self::PROFILE_ID,
                'data' => [
                    'miscellaneous' => '<p><strong>' . str_repeat('a', 1001) . '</strong></p>',
                ],
            ],
        );
        $body = json_decode((string)$response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(422, $response->getStatusCode(), (string)$response->getBody());
        $this->assertFalse($body['success'] ?? null);
        $this->assertSame('validation_failed', $body['error'] ?? null);
        $this->assertSame(
            ['The text must not exceed %d characters.'],
            $body['errors']['miscellaneous'] ?? null,
        );
        $this->assertSame(
            $persistedValue,
            (string)$connection->executeQuery(
                'SELECT miscellaneous FROM tx_academicpersons_domain_model_profile WHERE uid = ?',
                [self::PROFILE_ID],
            )->fetchOne(),
        );
    }

    #[Test]
    public function profileUpdateRejectsAProfileFieldMarkedReadOnlyInItsSection(): void
    {
        $this->setUpProfileEditingTestCase();
        $updateUrl = $this->extractDataUrl($this->renderProfileEditingPage(), 'data-update-url');
        $response = $this->postJson(
            $updateUrl,
            ['profile' => self::PROFILE_ID, 'data' => ['firstName' => 'Manipulated']],
        );
        $body = json_decode((string)$response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(422, $response->getStatusCode(), (string)$response->getBody());
        $this->assertFalse($body['success']);
        $this->assertSame('invalid_profile_data', $body['error']);
        $this->assertSame('Unknown profile property "firstName".', $body['message']);
        $storedValue = $this->getConnectionPool()
            ->getConnectionForTable('tx_academicpersons_domain_model_profile')
            ->executeQuery(
                'SELECT first_name FROM tx_academicpersons_domain_model_profile WHERE uid = ?',
                [self::PROFILE_ID],
            )
            ->fetchOne();
        $this->assertSame('Max', $storedValue);
    }

    /**
     * Full form editing is one form with one set of controls.
     *
     * The bar is rendered by Fluid at the end of every `data-pe-fields-form`
     * and is delivered `hidden`; nothing but the field editing module ever
     * shows it, and no label of it is spelled in JavaScript. The per-field
     * groups stay in the markup - they are hidden while the form is open, not
     * removed, because single-field editing has to work again afterwards.
     */
    #[Test]
    public function everyFieldsFormRendersOneHiddenFormActionBar(): void
    {
        $this->setUpProfileEditingTestCase();
        $content = $this->renderProfileEditingPage();
        $document = new \DOMDocument();
        $this->assertTrue($document->loadHTML($content, LIBXML_NOERROR | LIBXML_NOWARNING));
        $xpath = new \DOMXPath($document);
        $forms = $xpath->query('//form[@data-pe-fields-form]');
        $bars = $xpath->query('//*[@data-pe-form-actions]');
        $this->assertNotFalse($forms);
        $this->assertNotFalse($bars);
        $this->assertGreaterThan(0, $forms->length);
        $this->assertSame($forms->length, $bars->length);
        $groupNames = [];
        foreach ($bars as $bar) {
            $this->assertInstanceOf(\DOMElement::class, $bar);
            $this->assertTrue($bar->hasAttribute('hidden'));
            $this->assertSame('group', $bar->getAttribute('role'));
            // Two groups with the same accessible name are two useless entries
            // in a screen reader's group list, so each is named after its
            // section.
            $this->assertStringStartsWith('Form actions: ', $bar->getAttribute('aria-label'));
            $groupNames[] = $bar->getAttribute('aria-label');
            $this->assertSame(
                'All fields were restored to the saved values.',
                $bar->getAttribute('data-pe-form-reverted-message'),
            );
            $labels = [];
            foreach (['data-pe-form-apply', 'data-pe-form-undo', 'data-pe-form-discard'] as $hook) {
                $buttons = $xpath->query(sprintf('.//button[@%s]', $hook), $bar);
                $this->assertNotFalse($buttons);
                $this->assertSame(1, $buttons->length, $hook);
                $button = $buttons->item(0);
                $this->assertInstanceOf(\DOMElement::class, $button);
                $this->assertSame('button', $button->getAttribute('type'));
                $this->assertNotSame('', $button->getAttribute('title'));
                $labels[] = trim($button->textContent);
            }
            // The order the bar is tabbed through from the last field.
            $this->assertSame(['Apply', 'Undo', 'Discard'], $labels);
        }
        $this->assertSame($groupNames, array_unique($groupNames));
        $toggles = $xpath->query('//*[@data-academic-persons-profile-editing-edit-all-btn]');
        $this->assertNotFalse($toggles);
        $this->assertSame(1, $toggles->length);
        $toggle = $toggles->item(0);
        $this->assertInstanceOf(\DOMElement::class, $toggle);
        $controlled = preg_split('@\s+@', trim($toggle->getAttribute('aria-controls'))) ?: [];
        $this->assertSame($forms->length, count($controlled));
        foreach ($controlled as $formId) {
            $controlledForms = $xpath->query(sprintf('//form[@id="%s"][@data-pe-fields-form]', $formId));
            $this->assertNotFalse($controlledForms);
            $this->assertSame(1, $controlledForms->length, $formId);
        }
    }

    /**
     * The jsdom suite drives the modules against a hand written transcription
     * of this bar, and nothing in that suite can notice a hook or a label that
     * was renamed here - a fixture is the test's own input. This is the
     * fixture-provenance rule of `docs/testing/javascript-tests.md` applied to
     * it: every hook the rendered bar carries, every label it shows and the
     * message it hands to JavaScript has to appear in the fixture, so a rename
     * in the partial is a failure here rather than 362 cases staying green
     * against markup no visitor receives.
     */
    #[Test]
    public function theJavaScriptFixtureTranscribesTheRenderedFormActionBar(): void
    {
        $this->setUpProfileEditingTestCase();
        $content = $this->renderProfileEditingPage();
        $document = new \DOMDocument();
        $this->assertTrue($document->loadHTML($content, LIBXML_NOERROR | LIBXML_NOWARNING));
        $xpath = new \DOMXPath($document);
        $bars = $xpath->query('//*[@data-pe-form-actions]');
        $this->assertNotFalse($bars);
        $bar = $bars->item(0);
        $this->assertInstanceOf(\DOMElement::class, $bar);
        $expected = [$bar->getAttribute('data-pe-form-reverted-message')];
        foreach ($bar->attributes ?? [] as $attribute) {
            if (str_starts_with($attribute->nodeName, 'data-pe-')) {
                $expected[] = $attribute->nodeName;
            }
        }
        $buttons = $xpath->query('.//button', $bar);
        $this->assertNotFalse($buttons);
        foreach ($buttons as $button) {
            $this->assertInstanceOf(\DOMElement::class, $button);
            foreach ($button->attributes ?? [] as $attribute) {
                if (str_starts_with($attribute->nodeName, 'data-pe-')) {
                    $expected[] = $attribute->nodeName;
                }
            }
            $expected[] = trim($button->textContent);
            $expected[] = $button->getAttribute('title');
        }
        $fixture = file_get_contents(__DIR__ . '/../../JavaScript/Fixtures/profile-editing.ts');
        $this->assertIsString($fixture);
        foreach (array_unique($expected) as $token) {
            $this->assertNotSame('', $token);
            $this->assertStringContainsString(
                $token,
                $fixture,
                sprintf('The JavaScript fixture does not transcribe "%s".', $token),
            );
        }
    }

    /**
     * Apply is one request, and `updateAction()` validates the whole field map
     * before it writes anything. One refused property therefore leaves every
     * other submitted property unwritten as well - which is what makes a form
     * that applies at once safe to offer in the first place.
     */
    #[Test]
    public function oneRefusedPropertyLeavesEveryOtherSubmittedValueUnwritten(): void
    {
        $this->setUpProfileEditingTestCase();
        $connection = $this->getConnectionPool()
            ->getConnectionForTable('tx_academicpersons_domain_model_profile');
        $connection->update(
            'tx_academicpersons_domain_model_profile',
            ['gender' => 'ms'],
            ['uid' => self::PROFILE_ID],
        );
        $updateUrl = $this->extractDataUrl($this->renderProfileEditingPage(), 'data-update-url');
        $response = $this->postJson(
            $updateUrl,
            [
                'profile' => self::PROFILE_ID,
                'data' => ['title' => 'Prof. Dr.', 'gender' => ''],
            ],
        );
        $this->assertSame(422, $response->getStatusCode(), (string)$response->getBody());
        $body = json_decode((string)$response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($body);
        $this->assertSame('validation_failed', $body['error']);
        $this->assertNotEmpty($body['errors']['gender'] ?? []);
        $stored = $connection
            ->executeQuery(
                'SELECT title, gender FROM tx_academicpersons_domain_model_profile WHERE uid = ?',
                [self::PROFILE_ID],
            )
            ->fetchAssociative();
        $this->assertIsArray($stored);
        $this->assertSame('', $stored['title']);
        $this->assertSame('ms', $stored['gender']);
    }

    #[Test]
    public function fullFormEditingLabelsAreShippedInBothLanguages(): void
    {
        $expectedEnglish = [
            'profileEditing.actions.formGroup' => 'Form actions',
            'profileEditing.form.apply' => 'Apply',
            'profileEditing.form.apply.title' => 'Save every changed field of this profile',
            'profileEditing.form.undo' => 'Undo',
            'profileEditing.form.undo.title' =>
                'Restore every field to the saved value and continue editing',
            'profileEditing.form.discard' => 'Discard',
            'profileEditing.form.discard.title' =>
                'Restore every field to the saved value and close the form',
            'profileEditing.status.formReverted' => 'All fields were restored to the saved values.',
        ];
        $expectedGerman = [
            'profileEditing.actions.formGroup' => 'Formularaktionen',
            'profileEditing.form.apply' => 'Übernehmen',
            'profileEditing.form.apply.title' => 'Alle geänderten Felder dieses Profils speichern',
            'profileEditing.form.undo' => 'Rückgängig',
            'profileEditing.form.undo.title' =>
                'Alle Felder auf den gespeicherten Wert zurücksetzen und weiter bearbeiten',
            'profileEditing.form.discard' => 'Verwerfen',
            'profileEditing.form.discard.title' =>
                'Alle Felder auf den gespeicherten Wert zurücksetzen und das Formular schließen',
            'profileEditing.status.formReverted' =>
                'Alle Felder wurden auf die gespeicherten Werte zurückgesetzt.',
        ];
        $languageService = $this->get(LanguageServiceFactory::class)->create('default');
        foreach ($expectedEnglish as $key => $translation) {
            $this->assertSame(
                $translation,
                $languageService->sL(
                    'LLL:EXT:academic_persons_edit/Resources/Private/Language/locallang.xlf:' . $key,
                ),
            );
        }
        $document = new \DOMDocument();
        $this->assertTrue(
            $document->load(__DIR__ . '/../../../Resources/Private/Language/de.locallang.xlf'),
        );
        $germanTranslations = [];
        foreach ($document->getElementsByTagName('trans-unit') as $unit) {
            $target = $unit->getElementsByTagName('target')->item(0);
            if ($target !== null) {
                $germanTranslations[(string)$unit->getAttribute('id')] = trim($target->textContent);
            }
        }
        foreach ($expectedGerman as $key => $translation) {
            $this->assertArrayHasKey($key, $germanTranslations, $key);
            $this->assertSame($translation, $germanTranslations[$key], $key);
        }
    }

    #[Test]
    public function profileUpdatePropagatesSectionValidationErrorsWithStatus422(): void
    {
        $this->setUpProfileEditingTestCase();
        $this->getConnectionPool()
            ->getConnectionForTable('tx_academicpersons_domain_model_profile')
            ->update(
                'tx_academicpersons_domain_model_profile',
                ['gender' => 'ms'],
                ['uid' => self::PROFILE_ID],
            );
        $updateUrl = $this->extractDataUrl($this->renderProfileEditingPage(), 'data-update-url');
        $response = $this->postJson(
            $updateUrl,
            ['profile' => self::PROFILE_ID, 'data' => ['gender' => '']],
        );
        $body = json_decode((string)$response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($body);
        $this->assertSame(422, $response->getStatusCode(), (string)$response->getBody());
        $this->assertFalse($body['success']);
        $this->assertSame('validation_failed', $body['error']);
        $this->assertIsArray($body['errors'] ?? null);
        $this->assertNotEmpty($body['errors']['gender'] ?? []);
        $storedValue = $this->getConnectionPool()
            ->getConnectionForTable('tx_academicpersons_domain_model_profile')
            ->executeQuery(
                'SELECT gender FROM tx_academicpersons_domain_model_profile WHERE uid = ?',
                [self::PROFILE_ID],
            )
            ->fetchOne();
        $this->assertSame('ms', $storedValue);
    }

    #[Test]
    public function academicTitleUpdatePersistsTheConfiguredNameComponent(): void
    {
        $this->setUpProfileEditingTestCase();
        $updateUrl = $this->extractDataUrl($this->renderProfileEditingPage(), 'data-update-url');
        $response = $this->postJson(
            $updateUrl,
            ['profile' => self::PROFILE_ID, 'data' => ['title' => 'Prof. Dr.']],
        );
        $this->assertSame(200, $response->getStatusCode(), (string)$response->getBody());
        $body = json_decode((string)$response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(['title' => 'Prof. Dr.'], $body['data'] ?? null);
        $storedTitle = $this->getConnectionPool()
            ->getConnectionForTable('tx_academicpersons_domain_model_profile')
            ->executeQuery(
                'SELECT title FROM tx_academicpersons_domain_model_profile WHERE uid = ?',
                [self::PROFILE_ID],
            )
            ->fetchOne();
        $this->assertSame('Prof. Dr.', $storedTitle);
    }

    /**
     * Every icon identifier a shipped partial asks for has to be registered, and every
     * registered one has to be asked for by something.
     *
     * `<core:icon>` never fails on an unknown identifier: `IconFactory` answers with the
     * `default-not-found` placeholder and the identifier that was asked for is gone from
     * the markup. The Fluid scan catches the identifiers of a state the fixture does not
     * reach - a read-only section renders no save button - which is why it is a scan and
     * not only an assertion on the rendered page.
     */
    #[Test]
    public function everyIconIdentifierOfTheShippedTemplatesIsRegistered(): void
    {
        $registeredIcons = require __DIR__ . '/../../../Configuration/Icons.php';
        $this->assertIsArray($registeredIcons);
        $fluidSources = $this->getProfileEditingFluidSources();
        $this->assertGreaterThan(
            0,
            preg_match_all(
                '@<core:icon\b[^>]*?\bidentifier="([^"]+)"@s',
                $fluidSources,
                $matches,
            ),
            'No icon identifier is used by any profile editing template.',
        );
        $usedIdentifiers = array_values(array_unique($matches[1]));
        sort($usedIdentifiers);
        foreach ($usedIdentifiers as $identifier) {
            $this->assertArrayHasKey(
                $identifier,
                $registeredIcons,
                sprintf('The icon identifier "%s" is used but not registered.', $identifier),
            );
            $source = (string)($registeredIcons[$identifier]['source'] ?? '');
            $this->assertFileExists(
                GeneralUtility::getFileAbsFileName($source),
                sprintf('The icon file of "%s" does not exist.', $identifier),
            );
        }
        $registeredActionIcons = array_values(array_filter(
            array_keys($registeredIcons),
            static fn(string $identifier): bool => str_starts_with($identifier, 'academic-persons-edit-'),
        ));
        sort($registeredActionIcons);
        $this->assertSame(
            $registeredActionIcons,
            $usedIdentifiers,
            'Registered editor icons and the icons the templates use have drifted apart.',
        );
    }

    /**
     * The counterpart of the scan above, on the rendered page: the identifiers really
     * resolve at runtime and are inlined rather than rendered as an <img>, which is what
     * lets a button's own colour reach its glyph.
     */
    #[Test]
    public function theRenderedEditorResolvesEveryIconItAsksFor(): void
    {
        $this->setUpProfileEditingTestCase();
        $this->seedStructuredDocumentSections();

        $content = $this->renderProfileEditingPage();

        $this->assertStringNotContainsString('default-not-found', $content);
        $this->assertStringNotContainsString('Resources/Public/Icons/edit.svg', $content);
        $this->assertStringContainsString('<svg', $content);
        foreach ([
            'academic-persons-edit-add',
            'academic-persons-edit-back',
            'academic-persons-edit-delete',
            'academic-persons-edit-edit',
            'academic-persons-edit-help',
            'academic-persons-edit-move-down',
            'academic-persons-edit-move-up',
            'academic-persons-edit-view',
            'academic-persons-edit-view-close',
            'academic-persons-edit-visible',
            'academic-persons-edit-hidden',
        ] as $identifier) {
            $this->assertStringContainsString(
                'data-identifier="' . $identifier . '"',
                $content,
                sprintf('The rendered editor does not carry the icon "%s".', $identifier),
            );
        }
    }

    /**
     * The regression guard of ACE-267.
     *
     * `listAction()` and `indexAction()` assign a `record` view variable that
     * Extbase does not provide by itself, because Fluid Styled Content's header
     * partial reads it on TYPO3 v14 and dies without it. The content element of
     * the fixture therefore carries a header - so that *every* rendering test of
     * this class exercises the assignment - and this one says so, on both core
     * versions and for both actions.
     */
    /**
     * The metadata of the profile image, as `Partials/Profile/Image/Card.html`
     * reads it back.
     *
     * Every value is read through `originalResource`, because the profile
     * carries an Extbase `FileReference` and that class exposes nothing else -
     * every other property path resolves to `null` and renders as an empty
     * string, which is the defect ACE-343 fixed and which no assertion on
     * `sys_file_reference` can see.
     */
    #[Test]
    public function theAlternativeTextAndTitleOfTheImageAreRendered(): void
    {
        $this->setUpProfileEditingTestCase();
        $fileUid = $this->seedProfileImage();
        $this->setProfileImageFileMetadata($fileUid, [
            'alternative' => 'Portrait of the profile owner',
            'title' => 'Profile portrait',
        ]);

        $figure = $this->getRenderedProfileImageFigure($this->renderProfileEditingPage());

        $this->assertStringContainsString('alt="Portrait of the profile owner"', $figure);
        $this->assertStringContainsString('title="Profile portrait"', $figure);
    }

    #[Test]
    public function theDescriptionOfTheImageIsRenderedAsACaption(): void
    {
        $this->setUpProfileEditingTestCase();
        $fileUid = $this->seedProfileImage();
        $this->setProfileImageFileMetadata($fileUid, [
            'description' => 'Taken at the faculty building',
        ]);

        $figure = $this->getRenderedProfileImageFigure($this->renderProfileEditingPage());

        $this->assertMatchesRegularExpression(
            '@<figcaption class="visually-hidden">\s*Taken at the faculty building\s*</figcaption>@',
            $figure,
        );
    }

    #[Test]
    public function noCaptionIsRenderedForAnImageWithoutADescription(): void
    {
        $this->setUpProfileEditingTestCase();
        $this->seedProfileImage();

        $figure = $this->getRenderedProfileImageFigure($this->renderProfileEditingPage());

        $this->assertStringNotContainsString('<figcaption', $figure);
    }

    /**
     * A default installation has no `copyright` column at all - the field comes
     * with `EXT:filemetadata`, which is not loaded here. The partial used to
     * reference it through a view variable that is never assigned, so a
     * copyright never rendered in any released version; ACE-343 removed the
     * output rather than repairing it, because rendering one is a feature.
     * `AcademicPersonsEditProfileEditingImageFileMetadataTest` holds the same
     * line with the extension installed.
     */
    #[Test]
    public function theImageRendersWithoutTheFileMetadataExtension(): void
    {
        $this->setUpProfileEditingTestCase();
        $fileUid = $this->seedProfileImage();
        $this->setProfileImageFileMetadata($fileUid, [
            'description' => 'Taken at the faculty building',
        ]);

        $this->assertFalse(
            $this->fileMetadataTableHasColumn('copyright'),
            'This test has to run without EXT:filemetadata, but the copyright column exists.',
        );

        $figure = $this->getRenderedProfileImageFigure($this->renderProfileEditingPage());

        $this->assertStringNotContainsString('copyright', $figure);
        $this->assertMatchesRegularExpression(
            '@<figcaption class="visually-hidden">\s*Taken at the faculty building\s*</figcaption>@',
            $figure,
        );
    }

    #[Test]
    public function bothHtmlActionsRenderTheContentElementHeader(): void
    {
        $this->setUpProfileEditingTestCase();

        $listPage = $this->getPageAsFrontendUser('https://www.acme.com/home');
        $this->assertStringContainsString('Edit your profile', $listPage);
        $this->assertStringContainsString('Edit your profile', $this->renderProfileEditingPage());
    }

    /**
     * The same guard from the side a template override sees it.
     *
     * `Fixtures/Templates/Profile/List.html` is a stand-in for a project's own
     * override and reads `{record}` through `f:render.text`, the ViewHelper the
     * v14 header partial uses. It refuses anything but a record object, so a
     * controller that stops assigning the variable fails here on both core
     * versions rather than only in a v14 frontend.
     */
    #[Test]
    public function aTemplateOverrideCanReadTheRecordViewVariable(): void
    {
        $this->setUpProfileEditingTestCase([
            'EXT:academic_persons_edit/Tests/Functional/Plugins/Fixtures/'
            . 'TypoScript/Setup/RecordViewVariable.typoscript',
        ]);

        $content = $this->getPageAsFrontendUser('https://www.acme.com/home');

        $this->assertMatchesRegularExpression(
            '@<div\b[^>]*data-test-record-header[^>]*>\s*Edit your profile\s*</div>@',
            $content,
        );
    }

    /**
     * The actions the plugin registers, read from the Extbase registry.
     *
     * `ext_localconf.php` is what writes it, but the registry is what Extbase
     * dispatches from - an action missing there is a 404 for the endpoint, and
     * an action left there is a controller method that is still reachable. The
     * five controllers of the editor this one replaced are gone with their
     * classes, so nothing may register them any more.
     */
    #[Test]
    public function profileEditingPluginRegistersOnlyTheNewControllerActions(): void
    {
        $plugin = $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['extbase']['extensions']
            ['AcademicPersonsEdit']['plugins']['ProfileEditing'] ?? null;
        $this->assertIsArray($plugin);
        $controllers = $plugin['controllers'] ?? null;
        $this->assertIsArray($controllers);
        $this->assertSame([ProfileController::class], array_keys($controllers));

        $this->assertSame(
            [
                'list',
                'index',
                'update',
                'updateSkipSync',
                'uploadImage',
                'deleteImage',
                'documentForm',
                'createDocument',
                'updateDocument',
                'deleteDocument',
                'sortDocument',
                'contractContactForm',
                'createContractContact',
                'updateContractContact',
                'deleteContractContact',
                'sortContractContact',
                'toggleContractContactVisibility',
            ],
            $controllers[ProfileController::class]['actions'] ?? null,
        );
        // Every registered action is uncacheable: all of them but the two that
        // render the page answer a request the visitor made, and the two that
        // render carry the editing state of the logged in user.
        $this->assertSame(
            $controllers[ProfileController::class]['actions'] ?? null,
            $controllers[ProfileController::class]['nonCacheableActions'] ?? null,
        );

        foreach ($controllers[ProfileController::class]['actions'] as $action) {
            $this->assertTrue(
                method_exists(ProfileController::class, $action . 'Action'),
                sprintf('The registered action "%s" has no controller method.', $action),
            );
        }
        foreach (['editImage', 'addImage', 'removeImage', 'toggleSkipSync'] as $legacyAction) {
            $this->assertNotContains($legacyAction, $controllers[ProfileController::class]['actions']);
        }
        foreach (
            [
                'ProfileInformationController',
                'ContractController',
                'PhysicalAddressController',
                'EmailAddressController',
                'PhoneNumberController',
            ] as $legacyController
        ) {
            $this->assertFalse(
                class_exists('FGTCLB\\AcademicPersonsEdit\\Controller\\' . $legacyController),
                sprintf('The controller "%s" still exists.', $legacyController),
            );
        }
    }

    #[Test]
    public function profileWithoutImageRendersEditingHooksAndDedicatedAjaxUrls(): void
    {
        $this->setUpProfileEditingTestCase();
        $content = $this->renderProfileEditingPage();
        $decodedContent = urldecode(html_entity_decode($content));
        $this->assertStringContainsString('data-academic-persons-profile-editing', $content);
        $this->assertStringContainsString('data-profile-uid="1"', $content);
        $this->assertStringNotContainsString('data-user="', $content);
        $this->assertStringContainsString('data-pe-open-image-view', $content);
        $this->assertStringContainsString('data-pe-image-view-container', $content);
        $this->assertStringContainsString('data-pe-image-editor-target', $content);
        $this->assertStringContainsString('data-image-render-type="cropper"', $content);
        $this->assertStringContainsString('data-has-image="0"', $content);
        $configuredRatio = $this->get(AcademicPersonsSettingsFactory::class)
            ->get()
            ->getSpecialField('image')?->settings['ratio'] ?? null;
        $this->assertIsString($configuredRatio);
        $this->assertNotSame('', trim($configuredRatio));
        $this->assertStringContainsString(
            'data-image-cropper-ratio="'
                . htmlspecialchars($configuredRatio, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                . '"',
            $content,
        );
        $this->assertStringNotContainsString('data-pe-image-modal', $content);
        $this->assertStringNotContainsString('data-pe-document-modal', $content);
        $this->assertStringNotContainsString('data-bs-toggle="modal"', $content);
        // One <dialog>, and it is the unsaved-changes template rather than a
        // dialog of the image editor.
        $this->assertSame(1, substr_count($content, '<dialog'));
        $this->assertStringContainsString('data-pe-unsaved-changes', $content);
        $this->assertMatchesRegularExpression(
            '@<form\b(?=[^>]*enctype="multipart/form-data")[^>]*>@s',
            $content,
        );
        $this->assertStringContainsString('academic-persons-profile-editing__image-form', $content);
        $this->assertStringContainsString('data-pe-upload-image', $content);
        $this->assertStringContainsString('[action]=update', $decodedContent);
        $this->assertStringContainsString('[action]=updateSkipSync', $decodedContent);
        $this->assertStringContainsString('[action]=uploadImage', $decodedContent);
        $this->assertStringContainsString('[action]=deleteImage', $decodedContent);
        $this->assertStringContainsString('[action]=documentForm', $decodedContent);
        $this->assertStringContainsString('[action]=createDocument', $decodedContent);
        $this->assertStringContainsString('[action]=updateDocument', $decodedContent);
        $this->assertStringContainsString('[action]=deleteDocument', $decodedContent);
        $this->assertStringContainsString('[action]=sortDocument', $decodedContent);
        $this->assertStringContainsString('[action]=contractContactForm', $decodedContent);
        $this->assertStringContainsString('[action]=createContractContact', $decodedContent);
        $this->assertStringContainsString('[action]=updateContractContact', $decodedContent);
        $this->assertStringContainsString('[action]=deleteContractContact', $decodedContent);
        $this->assertStringContainsString('[action]=sortContractContact', $decodedContent);
        $this->assertStringContainsString('[action]=toggleContractContactVisibility', $decodedContent);
        $this->assertStringContainsString('data-pe-document-add-collapse-target', $content);
        $this->assertStringContainsString('data-pe-document-item-collapse-target', $content);
        // The custom element that starts the editor wraps the root the contract
        // is carried by, so the JavaScript has an owner and the attributes stay
        // where every reader expects them.
        $document = new \DOMDocument();
        $this->assertTrue($document->loadHTML($content, LIBXML_NOERROR | LIBXML_NOWARNING));
        $xpath = new \DOMXPath($document);
        $wrappedRoots = $xpath->query(
            '//academic-persons-edit-profile-editing/*[@data-academic-persons-profile-editing]',
        );
        $this->assertNotFalse($wrappedRoots);
        $this->assertCount(1, $wrappedRoots);
        // The image editor is rendered where it is shown, inside its target and
        // wrapped in the element that drives it.
        $imageEditors = $xpath->query(
            '//*[@data-pe-image-editor-target]'
                . '/academic-persons-edit-image-editor'
                . '/*[@data-pe-image-view-container]',
        );
        $this->assertNotFalse($imageEditors);
        $this->assertCount(1, $imageEditors);
        // The document editor and the contact list are built in the browser
        // from the "documentForm" response, so their markup is in the page only
        // as a prototype - which is what makes it overridable in Fluid at all.
        $withoutPrototypes = $this->withoutProfileEditingPrototypes($content);
        foreach (
            [
                'data-pe-document-view-container',
                'data-pe-contract-contact-section',
                'data-pe-contract-contact-editor',
            ] as $hook
        ) {
            $this->assertStringContainsString($hook, $content);
            $this->assertStringNotContainsString($hook, $withoutPrototypes);
        }
        // The icons of those editors are drawn by the prototypes themselves
        // now, so the page carries no icon templates of its own, and the two
        // sort labels the contract used to hand over are gone with them.
        $this->assertStringNotContainsString('data-pe-icon=', $content);
        $this->assertStringNotContainsString('data-label-sort-up=', $content);
        $this->assertStringNotContainsString('data-label-sort-down=', $content);
        $this->assertStringContainsString('Save', $content);
        $this->assertStringNotContainsString('data-add-label', $content);
        $this->assertStringNotContainsString('data-replace-label', $content);
        $this->assertStringContainsString('data-pe-delete-image', $this->extractDeleteButtonOpeningTag($content));
    }

    #[Test]
    public function profileWithImageRendersSaveAndDeleteActions(): void
    {
        $this->setUpProfileEditingTestCase();
        $this->seedProfileImage();
        $content = $this->renderProfileEditingPage();
        $this->assertMatchesRegularExpression(
            '@<img\b[^>]+src="[^"]*profile-image[^"]*\.png"@',
            $content,
        );
        $this->assertStringContainsString('data-has-image="1"', $content);
        $this->assertStringContainsString('Save', $content);
        $this->assertStringContainsString('data-pe-delete-image', $this->extractDeleteButtonOpeningTag($content));
    }

    #[Test]
    public function imageUploadWithoutAFileNeverReturnsSuccess(): void
    {
        $this->setUpProfileEditingTestCase();
        $submitData = $this->extractImageFormSubmissionData($this->renderProfileEditingPage());
        $storedFilesBeforeRequest = $this->getStoredFiles();
        $response = $this->submitProfileImageForm($submitData['action'], $submitData['body']);
        $this->assertSame(422, $response->getStatusCode());
        $this->assertStringContainsString('application/json', $response->getHeaderLine('Content-Type'));
        $this->assertSame(
            [
                'success' => false,
                'error' => 'image_upload_missing',
                'message' => 'No new profile image was received.',
            ],
            json_decode((string)$response->getBody(), true, 512, JSON_THROW_ON_ERROR),
        );
        $this->assertSame(0, $this->getPersistedProfileImageCount());
        $this->assertSame($storedFilesBeforeRequest, $this->getStoredFiles());
    }

    #[Test]
    public function synchronizationAjaxEndpointPersistsOnlyTheCheckboxState(): void
    {
        $this->setUpProfileEditingTestCase();
        $content = $this->renderProfileEditingPage();
        $syncUrl = $this->extractDataUrl($content, 'data-skip-sync-url');
        $response = $this->postJson(
            $syncUrl,
            [
                'profile' => self::PROFILE_ID,
                'data' => ['skipSync' => true],
            ],
        );
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(
            [
                'success' => true,
                'profile' => self::PROFILE_ID,
                'skipSync' => true,
            ],
            json_decode((string)$response->getBody(), true, 512, JSON_THROW_ON_ERROR),
        );
        $storedValue = $this->getConnectionPool()
            ->getConnectionForTable('tx_academicpersons_domain_model_profile')
            ->executeQuery(
                'SELECT skip_sync FROM tx_academicpersons_domain_model_profile WHERE uid = ?',
                [self::PROFILE_ID],
            )
            ->fetchOne();
        $this->assertSame(1, (int)$storedValue);
    }

    #[Test]
    public function deleteImageAjaxEndpointDeletesRelationAndReturnsJson(): void
    {
        $this->setUpProfileEditingTestCase();
        $this->seedProfileImage();
        $imagePath = $this->instancePath . '/fileadmin' . self::IMAGE_IDENTIFIER;
        $this->assertFileExists($imagePath);
        $this->assertSame(1, $this->getPersistedProfileImageCount());
        $deleteUrl = $this->extractDataUrl(
            $this->renderProfileEditingPage(),
            'data-delete-image-url',
        );
        $response = $this->postJson(
            $deleteUrl,
            [
                'profile' => self::PROFILE_ID,
                'data' => [],
            ],
        );
        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('application/json', $response->getHeaderLine('Content-Type'));
        $this->assertSame(
            [
                'success' => true,
                'profile' => self::PROFILE_ID,
                'deleted' => true,
                'hasImage' => false,
            ],
            json_decode((string)$response->getBody(), true, 512, JSON_THROW_ON_ERROR),
        );
        $this->assertFileDoesNotExist($imagePath);
        $this->assertSame(0, $this->getPersistedProfileImageCount());
        $this->assertSame([], $this->getStoredFiles());
    }

    /**
     * A file the profile shares with another record survives the deletion.
     *
     * Deleting the profile image removes the reference and, when that was the
     * last one, the file itself - which is what keeps `fileadmin` from filling
     * up with orphans. A file that something else still references must not be
     * touched, and that branch is only reachable through the action: the
     * relation writer decides it, and the endpoint is what calls the writer.
     */
    #[Test]
    public function deletingTheProfileImageKeepsAFileThatIsStillUsedElsewhere(): void
    {
        $this->setUpProfileEditingTestCase();
        $fileUid = $this->seedProfileImage();
        // A second reference, as a content element image would hold it.
        $this->addFileReference($fileUid, 'tt_content', 'assets', 1);
        $imagePath = $this->instancePath . '/fileadmin' . self::IMAGE_IDENTIFIER;
        $this->assertFileExists($imagePath);

        $response = $this->postJson(
            $this->extractDataUrl($this->renderProfileEditingPage(), 'data-delete-image-url'),
            ['profile' => self::PROFILE_ID, 'data' => []],
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(
            [
                'success' => true,
                'profile' => self::PROFILE_ID,
                'deleted' => true,
                'hasImage' => false,
            ],
            json_decode((string)$response->getBody(), true, 512, JSON_THROW_ON_ERROR),
        );
        // The profile lost its image, the other record kept its file.
        $this->assertSame(0, $this->getPersistedProfileImageCount());
        $this->assertFileExists($imagePath);
        $this->assertSame(
            [$fileUid],
            array_column($this->getStoredFiles(), 'uid'),
        );
        $remaining = $this->getConnectionPool()
            ->getConnectionForTable('sys_file_reference')
            ->executeQuery(
                'SELECT tablenames FROM sys_file_reference WHERE uid_local = ? AND deleted = 0',
                [$fileUid],
            )
            ->fetchFirstColumn();
        $this->assertSame(['tt_content'], $remaining);
    }

    /**
     * The sorting values of one document section, keyed by record uid and ordered
     * the way the database holds them.
     *
     * @return array<int, int>
     */
    private function getPersistedDocumentSorting(string $type): array
    {
        $rows = $this->getConnectionPool()
            ->getConnectionForTable('tx_academicpersons_domain_model_profile_information')
            ->executeQuery(
                'SELECT uid, sorting FROM tx_academicpersons_domain_model_profile_information'
                . ' WHERE profile = ? AND type = ? AND deleted = 0 ORDER BY sorting ASC, uid ASC',
                [self::PROFILE_ID, $type],
            )
            ->fetchAllAssociative();
        $sorting = [];
        foreach ($rows as $row) {
            $sorting[(int)$row['uid']] = (int)$row['sorting'];
        }
        return $sorting;
    }

    /**
     * Both branches of "sortDocument" write what they answer.
     *
     * The response of the action is built from the in-memory objects and the existing
     * coverage asserts only that, so it reports the new order whether or not the same
     * order reached the database. This asserts the rows. Neither branch flushes by
     * itself - the step-wise one delegates to `ListSortingService`, which marks the
     * records through the persistence manager and leaves them there, and the reorder
     * branch does the same inline - so what makes the two agree today is the
     * `persistAll()` of `Extbase\Core\Bootstrap::resetSingletons()` at the end of every
     * plugin dispatch, plus the flush of `persistAndDispatchProfileUpdate()`. This pins
     * the outcome rather than either mechanism.
     */
    #[Test]
    public function sortDocumentPersistsTheNewOrderOfASection(): void
    {
        $this->setUpProfileEditingTestCase();
        $this->seedStructuredDocumentSections();
        $sortUrl = $this->extractDataUrl($this->renderProfileEditingPage(), 'data-sort-document-url');
        $this->assertSame([1 => 10, 2 => 20], $this->getPersistedDocumentSorting('cooperation'));
        $stepResponse = $this->postJson($sortUrl, [
            'profile' => self::PROFILE_ID,
            'data' => ['section' => 'cooperation', 'record' => 2, 'direction' => 'up'],
        ]);
        $this->assertSame(200, $stepResponse->getStatusCode(), (string)$stepResponse->getBody());
        $stepBody = json_decode((string)$stepResponse->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertTrue($stepBody['changed'] ?? null);
        $this->assertSame([2, 1], $stepBody['order'] ?? null);
        $this->assertSame([2 => 10, 1 => 20], $this->getPersistedDocumentSorting('cooperation'));
        $reorderResponse = $this->postJson($sortUrl, [
            'profile' => self::PROFILE_ID,
            'data' => ['section' => 'cooperation', 'order' => [1, 2]],
        ]);
        $this->assertSame(200, $reorderResponse->getStatusCode(), (string)$reorderResponse->getBody());
        $reorderBody = json_decode((string)$reorderResponse->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertTrue($reorderBody['changed'] ?? null);
        $this->assertSame([1, 2], $reorderBody['order'] ?? null);
        $this->assertSame([1 => 10, 2 => 20], $this->getPersistedDocumentSorting('cooperation'));
    }

    #[Test]
    public function profileEditorLabelsAreShippedInBothLanguages(): void
    {
        $expectedEnglish = [
            'list.language' => 'Language',
            'list.language.all' => 'All languages',
            'list.language.unknown' => 'Language %d',
            'profileEditing.backToList' => 'Back to profile overview',
            'profileEditing.btnEditAll' => 'Edit all',
            'profileEditing.btnCloseAll' => 'Close all',
            'profileEditing.image.heading' => 'Profile image',
            'profileEditing.image.editor.title' => 'Edit profile image',
            'profileEditing.status.close' => 'Close notification',
            'profileEditing.image.editor.hint.select' =>
            'Select an image to preview it. The profile image changes only after you save.',
            'profileEditing.image.editor.deleteHint' =>
            'The profile image is removed permanently unless another record still uses the file.',
            'profileEditing.image.upload.label' => 'Choose image',
            'profileEditing.image.status.uploaded' => 'The profile image has been saved.',
            'profileEditing.image.status.missing' =>
            'No new profile image was received. Please select the image again.',
            'profileEditing.image.status.deleted' => 'The profile image has been deleted.',
            'profileEditing.status.editorError' =>
            'The rich text editor could not be loaded. Please reload the page and try again.',
            'profileEditing.actions.clear' => 'Delete content',
            'profileEditing.actions.group' => 'Field actions',
            'profileEditing.content.empty' => 'No content',
            'profileEditing.visibility.private' => 'Private',
            'profileEditing.visibility.public' => 'Public',
            'profileEditing.documents.start' => 'Start',
            'profileEditing.documents.from' => 'From',
            'profileEditing.documents.to' => 'To',
            'profileEditing.documents.year' => 'Year',
            'profileEditing.documents.title' => 'Title',
            'profileEditing.documents.position' => 'Position',
            'profileEditing.documents.actionsHeading' => 'Actions',
            'profileEditing.documents.empty' => 'No entries have been added yet.',
            'profileEditing.documents.empty.contracts' => 'No contracts have been added yet.',
            'profileEditing.documents.empty.cooperation' => 'No cooperation entries have been added yet.',
            'profileEditing.documents.empty.lectures' => 'No lectures have been added yet.',
            'profileEditing.documents.empty.memberships' => 'No memberships have been added yet.',
            'profileEditing.documents.empty.pressMedia' => 'No press releases have been added yet.',
            'profileEditing.documents.empty.vita' => 'No vita entries have been added yet.',
            'profileEditing.documents.empty.publications' => 'No publications have been added yet.',
            'profileEditing.documents.empty.scientificResearch' =>
                'No scientific research entries have been added yet.',
        ];
        $languageService = $this->get(LanguageServiceFactory::class)->create('default');
        foreach ($expectedEnglish as $key => $translation) {
            $this->assertSame(
                $translation,
                $languageService->sL(
                    'LLL:EXT:academic_persons_edit/Resources/Private/Language/locallang.xlf:' . $key,
                ),
            );
        }
        $file = __DIR__ . '/../../../Resources/Private/Language/de.locallang.xlf';
        $document = new \DOMDocument();
        $this->assertTrue($document->load($file));
        $germanTranslations = [];
        foreach ($document->getElementsByTagName('trans-unit') as $unit) {
            $target = $unit->getElementsByTagName('target')->item(0);
            if ($target !== null) {
                $germanTranslations[(string)$unit->getAttribute('id')] = trim($target->textContent);
            }
        }
        foreach (array_keys($expectedEnglish) as $key) {
            $this->assertArrayHasKey($key, $germanTranslations, sprintf('No German translation for "%s".', $key));
            $this->assertNotSame('', $germanTranslations[$key]);
        }
        $this->assertSame('Alle bearbeiten', $germanTranslations['profileEditing.btnEditAll']);
        $this->assertSame('Alle schließen', $germanTranslations['profileEditing.btnCloseAll']);
        $this->assertSame('Sprache', $germanTranslations['list.language']);
        $this->assertSame('Zurück zur Profilübersicht', $germanTranslations['profileEditing.backToList']);
        $this->assertSame('Profilbild', $germanTranslations['profileEditing.image.heading']);
        $this->assertSame('Von', $germanTranslations['profileEditing.documents.from']);
        $this->assertSame('Bis', $germanTranslations['profileEditing.documents.to']);
        $this->assertSame('Jahr', $germanTranslations['profileEditing.documents.year']);
        $this->assertSame('Aktionen', $germanTranslations['profileEditing.documents.actionsHeading']);
        $this->assertSame(
            'Es wurden noch keine Einträge hinterlegt.',
            $germanTranslations['profileEditing.documents.empty'],
        );
        $this->assertSame(
            'Es wurden noch keine Pressemitteilungen hinterlegt.',
            $germanTranslations['profileEditing.documents.empty.pressMedia'],
        );
    }
}
