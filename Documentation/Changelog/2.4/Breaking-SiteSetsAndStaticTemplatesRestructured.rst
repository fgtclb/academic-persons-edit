..  _breaking-site-sets-and-static-templates-restructured:

===============================================================
Breaking: Site sets and static templates have been restructured
===============================================================

Description
===========

The TypoScript of this extension was shipped twice: the static template read
:file:`Configuration/TypoScript/`, and the site set
:yaml:`fgtclb/academic-persons-edit` shipped its own :file:`constants.typoscript`
and :file:`setup.typoscript`, each of them a single :typoscript:`@import` of
that folder. The page TSconfig existed only as
:file:`Configuration/TSconfig/page.tsconfig`, reachable through the
auto-included :file:`Configuration/page.tsconfig` and through nothing else — it
was not selectable on a page at all.

Both mechanisms now read one physical copy of every file, and both of them
deliver the extension per component instead of as one block:

*   :file:`Configuration/TypoScript/ProfileEditing/` holds the TypoScript of the
    :guilabel:`Profile editing` content element and is what the static template
    registers *and* what the set points its :yaml:`typoscript` key at.
*   :file:`Configuration/TSconfig/ProfileEditing/page.tsconfig` holds its page
    TSconfig and is what the page field :guilabel:`Page TSconfig` offers *and*
    what the set points its :yaml:`pagets` key at.
*   :file:`Configuration/TypoScript/Full/` and
    :file:`Configuration/TSconfig/Full/page.tsconfig` are the aggregates for
    installations that do not use site sets.

The content element is now **hidden by default**. The always-included
:file:`Configuration/page.tsconfig` removes
:typoscript:`academicpersonsedit_profileediting` from the selectable content
element types, and the page TSconfig of the component adds it back — so the
element is offered where it is wanted instead of on every page of every
installation. The TCA registration itself did not move, so the frontend renders
existing records exactly as before. Editing such a record in the backend is a
different matter — read the warning below before upgrading.

Impact
======

A :sql:`sys_template` record that selected the old static template keeps its
stored value, and that value now points at a folder holding no
:file:`constants.typoscript` and no :file:`setup.typoscript`. It is not an
error — the frontend simply loses the plugin configuration, and the plugin
renders with no template paths.

A site package that imported one of the shipped files by path fails to resolve
it. :typoscript:`@import` of a missing file is silent, so this also shows up as
missing configuration rather than as an error message.

The :guilabel:`Profile editing` content element is no longer offered in the
backend until the page TSconfig of the component is included, through the site
set or through the page field :guilabel:`Page TSconfig`.

..  warning::

    Do not open an existing :guilabel:`Profile editing` record in the backend
    form on a page that does not include that page TSconfig. An item removed
    through :typoscript:`TCEFORM.tt_content.CType.removeItems` is excluded from
    the :guilabel:`[ invalid value ]` fallback TYPO3 otherwise adds for a stored
    value it does not know, and the stored value is dropped from the form data
    as well. The field :guilabel:`Type` therefore comes up with nothing
    selected, and **saving the record writes whatever the browser preselected
    into** :sql:`CType` — the record silently becomes another content element.
    The frontend keeps rendering it correctly until that happens.

    Include the page TSconfig of the component on every page tree that holds
    such records, and do it before editing them.

The set :yaml:`fgtclb/academic-persons-edit` keeps its name and keeps delivering
everything, so a site configuration that depends on it needs no change.

Affected Installations
======================

Installations that select the static template of this extension in a
:sql:`sys_template` record, that import one of the shipped files from an own
site package, or that use the content element without including the page
TSconfig of this extension.

Migration
=========

Replace the static template entry in the :sql:`sys_template` record:

..  list-table::
    :header-rows: 1

    *   -   Old entry
        -   New entry
    *   -   :guilabel:`Academic Persons Edit Settings (academic_persons_edit)`,
            stored as `EXT:academic_persons_edit/Configuration/TypoScript`
        -   :guilabel:`Academic Persons Edit: All components
            (academic_persons_edit)`, stored as
            `EXT:academic_persons_edit/Configuration/TypoScript/Full` — or
            :guilabel:`Academic Persons Edit: Profile editing
            (academic_persons_edit)`, stored as
            `EXT:academic_persons_edit/Configuration/TypoScript/ProfileEditing`

Add the page TSconfig entry, which did not exist before, in the page record of
the site root, tab :guilabel:`Resources`, field :guilabel:`Page TSconfig`:
:guilabel:`Academic Persons Edit: All components (academic_persons_edit)`,
stored as
`EXT:academic_persons_edit/Configuration/TSconfig/Full/page.tsconfig`. Without
it the content element is not selectable any more, and existing records of it
lose their :sql:`CType` when they are saved from the backend form.

Sites that use the site set instead need no migration — but they must not use
both mechanisms at once, see the :guilabel:`Configuration` chapter.

Adjust every :typoscript:`@import` in an own site package:

..  list-table::
    :header-rows: 1

    *   -   Old path
        -   New path
    *   -   `EXT:academic_persons_edit/Configuration/TypoScript/constants.typoscript`
        -   `EXT:academic_persons_edit/Configuration/TypoScript/ProfileEditing/constants.typoscript`
    *   -   `EXT:academic_persons_edit/Configuration/TypoScript/setup.typoscript`
        -   `EXT:academic_persons_edit/Configuration/TypoScript/ProfileEditing/setup.typoscript`
    *   -   `EXT:academic_persons_edit/Configuration/TSconfig/page.tsconfig`
        -   `EXT:academic_persons_edit/Configuration/TSconfig/ProfileEditing/page.tsconfig`

A site configuration may name the new component set instead of the aggregate:

..  list-table::
    :header-rows: 1

    *   -   Set
        -   Delivers
    *   -   `fgtclb/academic-persons-edit`
        -   Unchanged in name, now delivers through the component set below.
    *   -   `fgtclb/academic-persons-edit-profile-editing`
        -   The :guilabel:`Profile editing` content element only.

..  index:: TypoScript, TSConfig, Backend, ext:academic_persons_edit
