<?php

declare(strict_types=1);

/*
 * This file is part of the "academic_persons_edit" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace Fgtclb\AcademicPersonsEdit\Domain\Model;

use Fgtclb\AcademicPersons\Domain\Model\ProfileInformation;
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

    /**
     * @param ObjectStorage<ProfileInformation> $memberships
     */
    public function setMemberships(ObjectStorage $memberships): void
    {
        foreach ($memberships as $membership) {
            $membership->setTitle($this->getHtmlSanitizer()->sanitize($membership->getTitle()));
            $membership->setBodytext($this->getHtmlSanitizer()->sanitize($membership->getBodytext()));
            $membership->setLink($this->getHtmlSanitizer()->sanitize($membership->getLink()));
        }
        parent::setMemberships($memberships);
    }

    public function setSupervisedThesis(string $supervisedThesis): void
    {
        parent::setSupervisedThesis($this->getHtmlSanitizer()->sanitize($supervisedThesis));
    }

    public function setSupervisedDoctoralThesis(string $supervisedDoctoralThesis): void
    {
        parent::setSupervisedDoctoralThesis($this->getHtmlSanitizer()->sanitize($supervisedDoctoralThesis));
    }

    /**
     * @param ObjectStorage<ProfileInformation> $vita
     */
    public function setVita(ObjectStorage $vita): void
    {
        foreach ($vita as $singleVita) {
            $singleVita->setTitle($this->getHtmlSanitizer()->sanitize($singleVita->getTitle()));
            $singleVita->setBodytext($this->getHtmlSanitizer()->sanitize($singleVita->getBodytext()));
            $singleVita->setLink($this->getHtmlSanitizer()->sanitize($singleVita->getLink()));
        }
        parent::setVita($vita);
    }

    /**
     * @param ObjectStorage<ProfileInformation> $publications
     */
    public function setPublications(ObjectStorage $publications): void
    {
        foreach ($publications as $publication) {
            $publication->setTitle($this->getHtmlSanitizer()->sanitize($publication->getTitle()));
            $publication->setBodytext($this->getHtmlSanitizer()->sanitize($publication->getBodytext()));
            $publication->setLink($this->getHtmlSanitizer()->sanitize($publication->getLink()));
        }
        parent::setPublications($publications);
    }

    public function setMiscellaneous(string $miscellaneous): void
    {
        parent::setMiscellaneous($this->getHtmlSanitizer()->sanitize($miscellaneous));
    }

    /**
     * @param ObjectStorage<ProfileInformation> $cooperation
     */
    public function setCooperation(ObjectStorage $cooperation): void
    {
        foreach ($cooperation as $singleCooperation) {
            $singleCooperation->setTitle($this->getHtmlSanitizer()->sanitize($singleCooperation->getTitle()));
            $singleCooperation->setBodytext($this->getHtmlSanitizer()->sanitize($singleCooperation->getBodytext()));
            $singleCooperation->setLink($this->getHtmlSanitizer()->sanitize($singleCooperation->getLink()));
        }
        parent::setCooperation($cooperation);
    }

    /**
     * @param ObjectStorage<ProfileInformation> $lectures
     */
    public function setLectures(ObjectStorage $lectures): void
    {
        foreach ($lectures as $lecture) {
            $lecture->setTitle($this->getHtmlSanitizer()->sanitize($lecture->getTitle()));
            $lecture->setBodytext($this->getHtmlSanitizer()->sanitize($lecture->getBodytext()));
            $lecture->setLink($this->getHtmlSanitizer()->sanitize($lecture->getLink()));
        }
        parent::setLectures($lectures);
    }

    /**
     * @param ObjectStorage<ProfileInformation> $pressMedia
     */
    public function setPressMedia(ObjectStorage $pressMedia): void
    {
        foreach ($pressMedia as $press) {
            $press->setTitle($this->getHtmlSanitizer()->sanitize($press->getTitle()));
            $press->setBodytext($this->getHtmlSanitizer()->sanitize($press->getBodytext()));
            $press->setLink($this->getHtmlSanitizer()->sanitize($press->getLink()));
        }
        parent::setPressMedia($pressMedia);
    }

    /**
     * @param ObjectStorage<ProfileInformation> $scientificResearch
     */
    public function setScientificResearch(ObjectStorage $scientificResearch): void
    {
        foreach ($scientificResearch as $research) {
            $research->setTitle($this->getHtmlSanitizer()->sanitize($research->getTitle()));
            $research->setBodytext($this->getHtmlSanitizer()->sanitize($research->getBodytext()));
            $research->setLink($this->getHtmlSanitizer()->sanitize($research->getLink()));
        }
        parent::setScientificResearch($scientificResearch);
    }

    public function getLanguageUid(): int
    {
        return $this->_languageUid;
    }

    public function getIsTranslation(): bool
    {
        return $this->_localizedUid !== $this->uid;
    }
}
