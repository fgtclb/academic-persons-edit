..  _important-profile-and-contract-forms-start-the-editor:

===========================================================
Important: The profile forms now start the rich text editor
===========================================================

Description
===========

The rich text editor of the profile editing plugin attaches itself to every
field with the class :html:`rich-text`, and only on a page that loads the
editor and :file:`Resources/Public/JavaScript/frontend/ckeditor.js`. Two
defects kept it from starting.

**The profile form on TYPO3 v12.** The five rich text fields of the profile
form received the class from
:file:`Resources/Private/Partials/Profile/Properties/Profile.html`, which
passed :html:`richtext: true` to :file:`Partials/Profile/Forms/Textarea.html`
in a Fluid array. Fluid 2, which TYPO3 v12 ships, reads an unquoted
:html:`true` there as a variable of that name, which does not exist, so on
TYPO3 v12 the fields were plain text areas. The partial now passes
:html:`richtext: 1`, which every Fluid version reads as the number one.

**The contract forms on TYPO3 v12 and v13.** The office hours of the new
contract and the contract edit form had the same flag in
:file:`Resources/Private/Partials/Profile/Properties/Contract.html`, which now
passes :html:`richtext: 1` as well. Independent of that, only
:file:`Resources/Private/Templates/Profile/Edit.html` loaded the editor.
:file:`Resources/Private/Templates/Contract/New.html` and
:file:`Resources/Private/Templates/Contract/Edit.html` now load it too, with
the same asset identifiers, so a page that renders more than one of these
forms loads it once.

Impact
======

The office hours of a contract are edited with the rich text editor on both
core versions, and the rich text fields of the profile form on TYPO3 v12 as
well.

An installation that overrides one of the files named above keeps the plain
text areas until it applies the same change to its copy: :html:`richtext: 1`
instead of :html:`richtext: true` in the two partials, and the two
:html:`f:asset.script` tags of :file:`Templates/Profile/Edit.html` in the two
contract templates.

Affected Installations
======================

Installations that use the profile editing plugin, for the contract forms on
TYPO3 v12 and v13 and for the profile form on TYPO3 v12.

..  index:: Frontend, Fluid, ext:academic_persons_edit
