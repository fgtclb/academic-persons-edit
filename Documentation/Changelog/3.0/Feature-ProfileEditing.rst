..  _feature-profile-editing:

===========================================
Feature: The profile editing view rewritten
===========================================

Description
===========

The :guilabel:`Profile editing` content element renders the whole profile on
one page and saves each change where it is made. The previous form flow -
one page per record, a submission per change, a redirect after every save - is
gone (:ref:`breaking-replaced-profile-editing-plugin`).
:ref:`profile-editing` is the reference; this entry is the overview.

Overview
--------

``list`` renders the profiles assigned to the authenticated frontend user,
with the language each of them belongs to and a link to the public detail page.
``index`` opens one of them for editing. Every other action of the plugin
answers JSON.

Editing in place
----------------

Each field carries an edit control. Activating it turns the rendered value into
an input, saving posts only what changed, and the answer is written back into
the page - no reload, no lost scroll position. A group of fields that belong
together (the name parts, a link and its title) is edited and saved as one.

An unsaved field can be restored to the value that is stored, and a checkbox
saves on change and reverts itself when the request fails, so what is on screen
is what is in the database. Only one field or group is open at a time: opening
another one discards the one that is open, so no value is ever left behind in a
control the visitor cannot see.

:guilabel:`Edit all` opens every field of the page at once and gives the form
its own controls; see
:ref:`feature-full-form-editing-applies-as-one-form`.

Structured sections
-------------------

Contracts and the seven kinds of timeline entry - cooperation, lectures,
memberships, press and media, publications, scientific research and vita - are
compact lists with an editor that folds out below the row it belongs to.
Creating, viewing, editing, deleting and reordering happen without leaving the
page; deleting asks first. Which columns a list shows, which actions it offers
and whether it is read-only is configured per section
(:ref:`breaking-contract-configuration-and-accessible-profile-editing`), and
the server enforces it.

Reordering works with the up and down buttons, with the keyboard, and by
dragging a row onto its new place. A failed request puts the previous order
back.

Contract contacts
-----------------

The addresses, email addresses and phone numbers of a contract are edited
inside the contract's own editor, each kind as its own list with its own
configured fields.

Rich text and character limits
------------------------------

A field configured as ``ckeditor`` opens a CKEditor 5 instance with bold,
italic, lists and links, in the language of the site. A configured character
limit is shown while typing, enforced in the editor and validated again on the
server. Every stored value is sanitized against an allow list - paragraphs,
line breaks, bold, italic, lists and links with an ``http``, ``https``,
``mailto`` or ``tel`` target - so what an editor pastes cannot reach the public
profile as markup the template did not expect.

Profile image
-------------

The image is edited in a panel that folds out over the profile. A newly
selected file is cropped to the configured aspect ratio in the browser before
it is uploaded, so the stored file is the one that is shown. Uploading replaces
the existing relation rather than adding a second one, and deleting asks for a
confirmation first. The upload is validated on the server against the
configured maximum file size and MIME type list - a blanked setting falls back
to the same defaults the file input advertises, it does not disable the check.

Years
-----

The three year fields of a timeline entry are ``<input type="number">``
controls carrying ``min="0"``, ``max="9999"`` and ``step="1"`` - the bounds the
TCA of :composer:`fgtclb/academic-persons` declares for the same columns, which
the endpoint enforces again on every submission. Nothing about a year is
formatted, so nothing about it follows a locale.

Contract dates
--------------

The two contract dates ``validFrom`` and ``validTo`` are typed into a plain
text control showing ``d.m.Y`` behind the hint ``dd.mm.yyyy``, exactly as the
previous editor showed them. They are deliberately **not** an
``<input type="date">``: the native control follows the locale of the browser
rather than the one of the site, it cannot be styled with the rest of the
editor, and its calendar is not the date picker this editor is meant to get.
**A date picker is deliberately not shipped in 3.0.** It is a feature of its
own and it will not be the browser's.

The endpoint reads ``d.m.Y`` and ``Y-m-d``, both strictly, and refuses
anything else. A stored date is displayed as the ``MEDIUMDATE`` of the site
language, the way the public profile renders it.

Synchronisation switch
----------------------

The switch that excludes a profile from the translation synchronisation is
saved through its own endpoint, and reverts itself when the request fails.

Accessibility
-------------

Every control has an accessible name, the fold-out regions carry
``aria-controls``/``aria-expanded``, validation errors are announced through
``aria-describedby``/``aria-invalid``, a failed request is announced through an
assertive live region and a successful one through a polite one, and closing an
editor returns the focus to the control that opened it.

That last one has one deliberate exception. An editor that is closed because
another one is being opened does *not* hand the focus back to its own pencil -
the caret belongs in the editor the visitor has just opened, and moving it
twice would take it out of there again. The rule is therefore that a close the
visitor asked for returns the focus, and a close that happens on the way to
somewhere else leaves it where it is going.

Bundled libraries
-----------------

One third-party set is shipped with the extension, with its licence file next
to it: Bootstrap Icons (MIT), the thirteen control icons of this view, as SVG
files under :file:`Resources/Public/Icons/` with
:file:`LICENSE-bootstrap-icons.txt` beside them.

No JavaScript library is shipped. The view is Fluid, driven by five plain
custom elements that depend on no framework; CKEditor 5 is loaded from the
system extension ``rte_ckeditor`` - see
:ref:`important-new-ckeditor-and-sanitizer-dependency` and
:ref:`important-profile-editing-custom-elements-and-prototypes` - and the image
cropper is the Cropper.js the TYPO3 core itself maps as ``cropperjs``, version
1.6.1 on both supported core versions. No CDN or other runtime request is
involved either way.

Impact
======

The editing view is replaced for every installation of the content element.
What has to be looked at when updating is listed in
:ref:`breaking-replaced-profile-editing-plugin`.

..  index:: AJAX, CKEditor, Fluid, Frontend, JavaScript, ext:academic_persons_edit, NotScanned
