.. _feature-1788197189:

========================================================
Feature: Frontend profile edits synchronize translations
========================================================

Description
===========

The frontend editing flow of `EXT:academic_persons_edit` dispatches
:php:`\FGTCLB\AcademicPersons\Event\AfterProfileUpdateEvent` again. The
dispatch had been lost in the 2.x profile editing restructuring: since then the
event fired only when a profile was auto-created on frontend user login, so a
profile edited through the frontend plugins was never synchronised into its
translations, and the profile slug was not regenerated either.

These actions dispatch the event once after the change is persisted: the
generic field update of the profile form, the ``skip_sync`` toggle, the profile
image upload — which is also a replacement — the removal of the profile image,
and the create, update, delete and sort actions of a document section and of
the contacts of a contract. The event always carries the persisted default
language profile; child records resolve their owning profile through the
contract, and an edit of a profile fetched as translation overlay does not
dispatch (synchronisation runs from the default language record only). A
removal that found no image to remove changed nothing and announces nothing.

Impact
======

With ``profile.allowedLanguages`` configured, a frontend edit keeps the
translated profile records in sync again: missing translations are created and
existing ones updated through the record synchronisation of
`EXT:academic_persons`, which routes every write through the TYPO3
:php:`DataHandler`. The profile slug is regenerated on every frontend edit as
well, since the slug listener reacts to the same event.

Installations that left ``profile.allowedLanguages`` empty only regain the slug
regeneration — the synchronisation itself stays a no-op for them.

Affected Installations
======================

Every installation using the frontend editing plugins of
`EXT:academic_persons_edit`. Behaviour beyond the editing flow is unchanged:
profile auto-creation dispatched the event before and still does, and project
side dispatches from own DataHandler hooks are not affected.

.. index:: Frontend, Database, ext:academic_persons_edit
