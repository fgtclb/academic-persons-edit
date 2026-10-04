..  _breaking-persons-edit-profile-editing-icons-moved-to-the-frontend-icon-registry:

=====================================================
Breaking: Profile editing renders the shared icon set
=====================================================

Description
===========

The action and state icons of the profile editor and the profile overview are
shown in the frontend only. Up to now this extension registered them itself,
under ``academic-persons-edit-*`` identifiers in
:file:`Configuration/Icons.php`, for the icon registry of the TYPO3 backend,
and the templates rendered them with ``core:icon`` (ACE-812, ACE-586).

The editor now renders the shared action and state icons of
:guilabel:`academic_base`. They are frontend icons, registered in the
:file:`Configuration/FrontendIcons.php` of :guilabel:`academic_base` for its
frontend icon registry, and the thirteen templates and partials that render
them use the ``ab:icon`` ViewHelper of :guilabel:`academic_base`, with the
arguments they had: :file:`Templates/Profile/Index.html`,
:file:`Templates/Profile/List.html` and, below :file:`Partials/Profile/`,
:file:`ButtonTemplates.html`, :file:`Prototypes.html`,
:file:`Field/Actions.html`, :file:`Field/AutosaveUndo.html`,
:file:`Field/Group.html`, :file:`Field/Helptext.html`,
:file:`Field/Preview.html`, :file:`Documents/Actions.html`,
:file:`Documents/ContractContacts.html`, :file:`Documents/Sections.html` and
:file:`Image/Card.html`. This extension registers no frontend icon of its own.

The icon of the content element is renamed as well and stays in
:file:`Configuration/Icons.php`, the only entry there.

Every identifier is renamed without an alias. The old ones are registered in
neither registry any more:

..  list-table::
    :header-rows: 1

    *   -   Removed identifier
        -   3.0 identifier
        -   Registry
    *   -   ``persons_edit_icon``
        -   ``tx-academicpersonsedit-plugin-profile-editing``
        -   :file:`Configuration/Icons.php` of this extension (backend)
    *   -   ``academic-persons-edit-add``
        -   ``tx-academicbase-action-add``
        -   :file:`Configuration/FrontendIcons.php` of :guilabel:`academic_base`
    *   -   ``academic-persons-edit-back``
        -   ``tx-academicbase-action-back``
        -   :file:`Configuration/FrontendIcons.php` of :guilabel:`academic_base`
    *   -   ``academic-persons-edit-clear``
        -   ``tx-academicbase-action-clear``
        -   :file:`Configuration/FrontendIcons.php` of :guilabel:`academic_base`
    *   -   ``academic-persons-edit-delete``
        -   ``tx-academicbase-action-delete``
        -   :file:`Configuration/FrontendIcons.php` of :guilabel:`academic_base`
    *   -   ``academic-persons-edit-edit``
        -   ``tx-academicbase-action-edit``
        -   :file:`Configuration/FrontendIcons.php` of :guilabel:`academic_base`
    *   -   ``academic-persons-edit-help``
        -   ``tx-academicbase-action-help``
        -   :file:`Configuration/FrontendIcons.php` of :guilabel:`academic_base`
    *   -   ``academic-persons-edit-move-down``
        -   ``tx-academicbase-action-move-down``
        -   :file:`Configuration/FrontendIcons.php` of :guilabel:`academic_base`
    *   -   ``academic-persons-edit-move-up``
        -   ``tx-academicbase-action-move-up``
        -   :file:`Configuration/FrontendIcons.php` of :guilabel:`academic_base`
    *   -   ``academic-persons-edit-save``
        -   ``tx-academicbase-action-save``
        -   :file:`Configuration/FrontendIcons.php` of :guilabel:`academic_base`
    *   -   ``academic-persons-edit-sort-handle``
        -   ``tx-academicbase-action-drag``
        -   :file:`Configuration/FrontendIcons.php` of :guilabel:`academic_base`
    *   -   ``academic-persons-edit-undo``
        -   ``tx-academicbase-action-undo``
        -   :file:`Configuration/FrontendIcons.php` of :guilabel:`academic_base`
    *   -   ``academic-persons-edit-upload-image``
        -   ``tx-academicbase-action-upload-image``
        -   :file:`Configuration/FrontendIcons.php` of :guilabel:`academic_base`
    *   -   ``academic-persons-edit-view``
        -   ``tx-academicbase-action-view``
        -   :file:`Configuration/FrontendIcons.php` of :guilabel:`academic_base`
    *   -   ``academic-persons-edit-view-close``
        -   ``tx-academicbase-action-view-close``
        -   :file:`Configuration/FrontendIcons.php` of :guilabel:`academic_base`
    *   -   ``academic-persons-edit-visible``
        -   ``tx-academicbase-state-visible``
        -   :file:`Configuration/FrontendIcons.php` of :guilabel:`academic_base`
    *   -   ``academic-persons-edit-hidden``
        -   ``tx-academicbase-state-hidden``
        -   :file:`Configuration/FrontendIcons.php` of :guilabel:`academic_base`

Of the removed identifiers, ``persons_edit_icon`` and
``academic-persons-edit-back``, ``-delete``, ``-edit``, ``-save`` and ``-view``
shipped with 2.x. The others only existed during the development of 3.0.

The 2.x identifiers ``academic-persons-edit-add-image``, ``-add-item``,
``-cancel``, ``-sort`` and ``-to-top`` went with the form flow they belonged
to and have no successor, see :ref:`breaking-replaced-profile-editing-plugin`.

The files of the removed identifiers are deleted from
:file:`Resources/Public/Icons/`, together with
:file:`LICENSE-bootstrap-icons.txt`. The shared icons and the new content
element icon are Font Awesome Free solid icons (CC BY 4.0), with their notice
in :file:`Resources/Public/Icons/LICENSE-font-awesome.txt` of the extension
that ships the file.

Every icon is drawn in ``currentColor`` and registered with
:php:`\FGTCLB\AcademicBase\Imaging\IconProvider\CurrentColorSvgIconProvider`,
which inlines the ``<svg>`` where the :php:`SvgIconProvider` of TYPO3 rendered
an ``<img>`` in 2.x. A glyph of the editor takes the colour of the control it
sits in, and the content element icon follows the backend colour scheme in the
page module and the new content element wizard instead of keeping a fixed red.

The drawings change with the identifiers. Two of them change in kind rather
than in style: the drag handle is a set of horizontal lines instead of a grid
of dots, and the help icon is a question mark in a circle instead of an ``i``.

Impact
======

None of the following shows an error:

*   A template override, PHP code or a site stylesheet or script that names a
    removed identifier gets TYPO3's not-found icon, or matches nothing. The
    rendered markup names the identifier twice, as ``data-identifier`` and in
    the ``icon-<identifier>`` class on the wrapper, so a selector such as
    ``.icon-academic-persons-edit-save`` or
    ``[data-identifier="academic-persons-edit-save"]`` stops matching.
*   A site package that replaced one of the editor icons, in its
    :file:`Configuration/Icons.php` or its
    :file:`Configuration/FrontendIcons.php`, sees the shipped drawing again.
*   An override of one of the thirteen files that renders an action icon with
    ``core:icon`` shows TYPO3's not-found icon in its place, because the icon
    registry of the backend does not know the shared icons. Three of the files
    hold the templates the editor clones in the browser:
    :file:`Prototypes.html` (the help button of a field),
    :file:`Documents/ContractContacts.html` (the add control of a contact
    section and the six controls of a contact row) and
    :file:`ButtonTemplates.html` (the edit button of a field without a
    value). An override of one of those clones the not-found icon into every
    control it builds.
*   Page TSconfig or TCA of a project that names ``persons_edit_icon``, for
    example in a new content element wizard entry of its own, shows the
    not-found icon.
*   A site stylesheet that selects the icons as ``.t3js-icon img`` no longer
    matches them.

Apart from the identifier in ``data-identifier`` and in the class, the
rendered markup of the editor icons keeps its classes and attributes, so the
shipped stylesheet and the controls the editor builds in the browser need no
change.

Affected Installations
======================

Every installation whose site package replaces one of the editor icons or the
content element icon, overrides one of the thirteen files, or names one of the
removed identifiers in a template, PHP code, page TSconfig, TCA, a stylesheet
or a script of its own. Installations that use the shipped templates as they
are need no change.

Migration
=========

#.  Replace every removed identifier with its 3.0 identifier from the table
    above, in template overrides, PHP code, page TSconfig, TCA, and CSS and
    JavaScript selectors.
#.  Move a replacement of one of the editor icons to the
    :file:`Configuration/FrontendIcons.php` of the site package, under the
    ``tx-academicbase-*`` identifier. The file has the format of
    :file:`Icons.php`, and the site package has to depend on
    :guilabel:`academic_base`, so its entry is read after the shipped one.
    :ref:`templates-override-icons` has an example. The identifier is shared by
    every academic extension, so the replacement changes the icon wherever the
    action or state appears, not only in the profile editor.
#.  Move a replacement of the content element icon to the
    :file:`Configuration/Icons.php` of the site package, under
    ``tx-academicpersonsedit-plugin-profile-editing``.
#.  In an override of one of the thirteen files, render the icons with
    ``<ab:icon`` instead of ``<core:icon``, keep every argument, and declare
    the namespace in the :html:`<html>` tag of the file:
    ``xmlns:ab="http://typo3.org/ns/FGTCLB/AcademicBase/ViewHelpers"``. Keep
    the ``data-pe-view-icon`` and ``data-pe-visibility-icon`` wrappers around
    the eye and visibility icons, the editor switches between the two icons of
    a control through them. An icon of the project in the same file either
    stays on ``core:icon`` or is registered in the
    :file:`Configuration/FrontendIcons.php` of the site package and rendered
    with ``ab:icon`` too.
#.  Style the inlined ``<svg>`` rather than an ``<img>``.
#.  Flush the TYPO3 caches, so the icon registries and the Fluid template cache
    are rebuilt.

..  index:: Backend, Fluid, Frontend, TCA, TSConfig, NotScanned, ext:academic_persons_edit
