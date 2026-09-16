..  _important-frontend-rich-text-editor-offers-links:

===========================================================
Important: Links and readable markup in the frontend editor
===========================================================

Description
===========

The rich text fields of the `academicpersonsedit_profileediting` plugin are
edited with CKEditor 4, configured by
:file:`Resources/Private/TypeScript/frontend/ckeditor.ts`. That configuration
offered no way to add a link, fixed the editor's own interface language to
English, and stored the markup of a whole field on a single line.

Three things changed:

*   The toolbar has a :guilabel:`Link` group. The `standard` CKEditor build the
    template loads already ships the link plugin, so only the group was
    missing. The :guilabel:`Anchor` button is removed, and the advanced tab of
    the link dialog is switched off — it sets ids, styles and classes that the
    public views do not render.

*   The editor's interface language follows the primary subtag of the language
    the document declares instead of always being English. CKEditor falls back
    to English by itself for a language it does not ship.

*   Block elements are written one per line. Without those writer rules a field
    holding several paragraphs or a list was stored as one long line, which is
    unreadable wherever the raw markup is shown — a database dump, a diff, or
    the backend text field.

Impact
======

*   People editing their profile in the frontend can add links in the rich text
    fields, which previously required typing markup that the editor then
    removed.

*   The stored markup of a rich text field gains newlines between block
    elements. The rendered output is unchanged — the rendering collapses
    whitespace between blocks — but a comparison of stored values across this
    change shows a difference for every field that is saved again.

*   Installations that shipped their own copy of the editor configuration to get
    a link button can drop it and use the shipped one.

Affected Installations
======================

Installations using the profile editing plugin
(`academicpersonsedit_profileediting`) whose editors use its rich text fields.

Migration
=========

No configuration change is required, and no stored value is rewritten. A field
keeps its current markup until it is saved again.

An installation that overrides
:file:`Resources/Private/TypeScript/frontend/ckeditor.ts`, or that loads a
JavaScript file of its own instead of the shipped
:file:`Resources/Public/JavaScript/frontend/ckeditor.js`, keeps its own
configuration and is unaffected — including the missing link button it worked
around.

..  index:: Frontend, JavaScript, NotScanned
