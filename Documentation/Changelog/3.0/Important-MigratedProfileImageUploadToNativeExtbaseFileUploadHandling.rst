..  _important-migrated-profile-image-upload-to-native-extbase-file-upload-handling:

===================================================================
Important: Profile image upload uses native Extbase upload handling
===================================================================

Description
===========

The profile image upload of the `academicpersonsedit_profileediting` plugin was
handled by the custom type converter
:php:`FGTCLB\AcademicBase\Extbase\Property\TypeConverter\FileUploadConverter`
(`EXT:academic_base`). It has been replaced with the native Extbase file upload
handling introduced in TYPO3 v13.3 (:php:`FileUploadConfiguration`, see TYPO3
feature :issue:`103511`), and the replacement survives the editor rewrite of
the same release: the new editing view uploads the image through the same
configuration.

The TypoScript setting names are unchanged.
:typoscript:`settings.editForm.profileImage.targetFolder`,
:typoscript:`settings.editForm.profileImage.validation.maxFileSize` and
:typoscript:`settings.editForm.profileImage.validation.allowedMimeTypes` keep
their names and are mapped onto the core :php:`FileSizeValidator` and
:php:`MimeTypeValidator`. What a *blank* value means changed - see
:ref:`breaking-profile-image-upload-validation-always-applies`.

Impact
======

The upload behaves differently in four ways:

*   **Stored file names change.** The custom converter built the file name from
    the profile data as ``<firstname>-<lastname>-<uid>.<extension>`` and replaced
    an existing file of that name. The native handling keeps the name supplied by
    the client, appends a random suffix and renames on conflict instead. Stored
    names therefore no longer contain the name of the person, which is an
    improvement for a folder that is usually reachable over the web.

*   **The previous image is deleted on re-upload.** Because the generated name
    changed on every upload, replacing an image would leave the previous file
    behind. Uploading a new profile image now deletes the file the profile
    referenced before, unless it is still referenced by another record.

*   **The mime type is detected from the file content.** The custom converter
    trusted the media type sent by the browser, which can be spoofed. The core
    :php:`MimeTypeValidator` inspects the uploaded file itself and additionally
    cross-checks the file extension. An upload whose real content does not match
    an allowed mime type is now rejected, even if the browser announced an
    allowed one. Uploads that only passed because of a faked header stop working
    - this is intended.

*   **The file is only stored once the upload validates.** Previously the file
    was imported into FAL while mapping the request, so an upload that failed
    validation afterwards left an unreferenced file behind in the upload folder.
    The file is imported after successful validation now, which avoids those
    orphaned files.

Affected Installations
======================

Installations using the profile editing plugin
(`academicpersonsedit_profileediting`) with profile image uploads. Installations
that rely on the stored file name - for example when referencing those files by
a fixed path outside of FAL, or when addressing them by the person's name - need
to review that assumption.

Migration
=========

No configuration change is required for the upload itself; check the blank-value
semantics of the two validation settings in the breaking entry named above.
Files uploaded before this change keep their existing names and references, and
are deleted as soon as the corresponding profile image is replaced.

.. index:: Fluid, TypoScript, ext_localconf, NotScanned
