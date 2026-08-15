..  _breaking-ckeditor-configuration-moved:

========================================
Breaking: The editor configuration moved
========================================

Description
===========

The script configuring the CKEditor instances of the frontend form is now
compiled from a TypeScript source and moved into a :file:`frontend/`
subdirectory:

..  code-block:: text

    EXT:academic_persons_edit/Resources/Public/JavaScript/CKEditor.js
    ->  EXT:academic_persons_edit/Resources/Public/JavaScript/frontend/ckeditor.js

It is still a classic script, loaded exactly as before, and CKEditor itself is
still loaded from a content delivery network by the same template.

Impact
======

An installation that uses the extension as shipped needs to do nothing: the
files are loaded by the extension itself, from the new location.

An installation that referenced the old path keeps pointing at a file that no
longer exists.

The rich text fields then fall back to plain textareas.

Affected installations
======================

Installations that override :file:`Templates/Profile/Edit.html` or reference
:file:`CKEditor.js` from their own site package.

Migration
=========

In an overridden template, add the :file:`frontend/` segment and the new file
name:

..  code-block:: html

    <f:asset.script
        identifier="ckeditor-config"
        src="EXT:academic_persons_edit/Resources/Public/JavaScript/frontend/ckeditor.js"
        defer="1"
    />
