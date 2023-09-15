<?php

declare(strict_types=1);

/*
 * This file is part of the "academic_persons" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace Fgtclb\AcademicPersonsEdit\Domain\Model;

use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\ObjectStorage;
use TYPO3\HtmlSanitizer\Builder\CommonBuilder;
use TYPO3\HtmlSanitizer\Sanitizer;

class Profile extends \Fgtclb\AcademicPersons\Domain\Model\Profile
{
    /**
     * @var ObjectStorage<FrontendUser>
     */
    protected ObjectStorage $frontendUsers;

    public function __construct()
    {
        parent::__construct();
        $this->frontendUsers = new ObjectStorage();
    }

    protected function getHtmlSanitizer(): Sanitizer
    {
        static $htmlSanitizer = null;
        if ($htmlSanitizer === null) {
            $htmlSanitizer = GeneralUtility::makeInstance(CommonBuilder::class)->build();
        }
        return $htmlSanitizer;
    }

    /**
     * @return ObjectStorage<FrontendUser>
     */
    public function getFrontendUsers(): ObjectStorage
    {
        return $this->frontendUsers;
    }

    /**
     * @param ObjectStorage<FrontendUser> $frontendUsers
     */
    public function setFrontendUsers(ObjectStorage $frontendUsers): void
    {
        $this->frontendUsers = $frontendUsers;
    }

    public function setTeachingArea(string $teachingArea): void
    {
        parent::setTeachingArea($this->getHtmlSanitizer()->sanitize($teachingArea));
    }

    public function setCoreCompetences(string $coreCompetences): void
    {
        parent::setCoreCompetences($this->getHtmlSanitizer()->sanitize($coreCompetences));
    }

    public function setMemberships(string $memberships): void
    {
        parent::setMemberships($this->getHtmlSanitizer()->sanitize($memberships));
    }

    public function setSupervisedThesis(string $supervisedThesis): void
    {
        parent::setSupervisedThesis($this->getHtmlSanitizer()->sanitize($supervisedThesis));
    }

    public function setSupervisedDoctoralThesis(string $supervisedDoctoralThesis): void
    {
        parent::setSupervisedDoctoralThesis($this->getHtmlSanitizer()->sanitize($supervisedDoctoralThesis));
    }

    public function setVita(string $vita): void
    {
        parent::setVita($this->getHtmlSanitizer()->sanitize($vita));
    }

    public function setPublications(string $publications): void
    {
        parent::setPublications($this->getHtmlSanitizer()->sanitize($publications));
    }

    public function setMiscellaneous(string $miscellaneous): void
    {
        parent::setMiscellaneous($this->getHtmlSanitizer()->sanitize($miscellaneous));
    }

    public function getLanguageUid(): int
    {
        return $this->_languageUid;
    }
}
