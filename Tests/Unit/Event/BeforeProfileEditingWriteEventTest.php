<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Tests\Unit\Event;

use FGTCLB\AcademicBase\Domain\Model\Dto\PluginControllerActionContext;
use FGTCLB\AcademicPersons\Domain\Model\Profile;
use FGTCLB\AcademicPersonsEdit\Controller\ProfileController;
use FGTCLB\AcademicPersonsEdit\Event\BeforeProfileEditingWriteEvent;
use FGTCLB\AcademicPersonsEdit\Event\ProfileEditingAction;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Psr\EventDispatcher\ListenerProviderInterface;
use TYPO3\CMS\Core\EventDispatcher\EventDispatcher;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class BeforeProfileEditingWriteEventTest extends UnitTestCase
{
    /**
     * @param array<string, mixed> $fields
     */
    private function createEvent(ProfileEditingAction $action, array $fields = []): BeforeProfileEditingWriteEvent
    {
        return new BeforeProfileEditingWriteEvent(
            new Profile(),
            $action,
            null,
            null,
            $fields,
            new PluginControllerActionContext(new ServerRequest('https://www.acme.com/'), []),
        );
    }

    #[Test]
    public function aWriteIsNotRefusedUntilAListenerRefusesIt(): void
    {
        $event = $this->createEvent(ProfileEditingAction::CreateDocument);

        $this->assertFalse($event->isRefused());
        $this->assertNull($event->getReason());
        $this->assertFalse($event->isPropagationStopped());

        $event->refuse('Not now.');

        $this->assertTrue($event->isRefused());
        $this->assertSame('Not now.', $event->getReason());
        $this->assertTrue($event->isPropagationStopped());
    }

    /**
     * The dispatcher of TYPO3 honours the stop, so a listener after the refusing one
     * never sees the write.
     */
    #[Test]
    public function aListenerAfterTheRefusingOneIsNotCalled(): void
    {
        /** @var \ArrayObject<int, string> $calls */
        $calls = new \ArrayObject();
        $listenerProvider = new class ($calls) implements ListenerProviderInterface {
            /**
             * @param \ArrayObject<int, string> $calls
             */
            public function __construct(private readonly \ArrayObject $calls) {}

            /**
             * @return iterable<callable>
             */
            public function getListenersForEvent(object $event): iterable
            {
                yield function (BeforeProfileEditingWriteEvent $event): void {
                    $this->calls->append('first');
                    $event->refuse('Not now.');
                };
                yield function (): void {
                    $this->calls->append('second');
                };
            }
        };

        $event = (new EventDispatcher($listenerProvider))->dispatch(
            $this->createEvent(ProfileEditingAction::DeleteDocument),
        );

        $this->assertInstanceOf(BeforeProfileEditingWriteEvent::class, $event);
        $this->assertTrue($event->isRefused());
        $this->assertSame(['first'], $calls->getArrayCopy());
    }

    /**
     * @return \Generator<string, array{string}>
     */
    public static function emptyReasonProvider(): \Generator
    {
        yield 'empty' => [''];
        yield 'blank' => ["  \n"];
    }

    /**
     * An empty reason would show the person an empty message.
     */
    #[Test]
    #[DataProvider('emptyReasonProvider')]
    public function aWriteCannotBeRefusedWithoutAReason(string $reason): void
    {
        $event = $this->createEvent(ProfileEditingAction::CreateDocument);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionCode(1790578007);

        $event->refuse($reason);
    }

    /**
     * The value of a case is the name of the action of the endpoint it stands for.
     */
    #[Test]
    public function everyActionNamesAnActionOfTheEditor(): void
    {
        foreach (ProfileEditingAction::cases() as $action) {
            $this->assertTrue(
                method_exists(ProfileController::class, $action->value . 'Action'),
                sprintf('ProfileController has no action "%s".', $action->value),
            );
        }
    }

    #[Test]
    public function theFieldsOfAWriteThatCarriesThemCanBeReplaced(): void
    {
        $event = $this->createEvent(ProfileEditingAction::UpdateDocument, ['title' => 'Submitted']);

        $event->setFields(['title' => 'Replaced', 'year' => 2026]);

        $this->assertSame(['title' => 'Replaced', 'year' => 2026], $event->getFields());
    }

    /**
     * @return \Generator<string, array{ProfileEditingAction}>
     */
    public static function actionsWithoutFieldsProvider(): \Generator
    {
        foreach (ProfileEditingAction::cases() as $action) {
            if (!$action->carriesFields()) {
                yield $action->name => [$action];
            }
        }
    }

    #[Test]
    #[DataProvider('actionsWithoutFieldsProvider')]
    public function theFieldsOfAWriteWithoutFieldsCannotBeReplaced(ProfileEditingAction $action): void
    {
        $event = $this->createEvent($action, ['hidden' => true]);

        $this->expectException(\LogicException::class);
        $this->expectExceptionCode(1790575015);

        $event->setFields(['hidden' => false]);
    }

    /**
     * The actions whose values are validated again after a listener replaced them.
     * Every other action is fixed to what it does, so a new case is a decision and
     * not an accident.
     */
    #[Test]
    public function exactlyTheWritesOfValuesCarryFields(): void
    {
        $this->assertSame(
            ['update', 'updateSkipSync', 'createDocument', 'updateDocument', 'createContractContact', 'updateContractContact'],
            array_values(array_map(
                static fn(ProfileEditingAction $action): string => $action->value,
                array_filter(
                    ProfileEditingAction::cases(),
                    static fn(ProfileEditingAction $action): bool => $action->carriesFields(),
                ),
            )),
        );
        $this->assertCount(15, ProfileEditingAction::cases());
    }
}
