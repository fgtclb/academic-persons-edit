..  _important-profile-editing-custom-elements-and-prototypes:

======================================================================
Important: The profile editor is Fluid, driven by five custom elements
======================================================================

Description
===========

The :ref:`profile-editing` view of `EXT:academic_persons_edit` is rendered by
Fluid and driven by five custom elements. The elements **control** that markup;
none of them produces any. An integrator therefore changes what the editor looks
like by overriding a Fluid partial, never by replacing a JavaScript module, and
that holds for the two regions whose content only exists at runtime as well.

Those two regions are the editor of one document or contract and the contacts of
one contract: their fields, labels, options and display values come from the
``documentForm`` and ``contractContactForm`` responses, which answer field
descriptors and never HTML. Fluid renders their *shapes* as
``<template data-pe-proto="…">`` blocks - eleven of them in
:file:`Resources/Private/Partials/Profile/Prototypes.html` and five more in the
three :file:`Documents/` partials it renders - and the elements clone one and
fill it.

The four verbs
--------------

A prototype is filled through exactly four attributes, and there is no fifth:

..  list-table::
    :header-rows: 1

    *   - Attribute
        - Meaning
    *   - ``data-pe-slot="key"``
        - The text of the node becomes the value. Never markup: a value that
          contains ``<script>`` is shown, not run.
    *   - ``data-pe-attr="attribute:key …"``
        - Those attributes take the value. A value that is absent or false takes
          the attribute off the clone.
    *   - ``data-pe-when="key"``
        - The node is removed when the value is falsy.
    *   - ``data-pe-list="key"``
        - Where repeated clones go: into the marked element, or in place of it
          when the marker is a ``<template>``.

An override may change every tag, every class and every label of a prototype.
What it may not change is the vocabulary: the ``data-pe-*`` hooks with their
``profile-editing-{uid}-{field}`` id shape, and the slot, condition and list
keys. What it cannot change is the order the elements insert things in and which
slot carries which value - both are TypeScript.

One place spells a control
--------------------------

:file:`Resources/Private/Partials/Profile/Field/Control.html` is the only place
a **field** control of this editor is spelled - a text input, a textarea, a rich
text field, a select and a checkbox. It is rendered inline for the permanent
profile fields, once per type into the prototypes, and through those for every
field of a document or contact editor. Overriding that one file changes every
field control of the editor at once. Two controls stand outside it because they
are not profile fields: the synchronisation switch of :file:`Header.html` and
the :html:`f:form.upload` of :file:`Image/Editor.html`, which belongs to the
Extbase form that carries the upload signature.

The five elements
-----------------

Their names are public API from this release on. The prefix is the extension key
with its underscores replaced, because a custom element name is global.

..  list-table::
    :header-rows: 1

    *   - Element
        - What it owns
    *   - ``<academic-persons-edit-profile-editing>``
        - One editor. It wraps the plugin root, reads its ``data-*`` contract
          once and starts everything below it.
    *   - ``<academic-persons-edit-image-editor>``
        - The image editor over the server rendered upload form. That form stays
          server rendered because only the server can sign its
          ``__trustedProperties``.
    *   - ``<academic-persons-edit-document-editor>``
        - One open document or contract editor, in ``add``, ``view``, ``edit``
          or ``delete`` mode.
    *   - ``<academic-persons-edit-contract-contacts>``
        - The contacts of one contract and the editor of one contact.
    *   - ``<academic-persons-edit-rich-text>``
        - One rich text field and the CKEditor 5 instance on it.

Four events report what an open editor did, and one is dispatched upwards by any
descendant that wants a status shown: ``pe:document-close``,
``pe:document-submit``, ``pe:document-input`` with ``{ name, value }``,
``pe:document-closed`` and ``pe:status`` with ``{ type, message? }``.

**No element opens a shadow root.** A project's stylesheet, the theme's
Bootstrap classes and any CSS written against the rendered markup reach every
control - there is no style encapsulation to work around and no ``::part()`` to
declare.

Impact
======

*   Every part of the editor is overridable in Fluid, including the document
    editor and the contact list of a contract.
*   An override of a partial that carries a ``<template data-pe-proto>`` block
    has to keep the block, its name and its slot keys. An element that cannot
    find its prototype fails loudly: the editor that was being opened is taken
    back out, the error message of the plugin is announced, and the missing
    name is in the browser console. A slot that is not emitted leaves the value
    unwritten.
*   The class names and the ``data-pe-*`` hooks of the rendered controls are as
    much part of the contract as the endpoints are.
*   The element names must not be defined a second time by a project.

Affected Installations
======================

Installations of the :guilabel:`Profile editing` content element of
`EXT:academic_persons_edit` that override one of its Fluid files, or that style
or script the editor against its markup.

..  index:: Fluid, Frontend, JavaScript, Template, ext:academic_persons_edit, NotScanned
