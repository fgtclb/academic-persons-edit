..  _breaking-adapted-frontend-editing-fluid-files:

==============================================
Breaking: Adapted frontend editing Fluid files
==============================================

Description
===========

Version 2.4 changes Fluid templates and partials that
`EXT:academic_persons_edit` ships for its frontend editing plugin. They
are shipped files, not API, and an installation that overrides one of
them keeps its own copy — and therefore does not receive the change.

This page collects all of those changes for 2.4 in one place. It is
extended whenever another shipped Fluid file is adapted.

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

Installations that override :file:`Profile/Show/Image.html` keep the
behaviour of their own copy, including its copyright block — which
renders nothing there either.

Nothing breaks at runtime — the overrides keep working.

Affected Installations
======================

All installations using the `EXT:academic_persons_edit` extension that
override the profile image partial.

Migration
=========

Re-apply the project specific overrides on top of the updated files.

.. index:: Fluid, Frontend, Template, NotScanned
