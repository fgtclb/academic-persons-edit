..  _feature-hide-document-rows:

=============================================================
Feature: Contracts and profile information rows can be hidden
=============================================================

Description
===========

Every row of a document section of the :ref:`profile-editing` view - a
contract, a publication, a lecture, a cooperation and the other timeline
entries - carries a visibility switch as the ``hide`` entry of its section's
``actions`` list. Pressing it hides the record in the public profile, pressing
it again shows it. While the record is hidden the row is drawn in the
secondary text colour, its controls at full contrast, and carries a
:guilabel:`Hidden` tag in front of its action group; the label of the switch
names the press (:guilabel:`Hide in frontend` respectively :guilabel:`Show in
frontend`) and its glyph shows the state.

The switch is what the contact rows below a contract already have, extended to
the records they belong to. It flips the record's own ``hidden`` column, the
TCA ``enablecolumns.disabled`` field, through the new
``toggleDocumentVisibility`` JSON action of :php:`ProfileController`, which is
sent the target state rather than a "flip" so that a double press stores what
the visitor saw, answers the serialised record, and follows the ``hide`` entry
of the section's allow-list like every other action follows its own.

A hidden record stays in the editor: it is the one place it can be shown
again. The editor therefore lists the records of a section through the
repositories' ``IncludingHidden`` queries, while the public views keep reading
the relations of the profile, which respect the enable fields.

Impact
======

The switch is listed first in the shipped :yaml:`actions` of every section. A
site that overrides a section's :yaml:`actions` keeps its list and adds
``hide`` where the switch is wanted; a section marked :yaml:`readonly` never
offers it. A site overriding :file:`Templates/Profile/Index.html` has to carry
``data-toggle-document-visibility-url``, ``data-message-document-hidden`` and
``data-message-document-shown``; one overriding
:file:`Partials/Profile/Documents/Actions.html` renders the ``hide`` case and
the tag itself.

Affected Installations
======================

Installations of the :guilabel:`Profile editing` content element of
`EXT:academic_persons_edit`.

..  index:: Frontend, ext:academic_persons_edit, NotScanned
