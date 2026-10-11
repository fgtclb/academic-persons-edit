..  _breaking-adapted-frontend-editing-fluid-files:

==============================================
Breaking: Adapted frontend editing Fluid files
==============================================

Description
===========

Version 2.4 changes Fluid templates and partials that
`EXT:academic_persons_edit` ships for its frontend editing plugin. They
are shipped files, not API, and an installation that overrides one of
them keeps its own copy and therefore does not receive the change.

This page lists the changes of 2.4 to those files. Most of them have an
entry of their own, which describes the change and how to apply it to a
copy:

*   The contact record lists and :file:`Templates/Contract/Show.html`
    show hidden records and a show and hide toggle, see
    :ref:`breaking-1782285579`.
*   :file:`Partials/Profile/Show/Image.html` reads the image metadata
    from the file and no longer requests WebP unconditionally, see
    :ref:`important-1785686400` and
    :ref:`important-ace-848-academic-persons-edit`.
*   :file:`Partials/Profile/Properties/Profile.html`,
    :file:`Partials/Profile/Properties/Contract.html` and the contract
    templates start the rich text editor, see
    :ref:`important-profile-and-contract-forms-start-the-editor`.
*   :file:`Templates/Profile/Edit.html` loads the editor configuration
    from its new location, see
    :ref:`breaking-ckeditor-configuration-moved`.
*   The partials below :file:`Partials/Profile/Forms/` and the templates
    of the six forms show the messages of a rejected form, see
    :ref:`important-rejected-forms-show-their-messages`.
*   Every template and partial translates with the extension name
    :html:`AcademicPersonsEdit`, see
    :ref:`important-label-overrides-use-the-documented-path`.

The changes without an entry of their own follow.

Profile name without empty parts
--------------------------------

The headers of the templates and :file:`Partials/Profile/Show/Personal.html`
rendered the name of a profile as
:html:`{profile.title} {profile.firstName} {profile.middleName} {profile.lastName}`,
so a profile without a title or a middle name got a leading or a double
space. They now leave out an empty title and an empty middle name together
with their space:

..  code-block:: html

    {f:if(condition: profile.title, then: '{profile.title} ')}{profile.firstName}{f:if(condition: profile.middleName, then: ' {profile.middleName}')} {profile.lastName}

The browser collapses the spaces of the old markup, so the visible name
does not change.

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
assigned (see :ref:`important-1785686400`), and `show_copyright` is not
a field of TYPO3. It exists in no system extension, in no extension this
package depends on or suggests, and nowhere in this extension except
that condition. The `copyright` field itself is real, but comes with the
system extension `EXT:filemetadata`, which is not a dependency here.

Repairing the property paths would therefore not have produced a working
copyright. It would have produced a second, differently broken
condition. Rendering the copyright of a profile image is a feature, not a
defect fix: it needs a decision about where the value comes from, a
declared dependency on whatever provides it, and test coverage proving it
renders.

Impact
======

Installations that override one of the adapted files keep the behaviour
of their own copy. For the two changes described on this page that is
harmless: an overridden :file:`Profile/Show/Image.html` keeps its
copyright block, which renders nothing there either, and an overridden
header keeps its extra spaces.

The changes listed above with an entry of their own do change what a copy
misses. Their entries name the consequences.

Affected Installations
======================

All installations using `EXT:academic_persons_edit` that override one of
its templates or partials.

Migration
=========

Re-apply the project specific overrides on top of the updated files, using
the entries linked above for the changes they describe.

.. index:: Fluid, Frontend, Template, NotScanned, ext:academic_persons_edit
