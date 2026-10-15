..  _developers-event-before-profile-editing-write-event:
..  _developers-before-write-event:

==============================
BeforeProfileEditingWriteEvent
==============================

:php:`\FGTCLB\AcademicPersonsEdit\Event\BeforeProfileEditingWriteEvent` is
dispatched once for every write of the :ref:`profile editing
<profile-editing>`, right before anything of it is stored. A listener refuses
the write, or replaces the values it stores. That is how a project requires a
value, keeps one fixed, or completes one from a source of its own.

..  contents::
    :local:
    :depth: 1

..  _developers-before-write-event-dispatch:

When it is dispatched
=====================

The three controllers of the editor dispatch the event, through one shared
service, for every write of the endpoints the editor script calls: a field of
the profile, the synchronisation and visibility switches, the upload and
removal of the image, and the creation, update, hiding, deletion and sorting
of a document or of a contact of a contract. It is dispatched once per
request, after the editor checked the request and right before anything of
it is stored. What a listener leaves in place is stored, and
:php:`AfterProfileUpdateEvent` announces it afterwards, see
:ref:`developers-event-after-profile-update-event`.

The event is dispatched only for a request the editor accepted: the owner is
logged in and owns the profile, the action is allowed by the configuration and
not locked by the synchronisation, and the submitted values passed the
validation. A request refused for any of those reasons never reaches a
listener, so a listener sees only writes that would otherwise be stored.

What a listener reads and changes
=================================

..  list-table::
    :header-rows: 1
    :widths: 30 70

    *   -   Method
        -   Returns
    *   -   :php:`getProfile()`
        -   The profile the write belongs to. In a translated site language,
            the language overlay the editor works with.
    *   -   :php:`getAction()`
        -   The write, a case of
            :php:`\FGTCLB\AcademicPersonsEdit\Event\ProfileEditingAction`, see
            below.
    *   -   :php:`getSectionIdentifier()`
        -   The document section, ``vita`` or ``contracts`` for instance, or the
            contact section of a contract, ``physicalAddresses``,
            ``emailAddresses`` or ``phoneNumbers``. :php:`null` for a write of
            the profile itself or of its image.
    *   -   :php:`getRecord()`
        -   The record the write changes: the document or contact that is
            updated, hidden, deleted or moved, the uploaded image reference, or
            the image reference that is removed. :php:`null` for a record that
            is about to be created, for the new order of a whole section, for a
            write of the profile itself, and for the removal of an image the
            profile does not have.
    *   -   :php:`getContract()`
        -   The contract of a contact write, :php:`null` for every other write.
    *   -   :php:`getFields()`
        -   The values of the write, keyed by the field name.
    *   -   :php:`getPluginControllerActionContext()`
        -   The request of the write, its site and language, the settings
            of the content element the editor is rendered by, and the
            controller and action name of the endpoint. The controller name is
            ``Profile`` for a write of the profile, its switches and its
            image, ``Document`` for a contract or profile information entry
            and ``ContractContact`` for a contact of a contract.
    *   -   :php:`setFields(array $fields)`
        -   Replaces the values of a write that stores some, see
            :ref:`developers-before-write-event-fields`.
    *   -   :php:`refuse(string $reason)`
        -   Refuses the write, see :ref:`developers-before-write-event-refuse`.
    *   -   :php:`isRefused()`, :php:`getReason()`
        -   Whether a listener refused the write, and its reason.

The profile, the record and the contract are handed over for reading. The
editor stores them after the event, so a change a listener makes on them
directly is stored without any check of the editor: no validation, no
sanitiser and no lock. That is not supported. Values are changed through
:php:`setFields()`, see :ref:`developers-before-write-event-fields`.

..  _developers-before-write-event-actions:

The writes
==========

One case per writing endpoint of the editor. The value of a case is the name of
the endpoint's action.

..  list-table::
    :header-rows: 1
    :widths: 40 60

    *   -   Case
        -   :php:`getFields()`
    *   -   :php:`UpdateProfile`
        -   The fields of the profile the request stores, one or all of them
    *   -   :php:`UpdateSkipSync`
        -   ``['skipSync' => true]`` or ``false``
    *   -   :php:`UpdateVisibility`
        -   ``['hidden' => true]`` or ``false``
    *   -   :php:`CreateDocument`, :php:`UpdateDocument`
        -   The fields of the document the request stores
    *   -   :php:`ToggleDocumentVisibility`
        -   ``['hidden' => true]`` or ``false``
    *   -   :php:`DeleteDocument`
        -   nothing
    *   -   :php:`SortDocument`
        -   ``['direction' => 'up']`` or ``'down'`` for one row, with the row as
            the record, or ``['order' => [3, 1, 2]]`` for the whole section
    *   -   :php:`CreateContractContact`, :php:`UpdateContractContact`
        -   The fields of the address, email address or phone number the
            request stores
    *   -   :php:`ToggleContractContactVisibility`
        -   ``['hidden' => true]`` or ``false``
    *   -   :php:`DeleteContractContact`
        -   nothing
    *   -   :php:`SortContractContact`
        -   ``['direction' => 'up']`` or ``'down'``
    *   -   :php:`UploadImage`, :php:`DeleteImage`
        -   nothing, the image reference is the record

The fields are the values in the shape the editor submits them: a date as
``d.m.Y`` text, a related record as its uid, a rich text as the markup the
person entered. They are the values the write is going to store, so a field
locked with ``readonly``, ``frontendreadonly`` or ``disabled``, or managed by
the synchronisation, is not among them even when the browser sent it.

..  _developers-before-write-event-refuse:

Refusing a write
================

:php:`refuse(string $reason)` refuses the write. The endpoint answers with
status ``422`` and the error ``write_refused``, with the reason as its message,
and stores nothing of the write. The editor shows the reason to the person, as
text, so it is written for them and in their language. An empty reason throws
an :php:`\InvalidArgumentException`, which leaves the person with an error
rather than with an empty message. Refusing stops the propagation: a listener
registered after the refusing one is not called.

..  code-block:: php
    :caption: EXT:my_sitepackage/Classes/EventListener/KeepTheVitaWithTheOffice.php

    <?php

    declare(strict_types=1);

    namespace MyVendor\MySitepackage\EventListener;

    use FGTCLB\AcademicPersonsEdit\Event\BeforeProfileEditingWriteEvent;
    use FGTCLB\AcademicPersonsEdit\Event\ProfileEditingAction;
    use TYPO3\CMS\Core\Attribute\AsEventListener;

    final class KeepTheVitaWithTheOffice
    {
        #[AsEventListener(identifier: 'my-sitepackage/keep-the-vita-with-the-office')]
        public function __invoke(BeforeProfileEditingWriteEvent $event): void
        {
            if ($event->getSectionIdentifier() !== 'vita') {
                return;
            }
            if ($event->getAction() === ProfileEditingAction::CreateDocument
                || $event->getAction() === ProfileEditingAction::DeleteDocument
            ) {
                $event->refuse('Please ask the office to add or remove entries of your curriculum vitae.');
            }
        }
    }

Taking an action away from every person is a matter of configuration, not of a
listener: the ``actions`` and ``readonly`` settings of a section remove the
buttons as well, see :ref:`profile-editing`. A listener is for the decision
that depends on the record, the values or the person.

..  _developers-before-write-event-fields:

Replacing the values
====================

:php:`setFields(array $fields)` replaces the values of a write that stores
some: the profile, the synchronisation switch, and the creation and update of
a document or contact. :php:`ProfileEditingAction::carriesFields()` tells
which. Called for any other write, it throws a :php:`\LogicException`, and the
request fails with ``500`` and ``internal_server_error`` rather than storing
something the listener did not mean.

The replaced values are handled exactly as if the browser had submitted them.
They are validated and sanitised again, so replaced values can neither carry a
value the person could not store, nor bypass the rich text sanitiser. A locked
field among them is dropped and keeps its stored value. An invalid or unknown
value is answered as the same value submitted by the browser would be: with
``422`` and ``validation_failed`` or ``invalid_profile_data``, or with ``400``
for a synchronisation switch that is not one boolean value. Nothing is stored.

..  code-block:: php
    :caption: EXT:my_sitepackage/Classes/EventListener/NormaliseWebsite.php

    <?php

    declare(strict_types=1);

    namespace MyVendor\MySitepackage\EventListener;

    use FGTCLB\AcademicPersonsEdit\Event\BeforeProfileEditingWriteEvent;
    use FGTCLB\AcademicPersonsEdit\Event\ProfileEditingAction;
    use TYPO3\CMS\Core\Attribute\AsEventListener;

    final class NormaliseWebsite
    {
        #[AsEventListener(identifier: 'my-sitepackage/normalise-website')]
        public function __invoke(BeforeProfileEditingWriteEvent $event): void
        {
            $fields = $event->getFields();
            if ($event->getAction() !== ProfileEditingAction::UpdateProfile
                || !is_string($fields['website'] ?? null)
                || $fields['website'] === ''
                || str_contains($fields['website'], '://')
            ) {
                return;
            }
            $fields['website'] = 'https://' . $fields['website'];
            $event->setFields($fields);
        }
    }

The answers of the document and contact endpoints carry the stored record, so
the editor shows what the listener stored. The answer of the profile endpoint
carries the values of the fields the listener passed on. A profile field the
listener removed from the write keeps its stored value, while the editor goes
on showing what the person typed until the page is reloaded. To keep a value
fixed, refuse the write, or pass the field on with its stored value.

..  _developers-before-write-event-rules:

Rules and pitfalls
==================

*   **Values, not objects.** Change what is stored through
    :php:`setFields()` only. The profile, the record and the contract are
    stored after the event without any check of the editor, so a change made
    on them directly bypasses the validation, the sanitiser and the locks.
*   **A refusal ends the round.** :php:`refuse()` stops the propagation, so a
    listener registered after the refusing one is not called. A listener that
    has to see every write is ordered before the others with the
    :php:`before` argument of :php:`#[AsEventListener]`. No other order is
    promised.
*   **Check the action first.** :php:`setFields()` throws a
    :php:`\LogicException` for a write that carries no fields, and the
    request then fails with ``500``. Ask
    :php:`$event->getAction()->carriesFields()` before calling it.
*   **Configuration before code.** An action no person may take is removed
    with the ``actions`` and ``readonly`` settings of a section, which removes
    the buttons as well, see :ref:`profile-editing`. A listener is for a
    decision that depends on the record, the values or the person.
*   **The case set is fixed** for a major version, so a listener may
    :php:`match` over :php:`ProfileEditingAction` without a default arm. A
    write the editor gains is a new case of a later major version.

Introduced in
=============

*   :ref:`feature-before-profile-editing-write-event`
*   :ref:`breaking-contract-publish-switch-removed`, which removed
    ``publish`` from the fields of a contract write
