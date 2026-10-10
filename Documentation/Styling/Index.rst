..  index:: ! Styling
..  _styling:

=======
Styling
=======

The profile editing plugin renders its markup with the class system of the
academic extensions and ships no styling for it. The system, the classes of
Bootstrap it keeps and how a site package styles the markup are described once,
in the chapter `Styling of academic_base
<https://docs.typo3.org/p/fgtclb/academic-base/main/en-us/Styling/Index.html>`__.
This chapter shows how the markup of the profile list and the editor is built
up, and which classes the editor script writes.

The editor is an application rather than a page of text: it has more controls
and more states than any other plugin. Its buttons are Bootstrap buttons, so a
site with a Bootstrap theme shows them right away. Where the Bootstrap script
is on the page, the editor shows its messages as a Bootstrap toast and the help
text of a field, the button `ace-control ace-help`, as a Bootstrap popover with
the class `custom-popover`. The layout of the editor, its previews, its dialogs
and its states are the work of the site stylesheet.

..  contents::
    :local:
    :depth: 1

..  _styling-outermost-element:

The outermost elements
======================

..  list-table::
    :header-rows: 1
    :widths: 30 35 35

    *   -   View
        -   Content element type
        -   Outermost element
    *   -   Profile list
        -   `academicpersonsedit_profileediting`
        -   `academic-persons-edit academic-persons-profile-editing-list`
    *   -   Profile editor
        -   `academicpersonsedit_profileediting`
        -   `academic-persons-profile-editing`, inside the element
            :html:`<academic-persons-edit-profile-editing>`

Neither class follows the pattern `academic-<extension>-<plugin>` of the other
plugins exactly: the editor is named after the profile editing without the
`-edit` of the extension, and the profile list carries the class of the
extension, `academic-persons-edit`, next to its own.

..  _styling-list:

The profile list
================

..  code-block:: html

    <section class="academic-persons-edit academic-persons-profile-editing-list" …>
        <h1 class="ace-title">Assigned profiles</h1>
        <div class="ace-table-wrap">
            <table class="ace-table">
                <thead class="ace-header">
                    <tr>
                        <th class="ace-label" scope="col">Profile</th>
                        <th class="ace-label" scope="col">Language</th>
                        <th class="ace-label ace-actions" scope="col">Actions</th>
                    </tr>
                </thead>
                <tbody class="ace-content">
                    <tr class="ace-item">
                        <td class="ace-value">
                            <div class="ace-profile">
                                <img class="ace-image" … />
                                <span class="ace-title ace-name">Dr. Jane Doe</span>
                                <span class="ace-state" data-pe-profile-hidden>Hidden</span>
                            </div>
                        </td>
                        <td class="ace-value ace-language">English</td>
                        <td class="ace-actions">
                            <div class="ace-controls">
                                <a class="ace-link ace-control ace-view btn btn-link" …>…</a>
                                <a class="ace-link ace-control ace-edit btn btn-link" …>…</a>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>

The head and body of the table are `ace-header` and `ace-content`. The table
views of :guilabel:`EXT:academic_persons` and :guilabel:`EXT:academic_bite_jobs`
name them `ace-table-header` and `ace-table-body`, and `ace-head` and
`ace-body`.

A user without a profile sees the message in a :html:`<p class="ace-empty">`
in place of the table. The state `ace-state` is only rendered for a hidden
profile, and a hidden profile has no link to its detail page.

..  _styling-editor:

The editor
==========

..  code-block:: html

    <a class="ace-link ace-back-link" href="…">Back to the list</a>
    <academic-persons-edit-profile-editing>
        <div class="academic-persons-profile-editing" data-academic-persons-profile-editing …>
            <div data-pe-image-editor-target>
                <section class="ace-image-editor">…</section>
            </div>
            <div class="ace-profile-editing-wrapper">
                <header class="ace-header">
                    <h1 class="ace-title">…</h1>
                    <div class="ace-actions">
                        <form class="ace-form" …>
                            <div class="ace-switch">…</div>
                            <div class="ace-message">…</div>
                        </form>
                        <button class="ace-control ace-edit btn btn-outline-secondary btn-sm">…</button>
                    </div>
                </header>
                <div class="ace-content">
                    <div class="row">
                        <div class="col-12 col-lg-4" data-pe-image-preview-column>
                            <div class="ace-image-column">
                                <div class="ace-sticky-image" data-pe-sticky-image>
                                    <section class="ace-section">…</section>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 col-lg-8" data-pe-profile-fields-column>
                            <div class="ace-fields-column">
                                <section class="ace-section">…</section>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-12">
                            <div class="ace-documents">…</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="ace-messages">…</div>
            <dialog class="ace-dialog">…</dialog>
        </div>
    </academic-persons-edit-profile-editing>

*   The link back to the list, and the message an installation without the
    AJAX page type shows, an `ace-message ace-error`, are rendered before the
    editor element, outside `academic-persons-profile-editing`.
*   The header holds the title and the switches of the synchronisation and of
    the visibility of the profile, each an `ace-switch` in a form of its own
    with an `ace-message` for its result.
*   The sections are :html:`<section class="ace-section">` elements with an
    `ace-title`: the image, the personal fields, the about me fields and one
    section per kind of document.
*   The image editor is rendered where it is shown, above the editor, and
    opened by the script.

..  _styling-field:

A field
-------

Every field of the personal and the about me sections shows its value and
switches to a control when it is edited. A single field renders like this:

..  code-block:: html

    <div class="col-12">
        <div class="ace-field-group" data-pe-field-wrapper>
            <div class="ace-preview" …>
                <div class="ace-content">
                    <div class="ace-label">Room <abbr title="…">*</abbr></div>
                    <div class="ace-value">…</div>
                </div>
                <button class="ace-control ace-edit btn btn-sm" …>…</button>
            </div>
            <div class="ace-editor" hidden="hidden" data-pe-field-editor …>
                <div class="ace-header">
                    <label class="ace-label" for="…">Room</label>
                    <!-- the help button, see "The buttons" -->
                </div>
                <div class="ace-controls" data-pe-field-control-group>
                    <!-- the control, a text area inside an ace-control-wrap -->
                    <div class="ace-actions btn-group btn-group-sm" role="group" hidden="hidden" …>
                        <button class="ace-control ace-clear btn btn-outline-danger" …>…</button>
                        <button class="ace-control ace-undo btn btn-outline-secondary" …>…</button>
                        <button class="ace-control ace-save btn btn-success" …>…</button>
                    </div>
                </div>
                <div class="ace-message" role="alert"></div>
            </div>
        </div>
    </div>

*   The `ace-editor` and its `ace-actions` are rendered with the attribute
    :html:`hidden`. The editor script takes it off, so without the script a
    field cannot be edited.
*   A rich text field puts the `ace-actions` into its `ace-header`, next to
    the label, and its control into an `ace-control-wrap`, followed by an
    `ace-counter` when the field has a length limit.
*   Every other row of fields carries `ace-alternate` on its
    `ace-field-group`.
*   A field with a title of its own, a link with its link title for example,
    is edited as a group. The group has the same `ace-preview`. Its `ace-editor` holds a `row` with one grid column per
    field, each an `ace-field` with an `ace-header`, the control and an
    `ace-message`, and then a single `ace-actions` around a
    :html:`<div class="btn-group btn-group-sm">` of the buttons.
*   An empty value is an `ace-empty` inside the `ace-value`. A rich text value
    is a `ce-bodytext`.
*   The required mark is an :html:`<abbr>` without a class. The form partials
    of :guilabel:`EXT:academic_base` give it the class `ace-required`.

..  _styling-documents:

The documents
-------------

Each kind of document, the contracts and the entries of the profile, is an
`ace-section` with an `ace-header` holding its `ace-title` and an add button
`ace-add`, and an `ace-itemlist` of one :html:`<article class="ace-item">` per
document. A document row lays out its values in `ace-cell` elements, each with
an `ace-label` and an `ace-value`, in grid columns, and ends with its
`ace-actions`: its state `ace-state` and the buttons `ace-sort-handle`,
`ace-sort-up`, `ace-sort-down`, `ace-visibility-toggle`, `ace-view-toggle`,
`ace-edit` and `ace-delete`. A section without documents shows an
`ace-empty`. A contract holds its contacts the same way, in a section of its
own with an `ace-itemlist` of `ace-item` rows.

A row ends with an `ace-collapse`. When a document is edited, viewed or
deleted, the script opens the document editor there, a
:html:`<section class="ace-document-editor">` holding an `ace-form` with its
`ace-title`, an `ace-message ace-error`, a confirmation text `ace-copy` before
a deletion, the fields of the document, the contacts of a contract in an
`ace-contacts`, and its buttons. A new document opens its editor in the section
of its kind.

..  _styling-buttons:

The buttons
-----------

Every button is an `ace-control` with the action it performs and the Bootstrap
button classes of its kind:

..  list-table::
    :header-rows: 1
    :widths: 35 65

    *   -   Class
        -   Action
    *   -   `ace-edit`
        -   Open a field, the image or a document for editing.
    *   -   `ace-save`, `ace-apply`
        -   Save a field or a document, apply the open fields. `ace-apply`
            means the application link of a program in
            :guilabel:`EXT:academic_programs`. Scoped by the outermost element,
            a rule for one does not reach the other.
    *   -   `ace-undo`, `ace-clear`, `ace-discard`, `ace-cancel`
        -   Undo a change, clear a field, discard the changes, cancel a
            dialog.
    *   -   `ace-add`, `ace-delete`
        -   Add or delete a document or the image.
    *   -   `ace-sort ace-sort-handle`, `ace-sort-up`, `ace-sort-down`
        -   Move a document. The contacts of a contract have no handle.
    *   -   `ace-visibility-toggle`, `ace-view-toggle`
        -   Show or hide a document on the profile, show its details. The
            contacts of a contract name the same two buttons `ace-visibility`
            and `ace-view`.
    *   -   `ace-close`
        -   Close a message, with the Bootstrap class `btn-close`.
    *   -   `ace-help`
        -   Show the help text of a field. In the form partials of
            :guilabel:`EXT:academic_base`, `ace-help` is the help text itself.

..  _styling-more-classes:

Further classes of the editor
-----------------------------

..  list-table::
    :header-rows: 1
    :widths: 30 70

    *   -   Class
        -   Element
    *   -   `ace-spinner`
        -   An empty :html:`<span>` in the save and delete buttons of the image
            editor, the document editor and the contact editor, shown while a
            request is pending. It has no content, so it is invisible without
            a rule of the site stylesheet, a spinning border for example.
    *   -   `ace-counter`
        -   The character count of a rich text field with a length limit.
    *   -   `ace-alternate`
        -   Every other row of fields, on its `ace-field-group`.
    *   -   `ace-image-editor`, `ace-image-cropper`
        -   The section of the image editor, and the area of the image in it
            that is cropped.
    *   -   `ace-helptext`
        -   A help text of the image editor. Elsewhere, `ace-help` names the
            button of a help text in this editor and the help text itself in
            the form partials of :guilabel:`EXT:academic_base`.
    *   -   `ace-document-editor`, `ace-collapse`, `ace-contacts`, `ace-copy`
        -   The document editor, the place below a row it opens in, the
            contacts of a contract in it, and the confirmation text of a
            deletion, see :ref:`styling-documents`. The dialog about unsaved
            changes uses `ace-copy` for its text as well.
    *   -   `ace-fieldset`
        -   The :html:`<fieldset>` of the personal fields, with a
            `visually-hidden` legend.
    *   -   `ace-control-wrap`
        -   The wrapper of a text area or of a rich text control.
    *   -   `ace-button-label`
        -   The icon inside a field action button, hidden from screen readers.

..  _styling-script-classes:

What the editor script writes
=============================

The editor script works with the `data-pe-*` attributes, apart from the
`ace-message` of a field or a group, which it finds by its class and writes the
message of a refused value into, so an override keeps that class. It writes
these classes, which a site stylesheet should show. The editor does not work
without it: `plugin.tx_academicpersonsedit.assets.js` switches it off only for
a site that brings an editor script of its own, see
:ref:`configuration-javascript`.

..  list-table::
    :header-rows: 1
    :widths: 40 60

    *   -   Class
        -   Written on
    *   -   `is-invalid`
        -   The control of a field whose value was rejected, and its editor
            element: the `ace-editor` of the field or of its group, for a rich
            text field the `ace-control-wrap`. On a switch of the header, the
            checkbox whose change was rejected. This is the name of Bootstrap
            for the state the form partials of :guilabel:`EXT:academic_base`
            call `invalid`.
    *   -   `show`, `showing`, and one of `ace-message-success`,
            `ace-message-info`, `ace-message-warning`, `ace-message-danger`
        -   The `ace-message` of the `ace-messages` that reports a result. With
            the Bootstrap toast script on the page, the toast shows and hides
            it. Without it, the editor sets `show`, so the site stylesheet
            hides an `ace-message` there that is not `show`.
    *   -   `active`
        -   The button of the header that opens all fields, while they are
            open, with :html:`aria-pressed="true"`.
    *   -   `ace-empty`, `ce-bodytext`
        -   The value of a field after it was saved, as the template renders
            it.
    *   -   `ace-limit-exceeded`
        -   The `ace-counter` of a rich text field over its length limit.
    *   -   `is-dragging`, `is-drag-active`, `is-drop-before`,
            `is-drop-after`, `is-drop-at-end`
        -   The dragged document row, its list, and the place it would drop
            to.
    *   -   `is-image-closing`
        -   The editor while the image editor closes.
    *   -   `col-lg-4`, `col-lg-8`, `col-lg-12`
        -   The columns of the image and the fields, which the image editor
            widens and narrows.
    *   -   `academic-persons-profile-editing-image-editor-enter-active`,
            `-enter-from`, `-leave-active`, `-leave-to`, and the same with
            `academic-persons-profile-editing-document-collapse`
        -   The `ace-image-editor` and an `ace-document-editor` while they
            open and close. The script waits for the transition the stylesheet
            declares on them, and goes on at once when there is none.

..  _styling-example:

An example
==========

Rules in the stylesheet of the site package, scoped by the outermost element:

..  code-block:: css

    .academic-persons-profile-editing .ace-messages {
        position: fixed;
        right: 1rem;
        bottom: 1rem;
    }

    .academic-persons-profile-editing .ace-messages .ace-message:not(.show) {
        display: none;
    }

    .academic-persons-profile-editing .ace-message-danger {
        border-left: 4px solid #dc3545;
    }

    .academic-persons-profile-editing .ace-item.is-dragging {
        opacity: 0.5;
    }

The development instances style the editor with the partial
`_academic-persons-edit.scss
<https://github.com/fgtclb/academic-extensions/blob/main/packages-dev/dev-site/Resources/Private/Scss/frontend/_academic-persons-edit.scss>`__
of the site package `academics_dev_site`, a complete example to copy from,
including the transitions above.

To change the markup itself, override the partials as described in
:ref:`templates`. The editor script keeps working with other classes as long
as the `data-pe-*` attributes stay where the templates put them.
