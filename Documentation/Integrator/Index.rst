..  _integrator:

===============
For integrators
===============

This extension ships one content element, the profile editing. A person who
is logged in as a frontend user edits the profiles of
:guilabel:`academic_persons` assigned to that user with it.

The content element has no plugin options. Nothing is stored with the content
element, so there are no FlexForm values to meet TypoScript defaults, and
every editor of a site behaves the same. What an integrator configures applies
to the whole site or installation: the site settings or TypoScript constants
of the site set, the TypoScript settings of the plugin, the settings file of
:guilabel:`academic_persons` that describes every field and section, and the
extension configuration. The page of the content element names each of them.

..  list-table::
    :header-rows: 1
    :widths: 10 30 30 30

    *   -   Icon
        -   Content element
        -   CType
        -   Shows
    *   -   ..  image:: /Images/Icons/profile-editing.svg
                :width: 32px
                :alt: Icon of the profile editing content element
        -   :ref:`Edit Person’s Profiles <integrator-profile-editing>`
        -   ``academicpersonsedit_profileediting``
        -   The profiles assigned to the logged-in frontend user, and the
            editor of one of them.

..  toctree::
    :maxdepth: 1
    :titlesonly:
    :hidden:

    ProfileEditing
