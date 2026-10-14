<?php

declare(strict_types=1);

/*
 * This file is part of the "academic_persons_edit" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace FGTCLB\AcademicPersonsEdit\Service;

use FGTCLB\AcademicBase\Domain\Model\Dto\PluginControllerActionContextInterface;
use FGTCLB\AcademicPersons\Domain\Model\Contract;
use FGTCLB\AcademicPersons\Domain\Model\Profile;
use FGTCLB\AcademicPersons\Event\AfterProfileUpdateEvent;
use FGTCLB\AcademicPersons\Event\ProfileUpdateOrigin;
use FGTCLB\AcademicPersonsEdit\Event\BeforeProfileEditingWriteEvent;
use FGTCLB\AcademicPersonsEdit\Event\ProfileEditingAction;
use FGTCLB\AcademicPersonsEdit\Service\Exception\EditorRequestException;
use Psr\EventDispatcher\EventDispatcherInterface;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;
use TYPO3\CMS\Extbase\Mvc\RequestInterface;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;

/**
 * The lifecycle of a write of the profile editor, shared by its three
 * controllers: the offer to the listeners of {@see BeforeProfileEditingWriteEvent}
 * before anything is stored, the flush of the Extbase persistence, and the
 * announcement of the changed profile.
 *
 * @internal
 */
final readonly class EditorWrites
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
        private PersistenceManager $persistenceManager,
    ) {}

    /**
     * Offers a write the editor accepted to the listeners of
     * {@see BeforeProfileEditingWriteEvent}, right before anything of it is stored.
     *
     * A refused write is answered with 422 `write_refused` and the listener's reason.
     * Fields a listener replaced are returned for the caller to validate again, exactly
     * as submitted ones, so that replaced fields can neither carry an invalid or
     * unsanitised value nor write a field the configuration or the synchronisation
     * locks.
     *
     * @param array<string, mixed> $fields The values of the write in the shape the editor submits them.
     * @return array<string, mixed>|null The replaced fields, or null when no listener replaced them.
     */
    public function dispatchBeforeWrite(
        PluginControllerActionContextInterface $pluginControllerActionContext,
        Profile $profile,
        ProfileEditingAction $action,
        ?string $sectionIdentifier = null,
        ?AbstractEntity $record = null,
        array $fields = [],
        ?Contract $contract = null,
    ): ?array {
        $event = new BeforeProfileEditingWriteEvent(
            $profile,
            $action,
            $sectionIdentifier,
            $record,
            $fields,
            $pluginControllerActionContext,
            $contract,
        );
        $this->eventDispatcher->dispatch($event);
        if ($event->isRefused()) {
            throw new EditorRequestException('write_refused', 422, $event->getReason(), [], 1792001172);
        }
        return $event->getFields() !== $fields ? $event->getFields() : null;
    }

    /**
     * Announces a change of the editor, with the site of the request the change was
     * made in.
     */
    public function dispatchProfileUpdate(RequestInterface $request, Profile $profile): void
    {
        $site = $request->getAttribute('site');
        $this->eventDispatcher->dispatch(new AfterProfileUpdateEvent(
            $profile,
            $site instanceof Site ? $site : null,
            ProfileUpdateOrigin::FrontendEditing,
        ));
    }

    /**
     * Persists all pending changes before announcing the updated profile aggregate.
     *
     * @param (\Closure(): void)|null $beforeAnnouncement a write of its own, run after the
     *                                                     Extbase write and before the
     *                                                     announcement, whose result the
     *                                                     listeners have to see
     */
    public function persistAndDispatchProfileUpdate(RequestInterface $request, ?Profile $profile, ?\Closure $beforeAnnouncement = null): void
    {
        $this->persistenceManager->persistAll();
        if ($beforeAnnouncement !== null) {
            $beforeAnnouncement();
        }
        if ($profile === null || $profile->getUid() === null || $profile->getIsTranslation()) {
            return;
        }
        $this->dispatchProfileUpdate($request, $profile);
    }
}
