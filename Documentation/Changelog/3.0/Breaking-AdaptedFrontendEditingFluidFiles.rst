..  _breaking-adapted-frontend-editing-fluid-files:

==============================================
Breaking: Adapted frontend editing Fluid files
==============================================

Description
===========

Version 3.0 changes Fluid templates and partials that
`EXT:academic_persons_edit` ships for its frontend editing plugin. They
are shipped files, not API, and an installation that overrides one of
them keeps its own copy — and therefore does not receive the change.

This page collects all of those changes for 3.0 in one place. It is
extended whenever another shipped Fluid file is adapted.

Profile image actions
---------------------

:file:`Resources/Private/Templates/Profile/Show.html`

The profile image actions used to be rendered as an either/or: while the
profile had an image only the "Delete" link was offered, and the
:php:`editImage` action — the upload form — was only linked while the
profile had none.

Both are now rendered next to each other for a profile that has an
image: a "Replace" link pointing to :php:`editImage` and, as before, the
"Delete" link pointing to :php:`removeImage`. See
:ref:`feature-replace-profile-image` for what this enables.

The added link uses the new `actions.replace` label and the new
`academic-persons-edit-replace-image` icon identifier.

Profile image form
------------------

:file:`Resources/Private/Templates/Profile/EditImage.html`

The upload form renders the image that is currently in place above the
file field, by rendering the existing
:file:`Resources/Private/Partials/Profile/Show/Image.html` partial.
Reached as "Replace", the form otherwise gives no indication of what is
being replaced.

Profile image markup
--------------------

:file:`Resources/Private/Partials/Profile/Show/Image.html`

Every :html:`<source>` candidate of the :html:`<picture>` element
declares :html:`type="image/webp"`, and the :html:`<img>` fallback is no
longer requested as WebP but rendered in the source format. Without a
declared type a browser picks a candidate by media query alone and, per
specification, does not fall back to the :html:`<img>` when it cannot
decode it — which was WebP as well, so there was no fallback at any
point.

Profile image copyright
-----------------------

:file:`Resources/Private/Partials/Profile/Show/Image.html`

The partial rendered a copyright above the image:

..  code-block:: html

    <f:if condition="{image.properties.show_copyright} && {image.properties.copyright}">
        <span class="copyright">&copy; {image.properties.copyright}</span>
    </f:if>

That block is removed. It has never rendered anything in any released
version, for two independent reasons: the view variable `image` is never
assigned — see :ref:`important-1785686400` — and `show_copyright` is not
a field of TYPO3. It exists in no system extension, in no extension this
package depends on or suggests, and nowhere in this repository except
that condition. The `copyright` field itself is real, but comes with the
system extension :file:`EXT:filemetadata`, which is not a dependency
here.

Repairing the property paths would therefore not have produced a working
copyright — it would have produced a second, differently broken
condition. Rendering the copyright of a profile image is a feature, not a
defect fix: it needs a decision about where the value comes from, a
declared dependency on whatever provides it, and test coverage proving it
renders. It should be introduced that way if it is wanted.

Impact
======

Installations that override any of the listed Fluid files keep the
behaviour of their own copy:

* an overridden :file:`Profile/Show.html` continues to offer no replace
  link, so the replacement handling of :php:`addImageAction()` stays
  unreachable through the user interface,
* an overridden :file:`Profile/EditImage.html` does not show the image
  being replaced,
* an overridden :file:`Profile/Show/Image.html` continues to offer no
  format fallback for the profile image, and keeps its copy of the
  copyright block — which renders nothing there either.

Nothing breaks at runtime — the overrides keep working. What is lost is
the new behaviour.

Affected Installations
======================

All installations using the `EXT:academic_persons_edit` extension that
override the profile detail template, the profile image form template or
the profile image partial.

Migration
=========

Re-apply the project specific overrides on top of the updated files.

.. index:: Fluid, Frontend, Template, NotScanned
