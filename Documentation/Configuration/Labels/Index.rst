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
      default.list.profile = Profile
      de.list.profile = Profil
    }

    # Only in one content element:
    plugin.tx_academicpersonsedit_profileediting._LOCAL_LANG.default.list.profile = Profile

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
itself: :php:`$GLOBALS['TYPO3_CONF_VARS']['SYS']['locallangXMLOverride']`.

Earlier versions of this extension read these overrides on TYPO3 v12 and v13 from
:typoscript:`plugin.tx_academic_persons_edit` instead, and the options of its select fields from
:typoscript:`plugin.tx_persons_edit`, see
:ref:`the changelog <important-label-overrides-use-the-documented-path>`.

Where the labels are shown
==========================

Placeholders in angle brackets stand for a part of the key that the template
or the code fills in, a category type or a field name for example.

..  list-table::
    :header-rows: 1
    :widths: 45 55

    *   - Key
        - Shown by
    *   - :xml:`<field>.label`
        - :file:`Partials/Profile/Forms/FieldWrapper.html`
    *   - :xml:`<field>.placeholder`
        - :file:`Partials/Profile/Forms/Textarea.html`, :file:`Partials/Profile/Forms/Textfield.html`
    *   - :xml:`<property>.delete`
        - :file:`Partials/Profile/Buttons/DeleteCancel.html`
    *   - :xml:`<propertyName>.error.<code>`
        - :file:`Partials/Profile/Forms/Errors.html`
    *   - :xml:`actions.add`
        - :file:`Templates/Contract/Show.html`, :file:`Templates/Profile/Show.html`
    *   - :xml:`actions.cancel`
        - :file:`Partials/Profile/Buttons/DeleteCancel.html`, :file:`Partials/Profile/Buttons/SaveExitCancel.html`, :file:`Templates/Profile/EditImage.html`
    *   - :xml:`actions.delete`
        - :file:`Partials/Profile/Buttons/DeleteCancel.html`, :file:`Partials/Profile/List/Contracts.html`, :file:`Partials/Profile/List/EmailAddresses.html`, :file:`Partials/Profile/List/PhoneNumbers.html`, :file:`Partials/Profile/List/PhysicalAddresses.html`, :file:`Partials/Profile/List/ProfileInformation.html`, :file:`Templates/Profile/Show.html`
    *   - :xml:`actions.edit`
        - :file:`Partials/Profile/List/Contracts.html`, :file:`Partials/Profile/List/EmailAddresses.html`, :file:`Partials/Profile/List/PhoneNumbers.html`, :file:`Partials/Profile/List/PhysicalAddresses.html`, :file:`Partials/Profile/List/ProfileInformation.html`, :file:`Partials/Profile/Show/Personal.html`, :file:`Templates/Contract/Show.html`, :file:`Templates/EmailAddress/Show.html`, :file:`Templates/PhoneNumber/Show.html`, :file:`Templates/PhysicalAddress/Show.html`, :file:`Templates/Profile/List.html`, :file:`Templates/ProfileInformation/Show.html`
    *   - :xml:`actions.hide`
        - :file:`Partials/Profile/List/EmailAddresses.html`, :file:`Partials/Profile/List/PhoneNumbers.html`, :file:`Partials/Profile/List/PhysicalAddresses.html`
    *   - :xml:`actions.save`
        - :file:`Partials/Profile/Buttons/SaveExitCancel.html`, :file:`Templates/Profile/EditImage.html`
    *   - :xml:`actions.saveAndExit`
        - :file:`Partials/Profile/Buttons/SaveExitCancel.html`
    *   - :xml:`actions.setToBottom`
        - :file:`Partials/Profile/List/Contracts.html`, :file:`Partials/Profile/List/EmailAddresses.html`, :file:`Partials/Profile/List/PhoneNumbers.html`, :file:`Partials/Profile/List/PhysicalAddresses.html`, :file:`Partials/Profile/List/ProfileInformation.html`
    *   - :xml:`actions.setToTop`
        - :file:`Partials/Profile/List/Contracts.html`, :file:`Partials/Profile/List/EmailAddresses.html`, :file:`Partials/Profile/List/PhoneNumbers.html`, :file:`Partials/Profile/List/PhysicalAddresses.html`, :file:`Partials/Profile/List/ProfileInformation.html`
    *   - :xml:`actions.show`
        - :file:`Partials/Profile/List/EmailAddresses.html`, :file:`Partials/Profile/List/PhoneNumbers.html`, :file:`Partials/Profile/List/PhysicalAddresses.html`
    *   - :xml:`actions.sortDown`
        - :file:`Partials/Profile/List/Contracts.html`, :file:`Partials/Profile/List/EmailAddresses.html`, :file:`Partials/Profile/List/PhoneNumbers.html`, :file:`Partials/Profile/List/PhysicalAddresses.html`, :file:`Partials/Profile/List/ProfileInformation.html`
    *   - :xml:`actions.sortUp`
        - :file:`Partials/Profile/List/Contracts.html`, :file:`Partials/Profile/List/EmailAddresses.html`, :file:`Partials/Profile/List/PhoneNumbers.html`, :file:`Partials/Profile/List/PhysicalAddresses.html`, :file:`Partials/Profile/List/ProfileInformation.html`
    *   - :xml:`actions.translate`
        - :file:`Templates/Profile/Edit.html`
    *   - :xml:`actions.view`
        - :file:`Partials/Profile/List/Contracts.html`, :file:`Partials/Profile/List/PhysicalAddresses.html`, :file:`Partials/Profile/List/ProfileInformation.html`, :file:`Templates/Profile/List.html`
    *   - :xml:`back`
        - :file:`Layouts/ProfileEdit.html`
    *   - :xml:`contract`
        - :file:`Templates/Contract/New.html`
    *   - :xml:`contract.<field>.label`
        - :file:`Templates/Contract/Show.html`
    *   - :xml:`contract.data`
        - :file:`Templates/Contract/Show.html`
    *   - :xml:`contract.emailAddress`
        - :file:`Templates/EmailAddress/Edit.html`, :file:`Templates/EmailAddress/New.html`, :file:`Templates/EmailAddress/Show.html`
    *   - :xml:`contract.emailAddresses`
        - :file:`Templates/Contract/Show.html`
    *   - :xml:`contract.phoneNumber`
        - :file:`Templates/PhoneNumber/Edit.html`, :file:`Templates/PhoneNumber/New.html`, :file:`Templates/PhoneNumber/Show.html`
    *   - :xml:`contract.phoneNumbers`
        - :file:`Templates/Contract/Show.html`
    *   - :xml:`contract.physicalAddress`
        - :file:`Templates/PhysicalAddress/Edit.html`, :file:`Templates/PhysicalAddress/New.html`, :file:`Templates/PhysicalAddress/Show.html`
    *   - :xml:`contract.physicalAddresses`
        - :file:`Templates/Contract/Show.html`
    *   - :xml:`emailAddress.<field>`
        - :file:`Templates/EmailAddress/Show.html`
    *   - :xml:`emailAddress.email.label`
        - :file:`Partials/Profile/List/EmailAddresses.html`
    *   - :xml:`emailAddress.type.label`
        - :file:`Partials/Profile/List/EmailAddresses.html`
    *   - :xml:`form.<field>.error.<code>`
        - :file:`Partials/Profile/Forms/FieldWrapper.html`
    *   - :xml:`list.actions`
        - :file:`Partials/Profile/List/Contracts.html`, :file:`Partials/Profile/List/EmailAddresses.html`, :file:`Partials/Profile/List/PhoneNumbers.html`, :file:`Partials/Profile/List/PhysicalAddresses.html`, :file:`Partials/Profile/List/ProfileInformation.html`, :file:`Templates/Profile/List.html`
    *   - :xml:`list.contract.position`
        - :file:`Partials/Profile/List/Contracts.html`
    *   - :xml:`list.hidden.badge`
        - :file:`Partials/Profile/List/EmailAddresses.html`, :file:`Partials/Profile/List/PhoneNumbers.html`, :file:`Partials/Profile/List/PhysicalAddresses.html`
    *   - :xml:`list.no<type>Found`
        - :file:`Partials/Profile/List/ProfileInformation.html`
    *   - :xml:`list.noContractsFound`
        - :file:`Partials/Profile/List/Contracts.html`
    *   - :xml:`list.noEmailAddressesFound`
        - :file:`Partials/Profile/List/EmailAddresses.html`
    *   - :xml:`list.noPhoneNumbersFound`
        - :file:`Partials/Profile/List/PhoneNumbers.html`
    *   - :xml:`list.noPhysicalAddressesFound`
        - :file:`Partials/Profile/List/PhysicalAddresses.html`
    *   - :xml:`list.noProfilesFound`
        - :file:`Templates/Profile/List.html`
    *   - :xml:`list.noTranslationFound`
        - :file:`Templates/Profile/Edit.html`
    *   - :xml:`list.profile`
        - :file:`Templates/Profile/List.html`
    *   - :xml:`list.profile.assigned`
        - :file:`Templates/Profile/List.html`
    *   - :xml:`list.profileInformations.title`
        - :file:`Partials/Profile/List/ProfileInformation.html`
    *   - :xml:`list.profileInformations.year`
        - :file:`Partials/Profile/List/ProfileInformation.html`
    *   - :xml:`phoneNumber.<field>.label`
        - :file:`Templates/PhoneNumber/Show.html`
    *   - :xml:`phoneNumber.phoneNumber.label`
        - :file:`Partials/Profile/List/PhoneNumbers.html`
    *   - :xml:`phoneNumber.type.label`
        - :file:`Partials/Profile/List/PhoneNumbers.html`
    *   - :xml:`physicalAddress.<field>.label`
        - :file:`Templates/PhysicalAddress/Show.html`
    *   - :xml:`physicalAddress.city.label`
        - :file:`Partials/Profile/List/PhysicalAddresses.html`
    *   - :xml:`physicalAddress.country.label`
        - :file:`Partials/Profile/List/PhysicalAddresses.html`
    *   - :xml:`physicalAddress.street.label`
        - :file:`Partials/Profile/List/PhysicalAddresses.html`
    *   - :xml:`profile.<textarea>.label`
        - :file:`Partials/Profile/Show/Personal.html`
    *   - :xml:`profile.<type>`
        - :file:`Templates/Profile/Show.html`
    *   - :xml:`profile.contracts`
        - :file:`Templates/Profile/Show.html`
    *   - :xml:`profile.gender.label`
        - :file:`Partials/Profile/Show/Personal.html`
    *   - :xml:`profile.image`
        - :file:`Templates/Profile/EditImage.html`, :file:`Templates/Profile/Show.html`
    *   - :xml:`profile.name.label`
        - :file:`Partials/Profile/Show/Personal.html`
    *   - :xml:`profile.personal`
        - :file:`Partials/Profile/Show/Personal.html`
    *   - :xml:`profile.profileInformations.<type>`
        - :file:`Templates/ProfileInformation/Edit.html`, :file:`Templates/ProfileInformation/New.html`, :file:`Templates/ProfileInformation/Show.html`
    *   - :xml:`profile.publicationsLink.label`
        - :file:`Partials/Profile/Show/Personal.html`
    *   - :xml:`profile.skipSync.description`
        - :file:`Templates/Profile/Show.html`
    *   - :xml:`profile.website.label`
        - :file:`Partials/Profile/Show/Personal.html`
    *   - :xml:`profileInformation.<field>.label`
        - :file:`Templates/ProfileInformation/Show.html`
    *   - The flash messages of the editor, keyed by the action that shows them, for example :xml:`profile.update.success`
        - The messages after a profile or one of its records is saved or deleted
    *   - The item labels of the select fields in TCA, full references into a language file of academic_persons: the override is keyed by the id of the label in that file
        - The options of the select fields of the editor forms
    *   - :xml:`detail.since`, :xml:`detail.till` of :file:`EXT:academic_persons/Resources/Private/Language/locallang.xlf`, read with the extension name of academic_persons: from :typoscript:`plugin.tx_academicpersons._LOCAL_LANG` only, shared with the profile detail
        - :file:`Partials/Profile/List/ProfileInformation.html`
    *   - The messages of a rejected image upload, :xml:`upload.error.<code>` and :xml:`validation.error.1471708998` of :file:`EXT:academic_base/Resources/Private/Language/locallang.xlf`: no :typoscript:`_LOCAL_LANG` reaches them, a language file override does
        - :file:`Templates/Profile/EditImage.html`
