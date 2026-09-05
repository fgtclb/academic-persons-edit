.. _important-1785686400:

========================================
Important: Adapted profile image partial
========================================

Description
===========

`Resources/Private/Partials/Profile/Show/Image.html` read the image metadata from
a view variable `image` that is never assigned. The profile detail view of the
`academicpersonsedit_profileediting` plugin therefore never rendered the caption,
and it emitted an empty `alt` and `title` attribute even when the file carried
that metadata.

The profile carries a `\TYPO3\CMS\Extbase\Domain\Model\FileReference`, and that
class exposes nothing but `getOriginalResource()`. Every other property path on it
resolves to `null` — which is why `{profile.image.alternative}` was as empty as
`{image.description}`. All values are now read through `originalResource`:

..  code-block:: html

    <!-- before -->
    <img alt="{profile.image.alternative}" title="{profile.image.title}">
    <f:if condition="{image.description}">
        <figcaption class="visually-hidden">{image.description}</figcaption>
    </f:if>

    <!-- after -->
    <img alt="{profile.image.originalResource.alternative}"
         title="{profile.image.originalResource.title}">
    <f:if condition="{profile.image.originalResource.description}">
        <figcaption class="visually-hidden">{profile.image.originalResource.description}</figcaption>
    </f:if>

The same partial rendered a copyright, which never worked either and was
removed instead of repaired. The partial itself is gone from version 3.0 on -
see :ref:`breaking-replaced-profile-editing-plugin`.

Impact
======

The profile detail view of the frontend editing plugin now renders

*   the `alt` and `title` attribute of the profile image from the file metadata
    instead of always empty,
*   a `figcaption` when the image has a description.

Note that nothing in this extension writes image metadata — an image uploaded
through the plugin has none until it is maintained in the backend. Installations
that never maintained the metadata of their profile images therefore see no
change in the rendered output.

Affected Installations
======================

Installations using the `academicpersonsedit_profileediting` plugin, and any
installation overriding `Partials/Profile/Show/Image.html`.

Migration
=========

No configuration change is required.

Installations that override the partial keep their own copy and stay unaffected —
including its defect. Adopt the property paths above to render the metadata.

.. index:: Fluid, Frontend, NotScanned
