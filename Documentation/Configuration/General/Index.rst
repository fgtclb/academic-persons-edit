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

..  _configuration-general-webp:

Image processing: WebP is required
==================================

The profile detail view of the profile editing plugin renders the profile image
as `WebP`_ only. TYPO3 has to be allowed to produce that format, otherwise
rendering a profile **that has an image** fails with:

..  code-block:: text

    Unable to render image uri in "tt_content:1": The extension webp is not
    specified in $GLOBALS['TYPO3_CONF_VARS']['GFX']['imagefile_ext'] as a valid
    image file extension and can not be processed.

On **TYPO3 v13** `webp` is part of the default value of
:php:`$GLOBALS['TYPO3_CONF_VARS']['GFX']['imagefile_ext']` and nothing has to be
done.

On **TYPO3 v12** it is not. An instance running v12 has to add it, either in
:guilabel:`Admin Tools > Settings > Configure Installation-Wide Options >
[GFX][imagefile_ext]` or in :file:`config/system/settings.php`:

..  code-block:: php
    :caption: config/system/settings.php

    return [
        'GFX' => [
            'imagefile_ext' => 'gif,jpg,jpeg,tif,tiff,bmp,pcx,tga,png,pdf,ai,svg,webp',
        ],
    ];

..  note::

    Many installations already carry `webp` because another extension or the
    project setup added it, which is why this is not hit everywhere on v12.

Adding the extension to that list only permits the format. Whether the images
can actually be produced depends on the configured image processor - GraphicsMagick
or ImageMagick have to be built with WebP support.

..  _WebP: https://developers.google.com/speed/webp
