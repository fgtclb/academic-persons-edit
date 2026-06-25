.. include:: /Includes.rst.txt

.. _feature-1782285577:

==============================================================
Feature: Show and hide contact records in the frontend editing
==============================================================

Description
===========

Profile owners can now control which of their contact records are
displayed on the public profile directly from the frontend editing
plugin of `EXT:academic_persons_edit`.

For each physical address, email address and phone number a new
"show"/"hide" toggle is available in the contract editing view. Hiding
a record sets the standard TYPO3 `hidden` enable field on the record,
which removes it from the public profile display while keeping the data
intact.

The frontend editing list always shows hidden records (marked with a
"Hidden" badge) so that they can be made visible again at any time. The
other actions (view, edit, sort, delete) are only offered for visible
records — a hidden record has to be shown again before it can be
edited.

Records created and updated by the profile synchronization
(`EXT:academic_persons` create/update profile commands) can be hidden
as well: the synchronization keeps updating their data, but no longer
changes their visibility once a profile owner has decided to hide them.

..  note::

    This feature changes the frontend editing Fluid templates and is
    therefore breaking for projects that override them. See
    :ref:`breaking-1782285579` for the required adaptions.

Impact
======

Profile owners using the `EXT:academic_persons_edit` frontend editing
gain fine-grained control over the visibility of their:

* physical addresses
* email addresses
* phone numbers

without having to delete records they only want to hide temporarily.

Affected Installations
======================

All installations using the `EXT:academic_persons_edit` extension
starting with version 2.4.

Migration
=========

No migration is required. Existing records keep their current
visibility (they are visible unless they were already hidden in the
backend). The new toggle can be used immediately.

.. index:: Frontend, Template
