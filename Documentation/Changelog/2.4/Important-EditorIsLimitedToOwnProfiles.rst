..  _important-ace-827-academic-persons-edit:

================================================
Important: The editor is limited to own profiles
================================================

Description
===========

The actions of the profile editor now check the owner of every profile,
contract, email address, phone number, physical address and profile information
they show or change.

Every action accepts a record only when it belongs to a profile of the
logged in frontend user. A profile and each of its translations belong to the
frontend users the profile in the default language is linked to. A
contract and a profile information belong to the owner of their profile, a
physical address, an email address and a phone number to the owner of their
contract. Hidden, scheduled and access restricted records belong to their owner
like every other record.

A request for a record of another frontend user is answered with the access
denied response of the site, the same response an anonymous visitor gets. A
request for a record that does not exist is answered the same way.

The upload of a profile image is checked as well, before the file is stored.
An upload for a profile that is not one of the user's is refused, and the
file is not written.

Impact
======

Frontend users keep editing their own profiles as before. A request for a
record of another frontend user gets the access denied response and changes
nothing.

A profile image uploaded for a hidden, scheduled or access restricted profile
of the user is now stored under the name built from the profile, like the image
of every other profile. Before, it kept the file name sent by the browser.

Affected Installations
======================

Every installation that uses the profile editor. Update to this version. Nothing
else has to be done.

A frontend user who is meant to edit a profile has to be linked to that
profile in its default language. A link set only on a translation of the
profile is ignored.

..  index:: Frontend, ext:academic_persons_edit
