..  _important-ace-828-academic-persons-edit:

===============================================================
Important: Changes of the editor clear the cached profile pages
===============================================================

Description
===========

A change saved with the profile editor stayed invisible on the cached profile
list and detail pages of `EXT:academic_persons` until the page cache expired or
was cleared by hand. The editor writes the profile, its contracts, their
physical addresses, email addresses and phone numbers, the profile information
and the profile image through Extbase. Only a save in the backend cleared the
cache tags of those pages.

`EXT:academic_persons` now clears the cache tags of the profile list and of the
detail of the profile whenever one of these records is added, changed or
removed through Extbase. The profile editor needs no change for that.

Impact
======

Visitors see a change made in the profile editor on the next request of the
profile list or detail page. The profile card and the plugins for selected
profiles and selected contracts set none of these tags, and their pages are not
cleared.

Affected Installations
======================

Every installation that uses the profile editor together with cached profile
list or detail pages. Nothing has to be done on update.

..  index:: Frontend, ext:academic_persons_edit
