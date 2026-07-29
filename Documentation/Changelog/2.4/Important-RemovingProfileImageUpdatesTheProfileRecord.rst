..  _important-removing-profile-image-updates-the-profile-record:

========================================================
Important: Removing a profile image updates the relation
========================================================

Description
===========

Removing the profile image in the `academicpersonsedit_profileediting` plugin
deleted the image file, but never wrote the profile record afterwards. TYPO3
removes the file index entry and its file references when a file is deleted, so
the reference itself disappeared — but the reference count stored in
`tx_academicpersons_domain_model_profile.image` kept its previous value.

`FGTCLB\\AcademicPersonsEdit\\Controller\\ProfileController::removeImageAction()`
now drops the relation through Extbase before touching the file, so the record
and its references stay consistent.

The same action deleted the file unconditionally. It now checks whether any other
record still references the file and keeps it in that case.

Impact
======

*   The profile record no longer reports a profile image after the image was
    removed. Code reading the column directly instead of resolving the relation
    — raw queries, exports, integrity checks — saw a profile image that did not
    exist. The frontend was not affected, because it resolves the relation.

*   A file that another record still references is no longer deleted. Removing a
    profile image previously deleted the file for every record using it, leaving
    those records pointing at a missing file.

Affected Installations
======================

Installations using the profile editing plugin
(`academicpersonsedit_profileediting`) whose editors remove profile images.

Migration
=========

No configuration change is required.

Profile records that were left with a stale image count keep it until the record
is written again — editing and saving the profile in the backend or through the
plugin recalculates the value. The stale count has no effect on rendering.

.. index:: Frontend, PHP-API, NotScanned
