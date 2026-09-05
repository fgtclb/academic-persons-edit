..  _breaking-plugins-require-ctype-on-typo3-v14:

======================================================
Breaking: Extbase plugins require `CType` on TYPO3 v14
======================================================

..  seealso::
    The `upgrade chapter of academic_persons
    <https://docs.typo3.org/p/fgtclb/academic-persons/main/en-us/Upgrade/Index.html>`__
    is the order in which the 3.0 changes have to be applied.

Description
===========

TYPO3 v14 removed the `tt_content` sub-type feature (the `list_type` column) and
changed `ExtensionManagementUtility::addPlugin()` accordingly. The academic
plugins have been registered as first-class content elements (`CType`) since the
`2.1` version line (see the `2.1` breaking note about migrating from `list_type`
to `CType`); for TYPO3 v14 support the internal registration was adapted to the
new `addPlugin()` signature and the vestigial `list_type` handling was dropped.

Impact
======

On TYPO3 v14 the `tt_content.list_type` column no longer exists. Any content
records still stored as `CType=list` with a `list_type` of one of the plugins
below will no longer resolve, and custom TypoScript, TSconfig, page TSconfig or
SQL that references `list_type` for these plugins stops working.

The change relates to the following plugins:

* `academicpersonsedit_profileediting`
* `academicpersonsedit_profileswitcher`

Affected Installations
======================

Installations that upgrade to TYPO3 v14 and still hold content elements stored
as `CType=list` + `list_type=<plugin>`, or that reference `list_type` for these
plugins in their own configuration.

Migration
=========

Run the provided upgrade wizard `academicPersonsEdit_pluginContent` **before**
upgrading to TYPO3 v14 (it requires the `list_type` column, which v14 removes) to
migrate the `tt_content` records to the dedicated `CType` values. Update any
custom configuration referencing `list_type` to match on `CType` instead.

.. index:: Database, TCA, ext_localconf, TSConfig
