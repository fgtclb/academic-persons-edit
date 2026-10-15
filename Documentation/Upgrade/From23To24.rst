..  _upgrade-from-2-3-to-2-4:

=========================
Upgrading from 2.3 to 2.4
=========================

Two changes of version 2.4 need attention on every site: the profile editing
content element is hidden in the backend until the site includes the page
TSconfig of its component, and most of the Fluid files of the plugin changed,
so every override has to be compared with them. Work through the sections in
order. Skip a step that does not apply to the installation.

..  _upgrade-from-2-3-to-2-4-before-you-start:

Before you start
================

Version 2.4 runs on TYPO3 12.4 from 12.4.22 on and on TYPO3 13.4, with PHP 8.1
or later. That is the same as for 2.3, so the TYPO3 core does not have to
change for this upgrade.

:guilabel:`academic_persons_edit` 2.4 declares no new dependency of its own. It
needs these packages. Upgrade them first or in the same deployment, in this
order:

*   :composer:`fgtclb/environment-state-manager` 1.x, new in 2.4, which
    :guilabel:`academic_base` 2.4 and :guilabel:`academic_persons` 2.4 require.
    Composer installs it on its own. A classic installation installs
    :guilabel:`environment_state_manager` before it activates either of them.
*   :composer:`fgtclb/academic-base` 2.4, the same release as this extension.
*   :composer:`fgtclb/academic-persons` 2.4, the same release as this
    extension. Its upgrade chapter has steps of its own, and its database
    update and its upgrade wizard are due in the same deployment.

:guilabel:`academic_persons_sync` 2.4 requires this extension and follows it in
the same deployment, where installed.

..  _upgrade-from-2-3-to-2-4-package:

Update the package
==================

..  code-block:: bash

    composer require -W \
        'fgtclb/academic-base':'~2.4.0' \
        'fgtclb/academic-persons':'~2.4.0' \
        'fgtclb/academic-persons-edit':'~2.4.0'

A project that requires branch ``2`` as ``2.x-dev`` keeps an inline alias such
as ``2.x-dev as 2.4.x-dev`` working, see :ref:`important-1790796664`.

On a classic installation, install :guilabel:`environment_state_manager` 1.x
from the TER in :guilabel:`Admin Tools > Extensions` first, then update
:guilabel:`academic_base`, :guilabel:`academic_persons` and
:guilabel:`academic_persons_edit` there, in this order. Run the commands of
this page with :bash:`typo3/sysext/core/bin/typo3` instead of
:bash:`vendor/bin/typo3`.

..  code-block:: bash

    vendor/bin/typo3 cache:flush

..  _upgrade-from-2-3-to-2-4-database:

Update the database
===================

..  code-block:: bash

    vendor/bin/typo3 extension:setup

In the backend the same is :guilabel:`Admin Tools > Maintenance > Analyze
Database Structure`. Version 2.4 of this extension changes no table. Run the
update all the same, :guilabel:`academic_persons` 2.4 adds a column, and the
upgrade wizard of the next step requires an up to date schema.

..  _upgrade-from-2-3-to-2-4-wizards:

Run the upgrade wizards
=======================

..  code-block:: bash

    vendor/bin/typo3 upgrade:run academicPersonsEdit_removeProfileSwitcherContent

The wizard :guilabel:`Remove content elements of the removed
academic_persons_edit profile switcher plugin`,
``academicPersonsEdit_removeProfileSwitcherContent``, is new in 2.4. The profile
switcher has rendered nothing since version 2.1, which replaced it, but its
content type stayed registered. Version 2.4 removes the content type, and the
wizard sets every content element of it to deleted, in both shapes it can have:
with the :sql:`CType` `academicpersonsedit_profileswitcher`, and as a legacy
plugin with the :sql:`list_type` of that name. The records stay restorable from
the recycler. See :ref:`breaking-1785675428`, which also has the queries to look
at the affected records first.

The wizard can run in any order with the other wizards.
``academicPersonsEdit_pluginContent``, which shipped with 2.3 already, no longer
moves a profile switcher onto its own content type, so whether it ran before
does not matter. An installation that never used the profile switcher gets
nothing to do from the wizard.

Once TYPO3 has recorded a wizard as done, it is offered again only after it is
marked undone. TYPO3 13 marks a wizard undone with
``vendor/bin/typo3 upgrade:mark:undone academicPersonsEdit_removeProfileSwitcherContent``.
TYPO3 12 has no such command: open
:guilabel:`Admin Tools > Upgrade > Upgrade Wizard`, find the wizard under
:guilabel:`Wizards marked as done` and choose :guilabel:`Mark undone`.

..  _upgrade-from-2-3-to-2-4-configuration:

Adapt the configuration
=======================

..  _upgrade-from-2-3-to-2-4-page-tsconfig:

Make the content element selectable
-----------------------------------

The site set and the static template were cut per component, and the
:guilabel:`Profiles: Profile Editing` content element is now hidden for the
whole installation until the page TSconfig of its component brings it back.
See :ref:`breaking-site-sets-and-static-templates-restructured` for the
complete change.

*   **TYPO3 12, and TYPO3 13 without a site set:** add
    :guilabel:`Academic Persons Edit: All components (academic_persons_edit)`
    in the page record of every site root that offers profile editing, tab
    :guilabel:`Resources`, field :guilabel:`Include static Page TSconfig (from
    extensions)`, see :ref:`static-pagetsconfig`. This entry did not exist in
    2.3. Without it the content element is no longer offered in the backend,
    and on TYPO3 12 the new content element wizard has no entry for it.
*   **TYPO3 13 with a site set:** nothing to do. The set
    `fgtclb/academic-persons-edit` keeps its name and delivers the page
    TSconfig.

Use one mechanism per site, never a site set and a static template together,
see :ref:`one-mechanism-per-site`.

Existing content elements keep rendering in the frontend either way. The
breaking change entry warns that saving such a content element in the backend,
on a page that does not include the page TSconfig of its component, writes
another content type. That no longer happens in 2.4.0:
:guilabel:`academic_base` 2.4 keeps the stored type selected and labels it
:guilabel:`(not enabled on this page)`, so saving the record keeps it. A content
element whose type was already replaced before the update, for example on a
page that restricted the content types with
:typoscript:`TCEFORM.tt_content.CType.keepItems`, is not repaired. Its record
history shows the former type.

..  _upgrade-from-2-3-to-2-4-static-templates:

Static templates and imports
----------------------------

The static template of version 2.3, stored as
`EXT:academic_persons_edit/Configuration/TypoScript`, keeps delivering. It is
offered as :guilabel:`Academic Persons Edit: Path up to 2.3 (deprecated, use
All components)` now and will be removed in version 4.0. Replace it in the
field :guilabel:`Include TypoScript sets` on the tab :guilabel:`Advanced
Options` of the :sql:`sys_template` record with :guilabel:`Academic Persons
Edit: All components (academic_persons_edit)`, or on TYPO3 13 depend on the
set `fgtclb/academic-persons-edit` instead. See
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

..  _upgrade-from-2-3-to-2-4-header:

The header of the content element
---------------------------------

The layout of the plugin no longer renders the header and the subheader of the
content element. They appeared twice where the content element layout of the
site renders them as well, which the layouts of EXT:fluid_styled_content and of
the bootstrap package do.

A site whose content element layout renders no header switches the header of
the plugin on:

..  code-block:: typoscript
    :caption: TypoScript constants

    plugin.tx_academicpersonsedit.renderContentElementHeader = 1

See :ref:`important-1791825026`.

..  _upgrade-from-2-3-to-2-4-profile-image:

The profile image upload
------------------------

An empty list of accepted media types now accepts a profile image of any media
type, where it rejected every upload before:

..  code-block:: typoscript

    plugin.tx_academicpersonsedit.settings.editForm.profileImage.validation.allowedMimeTypes =

A site that emptied or removed the setting and wants to restrict the uploads
lists the accepted media types there. See
:ref:`important-ace-267-academic-persons-edit`.

On TYPO3 12 the profile image is rendered as WebP only where the installation
allows TYPO3 to produce WebP, see
:ref:`important-ace-848-academic-persons-edit`.

..  _upgrade-from-2-3-to-2-4-labels:

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
*   The e-mail address field reads the units :xml:`emailAddress.email.label`
    and :xml:`emailAddress.email.placeholder`. An override or a further
    translation keyed on :xml:`emailAddress.emailAddress.label` or
    :xml:`emailAddress.emailAddress.placeholder` uses the new ids. See
    :ref:`important-email-address-field-label`.
*   A validation message override keyed :xml:`form.<field>.error.<code>` was
    never shown. Key it :xml:`<formObject>.<field>.error.<code>`, for example
    :xml:`emailAddressFormData.email.error.1221559976`. See
    :ref:`important-rejected-forms-show-their-messages`.
*   The backend labels of the relation between frontend users and profiles
    moved into :guilabel:`academic_persons`. An override of them through
    :php:`$GLOBALS['TYPO3_CONF_VARS']['SYS']['locallangXMLOverride']` targets
    the file of that extension now, as its upgrade chapter describes.

..  _upgrade-from-2-3-to-2-4-templates:

Adapt templates and assets
==========================

An override keeps its own copy and does not receive a change of the shipped
file. Version 2.4 changes 35 of the Fluid files of the plugin, and nearly all
of them translate their labels with the extension name
:html:`AcademicPersonsEdit` now instead of :html:`academic_persons_edit`, which
moves the path of a label override, see :ref:`upgrade-from-2-3-to-2-4-labels`.
Re-apply each override on top of the 2.4 file.
:ref:`breaking-adapted-frontend-editing-fluid-files` lists every change, and
:ref:`templates-override` describes how the overrides are set up.

The changes beyond the label path follow. The paths below are relative to
:file:`Resources/Private/`:

..  list-table::
    :header-rows: 1

    *   -   File
        -   What changed
        -   Entry
    *   -   :file:`Layouts/ProfileEdit.html`
        -   Renders the header of the content element only when
            :typoscript:`renderContentElementHeader` is switched on, see
            :ref:`upgrade-from-2-3-to-2-4-header`. A copy drops the header
            render where the site layout renders the header, or wraps it in
            the same condition.
        -   :ref:`important-1791825026`
    *   -   :file:`Templates/Contract/Show.html`,
            :file:`Partials/Profile/List/PhysicalAddresses.html`,
            :file:`Partials/Profile/List/EmailAddresses.html`,
            :file:`Partials/Profile/List/PhoneNumbers.html`
        -   Iterate the new variables :html:`{physicalAddresses}`,
            :html:`{emailAddresses}` and :html:`{phoneNumbers}`, which contain
            hidden records, and render the show and hide toggle.
        -   :ref:`breaking-1782285579`
    *   -   :file:`Templates/Profile/Edit.html`
        -   Loads the editor configuration from
            :file:`Resources/Public/JavaScript/frontend/ckeditor.js`, see the
            asset below.
        -   :ref:`breaking-ckeditor-configuration-moved`
    *   -   :file:`Templates/Contract/New.html`,
            :file:`Templates/Contract/Edit.html`,
            :file:`Partials/Profile/Properties/Profile.html`,
            :file:`Partials/Profile/Properties/Contract.html`
        -   Start the rich text editor on TYPO3 12 and in the contract forms:
            the contract templates load the editor, the partials pass
            :html:`richtext: 1` instead of :html:`richtext: true`.
        -   :ref:`important-profile-and-contract-forms-start-the-editor`
    *   -   :file:`Partials/Profile/Show/Image.html`
        -   Reads the image metadata through :html:`originalResource`, no
            longer renders the copyright block, and requests WebP only where
            the installation allows it.
        -   :ref:`important-1785686400`,
            :ref:`important-ace-848-academic-persons-edit`
    *   -   :file:`Partials/Profile/Forms/Errors.html`,
            :file:`Partials/Profile/Forms/FieldWrapper.html`, the new
            :file:`Partials/Profile/Forms/ErrorMessage.html`, and the
            templates of the six forms
        -   Show the message of a rejected field at the field. The templates
            pass the name of their form object to the errors partial as a
            string.
        -   :ref:`important-rejected-forms-show-their-messages`
    *   -   :file:`Partials/Profile/Show/Personal.html` and the headers of the
            templates
        -   Leave out an empty title and middle name of the profile name.
        -   :ref:`breaking-adapted-frontend-editing-fluid-files`
    *   -   All other changed files: :file:`Partials/Profile/Buttons/*.html`,
            :file:`Partials/Profile/Forms/Textarea.html`,
            :file:`Partials/Profile/Forms/Textfield.html`,
            :file:`Partials/Profile/List/Contracts.html`,
            :file:`Partials/Profile/List/ProfileInformation.html`, the other
            templates below :file:`Templates/`
        -   The label path, and the changes of the rows above where the file
            belongs to the forms.
        -   :ref:`important-label-overrides-use-the-documented-path`

One asset moved. The editor configuration is compiled from TypeScript now:

..  list-table::
    :header-rows: 1

    *   -   Old path
        -   New path
    *   -   `EXT:academic_persons_edit/Resources/Public/JavaScript/CKEditor.js`
        -   `EXT:academic_persons_edit/Resources/Public/JavaScript/frontend/ckeditor.js`

A template copy or a site package that references the old path loads a missing
file, and the rich text fields fall back to plain text areas. See
:ref:`breaking-ckeditor-configuration-moved`. A site package that shipped an
editor configuration of its own only to get a link button can drop it, the
shipped one has one now, see
:ref:`important-frontend-rich-text-editor-offers-links`.

..  _upgrade-from-2-3-to-2-4-code:

Adapt your code
===============

The form data transformation now writes only the properties a form submitted.
Code that extends or replaces the internal form data classes or their
factories, and sets a property the form did not submit, registers the value
with :php:`AbstractFormData::setPropertyOverride()`, otherwise it is not
applied. See :ref:`important-ace-33-academic-persons-edit`.

..  _upgrade-from-2-3-to-2-4-check:

Check the result
================

#.  The new content element wizard offers :guilabel:`Profiles: Profile
    Editing` on the pages that should have it, and an existing profile editing
    content element opens in the backend with its own type.
#.  No profile switcher content element is left, and the page module shows
    no element with an invalid type in its place.
#.  The header of a profile editing content element appears once.
#.  The rich text fields of the profile form and of the contract forms start
    the editor, and the editor offers links.
#.  A frontend user can edit each of its profiles. The editor accepts only
    profiles the frontend user is linked to in the default language of the
    profile, a link set only on a translation is ignored. See
    :ref:`important-ace-827-academic-persons-edit`.
#.  A rejected form shows the message at the rejected field.
#.  A profile image of an accepted media type uploads, and the profile view of
    the plugin renders it.
