..  index:: Configuration
..  _configuration-general:

=====================
General configuration
=====================

**Extension configuration**
There are some options for global extension configuration:

..  confval:: profile.autoCreateProfiles

    :type: boolean
    :Default: false

    If enabled, a new profile will be created when a frontend user without an
assigned profile and that meets the criteria logs in.

..  confval:: profile.createProfileForUserGroups

    :type: string
    :Default:

    A comma-separated list of frontend group IDs. When a user without an assigned profile
    logs in and is assigned to one of these groups, a new profile will be created.

..  confval:: profile.allowedLanguages

    :type: string
    :Default:

    A comma-separated list of language IDs. These IDs configure in which languages a
    persons profile can be translated by a frontend user.

    The synchronisation into these languages runs after a profile is auto-created
    on frontend user login, and after every change persisted through the frontend
    editing plugins — the profile form as well as the contract, address, email
    address, phone number and profile information forms. Left empty, frontend
    edits do not touch translated profile records at all.

..  _configuration-general-validations:

Which fields can be edited
==========================

Which profile fields the editing forms offer, which are mandatory and which are
locked is **not** configured in this extension. It comes from the validation
settings shipped by :guilabel:`academic_persons`, in
:file:`Configuration/AcademicPersons/Settings.yaml`, which the editing forms
read directly.

Consequences worth knowing before reporting a problem:

*   A field configured :yaml:`disabled` or :yaml:`readonly` is rendered locked,
    and a value submitted for it anyway is discarded rather than stored. This is
    deliberate and protects already stored data.
*   :guilabel:`First name`, :guilabel:`Middle name` and :guilabel:`Last name` are
    **locked by default**, because profile names are usually owned by the
    connected frontend user record and synchronised from elsewhere. They are
    therefore not editable in the frontend form — and, since the same
    configuration also drives the backend, not in the TYPO3 record editor
    either.
*   Because both editing contexts share one configuration, unlocking a field for
    the frontend form also unlocks it in the backend.

See `Validation settings
<https://docs.typo3.org/p/fgtclb/academic-persons/main/en-us/Configuration/Validations/Index.html>`__
in the :guilabel:`academic_persons` manual for the available flags, the shipped
defaults and how to override them.

..  _configuration-general-webp:

Image processing: WebP
======================

The profile detail view of the profile editing plugin offers the profile image
as `WebP`_ through the :html:`<picture>` candidates, with the :html:`<img>`
fallback in the source format. TYPO3 has to be allowed to produce WebP,
otherwise rendering a profile **that has an image** fails with:

..  code-block:: text

    Unable to render image uri in "tt_content:1": The extension webp is not
    specified in $GLOBALS['TYPO3_CONF_VARS']['GFX']['imagefile_ext'] as a valid
    image file extension and can not be processed.

On TYPO3 v13 and v14 `webp` is part of the default value of
:php:`$GLOBALS['TYPO3_CONF_VARS']['GFX']['imagefile_ext']`, so nothing has to be
done. An installation that **removes** it from that list - some restrict the
allowed formats deliberately - has to put it back, either in
:guilabel:`Admin Tools > Settings > Configure Installation-Wide Options >
[GFX][imagefile_ext]` or in :file:`config/system/settings.php`.

Permitting the format is not the same as being able to produce it: the
configured image processor, GraphicsMagick or ImageMagick, has to be built with
WebP support.

..  _WebP: https://developers.google.com/speed/webp
