..  _profile-editing:

===============
Profile editing
===============

The :guilabel:`Profile editing` content element first renders all
profiles assigned to the authenticated frontend user. Its :guilabel:`Edit`
action opens the selected profile in Profile editing; :guilabel:`View` opens
the public ``academic_persons`` Detail plugin on the page configured through
``plugin.tx_academicpersons.detailPid``. This is the same target setting used
by the Academic Persons list views. The shipped
:file:`Resources/Private/Templates/Profile/Index.html` editor template
contains four independently persisted areas:

*   profile fields using the generic JSON update endpoint,
*   the synchronization checkbox using its own JSON endpoint,
*   the expanding profile-image editor using dedicated upload and delete
    endpoints, and
*   the structured document sections — contracts, their contacts and the
    profile-information collections — each with their own form, create, update,
    delete and sort endpoints.

Fluid renders the markup of that view, and five custom elements own its
behavior. The entry point is maintained as TypeScript in
:file:`Resources/Private/TypeScript/frontend/profile.ts`, from which the
frontend build generates
:file:`Resources/Public/JavaScript/frontend/profile.js`; it defines those
elements and does nothing else. Every editor on the page is an
``<academic-persons-edit-profile-editing>`` element that starts itself when the
browser upgrades it, so a second plugin on the page — or one that is loaded
into the page later — needs no start-up scan and cannot be started twice.

Typed feature modules below
:file:`Resources/Private/TypeScript/frontend/profile/` separately own common
requests and status output, field editing, documents, rich text,
synchronization, image editing and sticky positioning; the five elements below
:file:`…/profile/elements/` drive them. All of them are plain custom elements
that depend on no user interface library, and none of them renders markup: each
controls what Fluid rendered. No framework is bundled with the extension, and
none is loaded from anywhere else either.

All changes are saved through AJAX without reloading the page. Editable fields
are discovered across the complete component root, even when the responsive
page layout places them in separate ``data-pe-fields-form`` elements. Every
element renders into the light DOM — none of them has a shadow root — so a
project's stylesheet reaches every control they render, exactly as it reaches
the Fluid-rendered ones. The inline collapse targets, the two status regions
and the client-side templates live in the same component scope.

Assigned profile overview
=========================

``ProfileController::listAction()`` is the default action. It uses the
authenticated frontend-user relation and passes ``{profileListItems}`` to
:file:`Resources/Private/Templates/Profile/List.html`. Every item contains
the Profile object and the title of its ``sys_language_uid`` from the current
site configuration. The profile column contains a square thumbnail or the
shipped placeholder followed by the complete name. :guilabel:`Edit` passes the
explicit ``profileUid`` to ``indexAction()``; the action resolves that UID again
through the authenticated user's assigned profiles before rendering anything.
An unassigned or manipulated UID receives the ``403`` access denied response of
the site, the same one an unauthenticated visitor gets — raised as a propagated
response rather than returned by the action, because on TYPO3 v13 the status
code of an Extbase plugin response never reaches the frontend response.

The :guilabel:`View` URI uses extension ``academicpersons``, controller
``Profile``, action ``detail`` and plugin ``Detail``. Its target page comes
from ``plugin.tx_academicpersons.detailPid`` and must contain the
``academicpersons_detail`` content element. The ProfileEditing TypoScript copies
the Academic Persons constant into ``plugin.tx_academicpersonsedit.settings``;
there is no separate editor-specific detail PID. Importing
:file:`EXT:academic_persons/Configuration/Routes/Detail.yaml` is optional and
turns the query-string link into a speaking URI.

View data
=========

The controller assigns the following variables to the Fluid template:

..  list-table::
    :header-rows: 1

    *   - Variable
        - Description
    *   - ``{profile}``
        - Explicitly selected profile after its assignment to the authenticated
          frontend user has been verified.
    *   - ``{profileListItems}``
        - Assigned Profiles and their readable site-language labels. This
          variable belongs to the default list action and list template.
    *   - ``{profileSections}``
        - Ordered Fluid view models generated from :yaml:`profile`. Every
          section contains regular fields and inserted composite special items.
    *   - ``{specialFields}``
        - Typed :yaml:`special` components, including composed title, image and
          synchronization metadata.
    *   - ``{profileFieldOptions}``
        - Options for every configured :yaml:`renderType: select` field. The
          option source remains the matching Profile TCA field.
    *   - ``{documentSections}``
        - Ordered structured-section view models derived from
          :yaml:`documentSections`, including mapping, read-only and validation
          metadata plus the typed records.
    *   - ``{imageAllowedMimeTypes}``
        - Comma-separated MIME types accepted by the image input. The server
          validates the configured values independently.
    *   - ``{editorLanguage}``
        - The language code of the current site language, lower-cased and
          without its region. CKEditor is initialized with it; an empty string
          when the request carries no site language.
    *   - ``{data}`` and ``{record}``
        - Current content element data and its record object.

View structure
==============

The template is intentionally a composition root. The main partial groups are:

..  list-table::
    :header-rows: 1

    *   - Partial group
        - Responsibility
    *   - ``Image/Card.html`` and ``Image/Editor.html``
        - :guilabel:`Profile image` heading above the sticky page preview,
          animated full-width editor, file selection, crop preview and image
          actions.
    *   - ``Profile/Personal.html``, ``Profile/About.html`` and
          ``Profile/Fields.html``
        - Personal-data and about-section boundaries plus ordered iteration of
          settings-driven profile fields.
    *   - ``Field/Editable.html``, ``Field/Control.html``,
          ``Field/Select.html``, ``Field/Checkbox.html`` and
          ``Field/Group.html``
        - ``renderType``-driven controls, previews, grouped values and
          persistence actions.
    *   - ``Documents/Sections.html``, ``Documents/Contract.html``,
          ``Documents/ProfileInformation.html`` and their ``*Row.html``
        - Structured document sections, their rows, the row actions and the
          hidden row prototype that is cloned after a create.
    *   - ``Documents/Editor.html``
        - The panel of the document editor, as a prototype the element clones;
          see :ref:`profile-editing-document-editor`.
    *   - ``Header.html`` and ``StatusToast.html``
        - Complete profile-name heading with synchronization/edit-all controls,
          and scoped status output. The status output is two live regions, one
          assertive and one polite, because a region's politeness cannot be
          changed reliably once it is in the accessibility tree. The personal
          form renders its own :guilabel:`Personal data` heading.
          ``ButtonTemplates.html`` remains a compatibility fallback for
          existing template overrides; the shipped read view does not use its
          button-shaped value controls.

Layout and responsive behavior
==============================

The view uses Bootstrap 5 grid, spacing, typography, background, positioning
and form utilities; the Fluid templates contain no inline style declarations.
:file:`Resources/Public/Css/frontend/profile-editing.css` is generated from
:file:`Resources/Private/Scss/frontend/profile-editing.scss` by the repository
build and holds only what Bootstrap cannot express: the ``display: block`` the
custom elements need, a ``[hidden]`` rule that outranks Bootstrap's display
utilities, three corrections to a surrounding theme (a ``.section`` overflow,
one frame spacing variable, the stacking of the sticky card), the drag states
of a sortable list and the enter/leave classes of the two editor transitions.
All Bootstrap button controls of the shipped editor carry ``rounded-0`` so
their corners remain square.

Above the grid, the complete profile name and the synchronization/edit-all
controls share one responsive header row. The controls wrap below the name on
narrow viewports. On ``lg`` and larger viewports the first content row uses a
``4 / 8`` column split.
The profile image block has ``sticky-top`` so the image and its edit action
stay visible while the profile data scrolls. Below ``lg`` both columns stack in
document order. The about section follows the complete first row and therefore
never overlaps the sticky column.

At runtime ``initializeStickyImageOffset()`` reads the visible outer height of
``#page-header.navbar-fixed-top`` through ``getBoundingClientRect().height``,
adds a 10-pixel visual gap and assigns the result to the ``top`` property of
``data-pe-sticky-image``. The measurement itself is
``observePageHeaderOffset()`` of ``academic_persons``, which the public detail
view uses for its own sticky navigation — one implementation, two callers. A
``ResizeObserver`` watches the header's ``border-box`` and keeps the offset
synchronized whenever the navbar changes height, including height or padding
changes caused by a scroll-dependent header state. Environments without it use
the window ``resize`` event as a fallback. If the matching fixed page header
is absent, Bootstrap's regular ``sticky-top`` value remains in control.

The two columns live in their own ``align-items-stretch`` row. The image column
inherits the stretched cross-axis size, giving the sticky image a containing
block as tall as the adjacent profile data. The full-width about section keeps
its own ``col-12`` in a separate sibling row below it.

The complete profile name is the page's ``h1`` above both columns. Both Fluid and
JavaScript use the ordered ``fields`` list from :yaml:`special.title`. Fluid
renders the initial name; ``data-pe-profile-name-field-ids`` lets JavaScript
recompose the same name after a successful update without reloading the page.

Profile values are rendered as readable text rows with alternating
``bg-body-tertiary`` surfaces. The only read-mode action is a borderless pencil
button with an accessible label. Name components and the URL/title pair of
each link share one preview row and open as one editing group.
The special name editor retains the established responsive grid (academic
title / first name at ``4 / 8`` and middle / last name at ``6 / 6``) without
putting layout metadata into YAML.

Settings-driven controls
========================

``ProfileSectionProvider`` converts the typed :yaml:`profile` and
:yaml:`special` settings into an ordered Fluid view model. Section placement
itself remains explicit in :file:`Templates/Profile/Index.html`: the
template decides where ``profileSections.information`` and
``profileSections.aboutme`` appear. It does not enumerate their individual
fields.

``Profile/Fields.html`` chooses the field control solely from ``renderType``.
Option values are not duplicated in YAML: for a select field,
``ProfileFieldOptionsService`` reads the corresponding Profile TCA items.
Preview behavior likewise follows the rendered control type.
Removing a special component removes its controls; marking the image or
synchronization special ``readonly`` or ``disabled`` also blocks the matching
write endpoint, not just its Fluid control.

A translated profile initially uses TYPO3's ``parent`` localization state for
the image and follows changes to the default-language image. The first upload
or deletion in that frontend language changes the image field to ``custom``;
the translated profile then owns its FAL reference and can select, replace or
delete its image independently. The image controls therefore remain available
while editing a translated profile; the endpoint still verifies that the
requested profile is assigned to the authenticated frontend user.

The shared
:file:`Resources/Private/Partials/Profile/Field/Editable.html` partial
composes three focused, reusable partials:

*   :file:`Field/Preview.html` renders the text preview and pencil trigger,
*   :file:`Field/Control.html` renders either ``f:form.textfield`` or
    ``f:form.textarea``, including the CKEditor hook, and
*   :file:`Field/Actions.html` renders delete, cancel and save.

:file:`Field/Group.html` composes related textfields below one preview. Its
``data-pe-field-ids`` value defines which fields open, cancel and save together;
``data-pe-display-field-ids`` and ``data-pe-display-mode`` control whether the
preview joins values (the name) or uses the first non-empty value (link title
falling back to its URL).

Helptext buttons are edit controls: field and group previews do not render
them. They appear after the corresponding field editor is opened. Document
helptexts follow the same rule and are present in add/edit forms, but not in a
document editor opened in view mode.

``validation.inputType`` supplies the concrete HTML input type. Checkbox
controls save immediately on change. Select controls use the same clear, undo
and save actions as text fields. The synchronization switch in
:file:`Header.html` is persisted through its own endpoint.

..  list-table::
    :header-rows: 1

    *   - Requirement
        - Implementation
    *   - Telephone number
        - Textfield with ``inputType: 'tel'`` or a validation input type of
          ``tel``.
    *   - Website address
        - Textfield with ``inputType: 'url'`` or a validation input type of
          ``url``.
    *   - Free text input
        - Default textfield.
    *   - Select
        - :file:`Field/Select.html`; options come from the configured
          field's TCA items and changes save immediately.
    *   - Checkbox
        - :file:`Field/Checkbox.html` for direct Profile flags. The
          synchronization special uses its own form and endpoint.
    *   - Multiline text
        - Field partial with ``textarea: true``. Passing ``richText: true``
          additionally turns the textarea into the TYPO3 CKEditor 5 when the
          field is opened.

..  _profile-editing-full-form:

Two editing modes
=================

The profile fields have two mutually exclusive editing modes, and the controls
say which one is active.

**Single field.** The pencil beside a value opens that field — or, for a group,
that group — and the three buttons of :file:`Field/Actions.html` act on it:
clear empties the control and keeps it open, undo restores the saved value and
closes it, save posts what changed. This is the mode described everywhere else
on this page, and the editors of the document and contract panels behave the
same way.

**Full form.** :guilabel:`Edit all` opens every editable field of the profile
at once. While it is open, every per-field and per-group button group is hidden
— including the undo beside an autosaving checkbox — and one bar governs the
whole form. :file:`Field/FormActions.html` renders it at the end of every
``data-pe-fields-form``, and the shipped template has two of those (personal
data and about me); each bar acts on all of them.

..  list-table::
    :header-rows: 1

    *   - Control
        - Behavior
    *   - :guilabel:`Apply`
        - Posts every changed field in one request to the generic field update
          endpoint below. On success the stored values are written back into
          the controls, the previews and the name heading, the form closes and
          the focus returns to :guilabel:`Edit all`.
    *   - :guilabel:`Undo`
        - Restores every field to the value that is stored and keeps the form
          open. Announced in the polite live region.
    *   - :guilabel:`Discard`
        - Restores every field and closes the form. Pressing
          :guilabel:`Edit all` again does the same.

``updateAction()`` validates the complete field map before it persists anything,
so an apply either stores all of it or none of it. A refusal reverts nothing:
the entered values stay where they were entered, each refused field is marked
and described by its own message, the caret goes to the first of them, and the
refusal is announced once rather than once per field.

While an apply is on its way to the server, :guilabel:`Undo`,
:guilabel:`Discard`, :guilabel:`Edit all` and :kbd:`Escape` do nothing. The
request cannot be taken back, and reverting under it would leave the stored
profile and the editor's own baseline disagreeing without anything on screen
saying so.

A checkbox that saves on change does not save while the form is open — it is
applied with everything else. Without that it would reach the database while the
visitor is still deciding and :guilabel:`Discard` could not take it back. The
synchronization switch of :file:`Header.html` is outside the field forms and
keeps saving immediately.

Keyboard and assistive technology
---------------------------------

*   Opening the form puts the caret in the first editable field.
*   The bar stands after the fields in document order, so tabbing on from the
    last field reaches :guilabel:`Apply`, :guilabel:`Undo`,
    :guilabel:`Discard`.
*   :kbd:`Escape` discards the form, unless the caret is inside a CKEditor
    instance, where the key closes the editor's own balloon first.
*   :kbd:`Ctrl` + :kbd:`Enter` applies it, and so does :kbd:`Enter` in a text
    field: the form is submitted and the submission is turned into an apply
    rather than into a page load.
*   Both keys are handled by the field form itself, so a document, contract or
    image editor that is open at the same time keeps its own handling of them.
*   The bar is a ``role="group"`` with its own accessible name.
    :guilabel:`Edit all` carries ``aria-pressed`` and names the forms it
    controls in ``aria-controls``. Results are announced in the two live
    regions the editor already has. The restored notice and a success are
    polite; a failure and a refusal of the form are assertive, because the caret
    has just been moved to the first refused field and a polite message queued
    behind that is routinely dropped. A refusal beside a *single* field stays
    polite — it stands next to the control the visitor is already in.

What an override has to keep
----------------------------

:file:`Profile/Fields.html` renders :file:`Profile/Field/FormActions` at its
end; an override of it that drops the line leaves the profile with no way to
apply the form. An override of the bar itself keeps the
``data-pe-form-actions`` element, its ``data-pe-form-reverted-message`` and the
three buttons marked ``data-pe-form-apply``, ``data-pe-form-undo`` and
``data-pe-form-discard``. Everything else about it — the tags, the classes, the
labels, the order — is Fluid, and none of it is spelled in JavaScript. The
optional ``sectionLabel`` argument names the button group after the section it
stands in, so a page with two field forms does not offer two groups of the same
name.

Generic field update
====================

The URL is generated by ``f:uri.action`` for ``updateAction()`` with page type
:php:`1733735` (see :ref:`profile-editing-page-type`). Requests must use
``POST``, ``Content-Type: application/json`` and the request header
``X-Requested-With: XMLHttpRequest`` (see :ref:`profile-editing-request-header`).
Only values changed since the last successful save are sent. An empty string
clears a property when its section-local validation permits an empty value;
omitted properties remain unchanged.

..  code-block:: json
    :caption: Partial profile update

    {
      "profile": 123,
      "data": {
        "gender": "female",
        "website": ""
      }
    }

Unknown profile properties, configured read-only/disabled fields and select
values not configured in the matching TCA field are rejected. The direct academic/honorific
``profile.title`` field is an ordinary configured Profile property; the
composed display name is :yaml:`special.title`. ``skipSync`` is a direct special
property. A configured ``combinedLink`` additionally enables its matching
``*Title`` companion. All other writable Profile properties come from
:yaml:`profile`.
Extbase validation errors are returned in an ``errors`` object keyed by
property path and are mapped back to the corresponding form controls by the
frontend module.

``ProfileFieldOptionsService`` supplies both the presentation options and the
strict allow-list validation for every configured select. Thus another select
does not require a new Fluid partial, validator service or JavaScript branch.

Rich-text content fields
========================

The shipped configuration marks five profile properties with
:yaml:`renderType: ckeditor`. Four are displayed with the ``information``
section, while ``miscellaneous`` belongs to :yaml:`aboutme`:

*   ``coreCompetences``,
*   ``teachingArea``,
*   ``supervisedDoctoralThesis``,
*   ``supervisedThesis``, and
*   ``miscellaneous``.

The editor is TYPO3's shipped CKEditor 5 from the
``typo3/cms-rte-ckeditor`` package. The ``profile/rich-text.js`` module imports
its CKEditor modules through TYPO3's JavaScript import map; it does not load an
editor from a CDN. An editor instance is created lazily when a rich-text field is opened.
If initialization fails, the original textarea remains available and the
component reports an error.

The toolbar intentionally exposes only undo, redo, bold, italic, bulleted
lists, numbered lists and links. Editor changes are mirrored into the
underlying textarea, so required-field validation, changed-value detection and
the existing JSON request remain the single persistence path. CKEditor's
initial HTML normalization becomes the local comparison baseline; merely
opening an editor therefore does not submit or rewrite legacy content.

Profile fields may configure a positive ``characterLimit`` next to
``renderType: ckeditor``. Fluid then adds the limit metadata and an accessible
live counter to that field. The shared rich-text module counts normalized
visible text, keeps the last accepted value when an addition would exceed the
limit and still allows older over-limit content to be shortened. The Extbase
validator checks the submitted sanitized value again before the partial update
is persisted. The shipped ``miscellaneous`` field uses a limit of 1000.

Outside edit mode, each rich-text field renders its formatted content directly
and provides a borderless pencil action on the right. Empty values show a
localized placeholder. The preview is initially rendered through TYPO3's
HTML formatting pipeline and is replaced after a successful save with the
sanitized markup returned by the server. The frontend applies the same strict
tag, attribute and URI-scheme allowlist without assigning markup through
``innerHTML``.

Each open text or rich-text field has three explicit actions. For
``renderType: ckeditor`` only, the action group sits in the label row with
Bootstrap's ``ms-auto`` utility, leaving the editor itself full width. Other
field types retain the action group beside their control. Selects and checkboxes
save immediately when their value changes.

*   :guilabel:`Delete` (``data-pe-dismiss``) clears the current browser-side
    draft. The editor stays open and no request is sent.
*   :guilabel:`Cancel` (``data-pe-cancel``) restores the last successfully
    persisted value and closes only that field. No request is sent.
*   :guilabel:`Save` (``data-pe-save``) sends that field through the JSON AJAX
    endpoint. It closes the field only after a successful response or when
    there is no changed value to persist.

The action group uses Bootstrap utility classes to remain content-sized and
align itself to the end of the editor row instead of stretching to the
CKEditor height. No additional stylesheet or inline style is required. The
:guilabel:`Edit all` toggle beside the page heading opens both regular
fields and grouped rows, receives Bootstrap's ``active`` state and changes its
label to :guilabel:`Close all`. Activating it again collapses every editor
without saving or discarding browser-side drafts. There is no global footer
action area; save and undo remain explicit per-field actions.

The pencil is rendered through TYPO3's ``core:icon`` ViewHelper. Template
overrides may replace the icon but must retain the button's edit hook,
``data-pe-for`` target and accessible label. The profile value itself must not
be placed back inside the button.

Server-side sanitization
------------------------

Client-side editor configuration is not treated as a security boundary.
``ProfileUpdateValidationService`` derives writable properties from the
configured profile sections. It passes every configured
:yaml:`renderType: ckeditor` property through ``ProfileRichTextSanitizer``
before validation and persistence. The sanitizer uses TYPO3's allow-list based
HTML sanitizer and permits only:

*   the tags ``p``, ``br``, ``strong``, ``em``, ``ul``, ``ol``, ``li`` and
    ``a``;
*   the ``href`` attribute on ``a``; and
*   local links and the URI schemes ``http``, ``https``, ``mailto`` and
    ``tel``.

Scripts, event-handler attributes, style attributes, images, unknown tags and
unsafe URI schemes are removed. The successful JSON response contains the
normalized, sanitized values. The frontend replaces its local editor and
preview state with exactly those returned values rather than trusting the
submitted markup.

Each AJAX request is validated as a partial profile submission. The validator
selects only submitted or explicitly overridden DTO properties from their
configured profile sections. A ``required`` rule on an omitted sibling field
therefore does not block a field update or the dedicated ``skipSync`` request.
Submitted inline overrides are validated after normalization and sanitization.

The extension requires TYPO3 13.4 or TYPO3 14.3. These constraints
include the HTML-sanitizer fixes published with TYPO3-CORE-SA-2026-006. Projects
must still keep TYPO3 security updates current.

Editable structured profile sections
====================================

The profile editing view renders the structured records directly below the
:guilabel:`About me` field. ``ProfileDocumentSectionProvider`` supplies one
ordered view model instead of duplicating relation mapping in Fluid. It reads
all sections, including ``contracts``, from the shared settings graph. That
graph is built from the active packages'
:file:`EXT:academic_persons/Configuration/AcademicPersons/Settings.yaml` files,
so configured order
is also presentation order. The same graph supplies the public profile,
frontend validation and backend TCA metadata.

For every document section the provider reuses the configured ``identifier``,
``fieldName``, record ``type``, LLL ``label``, ``readonly`` state,
``rowFields``, ``actions`` and the section-local validations. The heading is
translated directly from that label.
A newly configured type consequently does not require another section registry
in ``academic_persons_edit``. The generic localized empty state is used until
an identifier-specific message is added.

``contracts`` contains ``FGTCLB\AcademicPersons\Domain\Model\Contract``
objects. Every other collection contains
``FGTCLB\AcademicPersons\Domain\Model\ProfileInformation`` objects. Contract
rows show the configured values, which are start date and position in the
shipped settings. Profile-information rows render only the configured values in
their declared order. The aliases ``from``, ``to`` and ``description`` map to
``yearStart``, ``yearEnd`` and ``bodytext``. All sections remain visible when
empty and display a localized empty state.

The three year properties ``year``, ``yearStart`` and ``yearEnd`` are four
digit integers stored in nullable :sql:`int` columns. Their add/edit control is
an ``<input type="number">`` carrying ``min="0"``, ``max="9999"`` and
``step="1"`` - the bounds of the TCA ``range`` of the same columns, which the
endpoint enforces again on every submission, so a client that ignores them is
refused rather than clamped. Nothing about a year is formatted and nothing
follows a locale.

The three controls each use ``col-12 col-md-3`` and share one responsive row on
medium and larger viewports. Their HTML and server-side required states come
from the same validation set: only a field with the additional ``required``
flag must be filled. In the shipped settings this applies to ``year`` but not
to ``from`` or ``to``.

..  _profile-editing-contract-dates:

The two Contract dates ``validFrom`` and ``validTo`` are the only date fields
of the editor, and their control is a plain ``<input type="text">`` showing
``d.m.Y`` behind the hint ``dd.mm.yyyy`` - the control the previous editor
had. It is **not** an ``<input type="date">``: the native control follows the
locale of the browser rather than the one of the site, it cannot be styled
with the rest of the editor, and its calendar is not the date picker this
editor is meant to get. **A date picker is deliberately not shipped yet.** It
is a feature of its own, it will not be the browser's, and until it exists the
date is typed.

The endpoint accepts two formats for such a field. ``d.m.Y`` is what the
control shows and submits. ``Y-m-d`` is what the endpoint answered and
accepted while the control was a native one and stays accepted, so a client
written against that shape keeps working. Both are read strictly - the parsed
date is formatted back and has to match what came in, so ``32.01.2026`` is
refused rather than read as the first of February - and anything that is
neither format is refused with :guilabel:`The value must be a valid date.`
The read view is unaffected: a stored date is rendered as the ``MEDIUMDATE``
of the site language, the way the public profile renders it.

..  _profile-editing-document-editor:

How the document editor is rendered
-----------------------------------

The editor of a document row is rendered in the browser, and cannot be
rendered anywhere else: its fields, their labels, their select options and
their display values all come from the ``documentForm`` response, which the
permission-checked endpoint decides per section, per record and per mode.
:file:`Partials/Profile/Documents/Editor.html` therefore renders the *shape* of
that editor, as a ``<template data-pe-proto="document-panel">`` block, and the
custom element ``<academic-persons-edit-document-editor>`` clones it and fills
its slots. The element is created by
:file:`TypeScript/frontend/profile/documents.ts` **inside** the collapse target
below the selected section heading for add actions, and inside the selected
record row for view, edit and delete actions. The element is handed the
response as properties and is removed again when its close transition reports
back. This avoids pre-rendering one hidden form per section and record while
keeping the static structure in Fluid and mapping validation errors directly
back to the returned field names.

Exactly one document collapse is open at a time, while the complete profile
view remains visible. Activating the same add or view trigger a second time
closes its collapse with the same cleanup as :guilabel:`Cancel`. The element is
created where it is shown and is never moved: moving it would disconnect it,
and a disconnect destroys the CKEditor instances below it. The collapse target
keeps the unique ID the controller assigns it when it is first opened, now
for ``aria-controls`` alone.

A hidden ordinary DOM container supplies the Fluid-rendered row prototype used
after creation (``data-pe-document-item-template``). It is a plain container
rather than an HTML ``template`` element, and the successful create response is
rendered by cloning the row inside it.

The icons of a browser-rendered editor cannot be resolved in the browser:
``core:icon`` asks the icon registry, which knows the set this extension
registers and whatever a site overrode. Under the prototype design that needs no
mechanism of its own: an icon is rendered by Fluid **inside the prototype that
draws it** — the help button of a field, the five row controls of a contact and
the add control of a section — so it is part of the markup an override reaches
and no module ever looks one up.

``contract`` is retained as a separate document kind. It uses the same editor
for its configured fields and appends three contract-specific contact sections
in the read view: physical addresses, email addresses and phone numbers. Their
field schemas and validation flags come from
``contracts.contactSections.<section>.fields`` in the shared settings graph.
The Contract form itself follows the order and metadata in
``contracts.fields``.
Those sections, their rows and the editor of one contact are rendered by a
second custom element, ``<academic-persons-edit-contract-contacts>``, which the
document editor creates in its own template. The contact editor stands below
the contact-section heading for add actions and directly inside the selected
contact row for view, edit and delete actions — the two placements
:file:`Documents/ContractContactEditor.html` renders one prototype for.

Every contact row of a contract also carries a visibility toggle, the first
control of its action group: it flips the record's own ``hidden`` column - the
TCA ``enablecolumns.disabled`` field of the address, email and phone number
tables, exactly the switch the previous editor offered for these three record
kinds - through the ``toggleContractContactVisibility`` endpoint, which is sent
the target state rather than a "flip" so that a double press stores what the
visitor saw. The button's label names the press - :guilabel:`Hide in frontend`
or :guilabel:`Show in frontend` - and its glyph (``academic-persons-edit-visible``
respectively ``academic-persons-edit-hidden``) shows the state; it carries no
``aria-pressed``, because a label that changes with the state would announce
the opposite of what a pressed button does. A hidden row is drawn in the
secondary text colour, its controls at full contrast, and carries a
:guilabel:`Hidden` tag in front of its action group. The toggle follows the
``edit`` entry of the ``contracts`` allow-list; contracts and profile
information rows never had the toggle and do not get one.

Every writable section heading has an :guilabel:`Add` action. Record controls
are rendered in the exact order of the configured ``actions`` list. The first
row's move-up action and the final row's move-down action are disabled. A list
with both directions also has a drag handle; dropping a row submits the complete
UID order and the server accepts it only when it contains every current section
record exactly once. After a successful mutation JavaScript updates the row
collection, alternating background, sort controls and empty placeholder without
reloading the page. The drag handle is hidden below Bootstrap's ``md``
breakpoint; the explicit up/down controls remain available on mobile.

The add, view, edit and delete workflows share one inline collapse. Its field
schema and current values are loaded through ``documentFormAction()``. Contract
fields include the current organisational-unit, function-type and location
options. Profile-information fields use the section's validation metadata. In
every mode the view heading uses the non-empty ``title`` field of the current
record. New records, contracts and records without a title fall back to the
translated section heading; the mode label remains as its prefix.
A field carrying the ``html`` flag is rendered as a full-width CKEditor 5
control; ordinary textareas are full width as well. Such a field is an
``<academic-persons-edit-rich-text>`` element of its own, which creates the
editor when it is connected and destroys it when it is disconnected, so a
re-render of the editor around it — which every validation error causes — never
reaches into the subtree CKEditor owns. Rich-text values are sanitized before
persistence and parsed through the frontend sanitizer before the row or the
read-only view receives the HTML.
When that field's ``editor.limit`` is a positive integer, the textarea carries
the normalized limit and the view renders an accessible live character
counter below CKEditor. The count uses normalized visible text rather than the
stored HTML. CKEditor rejects additions past the limit while still allowing an
older over-limit value to be shortened. The JSON endpoint and the Extbase
form-data validator independently reject over-limit submissions, so client-side
code is not the security boundary. Character limits do not alter backend TCA or
the database schema; the shared required, readonly and field-type metadata does.
The document pending state is released before CKEditor is initialized, because
CKEditor deliberately skips disabled controls.
Every JSON request increments one shared busy counter. While at least one
request is active the document shows the wait cursor, and the final request
restores the previous cursor in a ``finally`` path. ``aria-busy`` is set on the
region that is actually waiting — the plugin root while a profile field is
saved, the section while its rows are sorted, the open document or contact
editor, the image editor, the synchronization form — and never on ``<body>``,
which would make a screen reader stop reporting the rest of the page as well. This
keeps failures and concurrent requests from leaving a stale loading state.

Opening a different document replaces only the in-memory editor schema and
values after the new form has loaded. It never calls the submit action: document
forms have no blur, change, teardown or focus-loss save hook. Only their
explicit save/delete submit control persists a mutation. Focus moves into the
first writable control (or the read/delete heading), returns to the action that
opened a closed editor and remains on native buttons throughout keyboard
sorting. Drag sorting is an optional pointer enhancement; the up/down actions
remain the complete keyboard path.

All generated controls keep an explicit label. Validation errors have stable
IDs referenced through ``aria-describedby`` and update ``aria-invalid``;
dynamic editor headings and expanded controls expose their relationships via
``aria-controls`` and ``aria-expanded``. The field editors are not modal
dialogs and deliberately do not trap focus.
When the view enters delete mode, its submit control is rendered with
``btn-danger``. Every other mode renders it with ``btn-primary``.

``createDocumentAction()``, ``updateDocumentAction()``,
``deleteDocumentAction()`` and ``sortDocumentAction()`` complete the document
JSON API.
Up, down and drag-and-drop intentionally share the sort endpoint. All endpoints
resolve the profile from the authenticated frontend user and then resolve a
record only inside the requested section. A UID from another profile, model kind
or profile-information type is therefore rejected. They additionally enforce
``readonly`` and the configured action list. The shipped contracts section is
writable and exposes the complete configured Contract field set.

The dedicated ``contractContactForm``, ``createContractContact``,
``updateContractContact``, ``deleteContractContact`` and
``sortContractContact`` actions operate below a Contract resolved through the
authenticated Profile. A contact UID from another Contract is rejected. New
contacts are appended with a normalized sorting value; the up/down controls
persist their order independently in each contact section. The first and last
controls are disabled at their respective boundaries. Read-only Contract
configuration blocks every contact mutation at the endpoint as well.
Contract and contact helptexts use the same edit-only Bootstrap popovers as
other inline fields. Physical-address countries are selected from localized
TYPO3 ``CountryProvider`` options and persisted as ISO alpha-2 codes; submitted
values outside that list are rejected by the endpoint.

The ProfileEditing plugin registers only ``ProfileController``. All profile,
contract, profile-information and contact mutations are handled by its normal
or non-cacheable action map.

Section order is centralized and every section emits ``data-section-key`` and
``data-section-position`` together with the configured
``data-section-field-name``, ``data-section-record-type``,
``data-section-kind`` and ``data-section-readonly``. Records additionally emit ``data-item-uid``,
``data-item-sorting`` and ``data-item-position``. ``data-section-sortable``
exposes whether both sorting directions are available. The explicit up/down
controls and drag handle persist the same record order.

The presentation uses Bootstrap rows with one shared desktop column heading,
compact flat records, separating borders and alternating tertiary backgrounds
within each document section, which are a ``:nth-child(odd)`` rule of the
extension's stylesheet rather than a class on the row. The year columns
remain narrow while title and position columns consume the available width.
On small viewports every record repeats its field labels instead of rendering
the desktop heading. An empty section keeps its heading and add action,
followed by one unobtrusive translated status line. During drag sorting the
browser uses the complete record row as the drag image.
Extension-specific state classes outline both the source row and active list,
while a prominent line above or below the hovered row marks the exact insertion
edge. Dropping into free list space shows the same line at the end of the list.

Profile-editing development boundary
====================================

``academicpersonsedit_profileediting`` is the only profile-editing content
element offered in the backend CType selector and new-content-element wizard.
All profile-editing behavior is implemented through the ``Profile`` template
and partial tree, ``ProfileController`` AJAX actions and the inline JavaScript
component. The former dedicated controllers and their Fluid trees are removed;
they are not compatibility entry points.

The ProfileEditing TypoScript, AJAX page type, site set and page TSconfig live in
their own ``Configuration/*/ProfileEditing`` components. ProfileEditing is the
only component enabled through either its component configuration or the
aggregate.

The ProfileEditing functional test setup reflects the same boundary. It uses a
dedicated ``academicpersonsedit_profileediting`` fixture and the neutral
``AbstractFrontendProfilePluginTestCase`` base.

Synchronization checkbox
========================

The synchronization checkbox appears as the compact
:guilabel:`Private` switch
immediately left of the :guilabel:`Edit all`/:guilabel:`Close all` toggle in
the page header and is persisted immediately through
``updateSkipSyncAction()``. Its form is a sibling of the profile form, not a
nested form. Its presence and writable metadata follow the
``special.skipSync`` configuration; the underlying data and endpoint semantics
remain ``skipSync``. It does not submit or mutate any other field. The endpoint
accepts exactly one boolean property:

..  code-block:: json
    :caption: Synchronization update

    {
      "profile": 123,
      "data": {
        "skipSync": true
      }
    }

Any additional property or a non-boolean value returns ``invalid_payload``.
On failure the JavaScript restores the last successfully persisted checkbox
state.

Expanding profile image editor
==============================

Clicking the compact edit button below the current profile image or its
placeholder keeps the profile view active.
:file:`Partials/Profile/Image/Editor.html` is rendered by Fluid into a
dedicated full-width target above the profile header,
``data-pe-image-editor-target``, which is where it is shown: it is hidden until
the editor is opened rather than inserted then, so the container contributes
its final width and height from the first paint and the cropper can initialize
with the complete available width.
The header, profile fields and structured sections remain in the same profile
flow below the cropper; no separate view or overlay is involved. While the
editor is open, the complete image-preview column is hidden and the
profile-fields column changes from ``col-lg-8`` to ``col-lg-12``. The editor
itself uses a bordered, padded surface. Closing it scrolls the restored
image-preview column into view and focuses its edit action without causing a
second browser scroll. The focus is restored only after two animation frames
have applied the collapsed layout. A ``1.5rem`` scroll margin keeps the
restored preview clear of the viewport edge.

``<academic-persons-edit-image-editor>`` is the element that drives that
partial. It renders nothing of its own — the ``<f:form>`` carries the
``__trustedProperties`` signature the property mapper validates the upload
against, and only the server can produce that — so it is a controller over
Fluid's markup: it binds the events and writes everything the shipped view
derives from the editing state, including the two column widths of
:file:`Templates/Profile/Index.html`.

Opening and closing is animated with a short vertical move and fade, driven by
the ``…-image-editor-enter-active`` / ``-enter-from`` / ``-leave-active`` /
``-leave-to`` classes of the extension's stylesheet. An explicit CSS grid row
expands and collapses the editor height, padding, margin and border instead of
removing the complete block in one layout step. The open scroll leaves ``2rem``
above the editor. The return scroll starts together with the collapse and uses
the preview's calculated final position; native scroll anchoring is disabled
only during that phase, so it cannot introduce a competing correction.
Environments requesting reduced motion skip the transition, and the close then
completes in the same frame rather than waiting for an animation that never
runs.

The full-width ``btn-sm`` edit action sits directly below the preview. Its
visible label and image upload icon are complemented by localized ``title`` and
``aria-label`` attributes.

The image editor deliberately has no state-dependent :guilabel:`Add` or
:guilabel:`Replace` action. Selecting a file immediately replaces only the
inline crop preview with a local object URL. The page preview and persisted profile
remain unchanged until :guilabel:`Save` succeeds. A successful upload replaces
both previews, collapses the editor and shows the saved image directly. During
the leave transition, the preview remains hidden and the profile fields retain
their full width. The regular ``4 / 8`` grid is restored only after the editor
has finished collapsing, preventing competing layout changes while the page
returns to the image preview.
:guilabel:`Cancel` discards the selected preview and restores the persisted
image. :guilabel:`Delete` is shown only while an image is persisted.

If ``special.image.renderType`` is ``cropper`` and
``special.image.settings.ratio`` contains a positive ratio such as ``1x1`` or
``16:9``, the editor instantiates the CropperJS module the TYPO3 core maps as
``cropperjs`` - version 1.6.1 on both supported core versions. The selection
remains constrained to that ratio and only the generated cropped file is added
to the multipart request. No CDN or other runtime request is used. The ratio
remains fixed while the selection can be dragged and resized to choose the
visible image area, and the cropped file is written at the resolution of the
selected file rather than at the size the editor displays it, up to 2400 pixels
wide. The cropper is instantiated only after a new local file is selected. A
persisted profile image and the placeholder remain normal previews and cannot
themselves become crop input. The original upload behavior remains
active for every other render type.

The save button remains disabled until a new local file is selected. This also
prevents an already persisted image from being fetched, cropped and uploaded
again. While an upload or deletion is pending, all image controls are disabled
and the active action displays a Bootstrap spinner. This prevents duplicate
requests and leaving the image editor during a running operation.

Upload
------

The image form is intercepted by the frontend module and sends
``multipart/form-data`` to ``uploadImageAction()`` through ``fetch()``.
The ``FormData`` object is built before the file control is disabled for the
pending state. Disabled controls are omitted by the browser and would otherwise
produce an apparently valid request without an uploaded image.
Extbase's file handling service validates the configured maximum file size and
allowed MIME types, stores the file in the configured target folder and updates
the FAL relation. Authorization is checked before Extbase maps or stores the
uploaded file. A replaced physical file is removed only when it has no other
references.
After persistence, the ordered non-empty values ``title``, ``firstName``,
``middleName`` and ``lastName`` are joined with spaces. That composed profile
name is written to ``alternative`` and ``title`` of the profile's file-reference
overlay, which is the language-correct place for it: a translation carries its
own name there, and the file may be shared between the languages of a profile.

The metadata record of the uploaded file itself is written once, by the upload,
and only where it is empty — the record :sql:`sys_file_metadata` carries is the
backend editor's from then on, and no later change of the profile name touches
it. It is written at all because nothing else fills it: ``alternative`` and
``title`` are the fields an installation running :composer:`typo3/cms-filemetadata`
or :composer:`fgtclb/file-required-attributes` reports as missing required
attributes for a file uploaded in the frontend.

The JSON response returns the composed name so the in-page preview immediately
uses the persisted metadata.

The image editor does not render ``f:form.validationResults``. Upload validation
failures are returned as JSON and displayed in an alert inside the active view.
The controller additionally compares the submitted image with the
persisted FAL reference. It returns ``image_upload_missing`` with status
``422`` instead of reporting success when no new file arrived.

The cropper preserves JPEG, PNG and WebP as output formats and falls back to
PNG for unsupported source types. Every upload remains a separate FAL file.
When a profile already has exactly one independent image relation, replacing
the image keeps the ``sys_file_reference`` uid but assigns the newly uploaded
file to it through DataHandler and persists ``image=custom`` in the profile's
``l10n_state``. The old physical file is removed only after no active reference
uses it anymore. Localized or duplicate legacy references are rebuilt as one
independent relation, so changing one language cannot overwrite another
language's custom image.

The upgrade wizard
``academicPersonsEdit_repairLocalizedProfileImages`` repairs legacy shared,
localized or duplicate image relations and inconsistent relation counters. It
rebuilds every relation through the DataHandler and deletes no file: a file may
be reached through a soft reference that :sql:`sys_file_reference` knows nothing
about, and an unattended bulk run is the wrong place to act on that question.

All JSON actions propagate non-successful responses out of TYPO3's
Extbase ``USER`` content rendering with ``PropagateResponseException``. A
``JsonResponse`` returned by the action alone would contribute its body to the
surrounding ``PAGE`` object while the outer frontend response retained status
``200``. Propagation therefore preserves the documented non-``200`` status
codes for the AJAX client.

The relevant TypoScript settings are:

..  code-block:: typoscript

    plugin.tx_academicpersonsedit.settings.editForm.profileImage {
        targetFolder = 1:/profile-images
        validation {
            maxFileSize = 2M
            allowedMimeTypes = image/jpeg,image/png,image/webp
        }
    }

Delete
------

The delete button calls ``deleteImageAction()`` exclusively through AJAX. The
endpoint accepts a ``POST`` JSON request without profile field changes:

..  code-block:: json
    :caption: Image deletion

    {
      "profile": 123,
      "data": {}
    }

The profile relation is cleared first. The physical file is deleted only if no
other record references it. The response includes ``deleted`` and
``hasImage`` so clients can synchronize their local state.

Authentication and responses
============================

Every endpoint above requires an authenticated frontend user and accepts only the
profile assigned to that user. The generic update, synchronization and delete
endpoints propagate machine-readable JSON errors. Image upload validation is
also converted to and propagated as JSON by the controller's error action.

..  list-table:: Response status codes
    :header-rows: 1

    *   - Status
        - Error identifier
        - Meaning
    *   - ``200``
        - —
        - The request was persisted successfully.
    *   - ``400``
        - ``invalid_request``, ``invalid_json`` or ``invalid_payload``
        - The ``X-Requested-With`` header is missing, or the JSON or the request
          structure is invalid.
    *   - ``401``
        - ``authentication_required``
        - No frontend user is authenticated.
    *   - ``403``
        - ``profile_not_editable``
        - The profile is not assigned to the frontend user.
    *   - ``405``
        - ``method_not_allowed``
        - A JSON endpoint was called with a method other than ``POST``.
    *   - ``415``
        - ``unsupported_media_type``
        - A JSON endpoint was called without ``Content-Type:
          application/json``.
    *   - ``422``
        - ``invalid_profile_data``, ``validation_failed`` or
          ``image_upload_missing``
        - A field value or uploaded file is invalid.
    *   - ``500``
        - ``internal_server_error``
        - An unexpected error occurred. Details are logged but not exposed in
          the JSON response.

Customizing the view
====================

Override :file:`Resources/Private/Templates/Profile/Index.html` and the partials below
:file:`Resources/Private/Partials/Profile/` through the regular template
and partial root paths. The index keeps URL/data setup, the responsive main
grid, the prototypes and the composition. The ``Profile``, ``Documents``,
``Image`` and ``Field`` directories group the corresponding UI
responsibilities; the status regions and client-side button templates remain
shared at the root.

Every file in that tree is an override point, including the two regions a
browser builds at runtime. The editor of one document or contract and the
contacts of one contract cannot be rendered as finished markup - their fields,
labels, options and display values come from the ``documentForm`` and
``contractContactForm`` responses - so Fluid renders their *shapes* instead, as
``<template data-pe-proto="…">`` blocks, and the elements clone one and fill it.

..  list-table::
    :header-rows: 1

    *   - File
        - What it renders
    *   - :file:`Partials/Profile/Prototypes.html`
        - Every shape the browser-rendered editors draw, once per page: the five
          controls, the three field rows, an option, a help button and a
          display row. It renders the three partials below.
    *   - :file:`Partials/Profile/Documents/Editor.html`
        - The panel of one document or contract, as the ``document-panel``
          prototype.
    *   - :file:`Partials/Profile/Documents/ContractContacts.html`
        - The section, the row and one summary cell of a contact list.
    *   - :file:`Partials/Profile/Documents/ContractContactEditor.html`
        - The editor of one contact, for both places it is shown.
    *   - :file:`Partials/Profile/Field/Control.html`
        - The only place a form control is spelled - inline for the permanent
          profile fields and, with its ``prototype`` flag, into the prototypes.
    *   - :file:`Partials/Profile/Image/Editor.html`
        - The image editor. Its ``<f:form>`` and hidden fields must stay - only
          the server can sign ``__trustedProperties`` - and so must its
          ``data-pe-*`` hooks; what the template used to derive is written by
          the element.

A prototype is filled through exactly four attributes, and an override keeps
them and their keys:

..  list-table::
    :header-rows: 1

    *   - Attribute
        - Meaning
    *   - ``data-pe-slot="key"``
        - The text of the node becomes the value.
    *   - ``data-pe-attr="attribute:key …"``
        - Those attributes take the value; an absent or false value removes
          them.
    *   - ``data-pe-when="key"``
        - The node is removed when the value is falsy.
    *   - ``data-pe-list="key"``
        - Where repeated clones go.

What an override may change is every tag, every class and every label. What it
may not change is the vocabulary - the ``data-pe-*`` hooks and the slot,
condition and list keys - and what it cannot change is the order the elements
insert things in and which slot carries which value; both are TypeScript. See
:ref:`important-profile-editing-custom-elements-and-prototypes` in the
changelog.

..  _profile-editing-elements:

The custom elements and their events
------------------------------------

Five element names are part of the public contract of this extension from
version 3.0 on. The prefix is the extension key with its underscores replaced,
because a custom element name is global and has no scoping mechanism.

..  list-table::
    :header-rows: 1

    *   - Element
        - What it owns
    *   - ``<academic-persons-edit-profile-editing>``
        - One editor. It wraps the plugin root, reads its ``data-*`` contract
          once and starts everything below it. It renders nothing.
    *   - ``<academic-persons-edit-image-editor>``
        - The profile image editor, as a controller over the server-rendered
          upload form. It renders nothing.
    *   - ``<academic-persons-edit-document-editor>``
        - One open document or contract editor, in ``add``, ``view``, ``edit``
          or ``delete`` mode. It clones the ``document-panel`` prototype and
          fills it from the ``documentForm`` response. It renders nothing.
    *   - ``<academic-persons-edit-contract-contacts>``
        - The contacts of one contract and the editor of one contact, from the
          section, row and editor prototypes. It renders nothing.
    *   - ``<academic-persons-edit-rich-text>``
        - One rich text field of a document editor and the CKEditor 5 instance
          on it. It wraps the textarea its prototype carries and renders
          nothing. The rich text fields of the profile itself stay
          Fluid-rendered textareas and are not wrapped in it.

Four events report what an open document editor did; the root element listens
for a fifth, so that a descendant which does not hold the editing context can
still have a status shown. All of them bubble, and none of them crosses a
shadow boundary because there is none:

..  list-table::
    :header-rows: 1

    *   - Event
        - Meaning
    *   - ``pe:status``
        - Asks the root element to write one of the two live regions; detail
          ``{ type, message? }``. Listened for on the root, dispatched by
          nothing the extension ships.
    *   - ``pe:document-close``
        - The cancel button of an open editor was pressed.
    *   - ``pe:document-submit``
        - Its form was submitted; the browser's own submission is prevented.
    *   - ``pe:document-input``
        - A control changed; detail ``{ name, value }``.
    *   - ``pe:document-closed``
        - The close transition is over and the element may be removed.

Two vocabularies carry the rest, and both are unchanged in meaning by the move
to custom elements. The plugin root of :file:`Templates/Profile/Index.html`
carries the configuration of *this* profile — fourteen endpoint URLs, the
profile uid and the editor language, five image settings, twenty-two messages
and seven labels. It is read **once**, when the element above it starts the editor,
and an attribute changed afterwards is not seen. Every control below the root
carries a ``data-pe-*`` hook, including the controls an element clones out of a
prototype: those carry the same hooks the removed partials did.

Keep the following contracts when reusing the shipped JavaScript. A hook that
sits in a prototype is authored in Fluid like any other, so an override may
retag, restyle and relabel it; what an override may not do is rename the hook,
and what it cannot do is change the order the element inserts things in or
which slot carries which value.

..  list-table::
    :header-rows: 1

    *   - Selector or attribute
        - Purpose
    *   - ``data-academic-persons-profile-editing``
        - Root component and scope for all queries. Everything below is read
          from it, and the ``<academic-persons-edit-profile-editing>`` element
          that wraps it is what starts the editor.
    *   - ``data-pe-missing-ajax-page-type``
        - The ``role="alert"`` message :file:`Templates/Profile/Index.html`
          renders **above** the plugin root when the site delivers no
          :typoscript:`PAGE` object with :typoscript:`typeNum = 1733735`, which
          is the one configuration error the editor cannot survive: every save
          would be answered with the site's error page instead of JSON. The
          same request logs the cause and names the site set to include. It is
          absent on a correctly configured site.
    *   - ``data-profile-uid`` and ``data-editor-language``
        - Positive profile identifier, and the language code CKEditor is
          initialized with.
    *   - ``data-update-url``
        - Generic field update endpoint.
    *   - ``data-skip-sync-url``
        - Synchronization endpoint.
    *   - ``data-delete-image-url``
        - Image deletion endpoint.
    *   - ``data-document-form-url``, ``data-create-document-url``,
          ``data-update-document-url``, ``data-delete-document-url`` and
          ``data-sort-document-url``
        - The five document endpoints.
    *   - ``data-contract-contact-form-url``,
          ``data-create-contract-contact-url``,
          ``data-update-contract-contact-url``,
          ``data-delete-contract-contact-url``,
          ``data-sort-contract-contact-url`` and
          ``data-toggle-contract-contact-visibility-url``
        - The six contract-contact endpoints.
    *   - ``data-message-*`` and ``data-label-*``
        - The localized texts a browser-rendered control needs. They are on the
          root because a template cannot be reached from the browser.
    *   - ``data-pe-proto``
        - One ``<template>`` per shape a browser-rendered editor draws. The
          elements clone one and fill its ``data-pe-slot``, ``data-pe-attr``,
          ``data-pe-when`` and ``data-pe-list`` nodes.
    *   - ``data-pe-fields-form`` and
          ``academic-persons-profile-editing__field``
        - Generic field forms and controls. Separate forms preserve valid markup
          across the personal-data and about-section grid areas.
    *   - ``data-pe-rich-text`` and ``data-pe-editor-container``
        - Marks a textarea for lazy CKEditor initialization and its wrapper for
          show/hide handling.
    *   - ``data-pe-rich-text-preview`` and
          ``data-pe-rich-text-preview-content``
        - Direct formatted read preview and its safely replaceable content
          container.
    *   - ``data-pe-field-preview`` and ``data-pe-field-editor``
        - Plain read row and the inline control region for one field.
    *   - ``data-pe-profile-name`` and
          ``data-pe-profile-name-field-ids``
        - Main heading and the name controls used to refresh it after saving.
    *   - ``data-pe-sticky-image``
        - Sticky image container receiving the measured
          ``#page-header.navbar-fixed-top`` height plus a 10-pixel visual gap
          as its runtime ``top`` offset.
    *   - ``data-pe-document-sections`` and ``data-pe-document-section``
        - Editable structured-section list and stable boundary for its AJAX
          controls.
    *   - ``data-section-key`` and ``data-section-position``
        - Stable section identity and current zero-based presentation position.
    *   - ``data-section-field-name``, ``data-section-record-type``,
          ``data-section-kind`` and ``data-section-readonly``
        - Field, relation type, record kind and write state, taken from the
          shared settings graph for section-specific persistence.
    *   - ``data-pe-document-items`` and ``data-pe-document-item``
        - Mutable item collection and record boundaries inside a section.
    *   - ``data-pe-document-item-template``
        - Hidden Fluid-rendered row prototype retained in the mounted DOM and
          cloned after a successful create response.
    *   - ``data-pe-document-add-collapse-target`` and
          ``data-pe-document-item-collapse-target``
        - Where the ``<academic-persons-edit-document-editor>`` element is
          created: below a section heading for an addition, inside an individual
          record row for everything else. The document controller assigns a
          unique ID when a target is first opened, for ``aria-controls``.
    *   - ``data-item-uid``, ``data-item-sorting`` and ``data-item-position``
        - Persisted record identity, domain sorting value and current zero-based
          presentation position.
    *   - ``data-pe-document-empty-state``
        - Localized placeholder rendered when a structured collection is empty.
    *   - ``data-pe-document-add``, ``data-pe-document-view``,
          ``data-pe-document-edit`` and ``data-pe-document-delete``
        - Section creation and in-place row actions.
    *   - ``data-pe-document-sort``
        - Up/down row action persisted through the shared sort endpoint.
    *   - ``data-pe-document-view-container``, ``data-pe-document-form``,
          ``data-pe-document-heading``, ``data-pe-document-fields`` and
          ``data-pe-document-field``
        - The collapse, the form, the heading, the field region used for add,
          view, edit and delete, and one control inside it. Fluid renders all
          of them: the first four in the ``document-panel`` prototype of
          :file:`Partials/Profile/Documents/Editor.html`, the last through
          :file:`Partials/Profile/Field/Control.html`, which carries the field
          name as its value. ``<academic-persons-edit-document-editor>`` clones
          the prototype and fills its slots.
    *   - ``data-pe-contract-contact-section``, ``-item``, ``-hidden``,
          ``-heading``, ``-form``, ``-fields``, ``-field``, ``-editor``,
          ``-actions``, ``-add``, ``-view``, ``-edit``, ``-delete``, ``-sort``,
          ``-hide``, ``-cancel`` and ``-save``
        - The contact sections of a contract, their rows, the editor and its
          controls. Fluid renders them too: the section, the row and one
          summary cell in the ``contact-section``, ``contact-row`` and
          ``contact-summary-cell`` prototypes of
          :file:`Partials/Profile/Documents/ContractContacts.html`, the editor
          in the ``contact-editor-panel`` prototype of
          :file:`Partials/Profile/Documents/ContractContactEditor.html`, and
          ``-field`` again through :file:`Partials/Profile/Field/Control.html`.
          ``<academic-persons-edit-contract-contacts>`` clones and fills them;
          the presses are delegated on the plugin root like the document ones.
          ``-hidden`` marks a hidden contact row: it is the styling anchor of
          the dimmed row, and the ``hidden`` key that carries it also renders
          the :guilabel:`Hidden` badge. ``-hide`` is the visibility toggle of
          the row, see below.
    *   - ``data-pe-field-group``, ``data-pe-field-ids`` and
          ``data-pe-display-field-ids``
        - Grouped preview/editor and the controls participating in it.
    *   - ``data-pe-group-edit``, ``data-pe-group-dismiss``,
          ``data-pe-group-cancel`` and ``data-pe-group-save``
        - Open, clear the draft, restore and persist a grouped field row.
    *   - ``data-pe-field-actions``
        - Content-sized Bootstrap group for the three per-field actions.
    *   - ``data-pe-autosave-on-change``
        - Saves configured checkbox controls immediately after a change.
    *   - ``data-pe-autosave-undo`` and ``data-pe-cancel``
        - Marks the undo action beside an editable checkbox. It restores the
          last successfully persisted value and closes the editor without
          sending another request. Select fields use the regular clear, undo
          and save action group.
    *   - ``data-academic-persons-profile-editing-edit-all-btn``
        - Enters and leaves full form editing: it opens every editable field
          and grouped row at once, hides the per-field action groups and shows
          the form bars. Pressed again it discards the form, exactly as
          :guilabel:`Discard` does. It carries ``aria-pressed`` and names the
          field forms it controls in ``aria-controls``.
    *   - ``data-pe-edit-all-label``, ``data-pe-close-all-label`` and
          ``data-pe-edit-all-button-label``
        - Localized labels and replaceable label container for the edit-all
          toggle.
    *   - ``data-pe-dismiss``
        - Deletes the current draft value without closing or saving it.
    *   - ``data-pe-cancel``
        - Restores the last persisted value and closes one field without a
          request.
    *   - ``data-pe-save``
        - Persists one field through the generic JSON endpoint.
    *   - ``data-pe-sync-form`` and
          ``academic-persons-profile-editing__sync-checkbox``
        - Synchronization control.
    *   - ``academic-persons-profile-editing__image-form`` and
          ``data-pe-image-view-container``
        - AJAX-only multipart upload form and the editor panel around it. The
          form is server rendered and stays that way: it carries the
          ``__trustedProperties`` signature the property mapper validates the
          upload against, which only the server can produce.
    *   - ``data-pe-image-editor-target``
        - Profile-specific full-width container Fluid renders the image editor
          into, above the profile header.
    *   - ``data-pe-image-preview`` and
          ``data-pe-image-view-preview``
        - Image locations updated after upload or deletion.
    *   - ``data-image-render-type`` and ``data-image-cropper-ratio``
        - Render type and ratio consumed by the cropper of the image editor.
    *   - ``data-pe-status-toast="status"`` and
          ``data-pe-status-toast="alert"``
        - The two scoped status regions of the component: the polite one for
          saving, success and information, the assertive one for a failure. Both
          must exist — a region's politeness cannot be changed reliably once it
          is in the accessibility tree.

Not every hook in the table has a reader in the shipped JavaScript. Fifteen of
them are **override and styling anchors**: they name a region so a stylesheet or
a project's own script can address it, and no module of this extension looks
them up. They are part of the contract for that reason and not by accident - an
override may move them, and should keep them:

..  code-block:: text

    data-pe-autosave-undo          data-pe-document-kind
    data-pe-compact                data-pe-document-section-header
    data-pe-contract-contact-actions   data-pe-document-sections
    data-pe-contract-contact-fields    data-pe-group-actions
    data-pe-contract-contact-form      data-pe-helptext
    data-pe-document-actions       data-pe-image-editor-heading
    data-pe-document-fields        data-pe-profile-header
                                   data-pe-rich-text-heading

Of these, ``data-pe-compact`` is the only one a stylesheet of this extension
uses; the rest exist for overrides.

One consequence of the prototype mechanism is worth stating: a clone keeps the
``data-pe-slot``, ``data-pe-attr``, ``data-pe-when`` and ``data-pe-list``
attributes of the block it came from. They are inert in the live DOM - the
filler has already written the values they name - and they are not state. Do not
read them, and do not style on them.

Every editable field needs one ``invalid-feedback`` element in its closest
``data-pe-field-wrapper``, ``data-pe-group-control`` or ``.form-check`` wrapper.
Inline collapse targets, views, status regions, icon templates and
compatibility-template elements must remain inside the component root. All DOM
lookups are scoped to that root, so multiple components remain independent.

..  _profile-editing-state-classes:

State classes and selectors JavaScript uses
===========================================

No element and no prototype filler writes markup: every tag, every ``class``
attribute and every label of the editor is authored in Fluid. What the modules
below them do is toggle *state* classes on markup that already exists, and
select a few nodes by class. Both are part of the override contract - renaming
one of them breaks the editor silently, because nothing throws when a
``classList.toggle()`` writes a class no stylesheet defines.

..  list-table::
    :header-rows: 1

    *   - Class or selector
        - Written or read for
    *   - ``d-none``
        - Everything that is shown and hidden by the field editing: a preview,
          an editor, a per-field action group, an empty section, the toast.
    *   - ``d-md-flex``
        - The header row of a structured document list, which is shown only
          while the list has rows.
    *   - ``is-invalid``
        - A control the server refused, on a profile field and on the
          synchronisation switch.
    *   - ``text-danger``
        - A rich text character counter that is over its limit.
    *   - ``text-body-secondary``
        - A preview that shows the empty label instead of a value.
    *   - ``active``
        - The :guilabel:`Edit all` toggle while it is pressed.
    *   - ``bg-danger``, ``bg-success``, ``bg-info``, ``bg-warning``
        - The severity of the status toast; the four are exchanged, never
          combined.
    *   - ``.status-title`` and ``.status-message``
        - The two nodes of :file:`Partials/Profile/StatusToast.html` a status is
          written into.
    *   - ``is-drag-active``, ``is-dragging``, ``is-drop-before``,
          ``is-drop-after``, ``is-drop-at-end``
        - The drag sorting of a structured document list and its insertion
          indicator.
    *   - ``is-image-closing``
        - The plugin root while the image editor collapses.
    *   - ``col-lg-4``, ``col-lg-8``, ``col-lg-12`` and
          ``.academic-persons-profile-editing__profile-fields-column``
        - The two column widths the image editor exchanges while it is open.
    *   - ``.alert[role="alert"]`` and ``.spinner-border``
        - The message and the busy indicator of an open document or contact
          panel, written onto the panel that is already there rather than by
          rebuilding it.
    *   - ``.invalid-feedback``, ``.form-check`` and ``.mb-3``
        - The message element of a refused field, and the two wrappers it is
          looked up from when the field carries no ``data-pe-field-wrapper``
          or ``data-pe-group-control``.
    *   - ``.academic-persons-profile-editing__field``
        - Every editable control of the profile fields. This is the class the
          field editing enumerates by, so an override that drops it leaves the
          field unreachable.
    *   - ``.academic-persons-profile-editing__sync-checkbox``
        - The synchronisation switch of :file:`Partials/Profile/Header.html`.
    *   - ``.ck``
        - CKEditor's own root, read to decide whether :kbd:`Escape` belongs to
          the editor or to the form. It is the library's class, not this
          extension's, and is the one entry here an override cannot change.
    *   - ``…-enter-from``, ``…-enter-active``, ``…-leave-active``,
          ``…-leave-to``
        - The collapse transitions, derived from the prefix of the editor that
          runs them. The declarations are in
          :file:`Resources/Private/Scss/frontend/profile-editing.scss`.

Frontend build
==============

The package has no separate development toolchain below
:file:`Resources/Public/`. TypeScript sources, SCSS sources and the committed
JavaScript and CSS output use the repository-wide frontend suites from the
repository root:

..  code-block:: bash

    Build/Scripts/runTests.sh -s buildJs
    Build/Scripts/runTests.sh -s checkJsBuildClean
    Build/Scripts/runTests.sh -s lintTypescript -n
    Build/Scripts/runTests.sh -s typecheckJs
    Build/Scripts/runTests.sh -s testJs

Nothing is bundled: the build emits one module per source file and leaves every
import as it was written. No JavaScript library is committed with the
extension - both libraries the editor uses, CKEditor 5 and CropperJS, are
resolved through the import map from ``rte_ckeditor`` and ``core``.

Tests
=====

Controller unit tests cover method, payload and authentication errors for the
JSON actions. Sanitizer unit tests cover the supported profile properties,
allowed editor markup and rejection of scripts, event attributes, styles,
unknown tags and unsafe link schemes. Validation-service unit tests verify that
sanitization happens before a value is registered for persistence.

A JavaScript suite runs the shipped modules and elements against a DOM
(``Build/Scripts/runTests.sh -s testJs``). It drives the rendered markup of the
Fluid partials through the modules that read it, which is the only place the
event handling, the optimistic list updates, the transitions and the focus
management are executed at all.

Functional plugin tests render the inline-collapse targets, the prototypes and
the image view, verify the decomposed Fluid contracts, AJAX-only controls,
direct rich-text previews and the separate delete, cancel and save actions.
They also assert the prototype inventory, every slot key of every prototype,
and that a prototype control and the live control of the same type carry the
same tag, the same classes and the same attributes. The section-provider unit
test
verifies that order, identifiers, field names, relation types and labels come
from the shared settings graph while row fields, action capabilities,
presentation modes and typed records are preserved. Functional fixtures cover
contracts and every configured profile-information relation; the rendered page
test derives the expected
order and metadata from the same settings service, then checks placement below
:guilabel:`About me`, alternating records, empty states, writable add controls
and exactly the configured row actions. AJAX lifecycle tests cover schema
loading, CKEditor inline fields, creation, partial updates, arrow and drag
sorting, cross-section rejection, deletion and read-only endpoint denial.
Contract tests additionally cover every configured field plus address,
email-address and phone-number creation, display, update, independent sorting,
deletion and cross-Contract rejection. They also guard the ProfileEditing
plugin against accidentally exposing removed mutation controllers.
Registration tests ensure that ProfileEditing is the only editing content
element offered to editors. The architecture unit test scans the
``ProfileController``, Fluid tree and JavaScript for forbidden legacy
controller and template references.

The AJAX tests persist malicious rich-text input through the real update
endpoint and assert that only the sanitized response is stored. The inline
image tests verify that a missing file can never return success and that a real
multipart upload returns ``hasImage: true`` and creates the FAL relation. They
also exercise the dedicated image deletion endpoint through the generated
action URL. Form submissions reuse the complete rendered action URL, including
the JSON page type, so the tests exercise the same routing contract as the
browser. Upload tests are assigned to the ``not-core-13`` PHPUnit group because
TYPO3 v13's CLI upload permission check requires a real HTTP upload.

..  _profile-editing-page-type:

The page type of the JSON endpoints
===================================

Every writing endpoint of the editor is an Extbase action of the same plugin,
reached through a dedicated :typoscript:`PAGE` object with
:typoscript:`typeNum = 1733735`. The object is delivered by the site set and by
the static template of this extension, it is not cached, and it sets
``Content-Type: application/json`` on the page - TYPO3 v13 drops the headers a
plugin response carries, so the page has to declare it.

Three things in a project's infrastructure have to know about that number:

*   **The TypoScript of the site itself.** The object exists where the
    TypoScript of this extension is *included* - through the site set
    ``fgtclb/academic-persons-edit-profile-editing`` or the static template
    :guilabel:`Academic Persons Edit: Profile editing`. There was no such page
    type before version 3.0, because the previous editor was a server-rendered
    form flow, so a site package that **copied** the extension's TypoScript
    into its own instead of including it has no
    :typoscript:`academicPersonsProfileEditingAjax` object. The editor then
    renders, and every save is answered with the HTML of the page instead of
    JSON - a failure that happens in the browser, with nothing in the TYPO3 log
    to look at. The editor therefore checks the TypoScript setup of the request
    for a :typoscript:`PAGE` object with that :typoscript:`typeNum`, and where
    there is none it renders a ``role="alert"`` message
    (``data-pe-missing-ajax-page-type``) instead of the silent failure, and
    logs the cause at error level. Include the delivered TypoScript, or copy
    the object into the site package:

    ..  code-block:: typoscript
        :caption: EXT:my_sitepackage/Configuration/TypoScript/setup.typoscript

        academicPersonsProfileEditingAjax = PAGE
        academicPersonsProfileEditingAjax {
          typeNum = 1733735

          10 < tt_content.academicpersonsedit_profileediting.20

          config {
            disableAllHeaderCode = 1
            admPanel = 0
            debug = 0
            disablePrefixComment = 1
            no_cache = 1
            additionalHeaders.10 {
              header = Content-Type: application/json
              replace = 1
            }
          }
        }

*   **Route enhancers.** A ``PageTypeDecorator`` that maps page types onto URL
    suffixes has to list ``1733735``, or the editor's request URLs are not
    resolvable. Without a decorator the type travels as ``&type=1733735`` and
    nothing has to be configured.

    ..  code-block:: yaml
        :caption: config/sites/<identifier>/config.yaml

        routeEnhancers:
          PageTypeSuffix:
            type: PageType
            default: ''
            index: ''
            map:
              profile-editing.json: 1733735

*   **Web application firewalls and reverse proxies.** The endpoints are
    ``POST`` requests carrying JSON, and one of them carries a
    ``multipart/form-data`` image upload. A rule set that blocks unknown JSON
    bodies, strips the ``X-Requested-With`` header or limits the upload size
    below the configured ``maxFileSize`` makes the editor fail with a generic
    error message and no server side log entry.

..  _profile-editing-request-header:

The request header the endpoints require
========================================

Every writing endpoint - the fourteen JSON actions and the image upload -
requires the request header ``X-Requested-With: XMLHttpRequest``. A request
without it is answered with ``400`` and the error code ``invalid_request``.

The shipped JavaScript sends the header on every request, so this is invisible
in normal use. It exists for the image upload, which is a
``multipart/form-data`` request and therefore one that any foreign page could
submit with a plain form, carrying the visitor's session along. A custom header
cannot be set on a cross-origin request without a CORS preflight, which the
browser refuses for a cross-site page - so requiring one closes that door
without relying on the ``SameSite`` attribute of the session cookie, which an
installation may change.

A project that talks to the endpoints from its own code has to send the header.

..  _profile-editing-icons:

Icon identifiers
================

The action icons of the editor are registered in
:file:`Configuration/Icons.php` and rendered through ``core:icon`` with
``alternativeMarkupIdentifier="inline"``, so the SVG is inlined and follows the
text colour of the button it sits in. The files are `Bootstrap Icons
<https://icons.getbootstrap.com/>`__ (MIT, see
:file:`Resources/Public/Icons/LICENSE-bootstrap-icons.txt`) drawn in
``currentColor``.

Identifier and file name name the *action*, not the glyph: a project that
replaces the icon set changes the drawing, not the identifiers its template
overrides address.

..  list-table::
    :header-rows: 1

    *   - Identifier
        - File
        - Used for
    *   - ``academic-persons-edit-add``
        - :file:`add.svg`
        - Add a document, contract or contact row
    *   - ``academic-persons-edit-back``
        - :file:`back.svg`
        - Back to the profile overview
    *   - ``academic-persons-edit-clear``
        - :file:`clear.svg`
        - Clear the value of a field
    *   - ``academic-persons-edit-delete``
        - :file:`delete.svg`
        - Delete a row or the profile image
    *   - ``academic-persons-edit-edit``
        - :file:`edit.svg`
        - Open a field or a row for editing
    *   - ``academic-persons-edit-help``
        - :file:`help.svg`
        - Help text popover
    *   - ``academic-persons-edit-move-down``
        - :file:`move-down.svg`
        - Move a row down
    *   - ``academic-persons-edit-move-up``
        - :file:`move-up.svg`
        - Move a row up
    *   - ``academic-persons-edit-save``
        - :file:`save.svg`
        - Save a field or a row
    *   - ``academic-persons-edit-sort-handle``
        - :file:`sort-handle.svg`
        - Drag handle of a sortable list
    *   - ``academic-persons-edit-undo``
        - :file:`undo.svg`
        - Restore the last saved value
    *   - ``academic-persons-edit-upload-image``
        - :file:`upload-image.svg`
        - Open the profile image editor
    *   - ``academic-persons-edit-view``
        - :file:`view.svg`
        - Open a row read-only, or the public profile

..  index:: AJAX, CKEditor, Fluid, Frontend, JavaScript, JSON, Profile image, Rich text, NotScanned
