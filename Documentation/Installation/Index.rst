..  _installation:

============
Installation
============

The extension has to be installed like any other TYPO3 CMS extension. You can
download and install it using one of the following methods.

Requirements
============

Version 3 requires TYPO3 13.4 or TYPO3 14.3, and PHP 8.2 or newer.

Updating an existing installation from 2.4 is a sequence of schema update,
upgrade wizards, settings migration and template work, and the order of it
matters - two steps destroy data when they are taken too early. It is described
once, for both extensions, in `Upgrading from 2.4 to 3.0.0
<https://docs.typo3.org/p/fgtclb/academic-persons/main/en-us/Upgrade/Index.html>`__
of :guilabel:`academic_persons`.

Two dependencies are new in this version:

*   ``typo3/cms-rte-ckeditor``, a system extension. The profile editing frontend
    loads CKEditor 5 from it for its rich text fields: the rich text module
    imports six bundles, and :file:`Configuration/JavaScriptModules.php` maps
    nineteen - those six plus the thirteen they import from each other. It has
    to be installed and active for them to resolve. The extension does not use
    the backend rich text configuration in any way - only the shipped
    JavaScript. Composer resolves it; in a classic installation activate it in
    the Extension Manager.
*   ``typo3/html-sanitizer``, a Composer library the TYPO3 core already ships
    with - not an extension, so there is nothing to activate. Every rich text
    value is sanitized against an allow list on the server before it is stored,
    and the extension extends the builder of that package to declare it.

..  tabs::

    ..  group-tab:: Composer

        ..  code-block:: bash
            :caption: Install the stable release

            composer require 'fgtclb/academic-persons-edit':'^3'

        ..  tip::

            We recommend to pin academic extensions on minor level to mitigate
            possible issues in projects in case `composer update` is used based
            on the fact that projects commonly tends to override fluid templates
            and changes for otherwise non-breaking changes are possible promoted
            to be breaking in case template changes are not adopted why it has
            been considered to mark template changes as breaking changes on
            minor version updates. That means, we suggest to use for example
            following command to ensure that we stay in the minor version range
            but have the hightest patchlevel enforced for it and keep possible
            bugfix releases for that minor version possible to install:

            ..  code-block:: bash

                composer require 'fgtclb/academic-persons-edit':'~3.0.0@dev'

    ..  group-tab:: Extension Manager

        #.  Switch to the module :guilabel:`Admin Tools > Extensions`.
        #.  Switch to :guilabel:`Get Extensions`.
        #.  Search for the extension key :guilabel:`academic_persons_edit`.
        #.  Import the extension from the repository.

    ..  group-tab:: Upload ZIP (TER)

        #.  Get the current version from `TER`_ by downloading the ZIP version.
            Alternatively, get the ZIP from the `GitHub Releases`_ page.
        #.  Switch to the module :guilabel:`Admin Tools > Extensions`.
        #.  Enable :guilabel:`Upload Extension`.
        #.  Select or drag the extension ZIP archive and upload the file.

..  _TER: https://extensions.typo3.org/extension/academic_persons_edit
..  _GitHub Releases: https://github.com/fgtclb/academic-persons-edit/releases
