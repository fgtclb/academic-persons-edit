<?php

declare(strict_types=1);

/*
 * This file is part of the "academic_persons_edit" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace FGTCLB\AcademicPersonsEdit\Tests\Functional\Hook;

use FGTCLB\AcademicPersons\DataHandling\ProfileWriteCorrelation;
use FGTCLB\AcademicPersons\Domain\Model\Profile;
use FGTCLB\AcademicPersons\Domain\Repository\ProfileRepository;
use FGTCLB\AcademicPersons\Event\AfterProfileUpdateEvent;
use FGTCLB\AcademicPersons\Event\ProfileUpdateOrigin;
use FGTCLB\AcademicPersonsEdit\Tests\Functional\AbstractAcademicPersonsEditTestCase;
use PHPUnit\Framework\Attributes\Test;
use Psr\EventDispatcher\EventDispatcherInterface;
use SBUERK\TYPO3\Testing\SiteHandling\SiteBasedTestTrait;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\EventDispatcher\ListenerProvider;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Site\Entity\NullSite;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * What the listeners of this extension do with the announcement of a DataHandler
 * save of `academic_persons`: the translation follows a backend save and an import,
 * the slug follows an import only, and the synchronisation's own writes are never
 * announced a second time.
 */
final class BackendSaveAnnouncementTest extends AbstractAcademicPersonsEditTestCase
{
    use SiteBasedTestTrait;

    private const TABLE_PROFILE = 'tx_academicpersons_domain_model_profile';

    protected const LANGUAGE_PRESETS = [
        'EN' => ['id' => 0, 'title' => 'English', 'locale' => 'en_US.UTF8', 'iso' => 'en', 'hrefLang' => 'en-US', 'direction' => ''],
        'DE' => ['id' => 1, 'title' => 'Deutsch', 'locale' => 'de_DE.UTF8', 'iso' => 'de', 'hrefLang' => 'de-DE', 'direction' => ''],
    ];

    /**
     * @var \ArrayObject<int, AfterProfileUpdateEvent>
     */
    private \ArrayObject $events;

    protected function setUp(): void
    {
        $this->configurationToUseInTestInstance = [
            'EXTENSIONS' => [
                'academic_persons_edit' => [
                    'profile' => [
                        'allowedLanguages' => '1',
                    ],
                ],
            ],
        ];
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/backendSave.csv');
        $this->setUpBackendUser(1);
        $GLOBALS['LANG'] = $this->get(LanguageServiceFactory::class)->create('default');
        $this->writeSiteConfiguration(
            identifier: 'main',
            site: $this->buildSiteConfiguration(rootPageId: 1, base: 'https://www.acme.com/'),
            languages: [
                $this->buildDefaultLanguageConfiguration(identifier: 'EN', base: '/'),
                $this->buildLanguageConfiguration(identifier: 'DE', base: '/de/'),
            ],
        );
        $events = new \ArrayObject();
        $this->events = $events;
        $container = $this->get('service_container');
        $container->set(
            'counting-profile-update-listener',
            static function (AfterProfileUpdateEvent $event) use ($events): void {
                $events->append($event);
            },
        );
        $container->get(ListenerProvider::class)
            ->addListener(AfterProfileUpdateEvent::class, 'counting-profile-update-listener');
    }

    protected function tearDown(): void
    {
        GeneralUtility::rmdir($this->instancePath . '/typo3conf/sites', true);
        parent::tearDown();
    }

    #[Test]
    public function aBackendSaveCarriesTheNewLastNameIntoTheTranslation(): void
    {
        $this->runDataHandler([self::TABLE_PROFILE => [1 => ['last_name' => 'McDuck']]]);

        $translation = $this->fetchTranslation(1);
        $this->assertIsArray($translation, 'The backend save created the translation.');
        $this->assertSame('McDuck', $translation['last_name']);
        $this->assertCount(1, $this->events, 'The save was announced once, the synchronisation not at all.');
    }

    /**
     * The save as the backend form makes it: inside a backend request, where Extbase
     * reads the configuration of the backend and ignores every enable field, and
     * whose site is the one of the page selected in the page tree - none here.
     */
    #[Test]
    public function aSaveInABackendRequestIsAnnouncedWithTheSiteOfTheProfile(): void
    {
        $GLOBALS['TYPO3_REQUEST'] = (new ServerRequest('https://www.acme.com/typo3/record/edit'))
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE)
            ->withAttribute('site', new NullSite());

        $this->runDataHandler([self::TABLE_PROFILE => [3 => ['last_name' => 'Gearloose-Duck']]]);

        $translation = $this->fetchTranslation(3);
        $this->assertIsArray($translation, 'The backend save of the hidden profile created the translation.');
        $this->assertSame('Gearloose-Duck', $translation['last_name']);
        $this->assertSame(
            [['main', ProfileUpdateOrigin::Backend]],
            array_map(
                static fn(AfterProfileUpdateEvent $event): array => [$event->getSite()?->getIdentifier(), $event->getOrigin()],
                $this->events->getArrayCopy(),
            ),
        );
    }

    /**
     * The synchronisation of a backend save is a DataHandler run inside the one of
     * the save, and the DataHandler flushes the reference index of the outermost run
     * only. The synchronizer flushes its own, or the translation it creates would be
     * missing from `sys_refindex` - here with the image relation it carries.
     */
    #[Test]
    public function theTranslationOfABackendSaveIsInTheReferenceIndex(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/profileImage.csv');
        $this->getConnectionPool()
            ->getConnectionForTable(self::TABLE_PROFILE)
            ->update(self::TABLE_PROFILE, ['image' => 1], ['uid' => 1]);

        $this->runDataHandler([self::TABLE_PROFILE => [1 => ['last_name' => 'McDuck']]]);

        $translation = $this->fetchTranslation(1);
        $this->assertIsArray($translation, 'The backend save created the translation.');
        $this->assertSame(
            1,
            (int)$this->getConnectionPool()
                ->getConnectionForTable('sys_refindex')
                ->count('*', 'sys_refindex', [
                    'tablename' => self::TABLE_PROFILE,
                    'recuid' => (int)$translation['uid'],
                    'field' => 'image',
                    'ref_table' => 'sys_file_reference',
                ]),
            'The image relation of the translation is in the reference index.',
        );
    }

    /**
     * The editor owns the slug of a backend save, through the slug field; the
     * DataHandler has made it unique already.
     */
    #[Test]
    public function aBackendSaveKeepsTheSlugTheEditorSet(): void
    {
        $this->runDataHandler([self::TABLE_PROFILE => [1 => ['last_name' => 'McDuck']]]);

        $this->assertSame('uncle-scrooge', $this->fetchSlug(1));
    }

    #[Test]
    public function anImportRegeneratesTheSlugUniqueInTheFolder(): void
    {
        $this->runDataHandler(
            [self::TABLE_PROFILE => [1 => ['first_name' => 'John', 'last_name' => 'Doe']]],
            ProfileWriteCorrelation::Import,
        );

        $this->assertSame('john-doe-1', $this->fetchSlug(1), 'Profiles 2 and 4 have "john-doe" already.');
        $this->assertSame('john-doe', $this->fetchSlug(2));
        $translation = $this->fetchTranslation(1);
        $this->assertIsArray($translation, 'The import created the translation.');
        $this->assertSame('Doe', $translation['last_name']);
        $this->assertSame(
            [ProfileUpdateOrigin::Import],
            array_map(static fn(AfterProfileUpdateEvent $event): ProfileUpdateOrigin => $event->getOrigin(), $this->events->getArrayCopy()),
        );
    }

    /**
     * A hidden profile is announced like any other, and its slug follows as well:
     * the listener reads the row past the visibility restrictions.
     */
    #[Test]
    public function anImportRegeneratesTheSlugOfAHiddenProfile(): void
    {
        $this->runDataHandler(
            [self::TABLE_PROFILE => [3 => ['first_name' => 'John', 'last_name' => 'Doe']]],
            ProfileWriteCorrelation::Import,
        );

        $this->assertSame('john-doe-1', $this->fetchSlug(3));
    }

    /**
     * Profiles 2 and 4 share `john-doe` already. An announcement of either leaves it:
     * renumbering would change the URL of whichever profile is announced first.
     */
    #[Test]
    public function aSlugTheNameStillYieldsIsKept(): void
    {
        $this->runDataHandler(
            [self::TABLE_PROFILE => [4 => ['website' => 'https://example.org']]],
            ProfileWriteCorrelation::Import,
        );

        $this->assertSame('john-doe', $this->fetchSlug(4));
        $this->assertSame('john-doe', $this->fetchSlug(2));
    }

    /**
     * Profile 5 carries `john-doe-2`, the suffix the DataHandler gave it, while
     * `john-doe-1` is free. Its slug is unique and still its name's: renumbering
     * it to the lowest free suffix would change the URL of a profile whose name
     * did not change.
     */
    #[Test]
    public function aSuffixedSlugTheNameStillYieldsIsKept(): void
    {
        $this->runDataHandler(
            [self::TABLE_PROFILE => [5 => ['website' => 'https://example.org']]],
            ProfileWriteCorrelation::Import,
        );

        $this->assertSame('john-doe-2', $this->fetchSlug(5));
    }

    /**
     * Profiles 6 and 7 share `jane-roe-1`. A suffix is kept only while it is unique,
     * so the announced profile takes the lowest free slug of its name.
     */
    #[Test]
    public function aSuffixedSlugThatIsNotUniqueIsRenumbered(): void
    {
        $this->runDataHandler(
            [self::TABLE_PROFILE => [7 => ['website' => 'https://example.org']]],
            ProfileWriteCorrelation::Import,
        );

        $this->assertSame('jane-roe', $this->fetchSlug(7));
        $this->assertSame('jane-roe-1', $this->fetchSlug(6));
    }

    /**
     * With a translation in place the synchronisation writes a datamap onto the
     * default-language profile itself (the excluded columns). That run is marked as
     * internal, or the one update the frontend announced would be announced twice.
     */
    #[Test]
    public function theSynchronisationOfAnAnnouncedUpdateIsNotAnnouncedAgain(): void
    {
        unset($GLOBALS['TYPO3_REQUEST']);
        $profile = $this->get(ProfileRepository::class)->findByUid(1);
        $this->assertInstanceOf(Profile::class, $profile);
        $dispatcher = $this->get(EventDispatcherInterface::class);
        $dispatcher->dispatch(new AfterProfileUpdateEvent($profile, null, ProfileUpdateOrigin::FrontendEditing));
        $this->assertIsArray($this->fetchTranslation(1), 'Precondition: the first announcement created the translation.');
        $this->events->exchangeArray([]);

        $dispatcher->dispatch(new AfterProfileUpdateEvent($profile, null, ProfileUpdateOrigin::FrontendEditing));

        $this->assertCount(1, $this->events);
    }

    /**
     * @param array<string, array<int|string, array<string, mixed>>> $datamap
     */
    private function runDataHandler(array $datamap, ?ProfileWriteCorrelation $correlation = null): void
    {
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start($datamap, []);
        if ($correlation !== null) {
            $dataHandler->setCorrelationId($correlation->create());
        }
        $dataHandler->process_datamap();
        $this->assertSame([], $dataHandler->errorLog);
    }

    /**
     * @return array<string, mixed>|false
     */
    private function fetchTranslation(int $profileUid): array|false
    {
        $queryBuilder = $this->getConnectionPool()->getQueryBuilderForTable(self::TABLE_PROFILE);
        $queryBuilder->getRestrictions()->removeAll()->add(new DeletedRestriction());
        return $queryBuilder
            ->select('uid', 'first_name', 'last_name')
            ->from(self::TABLE_PROFILE)
            ->where(
                $queryBuilder->expr()->eq('l10n_parent', $queryBuilder->createNamedParameter($profileUid, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('sys_language_uid', $queryBuilder->createNamedParameter(1, Connection::PARAM_INT)),
            )
            ->executeQuery()
            ->fetchAssociative();
    }

    private function fetchSlug(int $profileUid): string
    {
        $queryBuilder = $this->getConnectionPool()->getQueryBuilderForTable(self::TABLE_PROFILE);
        $queryBuilder->getRestrictions()->removeAll();
        return (string)$queryBuilder
            ->select('slug')
            ->from(self::TABLE_PROFILE)
            ->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($profileUid, Connection::PARAM_INT)))
            ->executeQuery()
            ->fetchOne();
    }
}
