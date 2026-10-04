..  _feature-profile-editing-icon-set:

=====================================
Feature: The profile editing icon set
=====================================

Description
===========

The profile editing frontend addresses its action icons through sixteen
identifiers registered in :file:`Configuration/FrontendIcons.php`, for the
frontend icon registry of :guilabel:`academic_base`, and rendered with its
``ab:icon`` ViewHelper:

..  list-table::
    :header-rows: 1

    *   -   Identifier
        -   Action
    *   -   ``academic-persons-edit-add``
        -   Add an entry to a list
    *   -   ``academic-persons-edit-back``
        -   Leave the editor
    *   -   ``academic-persons-edit-clear``
        -   Clear the value of a field
    *   -   ``academic-persons-edit-delete``
        -   Delete an entry
    *   -   ``academic-persons-edit-edit``
        -   Start editing a field or an entry
    *   -   ``academic-persons-edit-help``
        -   Show the help text of a field
    *   -   ``academic-persons-edit-move-down``
        -   Move an entry down
    *   -   ``academic-persons-edit-move-up``
        -   Move an entry up
    *   -   ``academic-persons-edit-save``
        -   Apply an edit
    *   -   ``academic-persons-edit-sort-handle``
        -   Drag handle of a sortable entry
    *   -   ``academic-persons-edit-undo``
        -   Undo an edit
    *   -   ``academic-persons-edit-upload-image``
        -   Upload or replace the profile image
    *   -   ``academic-persons-edit-view``
        -   Open the public view of a record
    *   -   ``academic-persons-edit-view-close``
        -   Close the read view a row action opened
    *   -   ``academic-persons-edit-visible``
        -   The visibility toggle of a contact that is shown in the frontend
    *   -   ``academic-persons-edit-hidden``
        -   The visibility toggle of a contact that is hidden in the frontend

Identifier and file name are the action, never the drawing: a later icon set
changes the glyph, not the API the templates address.

The files are `Bootstrap Icons <https://icons.getbootstrap.com/>`__ and carry
their MIT licence in
:file:`Resources/Public/Icons/LICENSE-bootstrap-icons.txt`. They are drawn in
``currentColor`` and registered with
:php:`\FGTCLB\AcademicBase\Imaging\IconProvider\CurrentColorSvgIconProvider`,
which inlines the file instead of rendering an :html:`<img>` - so a glyph takes
the colour of the button it sits in.

Impact
======

The identifiers are public API. A site package that wants different artwork
and depends on :guilabel:`academic_persons_edit` registers one of them again in
its own :file:`Configuration/FrontendIcons.php` with its own file and needs no
template override. A file registered that way is drawn in ``currentColor`` as
well, or it will not follow the surrounding text. An entry in the
:file:`Configuration/Icons.php` of the site package does not reach the editor,
see :ref:`breaking-persons-edit-profile-editing-icons-moved-to-the-frontend-icon-registry`.

Five of the identifiers - ``academic-persons-edit-edit``,
``academic-persons-edit-view``, ``academic-persons-edit-delete``,
``academic-persons-edit-save`` and ``academic-persons-edit-back`` - existed
before and now resolve to the new artwork and the new provider. An
installation that renders them through the shipped templates sees a different
glyph that follows the text colour instead of a fixed dark grey.

Affected Installations
======================

All installations using the `EXT:academic_persons_edit` extension starting
with version 3.0.

Migration
=========

An installation that re-registered one of the five existing identifiers in the
:file:`Configuration/Icons.php` of a site package moves that entry to the
:file:`Configuration/FrontendIcons.php` of the site package to keep its own
artwork.

..  index:: Frontend, Fluid, Template
