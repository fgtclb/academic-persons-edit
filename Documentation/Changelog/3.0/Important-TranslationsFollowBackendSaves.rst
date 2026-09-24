..  _important-translations-follow-backend-saves:

========================================================
Important: Translations follow backend saves and imports
========================================================

Description
===========

A backend save of a default-language profile, and a DataHandler based
import, now synchronise the translations of the profile into the languages of
``profile.allowedLanguages``, exactly as a frontend edit does. A missing
translation is created, and an existing one takes over the values of the
columns that are excluded from translation. Which saves are announced is
described in the entry "Backend saves announce profile updates" of
`EXT:academic_persons` 3.0.

The site of the synchronisation is the site of the profile's page, taken from
the announcement. A profile on a page that belongs to no site is not
synchronised after a backend save or an import. The global request is
consulted only for an announcement of another origin that carries no site,
such as one dispatched by code written for 2.x.

The profile slug behaves differently by origin:

*   **A backend save keeps its slug.** The editor owns the slug in the backend
    form, through the slug field and its button to regenerate it, and the
    DataHandler has already made it unique. The slug is not regenerated from
    the name after a backend save.
*   **Everywhere else the slug is regenerated from the name and made unique in
    the profile's folder.** That covers a frontend edit, the frontend user
    commands and an import that marks its DataHandler run as one - an
    unmarked import is a backend save. The ``eval`` rules of the slug column
    are applied as the DataHandler applies them, so a second "John Doe" in a
    folder gets ``john-doe-1``. Before, the regenerated slug was never made
    unique, and two profiles of the same name ended up with the same slug.
    Hidden, scheduled and expired profiles are included.
*   **A slug the name still yields stays as it is**: the plain one, unique or
    not, and a suffixed one such as ``john-doe-2`` as long as it is unique.
    Two profiles that share a slug today keep it until the name of one of
    them changes, or until one of them is saved in the backend by an editor
    who may edit the slug field: the DataHandler makes the submitted slug
    unique. A save without the slug field in the form resolves nothing.

Impact
======

Backend editors see the translations follow their saves without a frontend
edit.

A slug set by hand in the backend survives the next backend save, but not the
next frontend edit, marked import or run of ``academic:updateprofiles``, which
announces every profile it runs through: those regenerate it from the name.

A profile whose name changes to one another profile of the folder already
carries gets a suffixed slug, and with it a URL that differs from the one the
same change produced before.

..  index:: Backend, Frontend, ext:academic_persons_edit
