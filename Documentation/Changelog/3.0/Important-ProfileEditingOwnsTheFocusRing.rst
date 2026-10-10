..  _important-profile-editing-owns-the-focus-ring:

======================================================
Important: The profile editor draws its own focus ring
======================================================

Description
===========

The :ref:`profile-editing` view now styles the focus of the controls and the
buttons it renders, and no longer leaves that to the surrounding theme.

Bootstrap draws a focused control's ring with ``box-shadow``, and a theme is
free to layer more rings behind Bootstrap's. `bk2k/bootstrap-package` does: it
adds an opaque white ring and an opaque black one behind the translucent accent
ring, on ``:focus-visible`` of ``.form-control``, ``.form-select``,
``.form-check-input`` and ``.btn``. Shadows paint in the order they are
written, so the black ring covers the full width and the white one only its
inner half, and a translucent accent ring over an opaque black one is dark: the
outer half of the ring reads as a hard black rectangle tight around the
control. Rendered and read back pixel by pixel, that band is ``#161e18`` on the
theme's light body background. The controls of the view carry speaking classes
since, see :ref:`breaking-persons-edit-speaking-frontend-classes`, and no form
class of Bootstrap, so of those rules only the one for ``.btn`` still reaches
the view. The ring of the view applies to every control and every button
regardless.

Two more things were wrong with drawing a focus ring as a shadow, and the view
now avoids all three at once by drawing a real ``outline`` instead.

A shadow is painted outside the border box, so an ancestor with
``overflow: hidden`` cuts it away. The document editor's collapse panel and the
image editor are exactly that, a full width control inside them reaches the
clipping edge, and every field and every button of those panels therefore sat
with its left and its right border edge on that edge. Measured before this
change, a focused field of a document panel and the file input of the image
editor - which the image editor focuses on every open - showed no ring pixel at
all on either side, only Bootstrap's ``#abbbb0`` border at about 2:1 against
white.

And forced-colours mode drops ``box-shadow`` altogether, while the rules that
draw a ring that way also set ``outline: 0``. What a user of a high contrast
theme then saw was not the site's focus indicator but whatever the browser put
in its place, which differs between browsers. An ``outline`` is kept and
recoloured in every one of them.

The ring is therefore drawn as an ``outline`` on ``:focus-visible``, inside the
border box so that no ancestor can clip it, and ``box-shadow: none`` in the
same rule takes the layered rings and the theme's inset shadow off with it.
``:focus-visible`` rather than ``:focus`` is what the theme's own rules use and
what Bootstrap uses for buttons, so it is exactly where the defect is.

``:focus-visible`` is not the same thing as keyboard focus, and the difference
decides what a visitor using a mouse sees. Measured in Chrome, a pointer click
on a checkbox or on the synchronisation switch does *not* match it, so those
two keep the soft glow Bootstrap has always drawn for them. A pointer click on
a text input or a select *does* match it, so those lose the glow and take the
inset ring instead. Four of the five controls of
:file:`Partials/Profile/Field/Control.html` - the text field, the two
textareas and the select - therefore look different on a plain mouse click
than they did before, and so do the file input of the image editor and the
controls of the document and contract editors. **The majority of the editor's
controls change their appearance on a mouse click, not only under the
keyboard.** Buttons are the exception in the other direction: the theme's rule
for ``.btn`` was already ``:focus-visible``, so what changes for a button is
the shape of the ring and not when it appears.

Its colour is ``currentcolor``, the colour the control draws its own text in.
That contrasts with the control by construction and in every colour mode,
which no fixed colour and no Bootstrap custom property does -
``--bs-primary-text-emphasis`` is dark by design and measures 1.6:1 against the
green of a focused ``.btn-success``.

The checkbox is the one control that reasoning does not cover, and it is worth
stating rather than glossing over. The view renders it as a native checkbox,
``ace-control ace-checkbox``, which the example stylesheet leaves to the
browser with ``appearance: auto``. The browser draws its tick in its own accent
colour, while the ring takes the text colour of the control. A site that draws
the checkbox itself, with a background image as Bootstrap's
``.form-check-input`` does, checks the contrast of the ring against the checked
control, and overrides the ``outline-color`` of the checkbox in the view where
it falls below the 3:1 WCAG 2.1 SC 1.4.11 asks of a focus indicator.

The rule reaches every ``input``, ``select``, ``textarea`` and ``button`` of
the view: the five controls of :file:`Partials/Profile/Field/Control.html`, the
image upload of :file:`Partials/Profile/Image/Editor.html`, the synchronisation
switch of :file:`Partials/Profile/Header.html` and the buttons of every action
group. Plain links are deliberately not covered - no theme rule takes their
focus ring away. CKEditor 5 is covered by halves: its editable region is a
``div`` and keeps the library's own focus styling, while the buttons of its
toolbar are ``button`` elements below the plugin root and take this ring
instead.

Impact
======

A site that did nothing about the focus appearance gets one clearly visible
ring on every control and every button of the view, in both colour modes, and
gets it in the panels where the previous appearance was cut away.

Check it with the mouse, not only with the keyboard. Most of the controls the
view renders match ``:focus-visible`` on a plain pointer click, so the new ring
replaces the theme's appearance for a visitor who never touches the tab key.
Only the checkboxes, the switches of the header among them, keep the focus
appearance of the browser on a pointer click.

A site that styled the focus of the editor's controls itself has to check that
its rule still wins. The shipped rule carries the plugin root three times
against the theme's compat layer, which puts it at four class selectors and a
tag name; a project rule that matched two class selectors now loses to it.
Adding the plugin root to the project's own selector, or an ``!important``,
restores the project's appearance.

Affected Installations
======================

Installations of the :guilabel:`Profile editing` content element of
`EXT:academic_persons_edit` whose site styles the focus of its form controls or
buttons, or whose theme relied on styling them.

..  index:: Frontend, ext:academic_persons_edit, NotScanned
