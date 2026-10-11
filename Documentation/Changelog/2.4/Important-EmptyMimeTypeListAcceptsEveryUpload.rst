.. _important-ace-267-academic-persons-edit:

=======================================================
Important: An empty mime type list accepts every upload
=======================================================

Description
===========

The profile image upload of the frontend profile editing checks the media type
of the uploaded file against a comma separated list:

..  code-block:: typoscript
    :caption: TypoScript setup

    plugin.tx_academicpersonsedit.settings.editForm.profileImage.validation.allowedMimeTypes = image/jpeg,image/png,image/webp

An empty value, or a setup that removed the setting, was read as a list holding
one empty entry, which no file matches. Every upload was rejected with the
message *You entered an incorrect media type, "image/png" is not allowed for
this file*, whatever the file was. An empty list now means that the media type
is not restricted.

Impact
======

With the shipped value nothing changes.

A site that set the value to an empty string, or removed it, accepts a profile
image of any media type now, where it rejected every image before. Files with a
PHP file extension are still rejected, as before. The size limit
:typoscript:`plugin.tx_academicpersonsedit.settings.editForm.profileImage.validation.maxFileSize`
is not affected.

Affected Installations
======================

Installations that set
:typoscript:`plugin.tx_academicpersonsedit.settings.editForm.profileImage.validation.allowedMimeTypes`
to an empty value or removed it. To keep restricting the profile image, list the
accepted media types instead.

.. index:: Frontend, TypoScript, ext:academic_persons_edit
