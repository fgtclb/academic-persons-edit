..  _important-migrated-profile-image-upload-to-native-extbase-file-upload-handling:

===================================================================
Important: Profile image upload uses native Extbase upload handling
===================================================================

Description
===========

The profile image upload of the `academicpersonsedit_profileediting` plugin was
handled by the custom type converter
`FGTCLB\\AcademicBase\\Extbase\\Property\\TypeConverter\\FileUploadConverter`
(`EXT:academic_base`). It has been replaced with the native Extbase file upload
handling introduced in TYPO3 v13.3 (`FileUploadConfiguration`, see TYPO3 feature
:issue:`103511`).

The TypoScript configuration is unchanged.
`settings.editForm.profileImage.targetFolder`,
`settings.editForm.profileImage.validation.maxFileSize` and
`settings.editForm.profileImage.validation.allowedMimeTypes` keep their names and
meaning and are now mapped onto the core `FileSizeValidator` and
`MimeTypeValidator`. The form template, the `Profile` domain model and the plugin
itself are untouched, so no integration or template change is required.

Impact
======

The upload behaves differently in four ways:

*   **Stored file names change.** The custom converter built the file name from
    the profile data as `<firstname>-<lastname>-<uid>.<extension>` and replaced an
    existing file of that name. The native handling keeps the name supplied by the
    client, appends a random suffix and renames on conflict instead. Stored names
    therefore no longer contain the name of the person, which is an improvement
    for a folder that is usually reachable over the web.

*   **The previous image is deleted on re-upload.** Because the generated name
    changed on every upload, replacing an image would leave the previous file
    behind. Uploading a new profile image now deletes the file the profile
    referenced before, unless it is still referenced by another record.

*   **The mime type is detected from the file content.** The custom converter
    trusted the media type sent by the browser, which can be spoofed. The core
    `MimeTypeValidator` inspects the uploaded file itself and additionally
    cross-checks the file extension. An upload whose real content does not match
    an allowed mime type is now rejected, even if the browser announced an
    allowed one. Uploads that only passed because of a faked header stop working
    — this is intended.

*   **The file is only stored once the whole model validates.** Previously the
    file was imported into FAL while mapping the request, so an upload that failed
    validation afterwards left an unreferenced file behind in the upload folder.
    The file is now imported after successful validation, which avoids those
    orphaned files but requires the editor to select the file again when the form
    is redisplayed with validation errors.

An empty `allowedMimeTypes` setting continues to mean "no mime type
restriction".

Affected Installations
======================

Installations using the profile editing plugin
(`academicpersonsedit_profileediting`) with profile image uploads. Installations
that rely on the stored file name — for example when referencing those files by a
fixed path outside of FAL, or when addressing them by the person's name — need to
review that assumption.

Migration
=========

No configuration change is required. Files uploaded before this change keep their
existing names and references, and are deleted as soon as the corresponding
profile image is replaced.

.. index:: Fluid, TypoScript, ext_localconf, NotScanned
