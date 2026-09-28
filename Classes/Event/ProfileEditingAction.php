<?php

declare(strict_types=1);

/*
 * This file is part of the "academic_persons_edit" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace FGTCLB\AcademicPersonsEdit\Event;

/**
 * The write of the profile editing a {@see BeforeProfileEditingWriteEvent} is
 * dispatched for, one case per writing endpoint of the editor. The value is the
 * name of the endpoint's action.
 *
 * The case set is fixed for a major version, so that a listener can `match` over
 * it exhaustively. A write the editor gains is a new case of a later major
 * version.
 *
 * @api
 */
enum ProfileEditingAction: string
{
    /**
     * A field of the profile itself, one or all of them at once.
     */
    case UpdateProfile = 'update';

    /**
     * The owner's switch that keeps the profile out of the synchronisation.
     */
    case UpdateSkipSync = 'updateSkipSync';

    /**
     * The owner's "Show my profile publicly" switch.
     */
    case UpdateVisibility = 'updateVisibility';

    case CreateDocument = 'createDocument';

    case UpdateDocument = 'updateDocument';

    case ToggleDocumentVisibility = 'toggleDocumentVisibility';

    case DeleteDocument = 'deleteDocument';

    /**
     * One row moved up or down, or the whole section put into a new order.
     */
    case SortDocument = 'sortDocument';

    case CreateContractContact = 'createContractContact';

    case UpdateContractContact = 'updateContractContact';

    case ToggleContractContactVisibility = 'toggleContractContactVisibility';

    case DeleteContractContact = 'deleteContractContact';

    case SortContractContact = 'sortContractContact';

    case UploadImage = 'uploadImage';

    case DeleteImage = 'deleteImage';

    /**
     * Whether the write stores submitted values a listener may replace. The
     * other writes carry only what they do: the visibility asked for, the
     * direction or order of a sort, or nothing at all.
     */
    public function carriesFields(): bool
    {
        return match ($this) {
            self::UpdateProfile,
            self::UpdateSkipSync,
            self::CreateDocument,
            self::UpdateDocument,
            self::CreateContractContact,
            self::UpdateContractContact => true,
            default => false,
        };
    }
}
