..  _feature-before-profile-editing-write-event:

================================================================
Feature: A PSR-14 event before every write of the profile editor
================================================================

Description
===========

The :ref:`profile editing <profile-editing>` dispatches the new event
:php:`\FGTCLB\AcademicPersonsEdit\Event\BeforeProfileEditingWriteEvent` once for
every write it accepted, right before anything of it is stored: a field of the
profile, the synchronisation and visibility switches, the upload and removal of
the image, and the creation, update, visibility, deletion and sorting of a
document or a contact of a contract. A listener learns the profile, the write as
a case of :php:`\FGTCLB\AcademicPersonsEdit\Event\ProfileEditingAction`, the
section, the record and the submitted values.

A listener may refuse the write with a reason. The endpoint then answers 422
with the error ``write_refused`` and the reason as its message, stores nothing,
and the editor shows the reason to the person. A listener may also replace the
values of a write that stores some, and the replaced values are validated and
sanitised exactly like submitted ones, so the configured locks and the locks of
the synchronisation keep applying to them.

A request the editor refuses on its own, for a missing login, a profile of
somebody else, an action the configuration does not allow, a row the
synchronisation owns or an invalid value, never reaches a listener. The profile
and the records the event hands over are for reading. Values are changed
through the event, so that the checks of the editor apply to them.

The controller of the editor is :php:`final` and internal, and 2.x installations
reached these writes through an XCLASS or a copy of a controller. On 3.0 the
event is the supported way. It is also the event the 2.4 entry
:ref:`important-ace-33-academic-persons-edit` refers to for filling form data
from another source: a listener replaces the values of the write instead of
registering overrides on the form data object. The :ref:`developer chapter
<developers-before-write-event>` describes the event, with examples.

Impact
======

Nothing changes for an installation without a listener.

The removal of the profile image now answers a request it cannot carry out with
the status the documentation names, ``404`` for a profile without a record in
the site language and ``409`` from a workspace preview. It answered both with
``500`` and ``internal_server_error`` before.

..  index:: Frontend, PHP-API, ext:academic_persons_edit, NotScanned
