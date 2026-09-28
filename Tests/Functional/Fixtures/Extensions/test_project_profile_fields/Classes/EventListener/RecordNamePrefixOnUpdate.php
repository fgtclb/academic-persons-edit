<?php

declare(strict_types=1);

namespace TESTS\TestProjectProfileFields\EventListener;

use FGTCLB\AcademicPersons\Event\AfterProfileUpdateEvent;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;

/**
 * Records every announced profile update with the name prefix stored at that moment,
 * which is how a test sees that the project field is written before the update is
 * announced, and announced once. A listener registered on the container of the test
 * case does not reach a frontend request. A test resets it before it acts.
 */
final class RecordNamePrefixOnUpdate
{
    /**
     * @var list<array{profile: int|null, origin: string, namePrefix: string}>
     */
    public static array $announcements = [];

    public function __construct(
        private readonly ConnectionPool $connectionPool,
    ) {}

    #[AsEventListener(identifier: 'test-project-profile-fields/record-name-prefix')]
    public function __invoke(AfterProfileUpdateEvent $event): void
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('tx_academicpersons_domain_model_profile');
        $queryBuilder->getRestrictions()->removeAll();
        self::$announcements[] = [
            'profile' => $event->getProfile()->getUid(),
            'origin' => $event->getOrigin()->value,
            'namePrefix' => (string)$queryBuilder
                ->select('tx_test_prefix')
                ->from('tx_academicpersons_domain_model_profile')
                ->where($queryBuilder->expr()->eq(
                    'uid',
                    $queryBuilder->createNamedParameter((int)$event->getProfile()->getUid(), Connection::PARAM_INT),
                ))
                ->executeQuery()
                ->fetchOne(),
        ];
    }
}
