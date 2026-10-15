..  _integrator-profile-editing:

======================
Edit Person’s Profiles
======================

..  image:: /Images/Icons/profile-editing.svg
    :width: 48px
    :alt: Icon of the profile editing content element

:CType:
    ``academicpersonsedit_profileediting``

:Plugin:
    `AcademicPersonsEdit`, plugin `ProfileEditing`, default action
    :php:`ProfileController::listAction()`, further actions of
    :php:`ProfileController`, :php:`DocumentController` and
    :php:`ContractContactController`

:Set:
    `fgtclb/academic-persons-edit-profile-editing`, also part of
    `fgtclb/academic-persons-edit`, see :ref:`configuration-components`

:Templates:
    :file:`Profile/List.html` and :file:`Profile/Index.html`, see
    :ref:`templates`

The content element is where people maintain their own profile. It is placed
on a page behind the frontend login, and shows the person who is logged in
the profiles assigned to their frontend user, with two views on the same
page:

*   **The list** of the assigned profiles, the default view. Each row shows
    the image and the name of a profile, its language, an :guilabel:`Edit`
    link and a :guilabel:`View` link to its public detail page. A profile the
    owner switched off is listed with a :guilabel:`Not public` mark and
    without the :guilabel:`View` link, so the owner can open it and switch it
    on again. A profile outside its start and end time, or restricted to a
    frontend user group the person is not in, is not listed.
*   **The editor** of one profile, which :guilabel:`Edit` opens. Every change
    is saved without a page reload: the fields of the profile,
    its image, the synchronisation and visibility switches, and its documents,
    contracts and contacts. :ref:`profile-editing` describes it in full.

..  figure:: /Images/Screenshots/profile-editing-list.png
    :alt: A table with one row per profile: image, name, language, and an icon link to view and one to edit the profile.
    :class: with-border with-shadow

    The list of the profiles assigned to the logged-in frontend user, with the
    Edit and View links.

..  figure:: /Images/Screenshots/profile-editing-editor.png
    :alt: The profile editor with the profile image, the switches Disable profile sync and Show my profile publicly, and the sections of profile fields with an edit button each.
    :class: with-border with-shadow

    The editor of a profile: every field, the image and the documents are
    saved one by one, without a page reload.

The content element works together with the :guilabel:`Person Details`
content element of :guilabel:`academic_persons`, which the :guilabel:`View`
link opens.

..  _integrator-profile-editing-requirements:

What it needs
=============

A logged-in frontend user
    The content element answers a visitor who is not logged in with the
    access denied response of the site, for the whole page. Restrict the page
    to logged-in users, with :guilabel:`Show at any login` or the frontend user
    group of the people who edit, so it does not appear to anybody else.
    Logging in is left to the site, with the login form of TYPO3 or a single
    sign-on.

Profiles assigned to the frontend user
    The list shows the profiles whose field :guilabel:`Assigned Users`, on the
    :guilabel:`Frontend Users` tab of the profile, names the frontend user.
    The profiles may be stored in any folder. An editor assigns them in the
    backend, or the commands of :guilabel:`academic_persons` create and assign
    them for the frontend users, see the `frontend user synchronisation of
    academic_persons
    <https://docs.typo3.org/p/fgtclb/academic-persons/main/en-us/Configuration/FrontendUserSync/Index.html>`__.
    A frontend user without a profile sees :guilabel:`No profiles found.`

The detail page
    The :guilabel:`View` link leads to the page set in the site setting
    :yaml:`plugin.tx_academicpersons.detailPid`, the detail page of the person
    lists of :guilabel:`academic_persons`. That page has to carry a
    :guilabel:`Person Details` content element. There is no separate setting
    for this extension, see :ref:`site-set`.

The page type of the editor
    The editor saves through the page type ``1733735``, which the site set
    and the static template deliver. A route enhancer that maps page types to
    a suffix has to list it, see :ref:`profile-editing-page-type`.

The content element is never cached, every view of it is rendered for the
person who asks. It renders no header of its own, the content element layout
of the site renders the header, see
:ref:`breaking-profile-editing-renders-no-content-element-header`.

..  _integrator-profile-editing-options:

Settings
========

The content element has no plugin options, there is no tab of its own in the
backend form. What it shows and allows is configured for the whole site or
installation, in these places:

..  list-table::
    :header-rows: 1
    :widths: 35 35 30

    *   -   What
        -   Where
        -   See
    *   -   Which fields and sections the editor shows, in which order, which
            of them are required, locked or read only, and which actions a
            document section offers
        -   :file:`Configuration/AcademicPersons/Settings.yaml` of a site
            package, shared with :guilabel:`academic_persons`
        -   :ref:`configuration-editor-settings`,
            :ref:`configuration-general-validations`
    *   -   Fields the synchronisation owns, which stay locked
        -   the configuration of the frontend user synchronisation of
            :guilabel:`academic_persons`
        -   :ref:`configuration-general-managed-fields`
    *   -   Whether the visibility switch :guilabel:`Show my profile publicly`
            is offered
        -   :yaml:`special.hidden` in the same settings file
        -   :ref:`profile-visibility-switch`
    *   -   The languages a profile is translated into after an edit
        -   extension configuration ``profile.allowedLanguages``
        -   :ref:`configuration-general`
    *   -   The folder an uploaded image is stored in, its maximum size and
            its file types
        -   TypoScript :typoscript:`plugin.tx_academicpersonsedit.settings.editForm.profileImage`
        -   :ref:`profile-editing-image-upload`
    *   -   The page the :guilabel:`View` link leads to
        -   site setting :yaml:`plugin.tx_academicpersons.detailPid`
        -   :ref:`site-set`
    *   -   Whether the page loads the editor script
        -   site setting :yaml:`plugin.tx_academicpersonsedit.assets.js`
        -   :ref:`configuration-javascript`
    *   -   The labels of the list and the editor
        -   TypoScript :typoscript:`plugin.tx_academicpersonsedit._LOCAL_LANG`
        -   :ref:`configuration-labels`
    *   -   The markup and the look
        -   template overrides and the stylesheet of the site package
        -   :ref:`templates`, :ref:`styling`

..  _integrator-profile-editing-pitfalls:

Pitfalls
========

*   **The editor needs its script.** Every field and button of the editor is
    driven by the script of the extension. Switching
    :yaml:`plugin.tx_academicpersonsedit.assets.js` off without bringing a
    script of your own leaves an editor that saves nothing.
*   **The TypoScript has to be included.** A site package that copies the
    TypoScript of the extension instead of including the set or the static
    template lacks the page type of the editor. The editor then shows a
    message instead of saving, see :ref:`profile-editing-page-type`.
*   **The name is locked by default.** First, middle and last name usually
    come from the frontend user and can be edited neither in the frontend nor
    in the backend until the settings file unlocks them, see
    :ref:`configuration-general-validations`.
