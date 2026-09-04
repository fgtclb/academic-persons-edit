<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Tests\Functional\Plugins;

use PHPUnit\Framework\Attributes\Test;

/**
 * Submits the profile edit form of the `academicpersonsedit_profileediting` plugin through a
 * real frontend request and pins the DTO to domain model transformation end to end.
 *
 * The factory tests in `Tests/Functional/Domain/Factory/` drive the property mapper directly,
 * which lets them configure the type converter with an `argumentName` of their choosing. This
 * test does not: it walks the plugin the way a visitor does, so the argument name is the one
 * `AbstractActionController::initializeAction()` derives from the action signature, and the
 * request arguments are the ones `RequestBuilder` assembles from the posted body.
 *
 * That closes the only gap the factory tests leave open — whether the "was this property sent"
 * detection works when nothing about the request is arranged by hand.
 *
 * The form walking itself (edit link, hidden fields, POST body) lives in
 * {@see AbstractProfileEditingPluginTestCase}, shared with
 * {@see AcademicPersonsEditProfileEditTranslationSyncTest}.
 */
final class AcademicPersonsEditProfileFormSubmissionTest extends AbstractProfileEditingPluginTestCase
{
    /**
     * @return array{website: string, website_title: string}
     */
    private function getStoredWebsiteFields(): array
    {
        $row = $this->getConnectionPool()
            ->getConnectionForTable('tx_academicpersons_domain_model_profile')
            ->executeQuery(
                'SELECT website, website_title FROM tx_academicpersons_domain_model_profile WHERE uid = ?',
                [self::PROFILE_ID],
            )
            ->fetchAssociative();
        $this->assertIsArray($row, 'The profile record is missing.');

        return [
            'website' => (string)$row['website'],
            'website_title' => (string)$row['website_title'],
        ];
    }

    /**
     * Seeds both website fields so that "kept its stored value" is distinguishable from
     * "was empty all along".
     */
    private function seedWebsiteFields(): void
    {
        $this->getConnectionPool()
            ->getConnectionForTable('tx_academicpersons_domain_model_profile')
            ->update(
                'tx_academicpersons_domain_model_profile',
                ['website' => 'https://stored.example.org', 'website_title' => 'Stored title'],
                ['uid' => self::PROFILE_ID],
            );
    }

    /**
     * The baseline: a submitted property reaches the record. If the argument name were not set
     * on the form data object, `shouldApplyProperty()` would be false for every property and
     * both fields would keep their seeded values.
     *
     * `website` and `websiteTitle` are used rather than the name fields, because the shipped
     * `profile` map marks `firstName`, `middleName` and `lastName` as `disabled`, so
     * `mayApplyProperty()` rejects them before the request is consulted at all. `gender` is
     * posted with every submission, as the rendered form does: the shipped map requires it,
     * and a submission without it is rejected as a whole.
     */
    #[Test]
    public function submittedPropertyIsPersisted(): void
    {
        $this->setUpTestCase();
        $this->seedWebsiteFields();

        $this->submitProfileForm($this->getProfileEditFormUrl(), [
            'gender' => 'ms',
            'website' => 'https://submitted.example.org',
            'websiteTitle' => 'Submitted title',
        ]);

        $this->assertSame(
            ['website' => 'https://submitted.example.org', 'website_title' => 'Submitted title'],
            $this->getStoredWebsiteFields(),
        );
    }

    /**
     * The other half, and the behaviour ACE-33 introduced: a property that is not part of the
     * submitted body keeps its stored value instead of being overwritten with the empty form
     * data default.
     *
     * Both halves together prove that `wasPropertySentInRequest()` discriminates correctly in a
     * request nothing arranged by hand — the argument name resolves from the action signature,
     * and the per-property decision follows the posted keys.
     */
    #[Test]
    public function propertyMissingFromTheSubmissionKeepsItsStoredValue(): void
    {
        $this->setUpTestCase();
        $this->seedWebsiteFields();

        $this->submitProfileForm(
            $this->getProfileEditFormUrl(),
            ['gender' => 'ms', 'website' => 'https://submitted.example.org'],
        );

        $this->assertSame(
            ['website' => 'https://submitted.example.org', 'website_title' => 'Stored title'],
            $this->getStoredWebsiteFields(),
        );
    }
}
