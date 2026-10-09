..  _breaking-profile-editing-ships-no-stylesheet:

========================================================
Breaking: The profile editing plugin ships no stylesheet
========================================================

Description
===========

The profile editing plugin no longer brings a stylesheet. The file
:file:`EXT:academic_persons_edit/Resources/Public/Css/frontend/profile-editing.css`
and its source are removed, and neither :file:`Templates/Profile/List.html` nor
:file:`Templates/Profile/Index.html` registers it any more. The markup carries
the Bootstrap 5 classes and the speaking ``ace-*`` classes, and the site
package styles it.

The stylesheet came with the profile editor of 3.0, and no release of version 2
shipped it. Changelog entries of 3.0 and the manual of the editor that speak of
the stylesheet of the editor describe the one this entry removes.

The rules are kept as an example in the mono repository the extension is
developed in: the stylesheet of its development instances,
`_academic-persons-edit.scss of EXT:academics_dev_site
<https://github.com/fgtclb/academic-extensions/blob/main/packages-dev/dev-site/Resources/Private/Scss/frontend/_academic-persons-edit.scss>`__,
which is never shipped with an extension.

Impact
======

The editor renders with Bootstrap and the theme of the site alone. Most of what
the removed file held is appearance, but several parts of the editor do not
work without its rules:

*   **The image cropper is not usable.** TYPO3 delivers the CropperJS module the
    image editor imports to the frontend, and its stylesheet only inside the
    backend. Without rules for the widget CropperJS builds, the cropper renders
    as an invisible stack of elements and nothing can be dragged.
*   **Regions the editor hides stay visible.** The editor hides the image
    editor, the contact editors, empty field messages and action groups with
    the :html:`hidden` attribute. Bootstrap's display utilities on the same
    elements outrank Bootstrap's own rule for the attribute, so the delete
    actions of the image editor, for example, stay on screen.
*   The custom elements of the editor are laid out as inline boxes, because an
    unknown element is :css:`display: inline`. A flex or grid parent of the
    theme then takes the wrong element as its item.
*   The collapse of the document editor and of the image editor, and the drag
    states of a sortable list, have no visible effect: the editor only writes
    the classes.

Affected Installations
======================

Every installation that renders the profile editing plugin and has no
stylesheet of its own for it. Installations whose template override registers
:file:`Css/frontend/profile-editing.css` by path.

Migration
=========

#.  Style the editor in the site package. Copy the rules of the example above
    into the stylesheet of the site and adapt them to the theme. Keep at least
    the cropper rules, the rule for :html:`[hidden]` and the :css:`display` of
    the custom elements, which the parts listed under *Impact* need to work.
#.  An overridden :file:`Templates/Profile/List.html` or
    :file:`Templates/Profile/Index.html` that registers
    :file:`Css/frontend/profile-editing.css` drops that line, the file does
    not exist any more.

.. index:: Frontend, Fluid, ext:academic_persons_edit
