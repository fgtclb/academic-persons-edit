..  _important-email-address-field-label:

===============================================
Important: The e-mail address field has a label
===============================================

Description
===========

The field of an e-mail address in the profile editor and the column of the
e-mail address list translate :xml:`emailAddress.email.label`, and the field
its placeholder from :xml:`emailAddress.email.placeholder`. The language files
declared both units as :xml:`emailAddress.emailAddress.label` and
:xml:`emailAddress.emailAddress.placeholder`, so the field and the column had
no label and the field no placeholder.

The units are now declared under the ids the templates read.

The labels of the frontend user and profile fields that
:composer:`fgtclb/academic-persons` uses in the backend moved into that
extension, see its changelog.

Impact
======

A label override keyed on :xml:`emailAddress.emailAddress.label` or
:xml:`emailAddress.emailAddress.placeholder` never had an effect and has to
use the new ids. A further translation of :file:`locallang.xlf` renames its
units the same way.

Affected Installations
======================

Installations that use the profile editor.

..  index:: Frontend, ext:academic_persons_edit
