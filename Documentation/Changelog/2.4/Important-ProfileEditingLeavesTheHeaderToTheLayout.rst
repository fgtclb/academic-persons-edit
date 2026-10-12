..  _important-1791825026:

=====================================================================================
Important: The profile editing plugin leaves the content element header to the layout
=====================================================================================

Description
===========

A :guilabel:`Profiles: Profile Editing` content element renders through the
content element layout of the site, like any other content element. The
layouts of EXT:fluid_styled_content and of the bootstrap package render the
header and the subheader an editor enters, and the layout
:file:`Layouts/ProfileEdit.html` of the plugin rendered them a second time, on
every view of the plugin:

..  code-block:: html

    <f:render partial="Header/All" arguments="{_all}" />

With an explicit header layout the header and the subheader appeared twice.
With the header layout :guilabel:`Default` the plugin left an empty
:html:`<header></header>` element behind, because the header partial takes the
heading level for that layout from a setting the plugin settings did not carry.

The layout now renders the header only when the site switches it on:

..  code-block:: typoscript
    :caption: TypoScript constants

    plugin.tx_academicpersonsedit.renderContentElementHeader = 1

It is off by default. The static template and the site set
`fgtclb/academic-persons-edit-profile-editing` deliver the constant alike, and
there is no site setting for it.

The plugin settings now also carry :typoscript:`settings.defaultHeaderType`,
mapped from the constant :typoscript:`styles.content.defaultHeaderType` of
EXT:fluid_styled_content, so with the switch on the header layout
:guilabel:`Default` renders a heading. The section "The header of the content
element" of the general configuration in the manual describes both.

The switch exists from version 2.4.0 on and only in the 2.x versions. The
editor of version 3.0 renders no header of its own and has no such switch. Its
changelog entry "Breaking: The profile editing plugin renders no content
element header" shows how to render the header there.

Impact
======

On a site whose content element layout renders the header, the header of the
profile editing content element appears once, and the markup of the plugin no
longer contains a :html:`<header>` element. TYPO3 v12 and v13 behave alike
here.

Affected Installations
======================

Every installation using the profile editing content element.

Installations whose content element layout renders **no** header, a site
package that renders the header in its element templates instead, lose the
header of the profile editing content element.

Migration
=========

On a site whose content element layout renders no header, switch
:typoscript:`plugin.tx_academicpersonsedit.renderContentElementHeader` on.

Projects that copied :file:`Layouts/ProfileEdit.html` keep rendering the header
in their copy. On a site whose layout renders the header, drop the
:html:`<f:render partial="Header/All" … />` line from the copy. On any other
site, keep it or wrap it in the same condition as the shipped layout:

..  code-block:: html

    <f:if condition="{settings.renderContentElementHeader}">
        <f:render partial="Header/All" arguments="{_all}" />
    </f:if>

.. index:: Frontend, Fluid, TypoScript, ext:academic_persons_edit
