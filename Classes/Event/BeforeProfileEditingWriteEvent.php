<?php

declare(strict_types=1);

/*
 * This file is part of the "academic_persons_edit" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace FGTCLB\AcademicPersonsEdit\Event;

use FGTCLB\AcademicBase\Domain\Model\Dto\PluginControllerActionContextInterface;
use FGTCLB\AcademicPersons\Domain\Model\Contract;
use FGTCLB\AcademicPersons\Domain\Model\Profile;
use Psr\EventDispatcher\StoppableEventInterface;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;

/**
 * Offers a write of the profile editing to the listeners before anything of it
 * is stored.
 *
 * It is dispatched once per request that the editor accepted: the owner is
 * logged in and owns the profile, the action is allowed by the configuration
 * and not locked by the synchronisation, and the submitted values passed the
 * validation. A request refused for any of those reasons never reaches a
 * listener.
 *
 * A listener may refuse the write with a reason. The editor then answers 422
 * with the error `write_refused` and the reason as its message, stores nothing,
 * and shows the reason to the person as text. Refusing stops the propagation,
 * so later listeners are not called.
 *
 * A listener may replace the fields of a write that carries some, see
 * {@see ProfileEditingAction::carriesFields()}. The fields are the values in the
 * shape the editor submits them, keyed by the field name, and only those the
 * write is going to store: a locked field the browser sent anyway is not among
 * them. Replaced fields are handled exactly as if the browser had submitted
 * them: they are validated and sanitised again, a locked field among them is
 * dropped, and an invalid or unknown value is answered as the same value
 * submitted by the browser would be.
 *
 * The profile, the record and the contract are handed over for reading. The
 * editor stores them after the event, so a change a listener makes on them
 * directly is stored without any check of the editor: no validation, no
 * sanitiser and no lock. That is not supported. Values are changed through
 * {@see self::setFields()}.
 *
 * @api
 */
final class BeforeProfileEditingWriteEvent implements StoppableEventInterface
{
    private ?string $reason = null;

    /**
     * @param array<string, mixed> $fields
     */
    public function __construct(
        private readonly Profile $profile,
        private readonly ProfileEditingAction $action,
        private readonly ?string $sectionIdentifier,
        private readonly ?AbstractEntity $record,
        private array $fields,
        private readonly PluginControllerActionContextInterface $pluginControllerActionContext,
        private readonly ?Contract $contract = null,
    ) {}

    /**
     * The profile the write belongs to. In a translated site language, the
     * Extbase language overlay the editor works with.
     */
    public function getProfile(): Profile
    {
        return $this->profile;
    }

    public function getAction(): ProfileEditingAction
    {
        return $this->action;
    }

    /**
     * The document section, `vita` or `contracts` for instance, or the contact
     * section of a contract, `physicalAddresses`, `emailAddresses` or
     * `phoneNumbers`. `null` for a write of the profile itself or its image.
     */
    public function getSectionIdentifier(): ?string
    {
        return $this->sectionIdentifier;
    }

    /**
     * The record the write changes: the document or contact that is updated,
     * hidden, deleted or moved, the uploaded image reference, or the image
     * reference that is removed. `null` for a record that is about to be created,
     * for the new order of a whole section, for a write of the profile itself,
     * and for the removal of an image the profile does not have.
     */
    public function getRecord(): ?AbstractEntity
    {
        return $this->record;
    }

    /**
     * The contract of a contact write, `null` for every other write.
     */
    public function getContract(): ?Contract
    {
        return $this->contract;
    }

    /**
     * The values of the write, keyed by the field name. For a write that carries
     * no fields, what it does: `['hidden' => true]` for a visibility,
     * `['direction' => 'up']` or `['order' => [3, 1, 2]]` for a sort, and
     * nothing for a delete or an image.
     *
     * @return array<string, mixed>
     */
    public function getFields(): array
    {
        return $this->fields;
    }

    /**
     * Replaces the values the write stores.
     *
     * @param array<string, mixed> $fields
     * @throws \LogicException for a write that carries no fields
     */
    public function setFields(array $fields): void
    {
        if (!$this->action->carriesFields()) {
            throw new \LogicException(
                sprintf('The fields of the profile editing action "%s" cannot be replaced.', $this->action->value),
                1790575015,
            );
        }
        $this->fields = $fields;
    }

    /**
     * The request of the write, its site and language, and the settings of the
     * content element the editor is rendered by.
     */
    public function getPluginControllerActionContext(): PluginControllerActionContextInterface
    {
        return $this->pluginControllerActionContext;
    }

    /**
     * Refuses the write. The reason is shown to the person in the editor, as
     * text, so it is written for them.
     *
     * @throws \InvalidArgumentException for an empty reason, which would leave the person without one
     */
    public function refuse(string $reason): void
    {
        if (trim($reason) === '') {
            throw new \InvalidArgumentException('A refused profile editing write needs a reason.', 1790578007);
        }
        $this->reason = $reason;
    }

    public function isRefused(): bool
    {
        return $this->reason !== null;
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }

    public function isPropagationStopped(): bool
    {
        return $this->isRefused();
    }
}
