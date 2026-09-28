<?php

declare(strict_types=1);

namespace TESTS\TestEditorWriteListener\EventListener;

use FGTCLB\AcademicPersonsEdit\Event\BeforeProfileEditingWriteEvent;
use TYPO3\CMS\Core\Attribute\AsEventListener;

/**
 * Records every write the profile editing offers, and hands it to the behaviour a
 * test configured: refusing it, or replacing its fields. A listener registered on the
 * container of the test case does not reach a frontend request, so the behaviour is a
 * static closure. A test resets both before it acts.
 */
final class EditorWriteListener
{
    /**
     * @var list<array{action: string, section: string|null, record: int|null, contract: int|null, fields: array<string, mixed>}>
     */
    public static array $calls = [];

    /**
     * @var (\Closure(BeforeProfileEditingWriteEvent): void)|null
     */
    public static ?\Closure $behaviour = null;

    #[AsEventListener(identifier: 'test-editor-write-listener/write')]
    public function __invoke(BeforeProfileEditingWriteEvent $event): void
    {
        self::$calls[] = [
            'action' => $event->getAction()->value,
            'section' => $event->getSectionIdentifier(),
            'record' => $event->getRecord()?->getUid(),
            'contract' => $event->getContract()?->getUid(),
            'fields' => $event->getFields(),
        ];
        if (self::$behaviour !== null) {
            (self::$behaviour)($event);
        }
    }

    public static function reset(): void
    {
        self::$calls = [];
        self::$behaviour = null;
    }
}
