..  _upgrade-from-2-3-to-3-0:

=========================
Upgrading from 2.3 to 3.0
=========================

This page takes an installation of :guilabel:`academic_persons_edit` 2.3
straight to 3.0, without the step to 2.4. Version 3.0 replaces the profile
editing view in place. The content element keeps its type, but every Fluid file
of the old form flow is gone, and the editor writes through a JSON page type
that the site has to deliver. The page leaves out the 2.4 states that 3.0
replaces: the 2.4 adaptations of the Fluid files of the old form flow, see
:ref:`breaking-adapted-frontend-editing-fluid-files` and
:ref:`breaking-1782285579`, the moved editor configuration
:file:`JavaScript/frontend/ckeditor.js`, see
:ref:`breaking-ckeditor-configuration-moved`, the 2.4 constant
:typoscript:`renderContentElementHeader`, see :ref:`important-1791825026`, and
the 2.4 meaning of an empty list of accepted media types, see
:ref:`important-ace-267-academic-persons-edit`. Work through the sections in
order. Skip a step that does not apply to the installation.

..  _upgrade-from-2-3-to-3-0-before-you-start:

Before you start
================

Version 3.0 runs on TYPO3 13.4 from 13.4.35 on and on TYPO3 14.3 from 14.3.7
on, with PHP 8.2 or later.

:guilabel:`academic_persons_edit` 3.0 declares two new dependencies,
:composer:`typo3/cms-rte-ckeditor` and :composer:`typo3/html-sanitizer`, see
:ref:`important-new-ckeditor-and-sanitizer-dependency`. Neither adds a
package to an installation: :guilabel:`academic_persons` requires
:guilabel:`rte_ckeditor` already, and the TYPO3 core requires
:composer:`typo3/html-sanitizer`. The extension also needs these academic
packages. Upgrade them first or in the same deployment, in this order:

*   :composer:`fgtclb/environment-state-manager` 2.x, new since 2.4, which
    :guilabel:`academic_base` 3.0 and :guilabel:`academic_persons` 3.0
    require. Composer installs it on its own. A classic installation installs
    :guilabel:`environment_state_manager` 2.x before it activates either of
    them.
*   :composer:`fgtclb/academic-base` 3.0, the same release as this extension.
*   :composer:`fgtclb/academic-persons` 3.0, the same release as this
    extension. Its upgrade chapter has steps of its own, and its database
    update, its upgrade wizards and its settings migration are due in the same
    deployment.

:guilabel:`academic_persons_sync` 3.0 requires this extension and follows it in
the same deployment, where installed.

These changes have to ship in the same deployment as the update:

*   A site without a site set includes the page TSconfig of the component, or
    the content element is no longer offered, see
    :ref:`upgrade-from-2-3-to-3-0-page-tsconfig`.
*   A site package that copied the TypoScript of this extension adds the page
    type the editor writes through, or the editor cannot save, see
    :ref:`upgrade-from-2-3-to-3-0-page-type`.
*   Project code that extends or links to a removed class or action of the old
    form flow goes, see :ref:`upgrade-from-2-3-to-3-0-replaced` and
    :ref:`upgrade-from-2-3-to-3-0-code`.

The module paths on this page are those of TYPO3 13, where the upgrade runs.

..  _upgrade-from-2-3-to-3-0-typo3:

Update TYPO3 first
==================

Update TYPO3 to 13.4.35 or later while the site still runs 2.3, which supports
TYPO3 13.4. Version 3.0 no longer installs on TYPO3 12, and Composer and the
Extension Manager refuse it next to an older patch release of TYPO3 13.

Stay on TYPO3 13 until the upgrade is complete. The content element wizards
``academicPersonsEdit_pluginContent`` and
``academicPersonsEdit_removeProfileSwitcherContent`` of this extension read
the column :sql:`tt_content.list_type`, which TYPO3 14 removes, so they only
run on TYPO3 13. The same holds for the content element wizard of
:guilabel:`academic_persons`, which this extension requires, and for those of
:guilabel:`academic_jobs` and :guilabel:`academic_projects` when the
installation also has them. Move to TYPO3 14.3.7 or later once they have run.

See :ref:`breaking-removed-typo3-v12-support` and
:ref:`important-minimum-typo3-versions`.

..  _upgrade-from-2-3-to-3-0-package:

Update the package
==================

..  code-block:: bash

    composer require -W \
        'fgtclb/academic-base':'~3.0.0' \
        'fgtclb/academic-persons':'~3.0.0' \
        'fgtclb/academic-persons-edit':'~3.0.0'

On a classic installation, install :guilabel:`environment_state_manager` 2.x
from the TER in :guilabel:`Admin Tools > Extensions` first, then update
:guilabel:`academic_base`, :guilabel:`academic_persons` and
:guilabel:`academic_persons_edit` there, in this order. Keep
:guilabel:`rte_ckeditor` active, the rich text fields of the editor load
CKEditor 5 from it. Run the commands of this page with
:bash:`typo3/sysext/core/bin/typo3` instead of :bash:`vendor/bin/typo3`.

..  code-block:: bash

    vendor/bin/typo3 cache:flush

..  _upgrade-from-2-3-to-3-0-database:

Update the database
===================

..  code-block:: bash

    vendor/bin/typo3 extension:setup

Or apply the changes in :guilabel:`Admin Tools > Maintenance > Analyze
Database Structure`.

Versions 2.4 and 3.0 of this extension change no table. Run the update all the
same, it applies the changes of the index definitions TYPO3 13.4.35 and 14.3.7
bring, and :guilabel:`academic_persons` 2.4 and 3.0 change its tables, which the
upgrade wizards of the next step require. Its upgrade chapter names what has to
be checked before.

..  _upgrade-from-2-3-to-3-0-wizards:

Run the upgrade wizards
=======================

Run the wizards in this order, after the database update, and on TYPO3 13:

#.  ``academicPersonsEdit_pluginContent``, :guilabel:`Migrate plugin list
    element from academic_persons_edit to normal content elements`, only if it
    is still offered.
#.  ``academicPersonsEdit_removeProfileSwitcherContent``, :guilabel:`Remove
    content elements of the removed academic_persons_edit profile switcher
    plugin`.
#.  ``academicPersonsEdit_repairLocalizedProfileImages``, :guilabel:`Repair
    the profile image relations of academic profiles`.

..  code-block:: bash

    vendor/bin/typo3 upgrade:run academicPersonsEdit_pluginContent
    vendor/bin/typo3 upgrade:run academicPersonsEdit_removeProfileSwitcherContent
    vendor/bin/typo3 upgrade:run academicPersonsEdit_repairLocalizedProfileImages

A wizard that TYPO3 recorded as done is offered again once it is marked
undone. TYPO3 13 marks a wizard undone with
``vendor/bin/typo3 upgrade:mark:undone <identifier>``.
The repair wizard is repeatable and never recorded as done.

..  _upgrade-from-2-3-to-3-0-ctype:

The content element wizard
--------------------------

``academicPersonsEdit_pluginContent`` shipped with 2.x already and moves a
profile editing content element stored as ``list`` with a :sql:`list_type`
onto its own :sql:`CType`. An installation that ran it there has nothing more
to do. TYPO3 14 no longer has :sql:`tt_content.list_type`, so the wizard has to
run on TYPO3 13, before the core update. Replace own TypoScript, TSconfig or
SQL that matches on :sql:`list_type` with a condition on :sql:`CType`. See
:ref:`breaking-plugins-require-ctype-on-typo3-v14`.

..  _upgrade-from-2-3-to-3-0-profile-switcher:

The profile switcher
--------------------

The wizard :guilabel:`Remove content elements of the removed
academic_persons_edit profile switcher plugin`,
``academicPersonsEdit_removeProfileSwitcherContent``, is new in 2.4. The profile
switcher has rendered nothing since version 2.1, and its content type is
removed. The wizard sets every content element of it to deleted, with the
:sql:`CType` `academicpersonsedit_profileswitcher` and as a legacy plugin with
the :sql:`list_type` of that name. The records stay restorable from the
recycler. An installation that never used the profile switcher gets nothing to
do from it. See :ref:`breaking-1785675428`, which also has the queries to look
at the affected records first.

..  _upgrade-from-2-3-to-3-0-profile-images:

The profile image relations
---------------------------

:guilabel:`academic_persons` 3.0 makes the profile image translatable, and a
translation can carry an image of its own. The relations the image upload and
the translation synchronisation of 2.x wrote do not always fit that. The
wizard :guilabel:`Repair the profile image relations of academic profiles`,
``academicPersonsEdit_repairLocalizedProfileImages``, is new in 3.0. It reduces
duplicate references of a profile, corrects the relation counters, and sets
the ``custom`` localization state on a translation that carries an image of its
own, so that the next synchronisation keeps it. See
:ref:`important-multilingual-profile-editing-images`.

The wizard writes through the DataHandler and deletes no file. It skips
workspace versions and acts in the live workspace, so publish a workspace with
person records and run the wizard again afterwards. Run it before editors use
the new editor of a multilingual site. Prefer the command line over the
upgrade module for it.

..  _upgrade-from-2-3-to-3-0-configuration:

Adapt the configuration
=======================

..  _upgrade-from-2-3-to-3-0-upgrade-check:

The upgrade check
-----------------

The console command :bash:`academic:upgrade:check` of :guilabel:`academic_base`
reads the stored configuration of the installation and reports what no longer
reaches the academic extensions. Run it on the staging copy right after the
package update, and work through what it reports together with the next
steps:

..  code-block:: bash

    vendor/bin/typo3 academic:upgrade:check

With the extension key and a site it also reports every template override of
the site whose file this extension no longer ships, which is every override of
the old form flow:

..  code-block:: bash

    vendor/bin/typo3 academic:upgrade:check academic_persons_edit --site=<site identifier>

Each finding, and what to do about it, is described in the :ref:`upgrade check
<fgtclb/academic-base:upgrade-check>`
of :guilabel:`academic_base`.

..  _upgrade-from-2-3-to-3-0-page-tsconfig:

Make the content element selectable
-----------------------------------

The site set and the static template are cut per component since 2.4, and the
:guilabel:`Edit Person’s Profiles` content element is hidden for the whole
installation until the page TSconfig of its component brings it back. See
:ref:`breaking-site-sets-and-static-templates-restructured` for the complete
change.

*   **Without a site set:** add
    :guilabel:`Academic Persons Edit: All components (academic_persons_edit)`
    in the page record of every site root that offers profile editing, tab
    :guilabel:`Resources`, field :guilabel:`Include static Page TSconfig (from
    extensions)`, see :ref:`static-pagetsconfig`. This entry did not exist in
    2.3. Without it the content element is no longer offered in the backend.
*   **With a site set:** nothing to do. The set
    `fgtclb/academic-persons-edit` keeps its name and delivers the page
    TSconfig.

Use one mechanism per site, never a site set and a static template together,
see :ref:`one-mechanism-per-site`.

Until a component is included, an existing content element of it keeps
rendering and keeps its type: :guilabel:`academic_base` shows the stored type
in the field :guilabel:`Type` followed by
:guilabel:`(not enabled on this page)`, and saving keeps it. The warning of the
2.4 changelog entry that saving such a record rewrites its type describes the
state before a fix that :guilabel:`academic_base` 2.4 carries as well, see
:ref:`its changelog entry <fgtclb/academic-base:important-ace-666-academic-base>`.
A content element that a page TSconfig of your own already turned into another
type is not repaired. Correct it by hand from its record history.

..  _upgrade-from-2-3-to-3-0-static-templates:

Static templates and imports
----------------------------

The static template of version 2.3, stored as
`EXT:academic_persons_edit/Configuration/TypoScript`, keeps delivering. It is
offered as :guilabel:`Academic Persons Edit: Path up to 2.3 (deprecated, use
All components)` now and will be removed in version 4.0. Replace it in the
field :guilabel:`Include TypoScript sets` on the tab :guilabel:`Advanced
Options` of the :sql:`sys_template` record with :guilabel:`Academic Persons
Edit: All components (academic_persons_edit)`, or depend on the set
`fgtclb/academic-persons-edit` instead. See
:ref:`deprecation-legacy-static-template-path`.

The same holds for an :typoscript:`@import` of
:file:`Configuration/TypoScript/constants.typoscript` or
:file:`Configuration/TypoScript/setup.typoscript` in a site package: it keeps
working and is deprecated. Import the files of
:file:`Configuration/TypoScript/ProfileEditing/` instead.
:file:`Configuration/TSconfig/page.tsconfig` and the files below
:file:`Configuration/Sets/AcademicPersonsEdit/` were removed. Their content is
in :file:`Configuration/TSconfig/ProfileEditing/page.tsconfig` and in the set
folders :file:`Configuration/Sets/Full/` and
:file:`Configuration/Sets/ProfileEditing/`. An :typoscript:`@import` of a
missing file fails silently and shows up as missing configuration.

..  _upgrade-from-2-3-to-3-0-page-type:

The page type of the editor
---------------------------

The editor writes through a :typoscript:`PAGE` object with
:typoscript:`typeNum = 1733735`, named
:typoscript:`academicPersonsProfileEditingAjax`. The site set
`fgtclb/academic-persons-edit-profile-editing`, the sets that depend on it and
the static templates of this extension deliver it. There was no such page type
in 2.3.

*   A site package that copied the TypoScript of this extension instead of
    including the set or a static template has no such object. Include the
    delivered TypoScript, or add the object to the copy. The editor reports a
    missing page type above itself and in the TYPO3 log, it cannot repair it.
*   A ``PageType`` route enhancer maps :typoscript:`typeNum` ``1733735``.
*   A web application firewall or a reverse proxy lets the page type and the
    ``X-Requested-With`` header through.

See :ref:`profile-editing-page-type` and
:ref:`important-profile-editing-replaced-in-place`.

..  _upgrade-from-2-3-to-3-0-header:

The header of the content element
---------------------------------

The layout of the plugin rendered the header and the subheader of the content
element in 2.3, twice where the content element layout of the site renders them
as well. The editor of 3.0 renders no header of its content element. A site
whose content element layout renders the header has it once now. A site whose
layout renders no header loses it, and renders it through a content element
layout of the site, as the entry shows. See
:ref:`breaking-profile-editing-renders-no-content-element-header`.

The constant :typoscript:`plugin.tx_academicpersonsedit.view.layoutRootPath`
is removed, the editor renders without a Fluid layout.

..  _upgrade-from-2-3-to-3-0-profile-image-upload:

The profile image upload
------------------------

An empty value of
:typoscript:`plugin.tx_academicpersonsedit.settings.editForm.profileImage.validation.allowedMimeTypes`
or :typoscript:`...validation.maxFileSize` falls back to
``image/jpeg,image/png,image/webp`` and ``2M`` now. In 2.3 an empty list of
media types rejected every upload. A site that wants to accept other types or
larger files lists them explicitly. See
:ref:`breaking-profile-image-upload-validation-always-applies`.

..  _upgrade-from-2-3-to-3-0-settings:

The settings of the editor
--------------------------

The editor reads its fields from
:file:`Configuration/AcademicPersons/Settings.yaml` of
:guilabel:`academic_persons`, which version 3.0 builds from sections. The
contract form renders the fields of ``contracts.fields``, and the address,
email address and phone number forms those of
``contracts.contactSections.<section>.fields``, in the order of the file. A
field the file does not declare is neither rendered nor written. A site
package with its own settings file declares the contract fields and the
contact sections it wants, after the migration the upgrade chapter of
:guilabel:`academic_persons` describes. See
:ref:`breaking-contract-configuration-and-accessible-profile-editing` and
:ref:`configuration-editor-settings`.

..  _upgrade-from-2-3-to-3-0-extension-configuration:

Extension configuration
-----------------------

``profile.autoCreateProfiles`` and ``profile.createProfileForUserGroups`` are
removed from the extension configuration of this extension. They have had no
effect since 2.1, where :guilabel:`academic_persons` took them over. Run the
wizard ``academicPersons_MigrateProfileAutoCreateExtensionsConfiguration`` of
:guilabel:`academic_persons` if it is still offered, it copies the values over,
and drop the two options from the deployment configuration afterwards.

..  _upgrade-from-2-3-to-3-0-labels:

Labels
------

*   A label override under
    :typoscript:`plugin.tx_academic_persons_edit._LOCAL_LANG`, or for the
    options of the select fields under
    :typoscript:`plugin.tx_persons_edit._LOCAL_LANG`, no longer has an effect.
    Move it to :typoscript:`plugin.tx_academicpersonsedit._LOCAL_LANG`, or to
    the path of the one plugin it is meant for. See
    :ref:`important-label-overrides-use-the-documented-path` and
    :ref:`configuration-labels`.
*   The labels of the old form flow are removed, 134 units of the
    :file:`locallang.xlf` of 2.3, and the editor brings its own under the prefix
    ``profileEditing.*``. An override or a translation of a removed unit has
    no effect any more. Compare the overrides with the shipped file.
*   The e-mail address field reads the unit :xml:`emailAddress.email.label`.
    An override keyed on :xml:`emailAddress.emailAddress.label` uses the new
    id. See :ref:`important-email-address-field-label`.
*   The help texts and the section headings of the editor read their
    overrides from :typoscript:`plugin.tx_academicpersonsedit._LOCAL_LANG`.
    See :ref:`important-editor-label-overrides-read-the-editor-paths`.
*   The backend labels of the relation between frontend users and profiles
    moved into :guilabel:`academic_persons`. An override of them through
    :php:`$GLOBALS['TYPO3_CONF_VARS']['SYS']['locallangXMLOverride']` targets
    the file of that extension now, as its upgrade chapter describes.

..  _upgrade-from-2-3-to-3-0-templates:

Adapt templates and assets
==========================

..  _upgrade-from-2-3-to-3-0-replaced:

The replaced view
-----------------

Every Fluid file of the form flow of 2.3 is removed: the layout
:file:`Layouts/ProfileEdit.html`, the templates of the profile, the contract,
the email address, the phone number, the address and the profile information
below :file:`Templates/`, and the partials below
:file:`Partials/Profile/Buttons/`, :file:`Partials/Profile/Forms/`,
:file:`Partials/Profile/List/`, :file:`Partials/Profile/Properties/` and
:file:`Partials/Profile/Show/`. Only :file:`Templates/Profile/List.html` stays,
rewritten. The new view is :file:`Templates/Profile/Index.html` with its
partials below :file:`Partials/Profile/`. See
:ref:`breaking-replaced-profile-editing-plugin`.

*   An override of a removed file is no longer rendered. Delete it, and
    re-apply what the project still wants on top of the new files.
    :ref:`templates-override` names the files that carry the hooks the script
    of the editor binds to.
*   A link to a removed controller or action answers with an error page. Link
    to the action ``index`` with the argument ``profileUid``, or to the list
    of profiles.
*   The editor configuration :file:`Resources/Public/JavaScript/CKEditor.js`
    of 2.3 is removed. The editor loads CKEditor 5 from
    :guilabel:`rte_ckeditor` itself.

The upgrade check with the extension key and a site reports every override of
a removed file, see :ref:`upgrade-from-2-3-to-3-0-upgrade-check`.

..  _upgrade-from-2-3-to-3-0-classes:

Classes and styles
------------------

The templates carry the speaking ``ace-*`` classes the academic extensions
share. Only the grid classes, the button classes and ``visually-hidden`` of
Bootstrap stay. The scripts show and hide markup through the attribute
:html:`hidden`, not the class ``d-none``. An override keeps the ``data-pe-*``
attributes and the ``ace-message`` of a field. See
:ref:`breaking-persons-edit-speaking-frontend-classes` and the chapter
:ref:`Styling <styling>`.

The extension ships no stylesheet, as in 2.3. Only the development versions of
3.0 shipped one for the editor. Some parts of the new editor do not work
without rules of the site: the image cropper, the elements hidden with
:html:`[hidden]` where a display utility of Bootstrap outranks the attribute,
and the :css:`display` of the custom elements. Copy those rules from the
stylesheet of the development instances, see :ref:`styling-example` and
:ref:`breaking-profile-editing-ships-no-stylesheet`. On TYPO3 13.4.36, 14.3.8
and later the rich text fields need the interface stylesheet of CKEditor 5 as
well, which the site links from :guilabel:`EXT:rte_ckeditor`, see
:ref:`styling-rich-text-editor`.

..  _upgrade-from-2-3-to-3-0-icons:

Icons
-----

The editor renders the shared action and state icons of
:guilabel:`academic_base` with its ``ab:icon`` ViewHelper. The
``academic-persons-edit-*`` identifiers are removed, five of them without a
successor, and the content element icon is
``tx-academicpersonsedit-plugin-profile-editing`` now. A replacement of an
editor icon moves to the :file:`Configuration/FrontendIcons.php` of the site
package, under the ``tx-academicbase-*`` identifier. The icons are inlined as
:html:`<svg>`, so a selector such as ``.t3js-icon img`` no longer matches. See
:ref:`breaking-persons-edit-profile-editing-icons-moved-to-the-frontend-icon-registry`
and :ref:`profile-editing-icons`.

..  _upgrade-from-2-3-to-3-0-code:

Adapt your code
===============

*   The contract form has no :guilabel:`Publish` switch any more.
    :php:`ContractFormData` loses ``$publish`` and :php:`isPublish()`, and a
    save that still sends ``publish`` is refused with HTTP 422. Drop it from
    overrides, clients and constructor calls. See
    :ref:`breaking-contract-publish-switch-removed`.
*   The five controllers of the contracts, the profile information and the
    contacts are removed, and :php:`ProfileController` keeps only the actions
    of the new editor. :php:`AbstractActionController`,
    :php:`UserSessionService` and the five validators of the contract,
    address, email, phone number and profile information forms are removed as
    well, and so is :php:`ProfileFormData::createFromProfile()`, deprecated
    since 2.3, see :ref:`upgrade-from-2-3-to-3-0-replaced`. A project that
    completes or refuses a write of the editor listens to
    :php:`BeforeProfileEditingWriteEvent`, see
    :ref:`developers-event-before-profile-editing-write-event`.
*   Code that extends or replaces the internal form data classes and sets a
    property the request did not submit registers the value with
    :php:`AbstractFormData::setPropertyOverride()`, otherwise it is not
    applied. See :ref:`important-ace-33-academic-persons-edit`.
*   :php:`UnknownValidatorException` and :php:`UnsuitableValidatorException`
    moved to the namespace :php:`FGTCLB\AcademicBase\Settings\Exception`. See
    :ref:`important-validator-exceptions-moved-to-academic-base`.
*   The listener :php:`SyncChangesToTranslations` takes its record
    synchronizer as :php:`$recordSynchronizer` now. Code that creates it with
    the named argument :php:`recordSyncronizer:` changes it, see the `breaking
    change entry of academic_persons
    <https://docs.typo3.org/p/fgtclb/academic-persons/main/en-us/Changelog/3.0/Breaking-SynchronizerContextNamespaceCorrected.html>`__.

..  _upgrade-from-2-3-to-3-0-check:

Check the result
================

*   The upgrade check reports no override of a removed file, and exits with
    ``0``, without and with the extension key, see
    :ref:`upgrade-from-2-3-to-3-0-upgrade-check`.
*   The new content element wizard offers :guilabel:`Edit Person’s Profiles`
    on the pages that should have it, see
    :ref:`important-content-element-titles-and-descriptions`, and an existing
    profile editing content element opens in the backend with its own type.
*   No profile switcher content element is left, and the page module shows
    no element with an invalid type in its place.
*   The editor loads without the alert that the profile cannot be saved, a
    field can be saved, and the browser console shows no failed request to the
    page type ``1733735``.
*   The header of a profile editing content element appears once.
*   The image cropper can be dragged, and the regions the editor hides stay
    hidden.
*   A frontend user can edit each of its profiles. The editor accepts only
    profiles the frontend user is linked to in the default language of the
    profile, a link set only on a translation is ignored. See
    :ref:`important-ace-827-academic-persons-edit`.
*   A profile image uploads, and a translated profile shows an image in every
    language. An upload in one language does not change the image of another.
*   The rich text fields start the editor, and its toolbar shows small icons
    in one row.
