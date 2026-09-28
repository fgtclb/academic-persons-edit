..  _feature-project-profile-fields:

====================================================
Feature: Project columns can be edited in the editor
====================================================

Description
===========

A column a site package adds to the profile table can now be edited in the
profile editor. The site package ships the column, its SQL and its TCA, and
declares it in :file:`Configuration/AcademicPersons/Settings.yaml` below
:yaml:`profile` as a project field:

..  code-block:: yaml

    profile:
      namePrefix:
        custom: true
        section: information
        fieldName: tx_mysitepackage_name_prefix
        fieldType: input
        renderType: text
        validators:
          - required

The editor shows the field in its section with the configured renderer and the
stored value, validates it in the same pass as every other field, and writes
it to the row of the edited language through the DataHandler, a column all
languages share to the default-language record. The validators reach the
backend form of the column as well. The text, textarea, rich text and checkbox
renderers are available, on columns of the TCA types ``input``, ``text``,
``email``, ``link``, ``number`` and ``check``, and the renderer has to fit the
type.

A column that is not in the profile TCA, a system column, a column of the
shipped profile model, one of another type or one whose renderer or validators
do not fit it raises a deprecation notice naming it while the TCA is compiled,
and makes the editor fail with the same message. The backend and the install
tool keep working.

See :ref:`configuration-editor-project-fields`.

Impact
======

Nothing changes for an installation that declares no project field.

Until now the editor refused every value it had no property for, and the 2.x
way of adding one, an XCLASS of the form data object and of its factory, no
longer works on 3.0. Such an XCLASS can be replaced by a project field.

A checkbox field of the profile section now shows :guilabel:`Yes` or
:guilabel:`No` instead of :guilabel:`Public` or :guilabel:`Private`. None of the
shipped profile fields is a checkbox.

..  index:: Frontend, ext:academic_persons_edit, NotScanned
