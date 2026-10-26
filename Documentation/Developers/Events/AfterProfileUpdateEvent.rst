..  _developers-event-after-profile-update-event:

=======================
AfterProfileUpdateEvent
=======================

:php:`\FGTCLB\AcademicPersons\Event\AfterProfileUpdateEvent` announces a
profile that was changed and stored. It is owned by
:guilabel:`academic_persons`, which dispatches it for a backend save, an
import and its commands as well. The `developer chapter of academic_persons
<https://docs.typo3.org/p/fgtclb/academic-persons/3.0/en-us/Developers/Events/AfterProfileUpdateEvent.html>`__
describes the event in general. This page describes what the profile editing
of this extension passes in.

..  contents::
    :local:
    :depth: 1

When it is dispatched
=====================

The profile editing dispatches the event once after each write it stored, of
the profile and of everything that belongs to it:

*   a field of the profile, one or all of them,
*   the synchronisation switch and the visibility switch,
*   the upload and the removal of the profile image,
*   the creation, update, hiding, deletion and sorting of a document, a
    contract or a profile information entry, and of a contact of a contract.

The event comes after the write is stored, and after the
:ref:`write event <developers-event-before-profile-editing-write-event>` a
listener may have used to change or refuse it. A write a listener refused
neither stores nor announces anything, just like one the editor refused
itself.

Nothing is announced in three cases:

*   **An edit in a translated site language.** The editor then works on the
    translation of the profile, and the event announces default-language
    profiles only. Edits in the default language of the site are announced,
    and with ``profile.allowedLanguages`` configured, the translation
    synchronisation of this extension carries them into the translations.
*   **A write that changed nothing.** A sort that left every row in its place,
    and the removal of an image the profile does not have.
*   **A failed write.** A request the editor or a listener refused, or one
    that failed while it was stored.

What a listener reads
=====================

..  list-table::
    :header-rows: 1
    :widths: 30 70

    *   -   Method
        -   What the profile editing passes in
    *   -   :php:`getProfile()`
        -   The stored default-language profile, after the write. For a write
            of a document or a contact, the profile it belongs to.
    *   -   :php:`getSite()`
        -   The site of the request the editor runs in, or :php:`null` for a
            request without one.
    *   -   :php:`getOrigin()`
        -   Always :php:`ProfileUpdateOrigin::FrontendEditing`, a case of
            :php:`\FGTCLB\AcademicPersons\Event\ProfileUpdateOrigin`.

The event carries no information about what was written. A listener that needs
it listens to :php:`BeforeProfileEditingWriteEvent` as well, keeps what it
needs of the write, and acts on it once the update is announced.

Example
=======

An office that reviews the profiles people maintain themselves wants a log
entry for every change made through the editor, and none for the changes made
in the backend or by an import:

..  code-block:: php
    :caption: EXT:my_extension/Classes/EventListener/LogProfileEdit.php

    <?php

    declare(strict_types=1);

    namespace MyVendor\MyExtension\EventListener;

    use FGTCLB\AcademicPersons\Event\AfterProfileUpdateEvent;
    use FGTCLB\AcademicPersons\Event\ProfileUpdateOrigin;
    use Psr\Log\LoggerInterface;
    use TYPO3\CMS\Core\Attribute\AsEventListener;

    final readonly class LogProfileEdit
    {
        public function __construct(
            private LoggerInterface $logger,
        ) {}

        #[AsEventListener(identifier: 'my-extension/log-profile-edit')]
        public function __invoke(AfterProfileUpdateEvent $event): void
        {
            if ($event->getOrigin() !== ProfileUpdateOrigin::FrontendEditing) {
                return;
            }
            $profile = $event->getProfile();
            $this->logger->info('Profile {uid} was edited in the frontend.', [
                'uid' => $profile->getUid(),
                'site' => $event->getSite()?->getIdentifier(),
            ]);
        }
    }

Rules and pitfalls
==================

*   **This extension listens itself.** Two listeners of this extension react
    to the event: one carries the change into the translations of the profile
    when ``profile.allowedLanguages`` is configured, see
    :ref:`configuration-general`, and one regenerates the slug of the profile
    from its name. Their identifiers are not public API, and no order between
    them and a listener of a project is promised.
*   **Writing a profile again.** A listener that writes the profile through
    the DataHandler marks that run as internal, or the backend announcement of
    :guilabel:`academic_persons` announces it once more. The :ref:`extension points
    page <fgtclb/academic-base:developers-extension-points-services>`
    names the class for it.
*   **The answer waits for the listeners.** The event is dispatched inside
    the request of the editor, so the person waits for every listener before
    the editor shows the write as saved. Hand slow work, a mail or a request
    to another system, to a queue.

Introduced in
=============

*   :ref:`feature-1788197189`
