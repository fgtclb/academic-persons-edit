..  _breaking-replaced-profile-editing-plugin:

===========================================
Breaking: Replaced the profile editing view
===========================================

..  seealso::
    The `upgrade chapter of academic_persons
    <https://docs.typo3.org/p/fgtclb/academic-persons/main/en-us/Upgrade/Index.html>`__
    is the order in which the 3.0 changes have to be applied.

Description
===========

The :guilabel:`Profile editing` content element used to be a set of Extbase
forms: one page per record, a form submission per change, a redirect after
every save. It is replaced by a single view that edits the profile in place
and writes through JSON endpoints of the same plugin - see
:ref:`profile-editing` for what it does and
:ref:`important-profile-editing-replaced-in-place` for why nothing has to be
migrated in the page tree.

The plugin identity is unchanged: the content type stays
``academicpersonsedit_profileediting``, the plugin stays
``AcademicPersonsEdit`` / ``ProfileEditing``, the request namespace stays
``tx_academicpersonsedit_profileediting``, the site sets keep their names and
the default action stays ``list``. Everything below the plugin is new.

Removed controllers and actions
-------------------------------

Five of the six controllers are removed. ``ProfileController`` is the only one
the plugin registers, and its action list is new:

..  list-table::
    :header-rows: 1

    *   - Removed
        - Replacement
    *   - :php:`ContractController`, :php:`ProfileInformationController`,
          :php:`PhysicalAddressController`, :php:`EmailAddressController`,
          :php:`PhoneNumberController`, each with ``list``, ``show``, ``new``,
          ``create``, ``edit``, ``update``, ``confirmDelete``, ``delete``,
          ``sort`` and partly ``toggleVisibility``
        - The ``documentForm``/``createDocument``/``updateDocument``/
          ``deleteDocument``/``sortDocument``/``toggleDocumentVisibility`` and
          ``contractContactForm``/``createContractContact``/
          ``updateContractContact``/``deleteContractContact``/
          ``sortContractContact``/``toggleContractContactVisibility``
          JSON actions of :php:`ProfileController`
    *   - :php:`ProfileController::showAction()`,
          :php:`editAction()`, :php:`editImageAction()`,
          :php:`addImageAction()`, :php:`removeImageAction()`,
          :php:`toggleSkipSyncAction()`
        - :php:`indexAction()` for the view, and the ``update``,
          ``uploadImage``, ``deleteImage`` and ``updateSkipSync`` actions
    *   - :php:`AbstractActionController`,
          :php:`Property\TypeConverter\AbstractFormDataConverter`,
          :php:`Service\UserSessionService`,
          :php:`Exception\AccessDeniedException`
        - :php:`Service\ProfileUpdateRequestService`,
          :php:`Domain\Parser\ProfileUpdatePayloadParser` and
          :php:`Service\ProfileUpdateValidationService`
    *   - :php:`Domain\Validator\AddressFormDataValidator`,
          :php:`ContractFormDataValidator`, :php:`EmailFormDataValidator`,
          :php:`PhoneNumberFormDataValidator`,
          :php:`ProfileInformationFormDataValidator`
        - The validation set of the section a record belongs to, resolved from
          the settings graph - see :ref:`configuration-editor-settings`
    *   - :php:`Domain\Model\Dto\ProfileFormData::createFromProfile()`,
          deprecated since 2.3 and announced for removal in 3.0
        - :php:`Domain\Factory\ProfileFormDataFactory::createFromProfile()`

An installation that links to one of the removed actions - a
``f:link.action`` in an override, a hand written URL, a bookmark - gets a
**500**, not a fallback view. Extbase throws
:php:`InvalidControllerNameException` (1313855173) for a removed controller and
:php:`InvalidActionNameException` (1313855175) for a removed action; the
fallback to the default action only happens where
:typoscript:`config.tx_extbase.mvc.callDefaultActionIfActionCantBeResolved` is
set, and this extension does not set it.

Removed templates, layouts and partials
---------------------------------------

Every Fluid file of the form flow is removed. An override of one of them is
dead: the file is no longer rendered, and the project keeps a copy of a view
that no longer exists.

*   :file:`Resources/Private/Layouts/ProfileEdit.html`
*   :file:`Resources/Private/Templates/Profile/Edit.html`,
    :file:`Profile/EditImage.html`, :file:`Profile/Show.html`
*   :file:`Resources/Private/Templates/Contract/{Edit,New,Show}.html` and the
    same three files for :file:`EmailAddress/`, :file:`PhoneNumber/`,
    :file:`PhysicalAddress/` and :file:`ProfileInformation/`
*   :file:`Resources/Private/Partials/Profile/Buttons/{DeleteCancel,SaveExitCancel}.html`
*   :file:`Resources/Private/Partials/Profile/Forms/{Checkbox,DateTime,Errors,FieldWrapper,Select,Textarea,Textfield}.html`
*   :file:`Resources/Private/Partials/Profile/List/{Contracts,EmailAddresses,PhoneNumbers,PhysicalAddresses,ProfileInformation}.html`
*   :file:`Resources/Private/Partials/Profile/Properties/{Contract,EmailAddress,PhoneNumber,PhysicalAddress,Profile,ProfileInformation}.html`
*   :file:`Resources/Private/Partials/Profile/Show/{Image,Personal}.html`

The plugin renders without a Fluid layout since, so the TypoScript constant
:typoscript:`plugin.tx_academicpersonsedit.view.layoutRootPath` and the
:typoscript:`view.layoutRootPaths` block it filled are removed as well. A site
package that still sets the constant sets something nothing reads.

:file:`Resources/Private/Templates/Profile/List.html` is kept and rewritten,
and :file:`Resources/Private/Templates/Profile/Index.html` with the
thirty-one partials below :file:`Partials/Profile/` is the new tree: four of
them - :file:`Header.html`, :file:`StatusToast.html`,
:file:`ButtonTemplates.html` and :file:`Prototypes.html` - directly in that
directory and the rest in :file:`{Documents,Field,Image,Profile}/`.
:ref:`templates-override` describes what may be overridden.

The JavaScript module of the removed form flow goes with it:
:file:`Resources/Public/JavaScript/frontend/rich-text.js`, addressed by the bare
specifier :code:`@fgtclb/academic-persons-edit/frontend/rich-text.js`, configured
CKEditor 4 for :file:`Templates/Profile/Edit.html` and is deleted. A site package
that still loads that specifier loads nothing. Rich text is CKEditor 5 from
:file:`EXT:rte_ckeditor` now, created by the editing view itself - see
:ref:`profile-editing`.

Removed icons
-------------

The ten icon files 2.4 shipped for the form flow are deleted:

:file:`add-image-icon.svg`, :file:`add-item-icon.svg`, :file:`back-icon.svg`,
:file:`cancel-icon.svg`, :file:`delete-icon.svg`, :file:`edit-icon.svg`,
:file:`save-icon.svg`, :file:`sort-icon.svg`, :file:`sort-vertical-icon.svg`,
:file:`view-icon.svg`.

Five of the ten identifiers they were registered under go with them:
``academic-persons-edit-add-image``, ``-add-item``, ``-cancel``, ``-sort`` and
``-to-top``. The other five - ``academic-persons-edit-edit``, ``-view``,
``-delete``, ``-save`` and ``-back`` - stay and now resolve to the new artwork.

They also resolve through a different icon provider:
:php:`FGTCLB\AcademicBase\Imaging\IconProvider\CurrentColorSvgIconProvider`
inlines the ``<svg>`` where TYPO3's own :php:`SvgIconProvider` emitted an
``<img>``. A site package styling the editor's icons through a rule such as
``.t3js-icon img`` therefore stops matching, and styles the ``<svg>`` instead;
the *Feature: Icon provider for icons that follow the text colour* entry of
`EXT:academic_base` describes the provider.

Thirteen action icons are registered, under the identifiers listed in
:ref:`profile-editing-icons`; the extension icon ``persons_edit_icon`` is the
fourteenth entry of :file:`Configuration/Icons.php` and is unchanged. They are
Bootstrap Icons (MIT) drawn in ``currentColor`` and rendered inline, so they
take the colour of the control they sit in - see
:ref:`feature-profile-editing-icon-set`.

Removed labels
--------------

:file:`Resources/Private/Language/locallang.xlf` goes from 208 to 145
trans-units: **144 are removed and 81 are new**. 64 survive, all of them
byte-identical. The
German :file:`de.locallang.xlf` follows one to one. That is not a list worth
printing - the authoritative one is the diff of the file for this release -
but the shape of it is:

*   Everything the removed Extbase form flow needed goes with it: every
    ``*.create.success`` / ``*.update.success`` / ``*.delete.success`` /
    ``*.sort.success`` message, every ``*.placeholder``, every
    ``list.no*Found`` entry except ``list.noProfilesFound``, which the profile
    overview still renders, the ``actions.hide`` / ``show`` / ``translate``
    / ``saveAndExit``
    / ``setToTop`` / ``setToBottom`` / ``replace`` actions, ``back``,
    ``list.hidden.badge``, ``list.contract.position``, the ``profile.*``
    section headings and every ``*FormData.*.error.*`` unit.
*   ``contract.published.label`` was a stale duplicate of
    ``contract.publish.label``, which stays, and is removed;
    ``emailAddress.emailAddress.label`` is replaced by the shorter
    ``emailAddress.email.label``, which is new in this release.
*   The year fields of a timeline entry keep their labels
    ``profileInformation.year.label``, ``profileInformation.yearStart.label``
    and ``profileInformation.yearEnd.label``; their ``*.placeholder``
    companions are removed with the form flow that rendered them.
*   The new view brings its own vocabulary under the ``profileEditing.*``
    prefix - status messages, empty states, the image editor, the document
    section labels and the controls of full form editing
    (:ref:`feature-full-form-editing-applies-as-one-form`).

All of them are overridable through :typoscript:`locallangXMLOverride`, so an
installation that translated or reworded one of the 149 removed units loses
that override silently: the key is simply not read any more. Compare the
overrides against the shipped file after the update.

Removed extension configuration
-------------------------------

``profile.autoCreateProfiles`` and ``profile.createProfileForUserGroups`` are
removed from :file:`ext_conf_template.txt`. Neither is read by any code path of
this extension. Their stored values are not lost by the removal - the extension
configuration merges the current values over the template and prunes nothing -
and they are still the source the
``academicPersons_MigrateProfileAutoCreateExtensionsConfiguration`` upgrade
wizard of `EXT:academic_persons` reads to carry both settings over.
``profile.allowedLanguages`` is unchanged.

New page type
-------------

The JSON endpoints are reached through a :typoscript:`PAGE` object named
``academicPersonsProfileEditingAjax`` with :typoscript:`typeNum = 1733735`,
delivered by the site set ``fgtclb/academic-persons-edit-profile-editing`` and
by the static template. A project with a ``PageType`` route enhancer, a web
application firewall or a reverse proxy in front of TYPO3 has to let that page
type and the ``X-Requested-With`` header through - see
:ref:`profile-editing-page-type`.

**There was no such page type in 2.4**, where the editor was an Extbase form
flow, so a site package that copied the TypoScript of this extension into its
own instead of including the site set or the static template has the editor and
not the page type. Every save is then answered with the site's error page where
the browser expects JSON, and the editor can do nothing about it. The editor
therefore looks the page type up while it renders: when no ``PAGE`` object of
this site carries :typoscript:`typeNum = 1733735`, it writes an error naming
the cause to the TYPO3 log and tells the visitor that the profile cannot be
saved, instead of letting them find out on their first change. The object is
matched by its :typoscript:`typeNum`, not by its name, so a project that
declares it under a name of its own is recognised.

Impact
======

*   Overrides of any removed Fluid file stop having an effect. The editing
    view renders from the shipped templates.
*   Links to the removed actions are a 500 rather than a fallback view.
*   Templates and PHP code referring to the five removed icon identifiers,
    labels or controller classes fail: an unknown icon identifier renders
    TYPO3's ``default-not-found`` placeholder, an unknown label renders its
    own key, and an unknown class is a fatal error.
*   A form posting ``profileInformationFormData[year]``,
    ``contractFormData[...]``, ``addressFormData[...]``,
    ``emailFormData[...]`` or ``phoneNumberFormData[...]`` to one of the
    removed actions is not processed. The editor posts JSON, under the
    property names ``year``, ``yearStart`` and ``yearEnd``.
*   The two removed extension configuration options disappear from the
    Settings module. A stored value is ignored.

Affected Installations
======================

All installations using the :guilabel:`Profile editing` content element of
`EXT:academic_persons_edit`, and in particular every installation that
overrides one of its Fluid files or links to one of its actions.

Migration
=========

#.  Remove the overrides of the deleted Fluid files, and re-apply the project
    specific changes on top of the new template tree where they are still
    wanted. :ref:`templates-override` names the files that carry the hooks the
    JavaScript binds to; changing those breaks the editor rather than the
    layout.
#.  Replace links to the removed actions with a link to the ``index`` action
    and the ``profileUid`` argument, or with the profile overview.
#.  Replace the five removed icon identifiers with the ones of
    :ref:`profile-editing-icons`, and re-point CSS that selected the icons as
    an ``<img>``.
#.  Confirm that the site actually delivers page type ``1733735``. Include the
    site set ``fgtclb/academic-persons-edit-profile-editing`` or the static
    template of this extension; a site package that maintains a copy of the
    extension's TypoScript adds the ``academicPersonsProfileEditingAjax``
    :typoscript:`PAGE` object to that copy. The editor reports a missing page
    type in the TYPO3 log and on the page itself, but it reports it, it cannot
    repair it.
#.  Let page type ``1733735`` and the ``X-Requested-With`` header pass through
    route enhancers and firewalls.
#.  Drop ``profile.autoCreateProfiles`` and
    ``profile.createProfileForUserGroups`` from the deployment configuration -
    but only after the
    ``academicPersons_MigrateProfileAutoCreateExtensionsConfiguration`` upgrade
    wizard has run, because that wizard reads them.

..  index:: Backend, Fluid, Frontend, JavaScript, TypoScript, ext:academic_persons_edit, NotScanned
