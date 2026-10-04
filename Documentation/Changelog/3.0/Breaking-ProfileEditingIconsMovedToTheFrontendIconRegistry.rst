..  _breaking-persons-edit-profile-editing-icons-moved-to-the-frontend-icon-registry:

=======================================================================
Breaking: The profile editing icons moved to the frontend icon registry
=======================================================================

Description
===========

The sixteen action icons of the profile editor and the profile overview are
shown in the frontend only: ``academic-persons-edit-add``, ``-back``,
``-clear``, ``-delete``, ``-edit``, ``-help``, ``-move-down``, ``-move-up``,
``-save``, ``-sort-handle``, ``-undo``, ``-upload-image``, ``-view``,
``-view-close``, ``-visible`` and ``-hidden``. They were registered in
:file:`Configuration/Icons.php`, for the icon registry of the TYPO3 backend,
and the templates rendered them with ``core:icon``.

They are now registered in :file:`Configuration/FrontendIcons.php`, for the
frontend icon registry of :guilabel:`academic_base`, and are no longer
registered in :file:`Configuration/Icons.php`. The thirteen templates and
partials that render them use the ``ab:icon`` ViewHelper of
:guilabel:`academic_base`, with the arguments they had:
:file:`Templates/Profile/Index.html`, :file:`Templates/Profile/List.html` and,
below :file:`Partials/Profile/`, :file:`ButtonTemplates.html`,
:file:`Prototypes.html`, :file:`Field/Actions.html`,
:file:`Field/AutosaveUndo.html`, :file:`Field/Group.html`,
:file:`Field/Helptext.html`, :file:`Field/Preview.html`,
:file:`Documents/Actions.html`, :file:`Documents/ContractContacts.html`,
:file:`Documents/Sections.html` and :file:`Image/Card.html`. Identifiers,
files and icon provider are unchanged.

The icon of the content element, ``persons_edit_icon``, stays in
:file:`Configuration/Icons.php` and is the only entry there.

Impact
======

Two things change for a site, and neither shows an error:

*   A site package that replaced one of the sixteen icons in its own
    :file:`Configuration/Icons.php` sees the shipped drawing again in the
    editor. The frontend icon registry does not read that file.
*   An override of one of the thirteen files that still renders an action icon
    with ``core:icon`` shows TYPO3's not-found icon in its place, because the
    icon registry of the backend no longer knows the identifier. Three of them
    hold the templates the editor clones in the browser:
    :file:`Prototypes.html` (the help button of a field),
    :file:`Documents/ContractContacts.html` (the add control of a contact
    section and the six controls of a contact row) and
    :file:`ButtonTemplates.html` (the edit button of a field without a
    value). An override of one of those clones the not-found icon into every
    control it builds.

PHP or backend code that asks the :php:`IconFactory` of TYPO3 for one of the
sixteen identifiers gets the not-found icon as well.

The rendered markup of the icons is the same as before, with the same classes,
attributes and inlined SVG, so the shipped stylesheet, the controls the editor
builds in the browser and a site stylesheet need no change.

Affected Installations
======================

Every installation whose site package replaces one of the sixteen icons, or
overrides one of the thirteen files, or renders one of the sixteen identifiers
in a template or PHP code of its own.

Migration
=========

#.  Move a replacement of one of the sixteen icons from the
    :file:`Configuration/Icons.php` of the site package to its
    :file:`Configuration/FrontendIcons.php`. The file has the format of
    :file:`Icons.php`, and the site package has to depend on
    :guilabel:`academic_persons_edit`, so its entry is read after the shipped
    one. :ref:`templates-override-icons` has an example.
#.  In an override of one of the thirteen files, replace ``<core:icon`` with
    ``<ab:icon`` for the action icons, keep every argument, and declare the
    namespace in the :html:`<html>` tag of the file:
    ``xmlns:ab="http://typo3.org/ns/FGTCLB/AcademicBase/ViewHelpers"``. Keep
    the ``data-pe-view-icon`` and ``data-pe-visibility-icon`` wrappers around
    the eye and visibility icons, the editor switches between the two icons of
    a control through them. An icon of the project in the same file either
    stays on ``core:icon`` or is registered in the
    :file:`Configuration/FrontendIcons.php` of the site package and rendered
    with ``ab:icon`` too.
#.  Flush the TYPO3 caches, so the icon registries and the Fluid template cache
    are rebuilt.

..  index:: Fluid, Frontend, ext:academic_persons_edit
