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
    command of :guilabel:`EXT:academic_persons` — after it is updated by
    :bash:`academic:updateprofiles`, after every change persisted through
    ProfileEditing, and after a backend save or a DataHandler based import of
    the default-language profile. Left empty, none of them touches translated
    profile records at all.

    The profile slug is regenerated from the name after each of these, made
    unique in the profile's folder, except after a backend save: there the
    editor keeps the slug of the backend form. An import counts as a backend
    save unless it marks its DataHandler run as an import. A slug the name
    still yields is kept - the plain one even when another profile of the
    folder shares it, a suffixed one while it is unique.

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

*   A field configured :yaml:`disabled`, :yaml:`readonly` or
    :yaml:`frontendreadonly` is rendered locked. A value submitted for it is
    ignored and keeps the stored one, while the other fields of the same save
    are stored. The editor sends every field of an open contract or contact
    when it saves, the locked ones included.
*   A field the synchronisation owns on a record is locked on that record
    only, see :ref:`configuration-general-managed-fields`.
*   :guilabel:`First name`, :guilabel:`Middle name` and :guilabel:`Last name` are
    **locked by default**, because profile names are usually owned by the
    connected frontend user record and synchronised from elsewhere. They are
    therefore not editable in the frontend form - and not in the TYPO3 record
    editor either: the same validation set is merged into the TCA of the
    profile table through :php:`TcaValidationMerger`, where :yaml:`disabled`
    becomes :php:`readOnly`. Unlocking a field for the frontend form unlocks it
    in the backend as well. :yaml:`frontendreadonly` locks a field for the
    frontend form only and keeps it editable in the record editor.
*   Document validators are selected by the section's stored record ``type``;
    validators from sibling sections are never merged as a fallback.
*   The normalized rules are applied to the frontend controls, server-side
    Extbase validation and the corresponding backend TCA field state.

See :ref:`configuration-editor-settings` for the schema, supported validator
flags, document aliases, shipped defaults and override rules. The same
:yaml:`profile` map also controls the public detail layout.

..  _configuration-general-managed-fields:

Fields the synchronisation owns
===============================

The :yaml:`managedFields` map of :file:`EXT:academic_persons` names the fields a
synchronisation or an import owns, per record type (see the
`Managed fields <https://docs.typo3.org/p/fgtclb/academic-persons/main/en-us/Configuration/ManagedFields/Index.html>`__
page of that extension). The editor applies it to the records the
synchronisation wrote: a profile, contract or contact with an import
identifier, of the default language, whose profile is not excluded with
:guilabel:`Skip synchronisation`.

*   A managed field is shown read-only with the marker
    :guilabel:`Synchronised`, on the profile page and in the contract and
    contact forms. A select or a checkbox is shown disabled. A value submitted
    for it is ignored.
*   A contract or contact with a managed field offers no delete, and no edit
    once every field the owner could edit is managed. The endpoints refuse
    both with the status 403.
*   Hiding, showing and sorting such a row stay available. The
    synchronisation never changes them.
*   In a translated site language a managed field whose value all languages
    share stays locked, a translated one is editable, as in the backend, and
    a synchronised row still offers no delete.
*   Records the owner added, and every record of an excluded profile, keep
    every action the configuration allows. An installation that names no
    managed field sees no difference.

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
