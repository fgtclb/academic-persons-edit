..  include:: /Includes.rst.txt
..  index:: Configuration
..  _configuration-general:

=====================
General configuration
=====================

**Extension configuration**
There are some options for global extension configuration:

..  confval:: profile.autoCreateProfiles

    :type: boolean
    :Default: false

    If enabled, a new profile will be created when a frontend user without an
assigned profile and that meets the criteria logs in.

..  confval:: profile.createProfileForUserGroups

    :type: string
    :Default:

    A comma-separated list of frontend group IDs. When a user without an assigned profile
    logs in and is assigned to one of these groups, a new profile will be created.

..  confval:: profile.allowedLanguages

    :type: string
    :Default:

    A comma-separated list of language IDs. These IDs configure in which languages a
    persons profile can be translated by a frontend user.
