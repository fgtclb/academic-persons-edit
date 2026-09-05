..  _important-new-ckeditor-and-sanitizer-dependency:

===================================================================
Important: New dependencies `cms-rte-ckeditor` and `html-sanitizer`
===================================================================

Description
===========

`EXT:academic_persons_edit` gained two hard requirements with the replaced
profile editing view:

..  list-table::
    :header-rows: 1

    *   -   Package
        -   Constraint
        -   What needs it
    *   -   :composer:`typo3/cms-rte-ckeditor`
        -   ``~13.4.0@dev || ~14.3.6@dev``
        -   The rich text fields of the editing view load six CKEditor 5
            bundles shipped by that system extension. Only its JavaScript is
            used - none of its backend rich text configuration.
    *   -   :composer:`typo3/html-sanitizer`
        -   ``^2.3``
        -   Every rich text value is sanitized against an allow list on the
            server before it is stored. The extension registers its own builder
            on that package.

:file:`ext_emconf.php` declares ``rte_ckeditor`` accordingly.
:composer:`typo3/html-sanitizer` is a Composer library, not an extension, so it
has no :file:`ext_emconf.php` counterpart.

Impact
======

Composer-managed installations resolve both automatically on update; nothing has
to be done. :composer:`typo3/html-sanitizer` is a dependency of the TYPO3 core
already, so only :composer:`typo3/cms-rte-ckeditor` is actually added to most
installations.

Classic, non-composer installations have to activate the system extension
``rte_ckeditor`` in the Extension Manager. Without it the rich text fields of
the editing view stay plain textareas and their JavaScript fails to load.

Affected Installations
======================

Every installation of `EXT:academic_persons_edit` 3.0.0. Classic installations
that had ``rte_ckeditor`` deactivated need to activate it.

Migration
=========

Nothing beyond installing the extension - see
:ref:`Installation <installation>`.

.. index:: Frontend, NotScanned, ext:academic_persons_edit
