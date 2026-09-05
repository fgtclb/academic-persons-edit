.. _important-multilingual-profile-editing-images:

====================================================
Important: Repair wizard for profile image relations
====================================================

..  seealso::
    The `upgrade chapter of academic_persons
    <https://docs.typo3.org/p/fgtclb/academic-persons/main/en-us/Upgrade/Index.html>`__
    is the order in which the 3.0 changes have to be applied.

Description
===========

With academic_persons 3.0 a translated profile can carry an image of its own
(changelog entry *Breaking: The profile image translates* of that
extension). The relation rows this
extension's pre-3.0 image upload and translation synchronisation wrote by hand
do not always fit that model, and the new upgrade wizard
``academicPersonsEdit_repairLocalizedProfileImages`` repairs the three shapes
that do not:

*   **Duplicate references** on one profile. The profile keeps the file of
    the reference the frontend renders — the first by :sql:`sorting_foreign`,
    then by :sql:`uid`. The relation itself is rewritten: one new reference
    for that file, every old row of the column deleted. A crop or a caption
    that sat on one of those rows is not carried over, because the new row is
    what carries the localization state the column now needs. No file is
    deleted.

*   **A relation counter** in :sql:`tx_academicpersons_domain_model_profile.image`
    that disagrees with the number of references — on a default-language
    profile, on a translation with an image of its own, or on a translation
    that follows the default-language image. The last case is the shape the
    2.x synchronisation left behind, a copied counter without the reference;
    it is repaired by letting the core localize the default-language reference
    into the translation, which is what every later synchronisation does.

*   **A translation carrying a reference of its own** — one that is not a
    localization of the default-language reference — without the ``custom``
    localization state. Without the state the next synchronisation would
    replace that reference with a localization of the default one. The state
    is set; the reference and its file stay.

A translation whose image follows the default language, with a localized
reference or none at all, is the regular shape of the translatable column and
is not touched. A translation whose :sql:`l10n_parent` is missing, deleted or
itself a translation cannot be propagated into; it is repaired as the
independent record it de facto is, and the orphan is written to the log.

Every write of the wizard goes through the TYPO3 DataHandler, so the reference
index, the record history and the localization state are maintained by the
core. The wizard is idempotent and reports nothing to do once the relations
are consistent. It is registered as repeatable, so it is never marked as done
and stays available in the upgrade module and on the command line, however
often it ran and whatever it found.

**The wizard reads and writes live records only.** Workspace versions and
records that exist only inside a workspace are skipped, and the run itself acts
as a backend user in the live workspace. It therefore repairs the same records
whether it is started from the command line or from the upgrade module, and it
does so also when the person who starts it has a workspace selected in the
backend. Repairing a workspace version is not possible and is not attempted:
publish the workspace and run the wizard again.

**No file is deleted, and none is copied.** The wizard rewrites relations
only. Whether a file is still used cannot be answered from
:sql:`sys_file_reference`, which records FAL relations and knows nothing about
an RTE :html:`t3://file` link, a typolink or a file collection — and an
unattended bulk delete on that basis is unrecoverable. A file left without a
relation stays in its storage; the "unused files" report of the install tool
lists such files for a human to judge.

Impact
======

Installations upgrading from 2.x see the wizard in the upgrade module after
the database has been updated. Until it ran, a profile with duplicate
references renders whichever reference sorts first — as before — and a
translation with a stale counter renders no image — as before. Running it
leaves no file behind that was not there before, so it is safe to run on an
installation whose files are also linked from content elements.

Run it with
:bash:`vendor/bin/typo3 upgrade:run academicPersonsEdit_repairLocalizedProfileImages`
or from :guilabel:`Admin Tools > Upgrade > Upgrade Wizard`. Because the wizard
is repeatable, it can be started before the database update as well: it reports
that there is nothing to do and stays available for the run that follows the
schema update.

Affected Installations
======================

Installations that uploaded profile images or synchronised translated
profiles with a 2.x version of this extension.

.. index:: Backend, FAL, Localization, ext:academic_persons_edit
