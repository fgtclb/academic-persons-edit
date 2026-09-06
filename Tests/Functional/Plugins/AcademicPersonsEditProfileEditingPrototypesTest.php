<?php

declare(strict_types=1);

/*
 * This file is part of the "academic_persons_edit" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace FGTCLB\AcademicPersonsEdit\Tests\Functional\Plugins;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

/**
 * The `<template data-pe-proto>` blocks the two browser rendered editors clone.
 *
 * They are the reason the document editor and the contact list of a contract
 * are still Fluid: their fields, options and display values come from a JSON
 * response, so the *shape* is rendered once per page and the elements fill it.
 * Two things have to hold for that to be worth anything, and neither is
 * visible to any other test:
 *
 * - The inventory and the slot keys have to be the ones
 *   `frontend/profile/prototypes.ts` declares. A `prototypeSlots` entry is a
 *   compile time guard against TypeScript filling a slot that does not exist;
 *   only this test guards the other direction, a partial that stops emitting
 *   one. What it compares is the *rendered* partial against the second hand
 *   copy of that table below, so a key renamed in Fluid alone fails here. The
 *   behavioural suite drives a hand written transcription of these blocks
 *   instead of the partial, and holds it against the same table from the other
 *   side (`prototypes.test.ts`, "the transcription in the fixture"), so a key
 *   renamed there alone fails in jsdom. Neither test sees the other's copy;
 *   the two together are what keeps partial, table and fixture in step.
 * - A prototype control and the live control of the same type have to be the
 *   same control. Before `Profile/Field/Control.html` became the one place a
 *   control is spelled there were three implementations of one control set and
 *   two of them had drifted - a checkbox with `form-check-input` in one editor
 *   and `form-control` in the other, shipped and reviewed. There is no
 *   exception list below on purpose: an exception is the drift starting again.
 */
final class AcademicPersonsEditProfileEditingPrototypesTest extends AbstractFrontendProfilePluginTestCase
{
    /**
     * Every prototype, with the slot, attribute and condition keys it carries.
     *
     * The mirror of `PrototypeSlots` and `PrototypeLists` in
     * `Resources/Private/TypeScript/frontend/profile/prototypes.ts`, spelled
     * out here rather than parsed out of the TypeScript: a rename has to be
     * made twice instead of silently agreeing with itself.
     *
     * @return array<string, array{0: list<string>, 1: list<string>}>
     */
    public static function prototypeProvider(): array
    {
        $control = ['controlId', 'name', 'disabled', 'describedBy', 'invalid', 'documentField', 'contactField'];
        $field = ['columnClass', 'compact', 'controlId', 'label', 'errorHidden', 'errorId', 'error'];
        $panel = [
            'busy', 'pending', 'spinnerHidden', 'errorHidden', 'error', 'isDelete',
            'isSave', 'deleteConfirmation', 'showDisplay', 'showFields', 'showActions',
        ];

        return [
            'control-input' => [
                [
                    ...$control, 'required', 'readOnly', 'inputType', 'value', 'autocomplete',
                    'placeholder', 'min', 'max', 'step',
                ],
                [],
            ],
            'control-textarea' => [[...$control, 'required', 'readOnly'], []],
            'control-rich-text' => [[...$control, 'required', 'readOnly', 'characterLimit'], []],
            'control-select' => [[...$control, 'required'], ['options']],
            'control-checkbox' => [[...$control, 'checked', 'autosave', 'checkedLabel', 'uncheckedLabel'], []],
            'option' => [['label', 'value'], []],
            'field-default' => [[...$field, 'required', 'hasCharacterLimit', 'characterLimit'], ['helptext', 'control']],
            'field-wide' => [[...$field, 'required', 'hasCharacterLimit', 'characterLimit'], ['helptext', 'control']],
            'field-checkbox' => [$field, ['helptext', 'control']],
            'helptext-button' => [['title', 'content', 'ariaLabel'], []],
            'display-row' => [['label', 'value', 'plain', 'richText'], ['richValue']],
            'document-panel' => [[...$panel, 'kind', 'heading', 'showContacts'], ['displayRows', 'contacts', 'fields']],
            'contact-section' => [
                [
                    'identifier', 'label', 'editorId', 'addExpanded', 'addDisabled',
                    'addEditorHidden', 'rowsHidden', 'emptyHidden', 'emptyMessage',
                ],
                ['addEditor', 'rows'],
            ],
            'contact-row' => [
                [
                    'uid', 'hidden', 'editorId', 'viewExpanded', 'editExpanded',
                    'deleteExpanded', 'editorHidden',
                ],
                ['summary', 'editor'],
            ],
            'contact-summary-cell' => [['label', 'value', 'hasValue', 'isEmpty'], []],
            'contact-editor-panel' => [[...$panel, 'editorId', 'title'], ['displayRows', 'fields']],
        ];
    }

    #[Test]
    public function everyPrototypeIsRenderedExactlyOnce(): void
    {
        $this->setUpProfileEditingTestCase();
        $prototypes = $this->getRenderedPrototypes();

        $this->assertSame(
            array_keys(self::prototypeProvider()),
            array_keys($prototypes),
            'Partials/Profile/Prototypes.html renders another set than PrototypeSlots declares.',
        );
    }

    /**
     * @param list<string> $slots
     * @param list<string> $lists
     */
    #[Test]
    #[DataProvider('prototypeProvider')]
    public function aPrototypeCarriesTheSlotsItDeclares(array $slots, array $lists): void
    {
        $this->setUpProfileEditingTestCase();
        $name = $this->dataName();
        $this->assertIsString($name);
        $prototypes = $this->getRenderedPrototypes();
        $this->assertArrayHasKey($name, $prototypes);
        $template = $prototypes[$name];

        sort($slots);
        sort($lists);
        $foundSlots = $this->collectKeys($template, ['data-pe-slot', 'data-pe-when'], true);
        $foundLists = $this->collectKeys($template, ['data-pe-list'], false);

        $this->assertSame($slots, $foundSlots);
        $this->assertSame($lists, $foundLists);
    }

    /**
     * The one that would have caught the drift: a prototype control and the
     * control the permanent profile fields are rendered with are one partial,
     * so they carry the same tag, the same classes and the same attributes.
     *
     * The prototype spells the concrete `id`, `name` and `value` as slot names
     * inside `data-pe-attr` instead of writing them, so the comparison is
     * between what the live control has and what the prototype either has or
     * binds. Nothing is exempt: *every* live control of a shape is compared,
     * not the first one, and the attribute names are compared in both
     * directions, so a prototype that carries one the live control does not
     * fails as well.
     *
     * Three of the five shapes have a live counterpart on the shipped
     * settings - a text input, a select and a rich text field. The plain
     * textarea and the checkbox have none, and neither is waved through: the
     * textarea is compared against the rich text control it shares a
     * `<f:form.textarea>` with, and that every field partial renders its
     * control through this one partial is asserted by
     * `everyFieldPartialRendersTheSharedControl()`.
     */
    #[Test]
    public function aPrototypeControlIsTheLiveControlOfItsType(): void
    {
        $this->setUpProfileEditingTestCase();
        $content = $this->renderProfileEditingPage();
        $document = new \DOMDocument();
        $this->assertTrue($document->loadHTML($content, LIBXML_NOERROR | LIBXML_NOWARNING));
        $xpath = new \DOMXPath($document);
        $prototypes = $this->getRenderedPrototypes();
        $compared = [];

        foreach (
            [
                'control-input' => 'input[not(@type="checkbox")]',
                'control-rich-text' => 'textarea[@data-pe-rich-text]',
                'control-select' => 'select',
            ] as $prototype => $selector
        ) {
            // Not below "[data-pe-field-editor]": libxml's HTML parser does
            // not nest the editing panels the way a browser does, and the
            // marker class plus "no prototype above it" names the same set.
            $live = $xpath->query(
                '//'
                    . $selector
                    . '[contains(concat(" ", normalize-space(@class), " "), " academic-persons-profile-editing__field ")]'
                    . '[not(ancestor::template)]',
            );
            $this->assertNotFalse($live);
            $this->assertGreaterThan(
                0,
                $live->length,
                sprintf('No live control of the "%s" shape is rendered.', $prototype),
            );
            $prototypeControl = $this->getPrototypeControl($prototypes[$prototype] ?? '');
            $compared[$prototype] = $live->length;
            $carriedLive = [];

            foreach ($live as $index => $liveControl) {
                $this->assertInstanceOf(\DOMElement::class, $liveControl);
                $where = sprintf('The "%s" prototype, against live control %d,', $prototype, $index);

                $this->assertSame(
                    $liveControl->tagName,
                    $prototypeControl->tagName,
                    $where . ' is a different element.',
                );
                $this->assertSame(
                    $this->classList($liveControl),
                    $this->classList($prototypeControl),
                    $where . ' carries other classes.',
                );
                $bound = $this->attributeNames($prototypeControl);
                $liveAttributes = $this->attributeNames($liveControl);
                $carriedLive = [...$carriedLive, ...$liveAttributes];
                foreach ($liveAttributes as $attribute) {
                    $this->assertContains(
                        $attribute,
                        $bound,
                        $where . sprintf(' neither carries nor binds "%s".', $attribute),
                    );
                }
            }

            // The other direction. An attribute the prototype carries and no
            // live control does is either one of the conditional ones below -
            // a boolean attribute Fluid omits when it is false, an
            // autocomplete token not every field declares, or a marker only a
            // document or contact field gets - or it is an attribute that
            // exists in the prototype alone, which is the drift this test is
            // about.
            $this->assertSame(
                [],
                array_values(array_diff(
                    $this->attributeNames($prototypeControl),
                    array_unique($carriedLive),
                    [
                        'autocomplete',
                        'data-pe-character-limit',
                        'data-pe-contract-contact-field',
                        'data-pe-document-field',
                        'disabled',
                        // The bounds of a number control. Only the three
                        // timeline year fields of a document editor declare
                        // them, and no permanent profile field does.
                        'max',
                        'min',
                        // The hint of the two contract date controls. They
                        // are document fields, so no permanent profile field
                        // carries it either.
                        'placeholder',
                        'readonly',
                        'required',
                        'step',
                    ],
                )),
                sprintf('The "%s" prototype carries an attribute no live control does.', $prototype),
            );
        }

        // "control-textarea" is the one shape with no live counterpart: no
        // profile field of the shipped settings uses the plain textarea render
        // type, they are all rich text. It comes out of the same
        // "<f:form.textarea>" call as the rich text control does, one argument
        // apart, and this says so rather than leaving the shape uncovered.
        $this->assertSame(
            ['control-input', 'control-rich-text', 'control-select'],
            array_keys($compared),
            'Another set of control shapes is rendered live than this test compares.',
        );

        $plain = $this->getPrototypeControl($prototypes['control-textarea'] ?? '');
        $rich = $this->getPrototypeControl($prototypes['control-rich-text'] ?? '');
        $this->assertSame($rich->tagName, $plain->tagName);
        $this->assertSame($this->classList($rich), $this->classList($plain));
        $this->assertSame(
            array_values(array_diff(
                $this->attributeNames($rich),
                ['data-pe-rich-text', 'data-pe-character-limit'],
            )),
            $this->attributeNames($plain),
        );
    }

    /**
     * A value the visitor typed reaches a prototype as text.
     *
     * The filler writes `textContent`, never `innerHTML`, and this asserts the
     * server side of the same rule: the display value of a document field is
     * escaped when the row it belongs to is rendered, so what reaches the page
     * for `<script>` is the text of it.
     */
    #[Test]
    public function aDisplayValueThatContainsMarkupIsRenderedAsText(): void
    {
        $this->setUpProfileEditingTestCase();
        $this->getConnectionPool()
            ->getConnectionForTable('tx_academicpersons_domain_model_profile')
            ->update(
                'tx_academicpersons_domain_model_profile',
                ['middle_name' => '<script>alert(1)</script>'],
                ['uid' => self::PROFILE_ID],
            );
        $content = $this->renderProfileEditingPage();

        $this->assertStringNotContainsString('<script>alert(1)</script>', $content);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $content);
    }

    /**
     * The consolidation itself, asserted on the page rather than on the source:
     * every form control the editor renders comes out of
     * `Profile/Field/Control.html`.
     *
     * The marker class that partial writes is the evidence. A control spelled
     * anywhere else either lacks it - and is then found here - or carries it
     * and is compared attribute by attribute against the prototype of its shape
     * by `aPrototypeControlIsTheLiveControlOfItsType()`. That is what makes the
     * equivalence hold for the two shapes no page of the shipped settings
     * renders live.
     *
     * Two controls are named exceptions and neither is a profile field:
     *
     * - the synchronisation switch of `Profile/Header.html`, which deliberately
     *   carries `__sync-checkbox` and not `__field`, because
     *   `frontend/profile/fields.ts` collects the latter and would save and
     *   validate the switch as a mapped property. It is spelled once, and with
     *   the `disabled` expression `Field/Control.html` uses.
     * - the `<f:form.upload>` of `Image/Editor.html`, which is the Extbase file
     *   upload control and has no counterpart in the five shapes.
     *
     * The three field partials are additionally read as source, because "renders
     * no control of its own" is a statement about the file and not about one
     * page: the regular expression matches Fluid's tag form and its inline form
     * alike, which `'<f:form.'` did not.
     */
    #[Test]
    public function everyFieldPartialRendersTheSharedControl(): void
    {
        foreach (['Field/Editable', 'Field/Select', 'Field/Checkbox'] as $partial) {
            $source = $this->getPartialSource($partial);
            $this->assertStringContainsString('partial="Profile/Field/Control"', $source);
            $this->assertDoesNotMatchRegularExpression(
                '@\bf:form\.@',
                $source,
                sprintf('%s spells a form control of its own.', $partial),
            );
        }

        $this->setUpProfileEditingTestCase();
        $content = $this->renderProfileEditingPage();
        $document = new \DOMDocument();
        $this->assertTrue($document->loadHTML($content, LIBXML_NOERROR | LIBXML_NOWARNING));
        $xpath = new \DOMXPath($document);

        $controls = $xpath->query(
            '//*[self::input or self::select or self::textarea]'
                . '[not(@type="hidden")]'
                . '[not(@type="file")]'
                . '[not(contains(concat(" ", normalize-space(@class), " "), '
                . '" academic-persons-profile-editing__sync-checkbox "))]',
        );
        $this->assertNotFalse($controls);
        $this->assertGreaterThan(0, $controls->length);
        foreach ($controls as $control) {
            $this->assertInstanceOf(\DOMElement::class, $control);
            $this->assertContains(
                'academic-persons-profile-editing__field',
                $this->classList($control),
                sprintf(
                    'A <%s> control is rendered outside Profile/Field/Control.html: %s',
                    $control->tagName,
                    $document->saveHTML($control),
                ),
            );
        }

        // One control per prototype, found by the same marker class - which is
        // what lets the equivalence test above address it at all.
        $prototypes = $this->getRenderedPrototypes();
        foreach (
            [
                'control-input',
                'control-textarea',
                'control-rich-text',
                'control-select',
                'control-checkbox',
            ] as $prototype
        ) {
            $this->assertArrayHasKey($prototype, $prototypes);
            $this->getPrototypeControl($prototypes[$prototype]);
        }
    }

    /**
     * The three partials the prototypes of the two editors live in.
     *
     * They keep the names and the role they had: an integrator who overrode
     * them overrides the same file, and what changed is that the file renders
     * a shape instead of finished markup.
     */
    #[Test]
    public function theEditorPartialsOwnTheirPrototypes(): void
    {
        foreach (
            [
                'Documents/Editor' => ['document-panel'],
                'Documents/ContractContacts' => ['contact-section', 'contact-row', 'contact-summary-cell'],
                'Documents/ContractContactEditor' => ['contact-editor-panel'],
            ] as $partial => $names
        ) {
            $path = __DIR__ . '/../../../Resources/Private/Partials/Profile/' . $partial . '.html';
            $this->assertFileExists($path);
            $source = file_get_contents($path);
            $this->assertIsString($source);
            $this->assertSame(
                count($names),
                substr_count($source, 'data-pe-proto='),
                sprintf('%s renders another number of prototypes.', $partial),
            );
            foreach ($names as $name) {
                $this->assertStringContainsString('data-pe-proto="' . $name . '"', $source);
            }
            // No branching inside a prototype: the only decision a partial of
            // this kind makes is which prototype it emits, and it makes none.
            $this->assertStringNotContainsString('<f:if condition', $source);
        }
        // The same holds for the file that renders them all. The one branch of
        // the whole prototype tree is the "prototype" flag of
        // Profile/Field/Control.html, and the shape argument of
        // Profile/Field/PrototypeWrapper.html, which decides which prototype is
        // emitted rather than what one contains.
        $this->assertSame(
            0,
            substr_count($this->getPartialSource('Prototypes'), '<f:if condition'),
        );
    }

    private function getPartialSource(string $relativePath): string
    {
        $path = __DIR__ . '/../../../Resources/Private/Partials/Profile/' . $relativePath . '.html';
        $this->assertFileExists($path);
        $source = file_get_contents($path);
        $this->assertIsString($source);

        return $source;
    }

    /**
     * The rendered prototypes of one page, keyed by name.
     *
     * @return array<string, string>
     */
    private function getRenderedPrototypes(): array
    {
        $content = $this->renderProfileEditingPage();
        $document = new \DOMDocument();
        $this->assertTrue($document->loadHTML($content, LIBXML_NOERROR | LIBXML_NOWARNING));
        $templates = (new \DOMXPath($document))->query('//template[@data-pe-proto]');
        $this->assertNotFalse($templates);
        $prototypes = [];
        foreach ($templates as $template) {
            $this->assertInstanceOf(\DOMElement::class, $template);
            $name = $template->getAttribute('data-pe-proto');
            $this->assertArrayNotHasKey($name, $prototypes, sprintf('"%s" is rendered twice.', $name));
            $markup = '';
            foreach ($template->childNodes as $child) {
                $markup .= $document->saveHTML($child);
            }
            $prototypes[$name] = $markup;
        }

        return $prototypes;
    }

    /**
     * The keys one prototype names in the given verbs, sorted and unique.
     *
     * @param list<string> $attributes
     * @return list<string>
     */
    private function collectKeys(string $markup, array $attributes, bool $withBindings): array
    {
        $keys = [];
        foreach ($attributes as $attribute) {
            preg_match_all('@\b' . preg_quote($attribute, '@') . '="([^"]*)"@', $markup, $matches);
            foreach ($matches[1] as $value) {
                $keys[] = $value;
            }
        }
        if ($withBindings) {
            preg_match_all('@\bdata-pe-attr="([^"]*)"@', $markup, $matches);
            foreach ($matches[1] as $value) {
                foreach (preg_split('@\s+@', trim($value)) ?: [] as $binding) {
                    if ($binding === '') {
                        continue;
                    }
                    $separator = strpos($binding, ':');
                    $this->assertNotFalse($separator, sprintf('"%s" is not "attribute:key".', $binding));
                    $keys[] = substr($binding, $separator + 1);
                }
            }
        }
        $keys = array_values(array_unique($keys));
        sort($keys);

        return $keys;
    }

    private function getPrototypeControl(string $markup): \DOMElement
    {
        $this->assertNotSame('', $markup);
        $document = new \DOMDocument();
        $this->assertTrue(
            $document->loadHTML('<body>' . $markup . '</body>', LIBXML_NOERROR | LIBXML_NOWARNING),
        );
        $controls = (new \DOMXPath($document))->query(
            '//*[contains(concat(" ", normalize-space(@class), " "), " academic-persons-profile-editing__field ")]',
        );
        $this->assertNotFalse($controls);
        $this->assertSame(1, $controls->length);
        $control = $controls->item(0);
        $this->assertInstanceOf(\DOMElement::class, $control);

        return $control;
    }

    /**
     * @return list<string>
     */
    private function classList(\DOMElement $element): array
    {
        $classes = preg_split('@\s+@', trim($element->getAttribute('class'))) ?: [];
        sort($classes);

        return array_values($classes);
    }

    /**
     * Every attribute the element carries, plus every attribute its
     * `data-pe-attr` binds - which is the same thing said before and after the
     * filler ran.
     *
     * @return list<string>
     */
    private function attributeNames(\DOMElement $element): array
    {
        $names = [];
        foreach ($element->attributes as $attribute) {
            if ($attribute->nodeName === 'data-pe-attr' || $attribute->nodeName === 'data-pe-list') {
                continue;
            }
            $names[] = $attribute->nodeName;
        }
        foreach (preg_split('@\s+@', trim($element->getAttribute('data-pe-attr'))) ?: [] as $binding) {
            if ($binding === '') {
                continue;
            }
            $separator = strpos($binding, ':');
            if ($separator !== false) {
                $names[] = substr($binding, 0, $separator);
            }
        }
        $names = array_values(array_unique($names));
        sort($names);

        return $names;
    }
}
