<?php

declare(strict_types=1);

namespace TESTS\TestProfileUpdateRecorder\EventListener;

use FGTCLB\AcademicPersons\Event\AfterProfileUpdateEvent;
use TYPO3\CMS\Core\Attribute\AsEventListener;

/**
 * Records every announced profile update with its site and origin, which is how a
 * test counts the announcements of a frontend request: a listener registered on the
 * container of the test case does not reach the request. Changes nothing. A test
 * resets it before it acts.
 */
final class RecordProfileUpdateListener
{
    /**
     * @var list<array{0: int|null, 1: string|null, 2: string}> Profile uid, site identifier and origin
     */
    public static array $announcements = [];

    #[AsEventListener(identifier: 'test-profile-update-recorder/record')]
    public function __invoke(AfterProfileUpdateEvent $event): void
    {
        self::$announcements[] = [
            $event->getProfile()->getUid(),
            $event->getSite()?->getIdentifier(),
            $event->getOrigin()->value,
        ];
    }
}
