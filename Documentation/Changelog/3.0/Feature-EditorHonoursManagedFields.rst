..  _feature-editor-honours-managed-fields:

===========================================================
Feature: The editor locks the fields a synchronisation owns
===========================================================

Description
===========

The profile editor now reads the :yaml:`managedFields` map of
:file:`EXT:academic_persons`, which already locks the fields a synchronisation
or an import owns in the backend record form. It applies the map to the same
records: a profile, contract or contact with an import identifier, of the
default language, whose profile is not excluded with :guilabel:`Skip
synchronisation`.

On such a record a managed field is shown read-only with the marker
:guilabel:`Synchronised`, on the profile page and in the contract and contact
forms. A contract or contact with a managed field offers no delete, and no edit
once every field the owner could edit is managed. Hiding, showing and sorting
it stay available, because the synchronisation never changes them. The
endpoints refuse a delete or an edit the row does not offer with the existing
error codes ``document_action_not_allowed`` and
``contract_contact_action_not_allowed``. In a translated site language a
managed field whose value all languages share stays locked, a translated one
is editable, as in the backend, and a synchronised row still offers no
delete.

A select or a checkbox that is read-only, managed or configured
:yaml:`readonly`, is now rendered disabled. Neither control has a read-only
state, so it could be changed before although the change was not stored.

Impact
======

Nothing changes for an installation that names no managed field: the shipped
lists are empty. A name in the map that matches no field makes the editor fail
with the exception ``1790536034``, as it makes the backend person record forms
fail, rather than leave the field editable without a word.

A value submitted for a locked field is now ignored and the other fields of the
request are stored. This holds for a managed field and for one configured
:yaml:`readonly`, :yaml:`frontendreadonly` or :yaml:`disabled`, on the profile,
contract and contact endpoints. They answered such a value with 422 before, and
because the editor sends every field of an open contract or contact when it
saves, a contract or contact with a locked field could not be saved from the
browser at all. A field the section does not know is still refused with 422.
The switches for the synchronisation and for the profile visibility keep
refusing a locked value, since that value is their whole request.

The prototypes carry three new keys, and an override has to carry them like
every other slot key: ``managed`` in the three field rows of
:file:`Partials/Profile/Field/PrototypeWrapper.html`, and ``managed``,
``editable`` and ``deletable`` in the ``contact-row`` of
:file:`Partials/Profile/Documents/ContractContacts.html`. The editor refuses
to fill a prototype that lacks a key it writes, so an override without them
fails the way one without its prototype does, see
:ref:`important-profile-editing-custom-elements-and-prototypes`. An override of :file:`Partials/Profile/Documents/Actions.html`,
:file:`Partials/Profile/Field/Preview.html` or
:file:`Partials/Profile/Field/Group.html` keeps working and only shows no
marker. The shipped :file:`Partials/Profile/Documents/Contract.html` hands
every row its own action list from ``rowActions``. An override that still
hands the list of the section to every row shows the delete button on a
synchronised contract, and the server refuses the delete.

..  index:: Frontend, ext:academic_persons_edit, NotScanned
