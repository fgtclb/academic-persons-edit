<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Tests\Functional\Plugins;

use PHPUnit\Framework\Attributes\Test;

/**
 * A profile field an installation locks for owners with `frontendreadonly`.
 *
 * The fixture extension `test_frontend_readonly` of `academic_persons` marks the
 * profile website. The edit form shows it read-only and a submitted value is
 * ignored, exactly as for `readonly`, while the website title next to it is still
 * written. That the backend form keeps the website editable is covered next to the
 * TCA, in `academic_persons`.
 */
final class AcademicPersonsEditFrontendReadOnlyFieldTest extends AbstractProfileEditingPluginTestCase
{
    protected function setUp(): void
    {
        $this->testExtensionsToLoad[] = 'tests/test-frontend-readonly';
        parent::setUp();
    }

    /**
     * @return array{website: string, website_title: string}
     */
    private function getStoredWebsiteFields(): array
    {
        $row = $this->getConnectionPool()
            ->getConnectionForTable('tx_academicpersons_domain_model_profile')
            ->select(['website', 'website_title'], 'tx_academicpersons_domain_model_profile', ['uid' => self::PROFILE_ID])
            ->fetchAssociative();
        $this->assertIsArray($row, 'The profile record is missing.');

        return [
            'website' => (string)$row['website'],
            'website_title' => (string)$row['website_title'],
        ];
    }

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

    #[Test]
    public function theWebsiteIsRenderedReadOnly(): void
    {
        $this->setUpTestCase();

        $content = $this->getPageAsFrontendUser($this->getProfileEditFormUrl());

        $this->assertSame(
            1,
            preg_match('@<input\b(?=[^>]*\bname="[^"]*\[profileFormData\]\[website\]")[^>]*>@', $content, $control),
            'The website control is not rendered.',
        );
        $this->assertStringContainsString('readonly="readonly"', $control[0]);
    }

    #[Test]
    public function aSubmittedWebsiteIsIgnoredAndTheOtherFieldIsStored(): void
    {
        $this->setUpTestCase();
        $this->seedWebsiteFields();

        $this->submitProfileForm($this->getProfileEditFormUrl(), [
            'website' => 'https://submitted.example.org',
            'websiteTitle' => 'Submitted title',
        ]);

        $this->assertSame(
            ['website' => 'https://stored.example.org', 'website_title' => 'Submitted title'],
            $this->getStoredWebsiteFields(),
        );
    }
}
