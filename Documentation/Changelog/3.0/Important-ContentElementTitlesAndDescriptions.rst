.. _important-content-element-titles-and-descriptions:

============================================================
Important: Content elements have new titles and descriptions
============================================================

Description
===========

The content element of this extension has a new title and a new description, in
English and in German. They follow one wording that all academic extensions
share now. An editor sees the title in the new content element wizard and in
the type field of a content element, and the description below the title in the
wizard.

..  list-table::
    :header-rows: 1

    *   -   Content type
        -   Title until now
        -   Title now
        -   Description now
    *   -   :typoscript:`academicpersonsedit_profileediting`
        -   :guilabel:`Academic Persons Profile Editing` (German :guilabel:`Akademische Personen Profil Bearbeitung`) or :guilabel:`Profiles: Profile Editing` (German :guilabel:`Profile: Profil Editor`)
        -   :guilabel:`Edit Person’s Profiles` (German :guilabel:`Personen Profil Bearbeitung`)
        -   Editing of personal or commissioned profiles after logging in

The type field and the wizard read the title from two different labels for this
content element. Both labels carry the new title.

Impact
======

Editors see the new title and description. The content type, the label keys
and their files did not change. A site that replaces a title or a description,
with page TSconfig of the wizard, with
:typoscript:`TCEFORM.tt_content.CType.altLabels` or with a language file
override, keeps its own text.

Affected Installations
======================

Every installation that offers a content element of this extension to its
editors. Nothing has to be migrated.

.. index:: Backend, TSConfig, ext:academic_persons_edit
