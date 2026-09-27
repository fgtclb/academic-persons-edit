..  _feature-profile-visibility-switch:

==========================================
Feature: Owners show or hide their profile
==========================================

Description
===========

The :ref:`profile-editing` view offers the owner of a profile the switch
:guilabel:`Show my profile publicly` in its header, next to the
synchronization checkbox. It writes the ``hidden`` field of the profile, the
one the backend shows as :guilabel:`Visible`, so a profile its owner switched
off is left out of every public list and its detail page answers with page not
found. The field is shared by every language of the profile, and the switch
hides or shows all of them, from whichever site language the owner uses. The
profile model of :guilabel:`EXT:academic_persons` exposes the field as
``hidden``.

An owner who switched a profile off still reaches it. The list of the editor
shows it with a :guilabel:`Not public` mark and without the :guilabel:`View`
link, and the owner opens it, edits it, image included, and switches it on
again. Start time, end time and frontend user groups of a profile keep
applying.

The switch is the special field ``hidden`` of
:file:`Configuration/AcademicPersons/Settings.yaml` and is on by default. An
installation that leaves visibility to its editors takes it away there:

..  code-block:: yaml
    :caption: EXT:my_sitepackage/Configuration/AcademicPersons/Settings.yaml

    special:
      hidden:
        validators:
          - readonly

``readonly`` and ``disabled`` render the switch disabled, ``hidden: ~`` removes
it, and a profile an editor hid then stays hidden. Backend editors keep the
checkbox either way.

A directory for logged-in visitors that lists every profile, including those
that are not public, uses the existing option :guilabel:`Show hidden records`
of the list and detail elements of :guilabel:`EXT:academic_persons`. That
directory cannot tell a profile its owner switched off from one an editor hid.

Impact
======

Owners of an existing installation get the switch with the update, and they can
show a profile an editor hid before. Take the switch away as shown above where
that must not happen.

Owners now also see their hidden profiles in the editor. Before, a hidden
profile was missing from the list, and opening it was refused. The
``{profiles}`` variable of the list template is a plain array now instead of
a query result, so an override that reads ``{profiles.first}`` or calls a
method of the query result on it has to change. ``{profiles -> f:count()}``
and ``f:for`` keep working.

The image metadata of a hidden profile is now written like that of any other,
on a backend save as well. Before, a backend save left it out.

An installation that recorded the consent of the owner in a field of its own
moves it into ``hidden`` once, with an upgrade wizard of its site package:

#.  Set ``hidden = 1`` on every profile without consent, on the
    default-language record and on each of its translations. Leave profiles
    that are hidden already as they are.
#.  Let an import that creates profiles set ``hidden = 1`` on a new profile
    where it created it without consent before.
#.  Remove the field, and every query condition, template condition and
    plugin option that read it.

A site overriding :file:`Templates/Profile/Index.html` carries
``data-visibility-url`` and passes ``visibility: specialFields.hidden`` to the
header partial, and one overriding :file:`Partials/Profile/Header.html` renders
the switch itself.

Affected Installations
======================

Installations of the :guilabel:`Profile editing` content element of
`EXT:academic_persons_edit`.

..  index:: Frontend, ext:academic_persons_edit, NotScanned
