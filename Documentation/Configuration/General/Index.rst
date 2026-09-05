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

    The synchronisation into these languages runs after a profile is auto-created
    — on frontend user login or through the :bash:`academic:createprofiles`
    command of :guilabel:`EXT:academic_persons` — and after every change
    persisted through ProfileEditing. Left empty, frontend edits do not touch
    translated profile records at all.

..  _configuration-general-validations:

Which fields can be edited
==========================

Which profile fields belong to each visual section, how they are rendered,
which are mandatory and which are locked is configured by
:file:`EXT:academic_persons` in
:file:`Configuration/AcademicPersons/Settings.yaml`. The
single :yaml:`profile` map contains both the public layout and the editable
field definitions. Structured records use the :yaml:`documentSections` map
from the same file.

Consequences worth knowing before reporting a problem:

*   A field configured :yaml:`disabled` or :yaml:`readonly` is rendered locked.
    The ProfileEditing JSON endpoint rejects attempts to submit it.
*   :guilabel:`First name`, :guilabel:`Middle name` and :guilabel:`Last name` are
    **locked by default**, because profile names are usually owned by the
    connected frontend user record and synchronised from elsewhere. They are
    therefore not editable in the frontend form - and not in the TYPO3 record
    editor either: the same validation set is merged into the TCA of the
    profile table through :php:`TcaValidationMerger`, where :yaml:`disabled`
    becomes :php:`readOnly`. Unlocking a field for the frontend form unlocks it
    in the backend as well.
*   Document validators are selected by the section's stored record ``type``;
    validators from sibling sections are never merged as a fallback.
*   The normalized rules are applied to the frontend controls, server-side
    Extbase validation and the corresponding backend TCA field state.

See :ref:`configuration-editor-settings` for the schema, supported validator
flags, document aliases, shipped defaults and override rules. The same
:yaml:`profile` map also controls the public detail layout.

..  _configuration-general-webp:

Image processing: WebP
======================

The ProfileEditing image editor offers the profile image as `WebP`_ through the
:html:`<picture>` candidates, with the :html:`<img>` fallback in the source
format. TYPO3 has to be allowed to produce WebP, otherwise rendering a profile
**that has an image** fails with:

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
