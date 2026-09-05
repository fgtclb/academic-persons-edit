..  _feature-full-form-editing-applies-as-one-form:

==============================================
Feature: Full form editing applies as one form
==============================================

Description
===========

:guilabel:`Edit all` opens every editable profile field at once. It now also
gives the form controls of its own: :guilabel:`Apply`, :guilabel:`Undo` and
:guilabel:`Discard`, rendered once at the end of every field form by
:file:`Partials/Profile/Field/FormActions.html`. The per-field clear, undo and
save groups are hidden for as long as the form is open, and the undo beside an
autosaving checkbox with them.

*   :guilabel:`Apply` sends every changed field of the profile in **one**
    request. ``updateAction()`` validates the whole field map before it writes
    anything, so either all of it is stored or none of it is - a refused
    property leaves the other submitted properties unwritten as well. On
    success the stored values are written back into the fields, the previews
    and the name heading, the form closes and the focus returns to
    :guilabel:`Edit all`.
*   :guilabel:`Undo` restores every field to the value that is stored and keeps
    the form open.
*   :guilabel:`Discard` restores every field and closes the form. Closing the
    form with :guilabel:`Edit all` does the same thing.

While an apply is on its way to the server none of the three, and neither
:guilabel:`Edit all` nor :kbd:`Escape`, does anything: the request cannot be
taken back, and reverting under it would leave the stored profile and the
editor's baseline disagreeing silently.

A refusal reverts nothing. Every entered value stays where it was entered, the
refused fields are marked and described by their own message, the caret goes to
the first of them and the refusal is announced once rather than once per field.
Reverting thirty typed fields because one of them was rejected is data loss;
the per-field paths revert only because a checkbox has one bit and no other way
to report a failure.

A checkbox that saves on change stops doing so while the form is open and is
applied with everything else instead. Without that, the value would reach the
database while the visitor is still deciding, and :guilabel:`Discard` could not
take it back. The synchronisation switch of the page header sits outside the
field forms and keeps saving immediately.

Keyboard and screen reader
--------------------------

*   Opening the form puts the caret in the **first** editable field.
*   The controls stand after the fields in document order and are tabbed
    through as :guilabel:`Apply`, :guilabel:`Undo`, :guilabel:`Discard`.
*   :kbd:`Escape` discards the form, unless the caret is inside a rich text
    editor - that key belongs to the editor's own balloons first.
*   :kbd:`Ctrl` + :kbd:`Enter` (:kbd:`Cmd` + :kbd:`Enter`) applies it, and so
    does :kbd:`Enter` in a text field, which submits the form. Both keys belong
    to the field form, so a document, contract or image editor open at the same
    time keeps its own handling of them.
*   The bar is a ``role="group"`` with its own accessible name, the
    :guilabel:`Edit all` button carries ``aria-pressed`` and names the forms it
    controls through ``aria-controls``, and the two live regions of the editor
    announce the result: the polite one carries the restored notice and the
    success, the assertive one a failure and a refusal of the form. A refusal
    beside a single field stays polite, as it always was.

Impact
======

This is a feature rather than a breaking change: the whole profile editing view
is new in 3.0.0 (:ref:`breaking-replaced-profile-editing-plugin`) and no
release ever shipped :guilabel:`Edit all` with per-field controls.

Single-field editing is unchanged - the pencil, the three buttons beside a
field, the field groups, and the editors of every document and contract panel
all behave exactly as before.

An override of :file:`Partials/Profile/Profile/Fields.html` has to render
:file:`Profile/Field/FormActions` at its end, or the profile offers no way to
apply the form. An override of the bar itself keeps the ``data-pe-form-actions``
element with its ``data-pe-form-reverted-message``, and the three
``data-pe-form-apply``, ``data-pe-form-undo`` and ``data-pe-form-discard``
buttons; every label of it is Fluid, none is spelled in JavaScript. See
:ref:`profile-editing-full-form`.

..  index:: Fluid, Frontend, JavaScript, ext:academic_persons_edit, NotScanned
