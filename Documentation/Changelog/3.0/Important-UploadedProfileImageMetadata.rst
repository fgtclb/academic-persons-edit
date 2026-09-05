.. _important-uploaded-profile-image-metadata:

=========================================================
Important: An uploaded profile image carries its metadata
=========================================================

Description
===========

A profile image uploaded in the frontend editor now arrives with its metadata
filled instead of empty. Two records are involved, and they are written for
different reasons:

*   The :sql:`sys_file_reference` row of the profile carries the composed name
    of the profile record it belongs to in :sql:`title` and :sql:`alternative`,
    and follows every later change of that name. This is the language-correct
    place for the text — a translation carries its own name, and the file may
    be shared between the languages of a profile. It is written by
    :composer:`fgtclb/academic-persons`, for a backend save as well as for a
    frontend one (changelog entry *Breaking: The profile image translates* of
    that extension).

*   The :sql:`sys_file_metadata` record of the **uploaded file** is filled
    once, by the upload that created the file, and only where it is empty.
    :sql:`alternative` and :sql:`title` are what an installation running
    :composer:`typo3/cms-filemetadata` or
    :composer:`fgtclb/file-required-attributes` reports as missing required
    attributes, and nothing else fills them for a file that was never touched
    in the backend. A value a backend editor maintained on the record is never
    overwritten, and a later change of the profile name does not reach it.

The composed name is the ordered non-empty values of :sql:`title`,
:sql:`first_name`, :sql:`middle_name` and :sql:`last_name`, joined with single
spaces.

Impact
======

An image uploaded through the profile editor no longer shows up in
:guilabel:`File > Filelist` as a file with missing required attributes, and the
name of the person is rendered as ``alt`` and ``title`` text wherever the file
is used — not only where the profile's own reference is rendered.

Nothing has to be configured for it. An installation that maintains file
metadata editorially keeps what it maintained: the upload fills empty fields
only.

Affected Installations
======================

Installations that let people upload a profile image in the frontend, in
particular those requiring file metadata through
:composer:`typo3/cms-filemetadata` or :composer:`fgtclb/file-required-attributes`.

.. index:: FAL, Frontend, ext:academic_persons_edit
