..  _deprecation-legacy-static-template-path:

====================================================
Deprecation: The static template path of version 2.3
====================================================

Description
===========

Up to version 2.3 this extension offered one static template, stored in a
:sql:`sys_template` record as
`EXT:academic_persons_edit/Configuration/TypoScript`. Version 2.4 moves the
TypoScript into component folders, see
:ref:`breaking-site-sets-and-static-templates-restructured`, and leaves no
TypoScript of its own in the folder of that path.

The path keeps delivering. Its :file:`constants.typoscript` and
:file:`setup.typoscript` import the files of the same name in
:file:`Configuration/TypoScript/ProfileEditing/`, so a record that stores the
path gets what :guilabel:`Academic Persons Edit: All components
(academic_persons_edit)` delivers, and an import of the two files gets what an
import of the files in :file:`Configuration/TypoScript/ProfileEditing/` gets.

The path is offered in the static template list again, as :guilabel:`Academic
Persons Edit: Path up to 2.3 (deprecated, use All components)
(academic_persons_edit)`, so saving the record keeps it. It is deprecated and
will be removed in version 4.0.

Impact
======

An installation that selects :guilabel:`All components` or depends on the site
set notices nothing. An installation that still stores the path of version 2.3,
or imports its files, keeps its plugin configuration.

A site that depends on the site set and still stores the path of version 2.3 in
its :sql:`sys_template` record, or imports its files, reads the TypoScript
twice, as it did in version 2.3. See :ref:`one-mechanism-per-site` for what
that costs.

Affected Installations
======================

Installations that store `EXT:academic_persons_edit/Configuration/TypoScript`
in a :sql:`sys_template` record, or import
:file:`EXT:academic_persons_edit/Configuration/TypoScript/setup.typoscript` or
:file:`EXT:academic_persons_edit/Configuration/TypoScript/constants.typoscript`
in a site package.

Migration
=========

Select :guilabel:`Academic Persons Edit: All components
(academic_persons_edit)` in the :sql:`sys_template` record instead of the path
up to 2.3, or, on TYPO3 v13, depend on the set
:yaml:`fgtclb/academic-persons-edit` in the site configuration.

Replace an import of the old files with the files in
`EXT:academic_persons_edit/Configuration/TypoScript/ProfileEditing/`, or remove
it when the site depends on the site set on TYPO3 v13.

..  index:: TypoScript, ext:academic_persons_edit
