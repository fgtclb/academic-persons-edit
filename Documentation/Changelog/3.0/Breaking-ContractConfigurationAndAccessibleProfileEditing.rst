..  _breaking-contract-configuration-and-accessible-profile-editing:

===============================================================
Breaking: Contract fields and contacts are configured as a unit
===============================================================

..  seealso::
    The `upgrade chapter of academic_persons
    <https://docs.typo3.org/p/fgtclb/academic-persons/main/en-us/Upgrade/Index.html>`__
    is the order in which the 3.0 changes have to be applied.

Description
===========

A contract and the address, email address and phone number records it owns are
edited as one document in the new profile editing view, and they are configured
that way too. The flat ``validations`` keys the editor used to read are gone
(see the *Breaking: Section-based AcademicPersons settings*
entry of `EXT:academic_persons`); what the editing frontend reads instead is:

*   ``contracts.fields``, the ordered list of the contract's own fields, each
    with its render type, its option source and its help text;
*   ``contracts.contactSections.<section>.fields``, the same per contact kind -
    ``physicalAddresses``, ``emailAddresses`` and ``phoneNumbers``;
*   ``documentSections.<section>``, which declares the rows a compact list
    shows (``rowFields``), the actions it offers (``actions``) and whether it
    is ``readonly``.

The order of the fields in the file is the order of the controls in the form,
and a field that is not declared is not rendered and not written. That is the
breaking half: a project that relied on the editor rendering every column of a
record now has to declare the fields it wants.

``readonly`` and the ``actions`` list are enforced on the server, not only in
the user interface. A request that creates, updates, deletes or sorts a record
of a read-only section is answered with ``403`` and the error code
``document_action_not_allowed`` or ``contract_contact_action_not_allowed``,
whether or not the button that would trigger it was rendered.

The rendered form itself changed with it: every control carries a label, its
``aria-describedby`` help text and its validation state; the compact lists put
a column heading row above the rows on wide viewports and repeat each column's
label beside its value on narrow ones; and the sortable lists offer keyboard
controls next to the drag handle. The lists are Bootstrap grid rows rather than
tables - a row is one record and its cells reflow into a block on a phone.
:ref:`profile-editing` describes the result.

Impact
======

*   A site package overriding the settings file has to move its contract and
    contact configuration into ``contracts.fields`` and
    ``contracts.contactSections``. The runtime overlay described in
    the *Feature: Legacy settings overlay and migration
    command* entry of `EXT:academic_persons` reads the old shape for one more release and logs a
    warning per package and key; it does not read the new keys above, which
    have no old equivalent.
*   A field the project's settings file does not declare disappears from the
    editing form.
*   Code or tests posting to a read-only section now receive ``403`` instead
    of writing.

Affected Installations
======================

All installations of `EXT:academic_persons_edit` that ship their own
``Configuration/AcademicPersons/Settings.yaml``, and all installations that
relied on the previous, non configurable contract form.

Migration
=========

Declare the contract fields and the contact sections in the settings file of
the site package, in the order the form should render them. The shipped
:file:`EXT:academic_persons/Configuration/AcademicPersons/Settings.yaml` is the
reference; :ref:`configuration-editor-settings` documents every key.

..  index:: Fluid, Frontend, YAML, ext:academic_persons_edit, NotScanned
