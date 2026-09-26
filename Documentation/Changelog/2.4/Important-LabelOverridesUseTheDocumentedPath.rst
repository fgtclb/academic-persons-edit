..  _important-label-overrides-use-the-documented-path:

============================================================
Important: Label overrides are read from the documented path
============================================================

Description
===========

The templates of this extension translate their labels with the extension name
:html:`AcademicPersonsEdit` instead of the extension key
:html:`academic_persons_edit`. TYPO3 v12 and v13 build the TypoScript path of
:typoscript:`_LOCAL_LANG` from that name as it is given, so they read label
overrides from :typoscript:`plugin.tx_academic_persons_edit`. They now read
them from :typoscript:`plugin.tx_academicpersonsedit` and
:typoscript:`plugin.tx_academicpersonsedit_<plugin>`, the paths the TYPO3
documentation names and TYPO3 v14 reads anyway.

The flash messages and the options of the select fields, which the editor
translates in PHP, follow the same rule. The options read their overrides from
:typoscript:`plugin.tx_persons_edit` until now, a path of no extension at all;
an override there moves as well.

Every label of the extension, and where it is shown, is listed in
:ref:`configuration-labels`.

Impact
======

On TYPO3 v12 and v13, a label override under
:typoscript:`plugin.tx_academic_persons_edit._LOCAL_LANG` no longer has an
effect. Move it to :typoscript:`plugin.tx_academicpersonsedit._LOCAL_LANG`, or
to the path of the one plugin it is meant for:

..  code-block:: typoscript

    plugin.tx_academicpersonsedit._LOCAL_LANG.default.list.profile = Profile
    plugin.tx_academicpersonsedit_profileediting._LOCAL_LANG.default.list.profile = Profile

..  index:: Frontend, Fluid, TypoScript, ext:academic_persons_edit
