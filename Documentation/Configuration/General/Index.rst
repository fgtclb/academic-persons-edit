..  index:: Configuration
..  _configuration-general:

=====================
General configuration
=====================

**Extension configuration**
There are some options for global extension configuration:

..  confval:: profile.allowedLanguages

    :type: string
    :Default:

    A comma-separated list of language IDs. These IDs configure in which languages a
    persons profile can be translated by a frontend user.

..  _configuration-general-deprecated-auto-create:

Deprecated: automatic profile creation
======================================

The extension configuration still lists ``profile.autoCreateProfiles`` and
``profile.createProfileForUserGroups``. **Both have no effect.** The profile
creation moved to :guilabel:`academic_persons` in version 2.1, together with
these two options, so that it works without this extension. Set them in the
extension configuration of :guilabel:`academic_persons` instead, where its manual
describes them under :guilabel:`Configuration > General configuration`.

The upgrade wizard :guilabel:`Migrate profile auto create options from
"EXT:academic_persons_edit" to "EXT:academic_persons"` copies a value set here
over to :guilabel:`academic_persons`, as long as the option there still has its
default.

..  _configuration-general-validations:

Which fields can be edited
==========================

Which profile fields the editing forms offer, which are mandatory and which are
locked is **not** configured in this extension. It comes from the validation
settings shipped by :guilabel:`academic_persons`, in
:file:`Configuration/AcademicPersons/Settings.yaml`, which the editing forms
read directly.

Consequences worth knowing before reporting a problem:

*   A field configured :yaml:`disabled`, :yaml:`readonly` or
    :yaml:`frontendreadonly` is rendered locked, and a value submitted for it
    anyway is discarded rather than stored. This is deliberate and protects
    already stored data. A select or checkbox stays operable in the browser,
    which ignores :html:`readonly` on those controls, but a changed value is
    discarded the same way.
*   :guilabel:`First name`, :guilabel:`Middle name` and :guilabel:`Last name` are
    **locked by default**, because profile names are usually owned by the
    connected frontend user record and synchronised from elsewhere. They are
    therefore not editable in the frontend form — and, since the same
    configuration also drives the backend, not in the TYPO3 record editor
    either.
*   Because both editing contexts share one configuration, unlocking a field for
    the frontend form also unlocks it in the backend. :yaml:`frontendreadonly`
    locks a field for the frontend form only and keeps it editable in the
    record editor.

See `Validation settings
<https://docs.typo3.org/p/fgtclb/academic-persons/main/en-us/Configuration/Validations/Index.html>`__
in the :guilabel:`academic_persons` manual for the available flags, the shipped
defaults and how to override them.

..  _configuration-general-webp:

Image processing: WebP where allowed
====================================

The profile detail view of the profile editing plugin renders the profile image
as `WebP`_ when the installation allows TYPO3 to produce that format, that is
when `webp` is listed in
:php:`$GLOBALS['TYPO3_CONF_VARS']['GFX']['imagefile_ext']`. Otherwise it renders
the image in its own format, or as PNG for a format a browser cannot show, which
is what TYPO3 does for any processed image without a file extension given.

On **TYPO3 v13** `webp` is part of the default value of that list.

On **TYPO3 v12** it is not, so the image is rendered without WebP unless
the installation adds it, either in :guilabel:`Admin Tools > Settings >
Configure Installation-Wide Options > [GFX][imagefile_ext]` or in
:file:`config/system/settings.php`:

..  code-block:: php
    :caption: config/system/settings.php

    return [
        'GFX' => [
            'imagefile_ext' => 'gif,jpg,jpeg,tif,tiff,bmp,pcx,tga,png,pdf,ai,svg,webp',
        ],
    ];

Adding the extension to that list only permits the format. Whether the images
can actually be produced depends on the configured image processor - GraphicsMagick
or ImageMagick have to be built with WebP support.

..  note::

    Before version 2.4.0 the partial requested WebP unconditionally, and a
    profile with an image failed to render where the list lacked `webp`. A
    partial overridden from that version still does so, see
    :ref:`important-ace-848-academic-persons-edit`.

..  _WebP: https://developers.google.com/speed/webp
