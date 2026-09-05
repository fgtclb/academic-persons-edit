<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Tests\Functional\Plugins;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

final class AcademicPersonsEditProfileEditingListTest extends AbstractFrontendProfilePluginTestCase
{
    private function setUpListTestCase(): void
    {
        $this->setUpFrontendProfileTestCase(
            __DIR__ . '/Fixtures/AcademicPersonsEditProfileEditing/profileEditingPage.csv',
            'ProfileEditing',
        );
    }

    private function getListPage(): string
    {
        return $this->getPageAsFrontendUser('https://www.acme.com/home');
    }

    private function addProfile(
        int $profileUid,
        string $firstName,
        string $lastName,
        bool $assigned,
    ): void {
        $this->getConnectionPool()
            ->getConnectionForTable('tx_academicpersons_domain_model_profile')
            ->insert('tx_academicpersons_domain_model_profile', [
                'uid' => $profileUid,
                'pid' => self::PROFILE_PAGE_ID,
                'deleted' => 0,
                'hidden' => 0,
                'sys_language_uid' => 0,
                'l10n_parent' => 0,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'slug' => strtolower($firstName . '-' . $lastName),
                'frontend_users' => $assigned ? 1 : 0,
            ]);
        if (!$assigned) {
            return;
        }
        $this->getConnectionPool()
            ->getConnectionForTable('tx_academicpersons_feuser_mm')
            ->insert('tx_academicpersons_feuser_mm', [
                'uid_local' => $profileUid,
                'uid_foreign' => self::FRONTEND_USER_ID,
                'sorting' => $profileUid,
                'sorting_foreign' => $profileUid,
            ]);
    }

    #[Test]
    public function listIsTheDefaultViewAndShowsOnlyAssignedProfilesWithLanguage(): void
    {
        $this->setUpListTestCase();
        $this->addProfile(2, 'Erika', 'Beispiel', true);
        $this->addProfile(3, 'Nicht', 'Zugewiesen', false);
        $content = $this->getListPage();
        $this->assertStringContainsString('data-academic-persons-profile-editing-list', $content);
        $this->assertStringContainsString('Assigned profiles', $content);
        $this->assertStringContainsString('Max', $content);
        $this->assertStringContainsString('Müllermann', $content);
        $this->assertStringContainsString('Erika', $content);
        $this->assertStringContainsString('Beispiel', $content);
        $this->assertStringNotContainsString('Nicht', $content);
        $this->assertStringNotContainsString('Zugewiesen', $content);
        $this->assertStringContainsString('academic-persons-profile-editing-list__image', $content);
        $document = new \DOMDocument();
        $this->assertTrue($document->loadHTML($content, LIBXML_NOERROR | LIBXML_NOWARNING));
        $xpath = new \DOMXPath($document);
        $headers = $xpath->query('//*[@data-academic-persons-profile-editing-list]//thead/tr/th');
        $this->assertNotFalse($headers);
        $this->assertCount(3, $headers);
        $this->assertSame('Profile', trim($headers->item(0)?->textContent ?? ''));
        $this->assertSame('Language', trim($headers->item(1)?->textContent ?? ''));
        $this->assertSame('Actions', trim($headers->item(2)?->textContent ?? ''));
        $this->assertGreaterThanOrEqual(2, substr_count($content, 'English'));
    }

    #[Test]
    public function editLinkOpensTheSelectedAssignedProfileInsteadOfTheFrontendUserUid(): void
    {
        $this->setUpListTestCase();
        $this->addProfile(2, 'Erika', 'Beispiel', true);
        $editUrl = $this->extractPluginActionLink(
            $this->getListPage(),
            'tx_academicpersonsedit_profileediting',
            'index',
            'profileUid',
            2,
        );
        $content = $this->getPageAsFrontendUser($editUrl);
        $this->assertStringContainsString('data-profile-uid="2"', $content);
        $this->assertStringContainsString('Erika', $content);
        $this->assertStringContainsString('Beispiel', $content);
        $this->assertStringNotContainsString('data-profile-uid="1"', $content);
    }

    #[Test]
    public function profileThatIsNoLongerAssignedCannotBeOpenedThroughAnExistingEditLink(): void
    {
        $this->setUpListTestCase();
        $this->addProfile(3, 'Nicht', 'Zugewiesen', true);
        $editUrl = $this->extractPluginActionLink(
            $this->getListPage(),
            'tx_academicpersonsedit_profileediting',
            'index',
            'profileUid',
            3,
        );
        $this->getConnectionPool()
            ->getConnectionForTable('tx_academicpersons_feuser_mm')
            ->delete('tx_academicpersons_feuser_mm', [
                'uid_local' => 3,
                'uid_foreign' => self::FRONTEND_USER_ID,
            ]);
        $this->getConnectionPool()
            ->getConnectionForTable('tx_academicpersons_domain_model_profile')
            ->update(
                'tx_academicpersons_domain_model_profile',
                ['frontend_users' => 0],
                ['uid' => 3],
            );
        $response = $this->requestAsFrontendUser(new InternalRequest($editUrl));
        $this->assertSame(403, $response->getStatusCode());
        $this->assertStringNotContainsString('Zugewiesen', (string)$response->getBody());
    }

    #[Test]
    public function viewLinkTargetsAndRendersTheAcademicPersonsDetailPlugin(): void
    {
        $this->setUpListTestCase();
        $viewUrl = $this->extractPluginActionLink(
            $this->getListPage(),
            'tx_academicpersons_detail',
            'detail',
            'profile',
            1,
        );
        $this->assertStringStartsWith('https://www.acme.com/academic-persons/detail?', $viewUrl);
        $this->assertStringNotContainsString('tx_academicpersons_listanddetail', $viewUrl);
        $content = $this->getPageAsFrontendUser($viewUrl);
        $this->assertStringContainsString('data-academic-persons-detail', $content);
        $this->assertStringContainsString('Max', $content);
        $this->assertStringContainsString('Müllermann', $content);
    }
}
