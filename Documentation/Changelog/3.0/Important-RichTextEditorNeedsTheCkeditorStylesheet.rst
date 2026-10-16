..  _important-rich-text-editor-needs-the-ckeditor-stylesheet:

===============================================================
Important: The rich text editor needs the CKEditor 5 stylesheet
===============================================================

Description
===========

The rich text fields of the :ref:`profile-editing` view are CKEditor 5, which
the view imports from :guilabel:`EXT:rte_ckeditor`. Up to TYPO3 13.4.35 and
14.3.7 the core ships CKEditor 47.6.0, whose scripts inject the styles of the
toolbar, the buttons, the link balloon and the dialogs into the page they run
on. TYPO3 13.4.36 and 14.3.8 ship CKEditor 48.5.2, whose scripts no longer do.
Its interface styles are in
:file:`EXT:rte_ckeditor/Resources/Public/Css/editor.css` now, and only the
backend form links that file. The extension uses the scripts of
:guilabel:`EXT:rte_ckeditor` only, see
:ref:`important-new-ckeditor-and-sanitizer-dependency`.

Impact
======

On TYPO3 13.4.36, 14.3.8 and later the rich text fields of the profile editor
render without the interface styles of CKEditor, unless the site links the
stylesheet: the icons of the toolbar take the size of their SVG files, and the
toolbar, the link balloon and the dialogs are not laid out. The extension does
not ship the stylesheet, as it ships no stylesheet at all, see
:ref:`breaking-profile-editing-ships-no-stylesheet`.

Affected Installations
======================

Installations of the :guilabel:`Profile editing` content element on TYPO3
13.4.36, 14.3.8 or later whose site does not link
:file:`EXT:rte_ckeditor/Resources/Public/Css/editor.css` on the page of the
editor.

Migration
=========

Link the file of the core on the page of the editor, as described in
:ref:`styling-rich-text-editor`, rather than a copy of it, so it stays in step
with the CKEditor release of the core.

..  index:: Frontend, TypoScript, NotScanned, ext:academic_persons_edit
