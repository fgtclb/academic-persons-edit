..  index:: Configuration; Editor settings, Configuration; Validation
..  _configuration-editor-settings:

=============================
Profile editor and validation
=============================

:file:`academic-persons/Configuration/AcademicPersons/Settings.yaml` is the
canonical settings source for :guilabel:`academic_persons` and
:guilabel:`academic_persons_edit`. It contains one ordered profile schema and
the related inline maps:

``profile``
    The public detail layout together with direct Profile fields grouped into
    visual sections. The ``structure`` and ``details`` keys configure public
    rendering; the remaining entries define editable fields and their shared
    validation metadata.
``special``
    Composed or dedicated components such as the title, image and sync switch.
``contracts``
    The reusable Contract document type. Its ordered ``fields`` map drives the
    Contract editor; ``contactSections`` contains the physical-address, email
    and phone editors and their respective ordered fields.
``documentSections``
    Ordered structured collections. The Contract entry only references
    ``type: contracts``; ordinary profile-information entries remain inline.

All consumers receive the same normalized settings graph. This keeps public
rendering, profile editing, frontend validation and the corresponding backend
TCA state aligned. Public layout stays below :yaml:`profile`; the other maps
remain technical sections in the same file.

Loading and overrides
=====================

The central factory reads the same relative path from every active package and
merges the maps recursively. A site package can therefore override the shared
configuration by providing
:file:`Configuration/AcademicPersons/Settings.yaml` that names the keys it
changes and nothing else. A list - a :yaml:`validators` list, a
:yaml:`structure` column - is replaced as a whole, and :yaml:`~` removes a key;
an entry left out is kept as shipped. The `overriding the file
<https://docs.typo3.org/p/fgtclb/academic-persons/main/en-us/Configuration/Sections/Index.html#configuration-sections-override>`__
section of :guilabel:`academic_persons` has the complete rules.

Flush TYPO3 caches after a change so the unified typed settings graph and its
cache entry are rebuilt.

Field validators
================

Regular fields declare an ordered list of flags:

..  code-block:: yaml

    profile:
      website:
        section: information
        fieldType: input
        renderType: combinedLink
        validators:
          - url

Supported flags are:

..  list-table::
    :header-rows: 1

    *   - Flag
        - Effect
    *   - ``required``
        - Adds ``NotEmptyValidator`` plus the frontend HTML, marker and JSON
          metadata.
    *   - ``readonly``
        - Prevents editing in the frontend editor. A submitted value is
          ignored.
    *   - ``frontendreadonly``
        - Prevents editing in the frontend editor like ``readonly``, and keeps
          the field editable in the TYPO3 backend.
    *   - ``disabled``
        - Disables editing and implies ``readonly``. A locked field can never
          remain required.
    *   - ``email``
        - Adds ``EmailAddressValidator`` and email input metadata.
    *   - ``url``
        - Adds ``UrlValidator`` and URL input metadata.
    *   - ``number``, ``tel`` and ``date``
        - Select the matching frontend input type. ``date`` is the exception
          to that: it selects the date *field* type, which the profile editor
          renders as a plain text control showing ``d.m.Y``. A date picker is
          deliberately not shipped yet, and the native
          ``<input type="date">`` is not used - see
          :ref:`the contract dates <profile-editing-contract-dates>`.
    *   - ``textarea`` and ``html``
        - Select text-area input; ``html`` also activates sanitized rich text.

Rules remain attached to the configured profile, contact or document section.
They are not collected from a sibling section. Only submitted fields are
validated during a partial AJAX update.

Profile CKEditor character limit
================================

A profile field configured as CKEditor may declare its character limit directly
on the field:

..  code-block:: yaml

    profile:
      miscellaneous:
        section: aboutme
        fieldType: textarea
        renderType: ckeditor
        characterLimit: 1000
        validators:
          - html

A positive integer ``characterLimit`` adds a live ``current / limit`` counter
and prevents the editor from accepting additional visible characters beyond
the limit. The partial AJAX update is checked independently by the same
server-side Extbase validation. HTML tags do not count. The property is ignored
when ``renderType`` is not ``ckeditor`` or its value is invalid or non-positive.
The key is case-sensitive; ``characterlimit`` is not normalized.

This deliberately differs from document descriptions, which keep their
existing nested ``editor.limit`` schema. Both forms normalize to the same typed
validation metadata. Character limits do not alter backend TCA or the database
schema.

Document validators and aliases
===============================

Document aliases map presentation names to DTO and database properties:

..  list-table::
    :header-rows: 1

    *   - Settings key
        - DTO property
        - Domain/database field
    *   - ``from``
        - ``yearStart``
        - ``year_start``
    *   - ``to``
        - ``yearEnd``
        - ``year_end``
    *   - ``contracts.fields.validFrom``
        - ``validFrom``
        - ``valid_from``
    *   - ``contracts.fields.validTo``
        - ``validTo``
        - ``valid_to``
    *   - ``year``
        - ``year``
        - ``year``
    *   - ``description``
        - ``bodytext``
        - ``bodytext``

The shipped year rules are deliberately dynamic:

..  code-block:: yaml

    documentSections:
      cooperation:
        validators:
          from:
            - number
          to:
            - number
          year:
            - required
            - number

Consequently ``year`` receives a required attribute and marker while ``from``
and ``to`` remain optional. Removing or adding ``required`` in a site override
changes the JSON metadata, the rendered controls, the Extbase validation and the corresponding
backend TCA field state together; no field name is hard-coded as mandatory.

The richer description form remains supported:

..  code-block:: yaml

    description:
      editor:
        limit: 500
        type: ckeditor

``editor.type: ckeditor`` is normalized to the ``html`` validation/input
metadata so the editor and the server side sanitizer agree. A positive integer
``editor.limit`` additionally defines the maximum number of visible
characters. HTML tags do not consume that allowance; entities, non-breaking
spaces and repeated whitespace are normalized before counting. The editor
returns the limit in its JSON field metadata, displays a live ``current / limit``
counter and prevents CKEditor from accepting further characters beyond it.
Both the document JSON actions and the Extbase form-data validator enforce the
same limit on the server.

``editor.limit`` is frontend validation metadata, not TCA configuration. It is
ignored for non-CKEditor controls, and removing it or setting it to a non-
positive value disables both the counter and the additional validation without
changing backend FormEngine.

..  _configuration-editor-project-fields:

Project fields
==============

A column a site package adds to the profile table can be edited in the profile
editor. The site package ships the column itself, its SQL and its TCA, and
declares it below :yaml:`profile` as a project field with :yaml:`custom: true`:

..  code-block:: sql
    :caption: EXT:my_sitepackage/ext_tables.sql

    CREATE TABLE tx_academicpersons_domain_model_profile (
        tx_mysitepackage_name_prefix varchar(30) DEFAULT '' NOT NULL
    );

..  code-block:: php
    :caption: EXT:my_sitepackage/Configuration/TCA/Overrides/tx_academicpersons_domain_model_profile.php

    \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTCAcolumns(
        'tx_academicpersons_domain_model_profile',
        [
            'tx_mysitepackage_name_prefix' => [
                'label' => 'LLL:EXT:my_sitepackage/Resources/Private/Language/locallang_db.xlf:profile.name_prefix',
                'config' => ['type' => 'input', 'max' => 30],
            ],
        ],
    );

..  code-block:: yaml
    :caption: EXT:my_sitepackage/Configuration/AcademicPersons/Settings.yaml

    profile:
      namePrefix:
        custom: true
        section: information
        fieldName: tx_mysitepackage_name_prefix
        fieldType: input
        renderType: text
        validators:
          - required

The identifier, :yaml:`namePrefix` here, is the name the editor submits the value
under. It must not be a property of the profile model, and a
:yaml:`propertyName` is ignored. :yaml:`fieldName` names the column and is
required. The field appears after the shipped fields of its section.

The column has to be in the TCA of the profile table with the type ``input``,
``text``, ``email``, ``link``, ``number`` or ``check``. It must be neither a
system column, such as the language column or the column that hides the
profile, nor a column of the shipped profile model. A column a project maps in
an XCLASS of the model is not a column of the shipped model and can be used.

The renderer has to fit the column:

..  list-table::
    :header-rows: 1

    *   - TCA type of the column
        - Renderer and validators
    *   - ``input``, ``number``
        - ``text``, ``textarea`` or a renderer of an input type, such as
          ``phone``
    *   - ``text``
        - ``text``, ``textarea`` or ``ckeditor``
    *   - ``email``
        - The ``email`` validator, which the DataHandler would otherwise apply
          after the other fields of the request are stored
    *   - ``link``
        - The ``url`` validator, for the same reason, and ``allowedTypes``
          that include ``url``. The field takes web addresses, ``http`` and
          ``https``, and no other link of TYPO3
    *   - ``check``
        - ``checkbox``, and no other column takes it

``select`` and ``combinedLink`` are not available. A checkbox shows
:guilabel:`Yes` or :guilabel:`No`, labels that are overridden like every other
label of the editor, see :ref:`configuration-labels`.

A project field is validated, limited and sanitised like any other field with
the same configuration, in the same pass, so an invalid value stores nothing of
the request. Its validators reach the backend form of the column as well, a
:yaml:`required` project field is required there too. The flags
:yaml:`readonly`, :yaml:`frontendreadonly` and :yaml:`disabled` and the
:yaml:`managedFields` map lock it like any other field.

The editor reads the value from the row of the edited language and writes it
there, through the DataHandler: a translation keeps its own value, and the
change gets a history entry. A column with ``l10n_mode: exclude`` is written to
the default-language record instead, from which every translation takes it. A
column with ``allowLanguageSynchronization`` keeps the value a translation is
given as the translation's own, as the backend does when an editor types one.
The value is written after the regular fields of the same request and before the
update is announced, so the translation synchronisation sees it, and the answer
carries the value as it was stored. An update made in a translation is not
announced, as for every other field. That holds for a shared column written to
the default-language record as well, which the DataHandler carries into the
translations itself. A workspace preview cannot write a project field and is
answered with ``409``.

The two writes of one request are not one transaction. A value that passed the
validation and is refused by the DataHandler nonetheless, by a hook of the
installation for example, is answered with ``500`` and logged, and the regular
fields of the request stay stored.

The label is :xml:`profile.<identifier>.label` of the editor, set like any
other label, see :ref:`configuration-labels`:

..  code-block:: typoscript

    plugin.tx_academicpersonsedit._LOCAL_LANG {
      default.profile.namePrefix.label = Name prefix
      de.profile.namePrefix.label = Namenszusatz
    }

A column that cannot be used raises a deprecation notice while the TCA is
compiled, which names the field, the column and the reason. The backend and
the install tool keep working, the settings of the field are not applied to the
TCA, and a test run that fails on deprecations fails on it. The profile editor
fails with an exception carrying the same message whenever it renders the
page, and answers a request writing the field with ``500`` and the error
``invalid_project_field``. Where deprecation notices are not logged, that
exception is what points to the mistake.

Contract form and contact sections
==================================

The order of :yaml:`contracts.fields` is the form order returned by the JSON
endpoint. Every entry declares its frontend field and render type, validation,
option source and helptext together. The shipped ``organisationalUnits``,
``functionTypes`` and ``locations`` option sources resolve their records
through the corresponding repositories. Removing or reordering an entry
therefore removes or reorders that control without changing the template.

Physical addresses, email addresses and phone numbers are ordered structural
children below :yaml:`contracts.contactSections`. Each child owns an ordered
``fields`` map and an isolated validation set:

..  code-block:: yaml

    contracts:
      type: contracts
      fields:
        position:
          fieldType: input
          renderType: text
          validators:
            - required
        organisationalUnit:
          fieldType: select
          renderType: select
          options: organisationalUnits
      contactSections:
        physicalAddresses:
          fields:
            country:
              fieldType: input
              renderType: select
              autocomplete: country
              helptext: 'LLL:EXT:site/Resources/Private/Language/locallang.xlf:address.country.helptext'
              validators:
                - required
        emailAddresses:
          fields:
            emailAddress:
              propertyName: email
              fieldType: input
              renderType: email
              autocomplete: email
        phoneNumbers:
          fields:
            phoneNumber:
              fieldType: input
              renderType: phone
              autocomplete: tel

    documentSections:
      contracts:
        type: contracts

The shipped physical-address country control obtains its localized labels and
ISO alpha-2 values from TYPO3's ``CountryProvider``. Keeping ``fieldType`` as
``input`` preserves the domain extension's existing backend TCA while
``renderType: select`` selects the frontend control and its option validation.
Every Contract and contact field helptext is rendered as a Bootstrap popover
in add/edit mode.

The shipped autocomplete tokens are ``street-address``, ``postal-code``,
``address-level2``, ``country``, ``email`` and ``tel``. They describe the
purpose of the corresponding control without changing validation or storage.

Backend TCA integration
=======================

The domain extension still owns the base TCA and the column configuration. It applies the normalized validation metadata from the unified
settings graph to the relevant Profile, contact and Profile-information TCA
fields. Consequently ``required``, ``readonly`` and field-type metadata remain
consistent between frontend validation, profile editing and FormEngine in
TYPO3 13 and TYPO3 14. Character limits remain frontend/server-side metadata
and do not change the database schema.

Year row
========

``year``, ``yearStart`` and ``yearEnd`` each receive ``col-12 col-md-3``. They
therefore stack on small screens and share one row from the medium breakpoint.
Each is an ``<input type="number">`` carrying the bounds of its TCA ``range``
as ``min``, ``max`` and ``step``.
