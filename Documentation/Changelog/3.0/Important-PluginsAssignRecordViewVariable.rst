..  _important-persons-edit-plugins-assign-record-view-variable:

==================================================
Important: Plugins assign a `record` view variable
==================================================

Description
===========

TYPO3 v14 rewrote the header partial of `EXT:fluid_styled_content`. Where v13
`Header/All.html` reads `{data.header}`, the v14 `Header/All.fluid.html` renders
header and subheader with `{record -> f:render.text(...)}`, and that ViewHelper
requires a record object.

Content elements based on `lib.contentElement` receive that record from the
`record-transformation` data processor, but an Extbase plugin view assigns only
the `data` array. Templates of this extension render the shared header partial,
so on TYPO3 v14 they aborted with

..  code-block:: text

    The record argument must be an instance of ... Given: null

The plugin controllers now assign an additional `record` view variable, built
from the `tt_content` row of the current content element. TYPO3 v13 ignores it,
its header partial keeps reading `data`, so one implementation serves both core
versions.

Impact
======

The affected plugins render again on TYPO3 v14. Nothing was removed or renamed,
so no configuration or template override needs to be adapted.

Custom templates and template overrides may use the new `{record}` variable, for
example with `<f:render.text record="{record}" field="header" />`.

Affected Installations
======================

Installations running the plugins of this extension on TYPO3 v14. TYPO3 v13
installations are unaffected.

Migration
=========

None required.

.. index:: Fluid, Frontend, NotScanned
