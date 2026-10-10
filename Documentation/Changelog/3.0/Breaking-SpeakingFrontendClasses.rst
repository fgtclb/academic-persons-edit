..  _breaking-persons-edit-speaking-frontend-classes:

==========================================================
Breaking: Profile editing templates carry speaking classes
==========================================================

Description
===========

The templates of the profile editing view carried the classes of the theme they
were developed with - toasts, badges, alerts, spinners, form controls, spacing,
display and colour utilities - next to block and element classes of the
extension such as `academic-persons-profile-editing__field`. They carry
speaking classes now, named after what an element is and shared by all academic
extensions: `ace-section`, `ace-preview`, `ace-editor`, `ace-label`,
`ace-value`, `ace-control` and so on. The outermost elements keep their
classes, `academic-persons-profile-editing` for the editor and
`academic-persons-edit academic-persons-profile-editing-list` for the profile
list. The classes of Bootstrap that a site builds on stay on purpose where the
templates render them: the grid classes `row` and `col-*`, the button classes
`btn`, `btn-*`, `btn-group` and `btn-close`, and `visually-hidden`. No other
theme class is rendered any more, `rounded-0` included, and the extension
ships no styles for the new classes, see
:ref:`breaking-profile-editing-ships-no-stylesheet`.

The scripts of the editor follow. They show and hide markup through its
attribute :html:`hidden` instead of the class `d-none`, and find the nodes
they write into by `data-pe-*` attributes instead of classes. They still read
one class of the markup, the `ace-message` of a field or a group for the
message of a refused value.

The column *Before* lists the markup of the development versions of 3.0,
because they were installed and styled. The editing plugin of version 2 is
replaced as a whole, and its markup with it, see
:ref:`breaking-replaced-profile-editing-plugin`.

..  list-table::
    :header-rows: 1

    *   -   Element
        -   Before
        -   After
    *   -   Layout of the editor
        -   `academic-persons-profile-editing container-fluid px-0`, a
            `row gx-lg-5 gy-5 align-items-stretch`, the columns
            `academic-persons-profile-editing__image-preview-column` and
            `academic-persons-profile-editing__profile-fields-column`, the
            image in a `sticky-top`
        -   `academic-persons-profile-editing` with
            `ace-profile-editing-wrapper` and `ace-content` around the `row`,
            `ace-image-column`, `ace-sticky-image`, `ace-fields-column` and
            `ace-documents`, the columns marked `data-pe-image-preview-column`
            and `data-pe-profile-fields-column`
    *   -   Link back to the list
        -   `back-link d-inline-flex align-items-center gap-2 mb-4`
        -   `ace-link ace-back-link`
    *   -   Message of a missing page type
        -   `alert alert-danger`
        -   `ace-message ace-error`
    *   -   Header with the switches
        -   `d-flex flex-column flex-lg-row …`, a heading `h2 fw-bolder`, the
            forms `academic-persons-profile-editing__sync-form` and
            `__visibility-form`, `form-check form-switch form-check-lg`,
            the checkboxes `form-check-input` with
            `academic-persons-profile-editing__sync-checkbox` or
            `__visibility-checkbox`, `form-check-label` and `invalid-feedback`
        -   `ace-header` with `ace-title` and `ace-actions`, every switch an
            `ace-form` with `ace-switch`, `ace-control ace-checkbox`,
            `ace-label` and `ace-message`, the checkboxes marked
            `data-pe-sync-checkbox` and `data-pe-visibility-checkbox`
    *   -   Sections of the editor
        -   headings `h2 fw-normal mb-4` or `display-6 fw-normal`, the forms
            `academic-persons-profile-editing__form`, a fieldset
            `border-0 p-0 m-0`
        -   `ace-section` with `ace-title`, `ace-form` and `ace-fieldset`
    *   -   Row of a field
        -   `rounded-0 p-3 p-lg-4`, `rounded-1` in place of `rounded-0` for a
            checkbox, a select and a group, every other row
            `bg-body-tertiary`
        -   `ace-field-group`, every other row `ace-alternate`
    *   -   Value of a field
        -   `d-flex align-items-start gap-3` with `flex-grow-1 overflow-hidden`,
            `fw-semibold d-flex …` and `text-break`, an empty value
            `text-body-secondary`, the edit button
            `btn rounded-0 btn-sm border-0 p-1 text-body flex-shrink-0`
        -   `ace-preview` with `ace-content`, `ace-label` and `ace-value`, an
            empty value `ace-empty`, the edit button
            `ace-control ace-edit btn btn-sm`
    *   -   Editor of a field
        -   `d-none mt-3`, the label `form-label fw-semibold`, the counter
            `form-text text-end`, the message `invalid-feedback`
        -   `ace-editor` with the attribute :html:`hidden`, the label
            `ace-label` in an `ace-header`, the counter `ace-counter`, the
            message `ace-message`
    *   -   Control of a field
        -   `form-control form-control-sm`, `form-select form-select-sm` or
            `form-check-input`, each with
            `academic-persons-profile-editing__field`
        -   `ace-control` with `ace-text`, `ace-textarea`, `ace-select` or
            `ace-checkbox`, marked `data-pe-field-control`
    *   -   Actions of a field
        -   `btn-group btn-group-sm d-none flex-shrink-0`, the icon of a
            button in `fs-5 lh-1`
        -   `ace-actions btn-group btn-group-sm` with the attribute
            :html:`hidden`, the buttons `ace-control` with `ace-clear`,
            `ace-undo` or `ace-save`, the icon in `ace-button-label`
    *   -   Actions of full form editing
        -   `col-12 d-flex justify-content-end align-items-center gap-2 pt-3 border-top`
        -   `ace-actions`, the buttons `ace-apply`, `ace-undo` and
            `ace-discard`
    *   -   Help text and managed field
        -   `btn rounded-0 btn-link link-info p-0 ms-2 mb-1`, the badge
            `badge rounded-pill border text-body-secondary bg-body fw-medium`
        -   `ace-control ace-help btn btn-link`, the badge `ace-state`
    *   -   Image of the profile
        -   a heading `h2 fw-normal mb-4`, `figure mb-0`, `picture d-block`,
            the image `img-fluid w-100 object-fit-cover`
        -   `ace-section` with `ace-title`, `ace-figure`, `ace-picture` and
            `ace-image`
    *   -   Image editor
        -   `academic-persons-profile-editing__image-editor border p-3 p-lg-4 mb-5`
            with `__image-editor-content`, the form `__image-form`, the
            cropper `__image-cropper`, help texts `text-body-secondary`, the
            message `alert alert-danger mt-3 mb-0`, the busy indicator
            `spinner-border spinner-border-sm me-1`
        -   `ace-image-editor` with `ace-content`, the form `ace-form` marked
            `data-pe-image-form`, `ace-image-cropper`, `ace-helptext`,
            `ace-message ace-error` and `ace-spinner`
    *   -   Section of documents
        -   `mt-5`, a heading `display-6 fw-normal`, the add button
            `btn rounded-0 btn-sm btn-link p-2`, the empty state
            `bg-body-tertiary py-2 ps-3 small text-body-secondary`, the column
            heads `row g-0 … d-none` with `d-md-flex`
        -   `ace-section` with `ace-header`, `ace-title` and
            `ace-control ace-add btn btn-sm btn-link`, the empty state
            `ace-empty`, the column heads `ace-header ace-label`, both shown
            and hidden through :html:`hidden`
    *   -   Row of a document
        -   `row g-0 align-items-center border-bottom py-2 py-lg-3 ps-3`, the
            values `col-* py-1 pe-md-3 text-break` with a label
            `d-md-none fw-semibold mb-1`
        -   `<article class="ace-item">` around the `row`, the values
            `ace-cell` with `ace-label` and `ace-value` in their columns, then
            `ace-collapse`
    *   -   Actions of a row
        -   `col-12 col-md-auto flex-shrink-0 d-flex …`, the state
            `badge rounded-pill border text-body-secondary bg-body fw-medium`,
            the buttons `btn rounded-0 btn-sm btn-link text-body p-2`
        -   `ace-actions` with `ace-state` and `ace-controls`, the buttons
            `ace-control` with `ace-sort ace-sort-handle`, `ace-sort-up`,
            `ace-sort-down`, `ace-visibility-toggle`, `ace-view-toggle`,
            `ace-edit` or `ace-delete`, in the contacts of a contract
            `ace-visibility` and `ace-view`
    *   -   Document editor
        -   `academic-persons-profile-editing__document-collapse border bg-body p-3 p-lg-4 my-3`
            with `__document-collapse-content`, a heading `display-6`, the
            message `alert alert-danger`, the busy indicator `spinner-border`
        -   `ace-document-editor` with `ace-content`, `ace-form`,
            `ace-title`, `ace-message ace-error`, `ace-copy`, `ace-contacts`
            and `ace-spinner`
    *   -   Contacts of a contract and their editor
        -   `pt-4 mt-4` with a heading `h4 mb-0`, rows
            `row g-0 align-items-center border-bottom py-2 ps-3`, the editor
            `border bg-body-tertiary p-3 p-lg-4` with `alert alert-danger`
        -   `ace-section` with `ace-header` and `ace-title`, rows `ace-item`,
            the editor `ace-item` with `ace-title`, `ace-message ace-error`,
            `ace-copy`, `ace-actions` and `ace-spinner`
    *   -   Status toast
        -   `toast-container position-fixed bottom-0 end-0 p-3`, `toast` with
            `toast-header`, `status-title`, `btn-close rounded-0` and
            `toast-body status-message`, the severity `bg-danger`,
            `bg-success`, `bg-info` or `bg-warning`
        -   `ace-messages`, `ace-message` with `ace-header`, `ace-title`,
            `ace-control ace-close btn-close` and `ace-content`, the severity
            `ace-message-danger`, `ace-message-success`, `ace-message-info`
            or `ace-message-warning`
    *   -   Dialog about unsaved changes
        -   `academic-persons-profile-editing__dialog rounded-0 border-0 p-0 shadow`
            with `p-4`, a heading `h4 mb-3` and the buttons
            `btn rounded-0 btn-*`
        -   `ace-dialog` with `ace-content`, `ace-title`, `ace-copy` and
            `ace-actions`, the buttons `ace-cancel`, `ace-discard` and
            `ace-save`
    *   -   Profile list
        -   a heading `h2 fw-normal mb-4`, `table-responsive` around a
            `table table-striped align-middle mb-0`, the image
            `academic-persons-profile-editing-list__image`, the name
            `fw-medium text-break`, the state `badge text-bg-secondary`, the
            actions `btn rounded-0 btn-link`
        -   `ace-title`, `ace-table-wrap` around an `ace-table` with
            `ace-header` and `ace-content`, rows `ace-item`, `ace-profile`
            with `ace-image`, `ace-title ace-name` and `ace-state`, the
            actions `ace-controls` with `ace-link ace-control ace-view` or
            `ace-edit` and `btn btn-link`

The state classes the scripts write change with the markup:

..  list-table::
    :header-rows: 1

    *   -   State
        -   Before
        -   After
    *   -   Hidden element
        -   `d-none`, the column heads of a document list `d-md-flex`
        -   the attribute :html:`hidden`
    *   -   Severity of the status toast
        -   `bg-danger`, `bg-success`, `bg-info`, `bg-warning`
        -   `ace-message-danger`, `ace-message-success`, `ace-message-info`,
            `ace-message-warning`
    *   -   Counter over its limit
        -   `text-danger`
        -   `ace-limit-exceeded`
    *   -   Empty preview
        -   `text-body-secondary`
        -   `ace-empty`
    *   -   Rich text preview with a value
        -   none
        -   `ce-bodytext`
    *   -   Refused field
        -   `is-invalid`, the message in the `invalid-feedback` of the field
            or of its `form-check`
        -   `is-invalid`, the message in the `ace-message` of the field or of
            its group

The classes `is-invalid`, `active`, `show`, `showing`, the drag classes
`is-drag-active`, `is-dragging`, `is-drop-before`, `is-drop-after` and
`is-drop-at-end`, `is-image-closing`, the column widths `col-lg-*` and the
transition classes keep their names.

Impact
======

*   A site stylesheet that styles the profile editing view or the profile list
    through the classes of the column *Before* no longer matches.
*   A theme stylesheet that styled the view through `toast`, `badge`, `alert`,
    `form-control`, `form-select` or `form-check` no longer reaches it. Where
    the Bootstrap toast script is on the page, it still shows and hides the
    status toast.
*   An override that keeps `d-none` on the editor of a field, its actions, an
    empty state or the column heads of a document list keeps them hidden for
    good: the scripts toggle the attribute :html:`hidden` only.
*   An override that drops `ace-message` from a field or a group loses the
    message of a refused value.

Affected Installations
======================

Every installation that renders the profile editing plugin and styles it or
overrides its templates.

Migration
=========

#.  Move the selectors of the site stylesheet from the classes in the
    column *Before* to those in the column *After*. The chapter
    :ref:`Styling <styling>` shows how the markup of the profile list and the
    editor is built up, and the stylesheet of the development instances is a
    complete example, see :ref:`breaking-profile-editing-ships-no-stylesheet`.
#.  A template override is compared with the shipped template of 3.0 and
    adopts its markup. It keeps the `data-pe-*` attributes, the attribute
    :html:`hidden` where the shipped template renders it, and the
    `ace-message` of a field or a group, see
    :ref:`important-profile-editing-custom-elements-and-prototypes`.
#.  Flush the TYPO3 caches, so the Fluid template cache is rebuilt.

..  index:: Fluid, Frontend, JavaScript, ext:academic_persons_edit
