.. _breaking-1785675428:

=============================================
Breaking: Removed the profile switcher plugin
=============================================

Description
===========

The content element `academicpersonsedit_profileswitcher` is no longer
registered. It cannot be selected any more, and the content elements that
still use it are removed by an upgrade wizard.

This is a cleanup that was missed in 2.1, not a change of behaviour.

Background
----------

The profile switcher used to be a real Extbase plugin. It let a frontend
user pick one of their profiles, stored that choice in the session, and
the profile editing plugin then worked on the chosen profile.

Version 2.1 restructured the frontend editing and replaced that concept:
the profile editing plugin now lists every profile of the logged in user
and links each one directly, so there is nothing left to switch between.
The restructure removed the plugin registration, both controller actions
(`showProfileSwitch` and `executeProfileSwitch`), the templates and the
whole "active profile" infrastructure around it — the request middleware,
the context aspect and the session handling.

What it did not remove was the content type registration. So since 2.1 the
element has been offered to editors while nothing rendered it: there is no
plugin configuration, no controller action and no TypoScript behind it.
Selecting it produces an empty content element.

The same release also migrated the plugins from `list_type` to `CType`
(see :ref:`breaking-1746708000`) and
carried the dead type along, which is why leftover records exist in two
different shapes today.

Upgrade wizard
--------------

The upgrade wizard **"Remove content elements of the removed
academic_persons_edit profile switcher plugin"**
(`academicPersonsEdit_removeProfileSwitcherContent`) sets the affected
content elements to deleted. It covers both shapes:

* `CType` = `academicpersonsedit_profileswitcher` — records of an
  installation that ran the `list_type` to `CType` migration while it still
  carried this type along.
* `CType` = `list` with `list_type` = `academicpersonsedit_profileswitcher`
  — records of an installation that never ran that migration. TYPO3 v14
  removed the `list_type` column, so this shape is only looked for when the
  column exists.

The records are set to deleted, not removed from the table, so they stay
visible in the recycler and can be restored if an installation wants to
look at them before they are finally discarded.

The `list_type` to `CType` migration no longer migrates this plugin. A
record that has not been migrated yet therefore stays in its legacy shape
until the wizard above deletes it, instead of being moved onto a content
type that does not exist.

Impact
======

The content element cannot be selected any more, and existing elements of
it are deleted by the upgrade wizard.

No rendered output changes. These elements have rendered nothing since 2.1,
on every page they sit on, so removing them cannot change what a visitor
sees. What changes is the backend: the type is gone from the content
element type list, and any element the wizard has not deleted yet is shown
with an "INVALID VALUE" badge in the page module instead of looking like a
working plugin.

Affected Installations
======================

All installations using the `EXT:academic_persons_edit` extension that
still have content elements of the profile switcher plugin — either as
`CType` or as a legacy `list_type` record.

Installations that never used the plugin are not affected. The upgrade
wizard reports nothing to do for them.

Migration
=========

Run the upgrade wizard in the Install Tool, or on the command line:

..  code-block:: bash

    vendor/bin/typo3 upgrade:run academicPersonsEdit_removeProfileSwitcherContent

To see which records are affected before running it:

..  code-block:: sql

    SELECT uid, pid, header, CType FROM tt_content
     WHERE deleted = 0 AND CType = 'academicpersonsedit_profileswitcher';

    -- TYPO3 v13 and below, for records that were never migrated:
    SELECT uid, pid, header, CType, list_type FROM tt_content
     WHERE deleted = 0 AND CType = 'list'
       AND list_type = 'academicpersonsedit_profileswitcher';

There is no replacement to migrate to. The profile editing plugin lists all
profiles of the logged in user by itself, which is what the switcher was
there for.

.. index:: Backend, TCA, NotScanned
