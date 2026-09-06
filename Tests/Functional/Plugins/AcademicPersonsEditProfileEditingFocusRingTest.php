<?php

declare(strict_types=1);

/*
 * This file is part of the "academic_persons_edit" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace FGTCLB\AcademicPersonsEdit\Tests\Functional\Plugins;

use PHPUnit\Framework\Attributes\Test;

/**
 * The markup side of the focus ring the editor draws for itself.
 *
 * `Resources/Private/Scss/frontend/profile-editing.scss` replaces the theme's
 * focus appearance for every control and every button of the plugin with one
 * rule, `… :is(input, select, textarea, button):focus-visible`. The cascade it
 * wins is not observable here and not in jsdom either - no stylesheet is ever
 * loaded into the JavaScript suite - and it was verified in a browser instead.
 * What *is* observable, and what actually breaks the rule in practice, is the
 * coupling between that selector and the markup:
 *
 * - A focusable thing rendered as something other than those four tags is not
 *   reached by the rule at all. That is how the ring would silently go missing
 *   from an action - an `<a class="btn">` looks like a button and is not one.
 * - A control rendered without its Bootstrap class has no border and no
 *   padding for a ring drawn *inside* its border box to sit on, so an inset
 *   outline would be painted across its own text.
 *
 * Both are asserted over the rendered page including the
 * `<template data-pe-proto>` blocks: those are the markup of the document
 * editor and of the contact list, and a control in them is a control on the
 * page as soon as an element clones it.
 */
final class AcademicPersonsEditProfileEditingFocusRingTest extends AbstractFrontendProfilePluginTestCase
{
    /**
     * The four tags of the rule's `:is()`.
     *
     * @var list<string>
     */
    private const COVERED_TAGS = ['input', 'select', 'textarea', 'button'];

    #[Test]
    public function everyFocusableThingTheEditorRendersIsOneOfTheTagsTheFocusRingCovers(): void
    {
        $this->setUpProfileEditingTestCase();
        $xpath = $this->parseProfileEditingPage();

        // Everything that takes focus and is not one of the four tags: a
        // widget built from a `<div>` or a link dressed as a button. A plain
        // anchor is deliberately outside the rule - no theme rule takes its
        // focus ring away and an inset outline would be drawn across its text
        // - so it is only rejected when it is dressed as a control.
        $foreign = $xpath->query(
            '//*['
                . 'not(self::input or self::select or self::textarea or self::button)'
                . ' and ('
                . '(@tabindex and @tabindex >= 0)'
                . ' or @contenteditable'
                . ' or @role = "button"'
                . ' or @role = "checkbox"'
                . ' or @role = "switch"'
                . ' or @role = "textbox"'
                . ' or (self::a and contains(concat(" ", normalize-space(@class), " "), " btn "))'
                . ')'
                . ']',
        );
        $this->assertNotFalse($foreign);
        $this->assertSame(
            [],
            $this->describe($foreign),
            'The profile editor renders a focusable control the focus ring rule of'
            . ' Resources/Private/Scss/frontend/profile-editing.scss cannot reach.'
            . ' Either render it as one of "' . implode('", "', self::COVERED_TAGS)
            . '", or widen the rule and this test together.',
        );
    }

    #[Test]
    public function everyControlAndButtonCarriesTheBootstrapClassTheInsetRingNeeds(): void
    {
        $this->setUpProfileEditingTestCase();
        $xpath = $this->parseProfileEditingPage();

        // A hidden input is not a control: Extbase's referrer and trusted
        // property fields, the checkbox companion field and the form tokens
        // are all `type="hidden"` and carry no class by design.
        $controls = $xpath->query(
            '//select | //textarea | //input[not(@type) or @type != "hidden"]',
        );
        $this->assertNotFalse($controls);
        $this->assertGreaterThan(0, $controls->length, 'The editor rendered no control at all.');
        $this->assertSame(
            [],
            $this->describe($this->withoutOneOf($controls, ['form-control', 'form-select', 'form-check-input'])),
            'A control of the profile editor carries none of the Bootstrap control classes.'
            . ' The focus ring is drawn inside the border box and needs the border and the'
            . ' padding those classes give the control.',
        );

        $buttons = $xpath->query('//button');
        $this->assertNotFalse($buttons);
        $this->assertGreaterThan(0, $buttons->length, 'The editor rendered no button at all.');
        $this->assertSame(
            [],
            $this->describe($this->withoutOneOf($buttons, ['btn', 'btn-close'])),
            'A button of the profile editor carries neither "btn" nor "btn-close".',
        );
    }

    #[Test]
    public function theEditorRendersAControlOfEveryTagTheFocusRingRuleNames(): void
    {
        $this->setUpProfileEditingTestCase();
        $xpath = $this->parseProfileEditingPage();

        foreach (self::COVERED_TAGS as $tag) {
            $found = $xpath->query('//' . $tag);
            $this->assertNotFalse($found);
            $this->assertGreaterThan(
                0,
                $found->length,
                sprintf(
                    'The focus ring rule names "%s" and the editor renders none, so the two'
                    . ' assertions above cannot fail for that tag.',
                    $tag,
                ),
            );
        }
    }

    private function parseProfileEditingPage(): \DOMXPath
    {
        $document = new \DOMDocument();
        $this->assertTrue(
            $document->loadHTML($this->renderProfileEditingPage(), LIBXML_NOERROR | LIBXML_NOWARNING),
        );

        return new \DOMXPath($document);
    }

    /**
     * @param iterable<\DOMNode> $elements
     * @param list<string> $classes
     * @return list<\DOMElement>
     */
    private function withoutOneOf(iterable $elements, array $classes): array
    {
        $offending = [];
        foreach ($elements as $element) {
            if (!$element instanceof \DOMElement) {
                continue;
            }
            $carried = preg_split('/\s+/', trim($element->getAttribute('class')));
            if ($carried === false || array_intersect($classes, $carried) === []) {
                $offending[] = $element;
            }
        }

        return $offending;
    }

    /**
     * The offending elements as `tag[attribute="value" …]`, so a failure names
     * the element instead of counting it.
     *
     * @param iterable<\DOMNode> $elements
     * @return list<string>
     */
    private function describe(iterable $elements): array
    {
        $described = [];
        foreach ($elements as $element) {
            if (!$element instanceof \DOMElement) {
                continue;
            }
            $attributes = [];
            foreach (['id', 'class', 'type', 'name', 'role', 'tabindex'] as $attribute) {
                if ($element->hasAttribute($attribute)) {
                    $attributes[] = sprintf('%s="%s"', $attribute, $element->getAttribute($attribute));
                }
            }
            $described[] = $element->tagName
                . ($attributes === [] ? '' : '[' . implode(' ', $attributes) . ']');
        }

        return $described;
    }
}
