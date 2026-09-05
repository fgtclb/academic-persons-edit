..  _important-profile-editing-replaced-in-place:

===============================================
Important: Profile editing is replaced in place
===============================================

Description
===========

The profile editing frontend is rewritten from the ground up
(:ref:`breaking-replaced-profile-editing-plugin`), but it keeps the identity
of the content element it replaces. Nothing in the page tree, in a site
configuration or in a deployment has to be renamed:

..  list-table::
    :header-rows: 1

    *   - What
        - Value
    *   - Content type (``tt_content.CType``)
        - ``academicpersonsedit_profileediting``
    *   - Extbase plugin
        - ``AcademicPersonsEdit`` / ``ProfileEditing``
    *   - Request namespace
        - ``tx_academicpersonsedit_profileediting``
    *   - Site sets
        - ``fgtclb/academic-persons-edit`` and
          ``fgtclb/academic-persons-edit-profile-editing``
    *   - Static template / page TSconfig
        - :file:`EXT:academic_persons_edit/Configuration/TypoScript/Full` and
          :file:`EXT:academic_persons_edit/Configuration/TSconfig/ProfileEditing/page.tsconfig`
    *   - TypoScript object
        - ``plugin.tx_academicpersonsedit``
    *   - Default action
        - ``list``

Existing content records keep working after the update. There is no upgrade
wizard for the content element and none is needed.

Impact
======

An update replaces the rendered view and the markup below the plugin, not the
record that renders it. What does change is described in
:ref:`breaking-replaced-profile-editing-plugin`; what an integrator has to look
at is the list of removed Fluid files, the removed action links and the new
page type of the JSON endpoints.

Affected Installations
======================

All installations using the :guilabel:`Profile editing` content element of
`EXT:academic_persons_edit`.

..  index:: Backend, Frontend, TypoScript, ext:academic_persons_edit, NotScanned
