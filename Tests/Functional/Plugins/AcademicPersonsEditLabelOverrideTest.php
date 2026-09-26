<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Tests\Functional\Plugins;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

/**
 * Label overrides through `_LOCAL_LANG` reach every kind of translation the profile editing
 * plugin renders, on TYPO3 v12 and v13: a label comes from its language file, an override of
 * the extension (`plugin.tx_academicpersonsedit`) replaces it, and an override of the plugin
 * (`plugin.tx_academicpersonsedit_profileediting`) replaces both.
 *
 * TYPO3 v12 and v13 build the TypoScript path from the extension name a translation passes,
 * only lowercased. The templates and the flash messages passed `academic_persons_edit` and
 * read `plugin.tx_academic_persons_edit`; the options of the select fields passed
 * `persons_edit`, no spelling of this extension at all, and read `plugin.tx_persons_edit`.
 *
 * The options are translated from the full `LLL:` references of their TCA items, which point
 * into the language file of academic_persons. TYPO3 applies the overrides of the name passed
 * along to that file, so such an override is keyed by the id of the label in that file.
 *
 * TYPO3 v12 and v13 also keep the labels of a language file, overrides included, for the rest
 * of the request: once one translation has read the right path, the ones after it show its
 * overrides whatever name they pass. Before the change no translation of these files had the
 * right name, so every override case failed on its own. Since then, a case that is not the
 * first translation of its file in the request would pass even if its own call lost the name
 * again; the check of the extension names of all translations holds those calls. A flash
 * message is translated while the update is handled, and shown on the next page of the
 * plugin.
 */
final class AcademicPersonsEditLabelOverrideTest extends AbstractProfileEditingPluginTestCase
{
    private function setUpSite(string $setup): void
    {
        $this->setUpTestCase();
        $connection = $this->getConnectionPool()->getConnectionForTable('sys_template');
        $template = $connection->select(['uid', 'config'], 'sys_template', ['pid' => 1])->fetchAssociative();
        $this->assertIsArray($template);
        $connection->update('sys_template', ['config' => $template['config'] . LF . $setup], ['uid' => $template['uid']]);
    }

    private function render(string $page, string $setup): string
    {
        $this->setUpSite($setup);
        switch ($page) {
            case 'list':
                $content = $this->getPageAsFrontendUser('https://www.acme.com/home');
                break;
            case 'edit':
                $content = $this->getPageAsFrontendUser($this->getProfileEditFormUrl());
                break;
            default:
                // The message is queued in the session of the frontend user and shown by the
                // next page of the plugin that renders the flash messages.
                $this->submitProfileForm($this->getProfileEditFormUrl(), [
                    'website' => 'https://submitted.example.org',
                ]);
                $content = $this->getPageAsFrontendUser('https://www.acme.com/home');
        }

        return (string)preg_replace('/\s+/', ' ', $content);
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
     * Every kind of translation the plugin renders: an inline call, one inside the argument of
     * a partial with its quotes escaped, the options of a select field and a flash message,
     * the last two translated in PHP.
     *
     * @return \Generator<string, array{0: string, 1: string, 2: string, 3: string}>
     */
    public static function translationDataProvider(): \Generator
    {
        yield 'list, inline' => [
            'list', 'list.profile', 'Profile',
            '<th class="single-column"> %s </th>',
        ];
        yield 'list, in the argument of a partial' => [
            'list', 'list.profile.assigned', 'Assigned profiles',
            '<h1 class="header"> %s </h1>',
        ];
        yield 'edit form, option of a select, translated in PHP' => [
            'edit', 'tx_academicpersons_domain_model_profile.columns.gender.items.ms', 'Ms.',
            '>%s</option>',
        ];
        yield 'flash message, translated in PHP' => [
            'flash', 'profile.update.success', 'Profile updated successfully.',
            '<div class="academic-persons-edit"> [OK] %s <div',
        ];
    }

    #[DataProvider('translationDataProvider')]
    #[Test]
    public function thePluginRendersTheLabelOfTheLanguageFile(string $page, string $key, string $label, string $markup): void
    {
        $this->assertStringContainsString(sprintf($markup, $label), $this->render($page, ''));
    }

    #[DataProvider('translationDataProvider')]
    #[Test]
    public function thePluginRendersTheLabelOfTheExtension(string $page, string $key, string $label, string $markup): void
    {
        $content = $this->render($page, $this->localLang($key, [
            'plugin.tx_academicpersonsedit' => 'Extension label',
        ]));

        $this->assertStringContainsString(sprintf($markup, 'Extension label'), $content);
    }

    #[DataProvider('translationDataProvider')]
    #[Test]
    public function thePluginRendersTheLabelOfThePlugin(string $page, string $key, string $label, string $markup): void
    {
        $content = $this->render($page, $this->localLang($key, [
            'plugin.tx_academicpersonsedit_profileediting' => 'Plugin label',
        ]));

        $this->assertStringContainsString(sprintf($markup, 'Plugin label'), $content);
    }

    #[DataProvider('translationDataProvider')]
    #[Test]
    public function theLabelOfThePluginWinsOverTheOneOfTheExtension(string $page, string $key, string $label, string $markup): void
    {
        $content = $this->render($page, $this->localLang($key, [
            'plugin.tx_academicpersonsedit' => 'Extension label',
            'plugin.tx_academicpersonsedit_profileediting' => 'Plugin label',
        ]));

        $this->assertStringContainsString(sprintf($markup, 'Plugin label'), $content);
        $this->assertStringNotContainsString('Extension label', $content);
    }

    /**
     * @return \Generator<string, array{0: string, 1: string}>
     */
    public static function singleYearDataProvider(): \Generator
    {
        yield 'label of the language file of academic_persons' => ['', 'Since 2020'];
        yield 'label of academic_persons' => ['plugin.tx_academicpersons._LOCAL_LANG.default.detail.since = Starting', 'Starting 2020'];
        yield 'label of the editor, which these words do not read' => ['plugin.tx_academicpersonsedit._LOCAL_LANG.default.detail.since = Starting', 'Since 2020'];
    }

    /**
     * The word before the year of a profile information entry with a start year only is a
     * label of academic_persons, shared with its profile detail, and the editor translates it
     * with that extension's name. It reads the override of academic_persons, not the ones of
     * the editor.
     */
    #[DataProvider('singleYearDataProvider')]
    #[Test]
    public function theWordBeforeASingleYearReadsTheLabelOfAcademicPersons(string $setup, string $expected): void
    {
        $this->setUpSite($setup);
        $this->getConnectionPool()
            ->getConnectionForTable('tx_academicpersons_domain_model_profile_information')
            ->insert('tx_academicpersons_domain_model_profile_information', [
                'uid' => 1,
                'pid' => self::PROFILE_PAGE_ID,
                'profile' => self::PROFILE_ID,
                'type' => 'publication',
                'title' => 'A publication',
                'year_start' => 2020,
            ]);
        // The relation counter of the profile, which Extbase reads before it loads the entries.
        $this->getConnectionPool()
            ->getConnectionForTable('tx_academicpersons_domain_model_profile')
            ->update('tx_academicpersons_domain_model_profile', ['publications' => 1], ['uid' => self::PROFILE_ID]);

        $this->assertStringContainsString($expected, (string)preg_replace('/\s+/', ' ', $this->getProfileShowPage()));
    }
}
