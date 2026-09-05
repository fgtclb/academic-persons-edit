:navigation-title: Configuration

..  _configuration:

=============
Configuration
=============

This extension ships its frontend TypoScript and its backend page TSconfig in
two forms: as TYPO3 **site sets**, and as classic **static templates** plus
**page TSconfig files** that are selected on a page. Both forms read the very
same files, so they configure an installation identically.

Pick one of them per site and stay with it — see
:ref:`Do not combine both <one-mechanism-per-site>` for what happens otherwise.

..  _configuration-components:

What the sets contain
=====================

The extension ships the ProfileEditing component and one aggregate set that
keeps the stable extension-level set name.

..  list-table::
    :header-rows: 1

    *   -   Set
        -   Delivers
    *   -   `fgtclb/academic-persons-edit-profile-editing`
        -   The assigned-profile list, profile editor, AJAX page type and the
            page TSconfig that offers ProfileEditing in the backend.
    *   -   `fgtclb/academic-persons-edit`
        -   ProfileEditing under the stable aggregate name. This is the normal
            set to use.

Both depend on `fgtclb/academic-base-ctype-group`, the set of
:guilabel:`EXT:academic_base` that labels the content element group all academic
extensions sort their elements into.

The plugin renders partials of :guilabel:`EXT:academic_persons`, but it reads
them from :file:`Resources/`, not from that extension's TypoScript. There is
therefore no set dependency on any `fgtclb/academic-persons-…` set, and none is
needed.

..  _configuration-hidden-by-default:

The content element is hidden by default
========================================

:guilabel:`EXT:academic_persons_edit` hides the editing content type for the
whole installation and brings ProfileEditing back per component. Whichever of
the two mechanisms below you use, ProfileEditing is the only profile-editing
content element offered in the backend.

..  _site-set:

Include the site set
====================

Add the set to the :file:`config.yaml` of the site that should offer the content
element:

..  code-block:: diff
    :caption: config/sites/my-site/config.yaml (diff)

     base: 'https://example.com/'
     rootPageId: 1
    +dependencies:
    +  - fgtclb/academic-persons-edit

See also `TYPO3 Explained, Using a site set as dependency in a site
<https://docs.typo3.org/permalink/t3coreapi:site-sets-usage>`__.

The :guilabel:`View` action in the assigned-profile list uses the same public
detail page as the Academic Persons list plugins. Configure
``plugin.tx_academicpersons.detailPid`` in the Academic Persons site settings
or TypoScript constants. ProfileEditing copies that value into its own Extbase
settings and targets the ``academicpersons_detail`` content element; it does
not require a second page setting in Academic Persons Edit.

..  _static-templates:

Include static templates
========================

For an installation that still configures its frontend through
:sql:`sys_template` records, the same files are registered as static templates
and as selectable page TSconfig files.

..  tip::

    On TYPO3 v13 and v14 we recommend the site set — and if you use it, do not
    press the backend button :guilabel:`Create a root TypoScript record` on that
    site. The :sql:`sys_template` record it creates carries the flag
    :guilabel:`Clear` for constants and setup, and that flag discards everything
    the site sets contributed. An installation that is already in that state
    gets its configuration back by selecting the static templates below in that
    very record.

..  _static-typoscript:

Include static TypoScript
-------------------------

Edit the :sql:`sys_template` record of the site root and add the entry to
:guilabel:`Include static (from extensions)`:

..  list-table::
    :header-rows: 1

    *   -   Entry
        -   Delivers
    *   -   :guilabel:`Academic Persons Edit: Profile editing (academic_persons_edit)`
        -   The TypoScript and AJAX page type of ProfileEditing.
    *   -   :guilabel:`Academic Persons Edit: All components (academic_persons_edit)`
        -   Every component this extension ships, in one entry.

..  _static-pagetsconfig:

Include static page TSconfig
----------------------------

Edit the page record of the site root, tab :guilabel:`Resources`, field
:guilabel:`Page TSconfig`, and add the entry:

..  list-table::
    :header-rows: 1

    *   -   Entry
        -   Delivers
    *   -   :guilabel:`Academic Persons Edit: Profile editing (academic_persons_edit)`
        -   Makes ProfileEditing selectable and configures its wizard entry.
    *   -   :guilabel:`Academic Persons Edit: All components (academic_persons_edit)`
        -   Every component this extension ships, in one entry.

The setting is inherited by every page below the one it is set on.

..  _one-mechanism-per-site:

Do not combine both
===================

A site that uses the site set **and** the static template reads the shipped
files twice. The site set is applied before the :sql:`sys_template` record, so
the second read happens after the site settings and after
:file:`config/sites/<site>/constants.typoscript` — and it resets every constant
the extension ships a default for back to that default.

Nothing else is damaged: the :guilabel:`Constants` and :guilabel:`Setup` fields
of the :sql:`sys_template` record, the page TSconfig of a page and the page
TSconfig files selected on a page are all applied afterwards and still win. Use
one mechanism per site and the question does not arise.

..  toctree::
   :maxdepth: 5
   :titlesonly:

   General/Index
   Settings/Index
