.. include:: /Includes.rst.txt

.. _breaking-1782285579:

==========================================================================
Breaking: Adapted frontend editing templates for contact record visibility
==========================================================================

Description
===========

To support showing and hiding contact records in the frontend (see
:ref:`feature-1782285577`), the frontend editing templates and partials
rendering the contact records of a contract were changed.

The following Fluid files were modified:

* :file:`Resources/Private/Templates/Contract/Show.html`
* :file:`Resources/Private/Partials/Profile/List/PhysicalAddresses.html`
* :file:`Resources/Private/Partials/Profile/List/EmailAddresses.html`
* :file:`Resources/Private/Partials/Profile/List/PhoneNumbers.html`

The relevant changes are:

* The list partials no longer iterate the contract relation
  (``{contract.physicalAddresses}``, ``{contract.emailAddresses}``,
  ``{contract.phoneNumbers}``). They now iterate dedicated variables
  (``{physicalAddresses}``, ``{emailAddresses}``, ``{phoneNumbers}``)
  assigned by the controller, which also contain hidden records.
* :file:`Contract/Show.html` passes these new variables to the partials.
* Each row gained a show/hide toggle and a "Hidden" badge, and the
  remaining row actions are only rendered for visible records.

Impact
======

Installations that override or extend any of these templates or partials
for project specific styling will not show the new show/hide
functionality and may not list hidden records, until the overrides are
adapted.

Affected Installations
======================

All installations using the `EXT:academic_persons_edit` extension that
override the contract show template or the contact record list partials.

Migration
=========

Re-apply the project specific overrides on top of the updated templates
and partials: switch the list iterations to the new ``{physicalAddresses}``,
``{emailAddresses}`` and ``{phoneNumbers}`` variables and integrate the new
show/hide toggle column.

.. index:: Fluid, Frontend, Template, NotScanned
