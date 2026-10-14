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
     * The controller and the action name of the plugin action context of each write,
     * kept apart from the calls so that the shape the other tests compare stays as it is.
     *
     * @var list<array{0: string|null, 1: string|null}>
     */
    public static array $contexts = [];

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
        self::$contexts[] = [
            $event->getPluginControllerActionContext()->getControllerName(),
            $event->getPluginControllerActionContext()->getActionName(),
        ];
        if (self::$behaviour !== null) {
            (self::$behaviour)($event);
        }
    }

    public static function reset(): void
    {
        self::$calls = [];
        self::$contexts = [];
        self::$behaviour = null;
    }
}
