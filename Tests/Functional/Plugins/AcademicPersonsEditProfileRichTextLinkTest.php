<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Tests\Functional\Plugins;

use PHPUnit\Framework\Attributes\Test;

/**
 * A link typed into a rich text field of the profile edit form survives the save.
 *
 * The editor offers a link button, and this branch stores what the form
 * submitted - there is no server side sanitiser between the two. This test pins
 * the storage half of that: a submitted `<a href>` reaches the record unchanged,
 * so the button is not made useless by something stripping its result on the way
 * in.
 *
 * The rendering half - which protocols a stored link is rendered with - is pinned
 * in `EXT:academic_persons`, by
 * `AcademicPersonsDetailPluginRichTextLinksTest`, because the rendering happens
 * in that extension's detail view.
 *
 * `teachingArea` is used rather than one of the name fields, because the shipped
 * `profile` validation set marks `firstName`, `middleName` and `lastName` as
 * `disabled`, so those are rejected before the request is consulted at all.
 */
final class AcademicPersonsEditProfileRichTextLinkTest extends AbstractProfileEditingPluginTestCase
{
    private const SUBMITTED_RICH_TEXT = '<p>See <a href="https://example.org">Example</a> for more.</p>';

    private function getStoredTeachingArea(): string
    {
        return (string)$this->getConnectionPool()
            ->getConnectionForTable('tx_academicpersons_domain_model_profile')
            ->executeQuery(
                'SELECT teaching_area FROM tx_academicpersons_domain_model_profile WHERE uid = ?',
                [self::PROFILE_ID],
            )
            ->fetchOne();
    }

    #[Test]
    public function aLinkSubmittedInARichTextFieldIsStoredUnchanged(): void
    {
        $this->setUpTestCase();

        $this->submitProfileForm(
            $this->getProfileEditFormUrl(),
            ['teachingArea' => self::SUBMITTED_RICH_TEXT],
        );

        $this->assertSame(self::SUBMITTED_RICH_TEXT, $this->getStoredTeachingArea());
    }

    /**
     * The rich text field is rendered back into the form with its markup intact,
     * so the editor that is attached to it opens on what was stored rather than
     * on an escaped copy of it.
     */
    #[Test]
    public function theStoredLinkIsRenderedBackIntoTheEditForm(): void
    {
        $this->setUpTestCase();
        $this->getConnectionPool()
            ->getConnectionForTable('tx_academicpersons_domain_model_profile')
            ->update(
                'tx_academicpersons_domain_model_profile',
                ['teaching_area' => self::SUBMITTED_RICH_TEXT],
                ['uid' => self::PROFILE_ID],
            );

        $content = $this->getPageAsFrontendUser($this->getProfileEditFormUrl());

        $this->assertStringContainsString(
            'href=&quot;https://example.org&quot;',
            $content,
            'The stored link is not rendered into the textarea the editor is attached to.',
        );
    }
}
