..  _breaking-profile-editing-renders-no-content-element-header:

======================================================================
Breaking: The profile editing plugin renders no content element header
======================================================================

Description
===========

Version 2.4.0 introduced the TypoScript constant
:typoscript:`plugin.tx_academicpersonsedit.renderContentElementHeader`, off by
default. Switched on, the layout :file:`Layouts/ProfileEdit.html` of the
profile editing plugin rendered the header and the subheader of its content
element itself, for a site whose content element layout renders no header.

The editor of version 3.0 renders no content element header on any of its
views, and nothing reads that constant or the plugin setting
:typoscript:`settings.renderContentElementHeader` any more. The layout is
removed together with the form flow, see
:ref:`breaking-replaced-profile-editing-plugin`. The header of the content
element is left to the content element layout of the site, as for every other
content element.

Impact
======

On a site whose content element layout renders the header, nothing changes:
the header appears once, as it did in 2.4 with the switch off.

On a site that switched the constant on, the header and the subheader of the
profile editing content element are no longer rendered, on the list of profiles
and in the editor alike. Nothing reports it.

Affected Installations
======================

Installations that set
:typoscript:`plugin.tx_academicpersonsedit.renderContentElementHeader = 1`,
because their content element layout renders no header, and installations that
set the plugin setting :typoscript:`settings.renderContentElementHeader`
directly. TYPO3 v13 and v14 behave alike here.

Migration
=========

Remove the constant, or the plugin setting where a site set it directly, from
the site TypoScript. Neither has an effect any more. Then render the header in
one of two ways.

The recommended one gives the profile editing content element a content element
layout that renders the header, the way the layout of EXT:fluid_styled_content
does. The plugin templates stay untouched:

..  code-block:: typoscript
    :caption: TypoScript setup

    tt_content.academicpersonsedit_profileediting.layoutRootPaths.50 = EXT:my_sitepackage/Resources/Private/Layouts/ContentElementWithHeader/

Name the layout file in that folder :file:`Default.html`. TYPO3 v14 reads it
as well as :file:`Default.fluid.html`, while TYPO3 v13 reads only
:file:`Default.html`. A layout copied from EXT:fluid_styled_content of TYPO3
v14 is therefore renamed to work on both.

The other way renders the header in an override of the plugin templates, see
:ref:`templates-override`. It ties the site to the editor template, which
carries the contract of the editor script and changes with the editor, so
prefer the layout where the site can provide one. The list of profiles renders
:file:`Templates/Profile/List.html` and the editor
:file:`Templates/Profile/Index.html`. Both views receive the variables the
header partial of EXT:fluid_styled_content reads, and the partials of that
extension are on the partial path of the plugin, so a copy of each template
renders the header with one line:

..  code-block:: html

    <f:render partial="Header/All" arguments="{_all}" />

For the header layout :guilabel:`Default`, the partial takes the heading level
from a plugin setting that the plugin no longer maps. Set it in the same site:

..  code-block:: typoscript
    :caption: TypoScript setup

    plugin.tx_academicpersonsedit.settings.defaultHeaderType = {$styles.content.defaultHeaderType}

Without it, a header with the header layout :guilabel:`Default` renders as an
empty :html:`<header>` element.

..  index:: Frontend, Fluid, TypoScript, ext:academic_persons_edit, NotScanned
