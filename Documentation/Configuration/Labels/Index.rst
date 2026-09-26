..  index:: Configuration; Labels
..  _configuration-labels:

======
Labels
======

The labels this extension shows in the frontend come from
:file:`EXT:academic_persons_edit/Resources/Private/Language/locallang.xlf` and its
translations; the table below names the ones that come from another file. A site
changes a label without copying a template, in TypoScript:
under :typoscript:`plugin.tx_academicpersonsedit._LOCAL_LANG` for every content element
of the extension, or under :typoscript:`plugin.tx_academicpersonsedit_<plugin>._LOCAL_LANG`
for one of them. A label set for the plugin wins over one set for the extension.

..  code-block:: typoscript
    :caption: EXT:my_sitepackage/Configuration/TypoScript/setup.typoscript

    plugin.tx_academicpersonsedit._LOCAL_LANG {
      default.profileEditing.section.personal = About me
      de.profileEditing.section.personal = Über mich
    }

    # Only in one content element:
    plugin.tx_academicpersonsedit_profileediting._LOCAL_LANG.default.profileEditing.section.personal = About me

The dots of a key need no escaping: TypoScript reads them as levels of its tree,
and TYPO3 joins the levels to the key again. A label of another language goes
under its language key, :typoscript:`de` for German.

..  list-table:: The path of each content element
    :header-rows: 1

    *   - Content element
        - Path
    *   - :guilabel:`Profile editing` (:typoscript:`academicpersonsedit_profileediting`)
        - :typoscript:`plugin.tx_academicpersonsedit_profileediting._LOCAL_LANG`

A language file override works as well, and replaces the label of the file
itself: :php:`$GLOBALS['TYPO3_CONF_VARS']['SYS']['locallangXMLOverride']` on
TYPO3 v13, :php:`$GLOBALS['TYPO3_CONF_VARS']['LANG']['resourceOverrides']` on
TYPO3 v14.

Earlier versions of this extension read these overrides on TYPO3 v13 from
:typoscript:`plugin.tx_academic_persons_edit` instead, see
:ref:`the changelog <important-label-overrides-use-the-documented-path>`.

Before, the help texts of the profile fields and the headings of the document sections
read the overrides of academic_persons on TYPO3 v14, and the help texts of the document
and contact forms and the country options read none at all, see
:ref:`important-editor-label-overrides-read-the-editor-paths`.

The help texts and the headings of the document sections a site configures take a full
``LLL:EXT:`` reference. The help texts of the document and contact forms also take
literal text; a profile field shows none, and a section heading shows the identifier
of the section instead. A translation domain reference of TYPO3 v14,
``LLL:my_sitepackage.messages:key``, does not resolve there: the editor translates
with its own name, which TYPO3 then prefers.

Where the labels are shown
==========================

Placeholders in angle brackets stand for a part of the key that the template
or the code fills in, a category type or a field name for example.

..  list-table::
    :header-rows: 1
    :widths: 45 55

    *   - Key
        - Shown by
    *   - :xml:`actions.add`
        - :file:`Partials/Profile/Documents/ContractContacts.html`, :file:`Partials/Profile/Documents/Sections.html`, :file:`Templates/Profile/Index.html`
    *   - :xml:`actions.cancel`
        - :file:`Partials/Profile/Documents/ContractContactEditor.html`, :file:`Partials/Profile/Documents/Editor.html`, :file:`Partials/Profile/Image/Editor.html`
    *   - :xml:`actions.delete`
        - :file:`Partials/Profile/Documents/Actions.html`, :file:`Partials/Profile/Documents/ContractContactEditor.html`, :file:`Partials/Profile/Documents/ContractContacts.html`, :file:`Partials/Profile/Documents/Editor.html`, :file:`Partials/Profile/Image/Editor.html`, :file:`Templates/Profile/Index.html`
    *   - :xml:`actions.dragSort`
        - :file:`Partials/Profile/Documents/Actions.html`
    *   - :xml:`actions.edit`
        - :file:`Partials/Profile/ButtonTemplates.html`, :file:`Partials/Profile/Documents/Actions.html`, :file:`Partials/Profile/Documents/ContractContacts.html`, :file:`Partials/Profile/Field/Group.html`, :file:`Partials/Profile/Field/Preview.html`, :file:`Templates/Profile/Index.html`, :file:`Templates/Profile/List.html`
    *   - :xml:`actions.hide`
        - :file:`Partials/Profile/Documents/Actions.html`, :file:`Partials/Profile/Documents/ContractContacts.html`
    *   - :xml:`actions.save`
        - :file:`Partials/Profile/Documents/ContractContactEditor.html`, :file:`Partials/Profile/Documents/Editor.html`, :file:`Partials/Profile/Field/Actions.html`, :file:`Partials/Profile/Field/Group.html`, :file:`Partials/Profile/Image/Editor.html`, :file:`Templates/Profile/Index.html`
    *   - :xml:`actions.show`
        - :file:`Partials/Profile/Documents/Actions.html`, :file:`Partials/Profile/Documents/ContractContacts.html`
    *   - :xml:`actions.sortDown`
        - :file:`Partials/Profile/Documents/Actions.html`, :file:`Partials/Profile/Documents/ContractContacts.html`
    *   - :xml:`actions.sortUp`
        - :file:`Partials/Profile/Documents/Actions.html`, :file:`Partials/Profile/Documents/ContractContacts.html`
    *   - :xml:`actions.undo`
        - :file:`Partials/Profile/Field/Actions.html`, :file:`Partials/Profile/Field/AutosaveUndo.html`, :file:`Partials/Profile/Field/Group.html`
    *   - :xml:`actions.view`
        - :file:`Partials/Profile/Documents/Actions.html`, :file:`Partials/Profile/Documents/ContractContacts.html`, :file:`Templates/Profile/Index.html`, :file:`Templates/Profile/List.html`
    *   - :xml:`actions.viewClose`
        - :file:`Partials/Profile/Documents/Actions.html`, :file:`Partials/Profile/Documents/ContractContacts.html`
    *   - :xml:`list.actions`
        - :file:`Templates/Profile/List.html`
    *   - :xml:`list.language`
        - :file:`Templates/Profile/List.html`
    *   - :xml:`list.noProfilesFound`
        - :file:`Templates/Profile/List.html`
    *   - :xml:`list.profile`
        - :file:`Templates/Profile/List.html`
    *   - :xml:`list.profile.assigned`
        - :file:`Templates/Profile/List.html`
    *   - :xml:`profile.<field>.label`
        - :file:`Partials/Profile/Field/Checkbox.html`, :file:`Partials/Profile/Field/Editable.html`, :file:`Partials/Profile/Field/Group.html`, :file:`Partials/Profile/Field/Helptext.html`, :file:`Partials/Profile/Field/Preview.html`, :file:`Partials/Profile/Field/Select.html`
    *   - :xml:`profile.image.placeholder.alt`
        - :file:`Partials/Profile/Image/Card.html`, :file:`Templates/Profile/Index.html`
    *   - :xml:`profile.skipSync.description`
        - :file:`Partials/Profile/Header.html`
    *   - :xml:`profile.skipSync.label`
        - :file:`Partials/Profile/Header.html`
    *   - :xml:`profileEditing.actions.clear`
        - :file:`Partials/Profile/Field/Actions.html`, :file:`Partials/Profile/Field/Group.html`
    *   - :xml:`profileEditing.actions.formGroup`
        - :file:`Partials/Profile/Field/FormActions.html`
    *   - :xml:`profileEditing.actions.group`
        - :file:`Partials/Profile/Field/Actions.html`, :file:`Partials/Profile/Field/Group.html`
    *   - :xml:`profileEditing.backToList`
        - :file:`Templates/Profile/Index.html`
    *   - :xml:`profileEditing.btnCloseAll`
        - :file:`Partials/Profile/Header.html`
    *   - :xml:`profileEditing.btnEditAll`
        - :file:`Partials/Profile/Header.html`
    *   - :xml:`profileEditing.content.empty`
        - :file:`Partials/Profile/Field/Preview.html`
    *   - :xml:`profileEditing.contractContacts.actions`
        - :file:`Partials/Profile/Documents/ContractContacts.html`
    *   - :xml:`profileEditing.contractContacts.delete.confirm`
        - :file:`Templates/Profile/Index.html`
    *   - :xml:`profileEditing.contractContacts.empty`
        - :file:`Templates/Profile/Index.html`
    *   - :xml:`profileEditing.contractContacts.hidden`
        - :file:`Partials/Profile/Documents/ContractContacts.html`
    *   - :xml:`profileEditing.contractContacts.status.hidden`
        - :file:`Templates/Profile/Index.html`
    *   - :xml:`profileEditing.contractContacts.status.visible`
        - :file:`Templates/Profile/Index.html`
    *   - :xml:`profileEditing.documents.actions`
        - :file:`Partials/Profile/Documents/Actions.html`
    *   - :xml:`profileEditing.documents.actionsHeading`
        - :file:`Partials/Profile/Documents/Header.html`
    *   - :xml:`profileEditing.documents.delete.confirm`
        - :file:`Templates/Profile/Index.html`
    *   - :xml:`profileEditing.documents.empty`
        - :file:`Partials/Profile/Documents/Sections.html`
    *   - :xml:`profileEditing.documents.empty.<section>`
        - :file:`Partials/Profile/Documents/Sections.html`
    *   - :xml:`profileEditing.documents.from`
        - :file:`Partials/Profile/Documents/ContractRow.html`, :file:`Partials/Profile/Documents/Header.html`, :file:`Partials/Profile/Documents/ProfileInformationRow.html`
    *   - :xml:`profileEditing.documents.hidden`
        - :file:`Partials/Profile/Documents/Actions.html`
    *   - :xml:`profileEditing.documents.position`
        - :file:`Partials/Profile/Documents/ContractRow.html`, :file:`Partials/Profile/Documents/Header.html`
    *   - :xml:`profileEditing.documents.status.deleted`
        - :file:`Templates/Profile/Index.html`
    *   - :xml:`profileEditing.documents.status.hidden`
        - :file:`Templates/Profile/Index.html`
    *   - :xml:`profileEditing.documents.status.saved`
        - :file:`Templates/Profile/Index.html`
    *   - :xml:`profileEditing.documents.status.sorted`
        - :file:`Templates/Profile/Index.html`
    *   - :xml:`profileEditing.documents.status.visible`
        - :file:`Templates/Profile/Index.html`
    *   - :xml:`profileEditing.documents.title`
        - :file:`Partials/Profile/Documents/Header.html`, :file:`Partials/Profile/Documents/ProfileInformationRow.html`
    *   - :xml:`profileEditing.documents.to`
        - :file:`Partials/Profile/Documents/ContractRow.html`, :file:`Partials/Profile/Documents/Header.html`, :file:`Partials/Profile/Documents/ProfileInformationRow.html`
    *   - :xml:`profileEditing.documents.year`
        - :file:`Partials/Profile/Documents/Header.html`, :file:`Partials/Profile/Documents/ProfileInformationRow.html`
    *   - :xml:`profileEditing.field.empty`
        - :file:`Partials/Profile/Field/Group.html`, :file:`Partials/Profile/Field/Preview.html`, :file:`Templates/Profile/Index.html`
    *   - :xml:`profileEditing.field.required`
        - :file:`Partials/Profile/Field/Editable.html`, :file:`Partials/Profile/Field/Group.html`, :file:`Partials/Profile/Field/Preview.html`, :file:`Partials/Profile/Field/Select.html`
    *   - :xml:`profileEditing.form.apply`
        - :file:`Partials/Profile/Field/FormActions.html`
    *   - :xml:`profileEditing.form.apply.title`
        - :file:`Partials/Profile/Field/FormActions.html`
    *   - :xml:`profileEditing.form.discard`
        - :file:`Partials/Profile/Field/FormActions.html`
    *   - :xml:`profileEditing.form.discard.title`
        - :file:`Partials/Profile/Field/FormActions.html`
    *   - :xml:`profileEditing.form.undo`
        - :file:`Partials/Profile/Field/FormActions.html`
    *   - :xml:`profileEditing.form.undo.title`
        - :file:`Partials/Profile/Field/FormActions.html`
    *   - :xml:`profileEditing.image.edit`
        - :file:`Partials/Profile/Image/Card.html`
    *   - :xml:`profileEditing.image.editor.deleteConfirm`
        - :file:`Partials/Profile/Image/Editor.html`
    *   - :xml:`profileEditing.image.editor.deleteHint`
        - :file:`Partials/Profile/Image/Editor.html`
    *   - :xml:`profileEditing.image.editor.hint.ratio`
        - :file:`Partials/Profile/Image/Editor.html`
    *   - :xml:`profileEditing.image.editor.hint.select`
        - :file:`Partials/Profile/Image/Editor.html`
    *   - :xml:`profileEditing.image.editor.title`
        - :file:`Partials/Profile/Image/Editor.html`
    *   - :xml:`profileEditing.image.heading`
        - :file:`Partials/Profile/Image/Card.html`
    *   - :xml:`profileEditing.image.status.deleted`
        - :file:`Templates/Profile/Index.html`
    *   - :xml:`profileEditing.image.status.missing`
        - :file:`Templates/Profile/Index.html`
    *   - :xml:`profileEditing.image.status.uploaded`
        - :file:`Templates/Profile/Index.html`
    *   - :xml:`profileEditing.image.upload.label`
        - :file:`Partials/Profile/Image/Editor.html`
    *   - :xml:`profileEditing.missingAjaxPageType`
        - :file:`Templates/Profile/Index.html`
    *   - :xml:`profileEditing.section.about`
        - :file:`Partials/Profile/Profile/About.html`
    *   - :xml:`profileEditing.section.personal`
        - :file:`Partials/Profile/Profile/Personal.html`
    *   - :xml:`profileEditing.status.close`
        - :file:`Partials/Profile/StatusToast.html`
    *   - :xml:`profileEditing.status.discarded`
        - :file:`Templates/Profile/Index.html`
    *   - :xml:`profileEditing.status.editorError`
        - :file:`Templates/Profile/Index.html`
    *   - :xml:`profileEditing.status.error.message`
        - :file:`Templates/Profile/Index.html`
    *   - :xml:`profileEditing.status.error.title`
        - :file:`Templates/Profile/Index.html`
    *   - :xml:`profileEditing.status.formReverted`
        - :file:`Partials/Profile/Field/FormActions.html`
    *   - :xml:`profileEditing.status.info.message`
        - :file:`Templates/Profile/Index.html`
    *   - :xml:`profileEditing.status.info.title`
        - :file:`Templates/Profile/Index.html`
    *   - :xml:`profileEditing.status.saveInProgress`
        - :file:`Templates/Profile/Index.html`
    *   - :xml:`profileEditing.status.saving`
        - :file:`Templates/Profile/Index.html`
    *   - :xml:`profileEditing.status.success.message`
        - :file:`Templates/Profile/Index.html`
    *   - :xml:`profileEditing.status.success.title`
        - :file:`Templates/Profile/Index.html`
    *   - :xml:`profileEditing.status.unchanged`
        - :file:`Templates/Profile/Index.html`
    *   - :xml:`profileEditing.status.validation`
        - :file:`Templates/Profile/Index.html`
    *   - :xml:`profileEditing.status.warning.title`
        - :file:`Templates/Profile/Index.html`
    *   - :xml:`profileEditing.unsavedChanges.cancel`
        - :file:`Partials/Profile/UnsavedChanges.html`
    *   - :xml:`profileEditing.unsavedChanges.discard`
        - :file:`Partials/Profile/UnsavedChanges.html`
    *   - :xml:`profileEditing.unsavedChanges.message`
        - :file:`Partials/Profile/UnsavedChanges.html`
    *   - :xml:`profileEditing.unsavedChanges.save`
        - :file:`Partials/Profile/UnsavedChanges.html`
    *   - :xml:`profileEditing.unsavedChanges.title`
        - :file:`Partials/Profile/UnsavedChanges.html`
    *   - :xml:`profileEditing.user_image_not_available`
        - :file:`Partials/Profile/Image/Card.html`
    *   - :xml:`profileEditing.visibility.private`
        - :file:`Partials/Profile/Field/Control.html`
    *   - :xml:`profileEditing.visibility.public`
        - :file:`Partials/Profile/Field/Control.html`
    *   - :xml:`profileInformation.bodytext.label`
        - :file:`Partials/Profile/Documents/Header.html`, :file:`Partials/Profile/Documents/ProfileInformationRow.html`
    *   - The help texts of the profile fields, full references into a language file: the override is keyed by the id of the label in that file
        - :file:`Partials/Profile/Field/Helptext.html`
    *   - The labels of the document sections, full references into a language file: the override is keyed by the id of the label in that file
        - :file:`Partials/Profile/Documents/Sections.html`
    *   - :xml:`<record>.<field>.label`, for the records contract, profileInformation, physicalAddress, emailAddress and phoneNumber
        - The fields and the summaries of the document forms, put together by the editor
    *   - :xml:`contract.physicalAddresses`, :xml:`contract.emailAddresses`, :xml:`contract.phoneNumbers`
        - The headings of the contact sections of a contract
    *   - :xml:`contract.physicalAddress`, :xml:`contract.emailAddress`, :xml:`contract.phoneNumber`
        - One entry of a contact section
    *   - The help texts of the fields of the document and contact forms, full references into a language file of academic_persons: the override is keyed by the id of the label in that file
        - The fields of the document and contact forms, put together by the editor
    *   - :xml:`<ISO code>.name` of :file:`EXT:core/Resources/Private/Language/Iso/countries.xlf`
        - The country options of an address
    *   - :xml:`profileEditing.visibility.public`, :xml:`profileEditing.visibility.private`
        - The value of a checkbox in a summary
    *   - :xml:`list.language.all`, :xml:`list.language.unknown`
        - The language column of the profile list
    *   - The item labels of the profile fields in TCA
        - The options of a select field of the editor; the key is the id of the label in its language file, which belongs to academic_persons
    *   - The types of an address, email address or phone number, read with the extension name of academic_persons: from :typoscript:`plugin.tx_academicpersons._LOCAL_LANG`, on TYPO3 v14 also from :typoscript:`plugin.tx_academicpersons_profileediting._LOCAL_LANG`
        - The type selects of the contact forms
