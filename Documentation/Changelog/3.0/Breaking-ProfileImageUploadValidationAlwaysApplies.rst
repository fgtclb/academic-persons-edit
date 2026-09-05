..  _breaking-profile-image-upload-validation-always-applies:

========================================================
Breaking: Profile image upload validation always applies
========================================================

..  seealso::
    The `upgrade chapter of academic_persons
    <https://docs.typo3.org/p/fgtclb/academic-persons/main/en-us/Upgrade/Index.html>`__
    is the order in which the 3.0 changes have to be applied.

Description
===========

:typoscript:`plugin.tx_academicpersonsedit.settings.editForm.profileImage.validation.allowedMimeTypes`
and
:typoscript:`...validation.maxFileSize` no longer switch their validator off
when they are set to an empty value.

Until 2.x an empty :typoscript:`allowedMimeTypes` meant "no mime type
restriction": the setting was passed through as configured, and an empty list
added no validator. The same held for an empty :typoscript:`maxFileSize`.

From 3.0.0 both settings fall back to the shipped default when they are missing,
blank or not a string, and both validators are always added:

..  list-table::
    :header-rows: 1

    *   -   Setting
        -   Default used for a blank value
    *   -   ``validation.allowedMimeTypes``
        -   ``image/jpeg,image/png,image/webp``
    *   -   ``validation.maxFileSize``
        -   ``2M``

The defaults are the same list the ``accept`` attribute of the file input
advertises, so what the browser offers and what the server accepts cannot drift
apart.

Impact
======

An installation that deliberately blanked one of the two settings to accept any
image, or any size, now has uploads rejected that were accepted before: a TIFF,
an SVG or a file above 2 MB reaches the editor as a validation error instead of
being stored.

Silently accepting whatever a browser sends is not a defensible default for an
upload a frontend user performs, which is why the semantics were changed rather
than kept.

Affected Installations
======================

Installations using the profile editing plugin
(`academicpersonsedit_profileediting`) that set either setting to an empty
value. An installation that never touched the two settings is unaffected -
the previous shipped values were the same defaults.

Migration
=========

Set the mime types you want to accept explicitly, for example:

..  code-block:: typoscript

    plugin.tx_academicpersonsedit.settings.editForm.profileImage.validation {
        allowedMimeTypes = image/jpeg,image/png,image/webp,image/gif
        maxFileSize = 8M
    }

There is no way to switch either validator off. A list wide enough for the
formats a site accepts is the replacement for the blank value.

.. index:: TypoScript, Frontend, NotScanned, ext:academic_persons_edit
