..  _important-editor-label-overrides-read-the-editor-paths:

=============================================================================
Important: Label overrides of the profile editor read the paths of the editor
=============================================================================

Description
===========

Apart from the types of an address, an email address and a phone number, every
label of the profile editor reads the :typoscript:`_LOCAL_LANG` overrides of
:typoscript:`plugin.tx_academicpersonsedit` and
:typoscript:`plugin.tx_academicpersonsedit_profileediting`, on TYPO3 v13 and
v14. Beyond what :ref:`important-label-overrides-use-the-documented-path`
describes, this changes for the editor of this version:

The help texts of the profile fields and the headings of the document sections
point into the language files of academic_persons. They are now translated with
the name of the profile editor, on every core version. TYPO3 v14 read their
overrides from :typoscript:`plugin.tx_academicpersons` and
:typoscript:`plugin.tx_academicpersons_profileediting` before.

The help texts of the document and contact forms and the country options of an
address could not be overridden at all before; they now read the overrides of
the profile editor as well. A help text of a contract, contact or timeline
field configured as literal text no longer keeps the forms from opening.

On TYPO3 v14, the labels the editor translates in PHP - the fields, options,
sections and summaries of its forms and the language column of its list - now
read the override of the plugin as well; before, only the one of the extension
reached them.

The settings that take a label reference accept an ``LLL:EXT:`` reference; only
the help texts of the document and contact forms also take literal text. A
translation domain reference of TYPO3 v14, ``LLL:my_sitepackage.messages:key``,
does not resolve there, because the editor translates with its own name, which
TYPO3 then prefers. The types of an address, an email address and a phone
number keep reading the overrides of academic_persons.

Every label of the editor, and where it is shown, is listed in
:ref:`configuration-labels`.

Impact
======

On TYPO3 v14, an override of a help text or of a document section heading under
:typoscript:`plugin.tx_academicpersons._LOCAL_LANG` or
:typoscript:`plugin.tx_academicpersons_profileediting._LOCAL_LANG` no longer
has an effect. Move it to
:typoscript:`plugin.tx_academicpersonsedit._LOCAL_LANG`, keyed by the id of the
label in its file:

..  code-block:: typoscript

    plugin.tx_academicpersonsedit._LOCAL_LANG.default.helptext.title = The academic title, as it is printed

..  index:: Frontend, TypoScript, ext:academic_persons_edit
