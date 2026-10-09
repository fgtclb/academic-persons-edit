..  _feature-1791566504:

=============================================================
Feature: The script of the profile editor can be switched off
=============================================================

Description
===========

The template :file:`Templates/Profile/Index.html` of the
:guilabel:`Profile editing` content element registers the module
:js:`@fgtclb/academic-persons-edit/frontend/profile.js`, which drives the whole
editor. An installation that brings an editor of its own had to override the
template to leave it out.

The module is switchable now, per site:

..  list-table::
    :header-rows: 1

    *   -   Site setting and constant
        -   Default
    *   -   :yaml:`plugin.tx_academicpersonsedit.assets.js`
        -   :yaml:`true`

It is declared by the site set `fgtclb/academic-persons-edit-profile-editing`,
the set of the component:

..  code-block:: yaml
    :caption: config/sites/my-site/settings.yaml

    plugin.tx_academicpersonsedit.assets.js: false

An installation that configures its frontend through :sql:`sys_template`
records sets the TypoScript constant of the very same name, in
:guilabel:`Constants`:

..  code-block:: typoscript
    :caption: Constants of the root sys_template record

    plugin.tx_academicpersonsedit.assets.js = 0

Every academic extension that loads a script has this switch, named
:typoscript:`assets.js` below its own plugin namespace and switched on by
default, after the switch of the study plan content element.

Impact
======

Nothing changes for an installation that configures nothing: the editor
loads its script exactly as before.

Switched off, the page does not load it, and the markup is unchanged. **The
editor does nothing without a script**, so switching it off means bringing a
complete editor for the same markup and the same endpoints. See
:ref:`configuration-javascript`.

A project that overrides :file:`Templates/Profile/Index.html` keeps whatever
its copy loads. The switch reaches it once it wraps its own
:html:`<f:asset.module>` in :html:`<f:if condition="{settings.assets.js}">`.

.. index:: Frontend, Fluid, TypoScript, ext:academic_persons_edit
