..  _feature-replace-profile-image:

========================================================
Feature: Replace a profile image in the frontend editing
========================================================

Description
===========

The profile detail view of the frontend editing plugin of
`EXT:academic_persons_edit` now offers a "Replace" action for a profile
that already has an image, next to the existing "Delete" action.

Replacing an image previously meant deleting it first and uploading a
new one afterwards. Between those two steps the profile had no image at
all, and the old file was already gone before the replacement was known
to be valid — an upload rejected by the file size or mime type
validation left the profile without an image.

The replacement itself is not new. The migration to the native Extbase
file upload handling
(:ref:`important-migrated-profile-image-upload-to-native-extbase-file-upload-handling`)
already implemented it completely: the upload configuration permits the
already referenced file plus the upload, the previously referenced file
is determined before the upload rewires the relation, and it is deleted
afterwards — but only when no other record still references it. None of
that could be reached from the shipped templates, because the link to
the upload form was rendered only while the profile had no image.

The form the "Replace" action leads to now also renders the image that
is about to be replaced.

..  note::

    This feature changes the frontend editing Fluid templates and is
    therefore breaking for projects that override them. See
    :ref:`breaking-adapted-frontend-editing-fluid-files` for the
    required adaptions.

Impact
======

Profile owners using the `EXT:academic_persons_edit` frontend editing
can exchange a profile image in a single step. The profile keeps its
current image until the replacement passed validation, and the replaced
file is removed from the storage unless another record still uses it.

Affected Installations
======================

All installations using the `EXT:academic_persons_edit` extension
starting with version 3.0.

Migration
=========

No migration is required for installations using the shipped templates.

Installations overriding
:file:`Resources/Private/Templates/Profile/Show.html` have to re-apply
their override on top of the updated template to offer the action, see
:ref:`breaking-adapted-frontend-editing-fluid-files`.

.. index:: Fluid, Frontend, Template
