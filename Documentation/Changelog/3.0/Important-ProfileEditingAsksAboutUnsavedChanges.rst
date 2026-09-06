..  _important-profile-editing-asks-about-unsaved-changes:

======================================================
Important: Opening an editor asks about the unsaved one
======================================================

Description
===========

Only one editor of the :ref:`profile-editing` view is open at a time — one
field or group, the whole form, one document row or one contact of a contract.
Opening another one, and pressing :guilabel:`Edit all`, closes the editor that
is open first. One that still holds the values it was opened with closes
silently, exactly as its own :guilabel:`Undo` or :guilabel:`Cancel` would close
it. One with changes is not thrown away: the view asks, in a dialog of its own,
whether to :guilabel:`Save and continue`, to :guilabel:`Discard changes` or to
:guilabel:`Keep editing`. Saving stores what the open editor's own save would
store and then opens the other one; a save the server refuses keeps the visitor
in the refused editor, with its messages, and opens nothing. Keeping — and
:kbd:`Escape` — opens nothing at all.

The dialog is a ``<dialog>`` element the view clones from the
``unsaved-changes`` template of
:file:`Partials/Profile/UnsavedChanges.html`, never a browser prompt: its
question and its three answers are labels of the extension, translated in both
languages, and overridable with the partial. A site that drops the template
leaves the visitor in the editor that is open.

The view also says what it did. A discard the visitor chose is announced in the
polite live region — *Unsaved changes were discarded.* — because the row
collapsing is the only other sign of it, and that is no sign at all for a
visitor who is not looking at it. An editor closed with the value it was opened
with announces nothing, and the :guilabel:`Undo` a visitor pressed themselves
keeps its silence.

Two related rules. While a save is on its way to the server, the pencil,
:guilabel:`Edit all` and the :guilabel:`Delete content` and :guilabel:`Undo`
beside a field or a group do nothing: the answer is about to write the stored
value back into the field it saved, so a discard under it would be taken back a
moment later with nothing saying why. They say *Please wait until the change has
been saved.* rather than simply not reacting. The requests carry no timeout, so
a save the server accepts and never answers leaves those controls refusing
until the page is reloaded. And a pencil pressed while :guilabel:`Edit all` is
open does nothing at all, silently: every editable field is already open, and
the form's own bar is the way out of that mode.

A saved *edit* of a document row or of a contact stays open with what it stored,
so the next change starts from the record; creating and deleting close the
editor as before.

Impact
======

The two status messages travel from :file:`Templates/Profile/Index.html` to
the frontend as ``data-message-discarded`` and
``data-message-save-in-progress``, translated from
``profileEditing.status.discarded`` and ``profileEditing.status.saveInProgress``.
A site that overrides that template has to carry the two attributes, and has to
render :file:`Partials/Profile/UnsavedChanges.html` below the plugin root;
without the attributes the view announces its generic information text in their
place, without the partial it never asks and never opens a second editor over
a changed one.

Affected Installations
======================

Installations of the :guilabel:`Profile editing` content element of
`EXT:academic_persons_edit`, and in particular those overriding
:file:`Templates/Profile/Index.html` or the language files of the extension.

..  index:: Frontend, ext:academic_persons_edit, NotScanned
