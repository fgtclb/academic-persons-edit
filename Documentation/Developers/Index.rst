..  _developers:

==============
For developers
==============

This chapter documents the programmatic surface of the profile editing: the
events it dispatches around every write of the editor.

Which classes of this extension are public API, and what that promises, is
stated for all academic extensions on the `extension points page of
academic_base <https://docs.typo3.org/p/fgtclb/academic-base/main/en-us/Developers/ExtensionPoints/Index.html>`__.
The three controllers of the editor are :php:`final` and :php:`@internal`, so
the events are the way to take part in a write.

..  _developers-events:

Events
======

..  list-table::
    :header-rows: 1
    :widths: 25 30 30 15

    *   -   Event
        -   Dispatched
        -   What a listener can change
        -   Owner
    *   -   :ref:`BeforeProfileEditingWriteEvent
            <developers-event-before-profile-editing-write-event>`
        -   once for every write the editor accepted, right before anything
            of it is stored
        -   refuse the write with a reason the person is shown, or replace
            the values it stores, which are validated again
        -   this extension
    *   -   :ref:`AfterProfileUpdateEvent
            <developers-event-after-profile-update-event>`
        -   once after a write was stored, for a profile edited in the
            default language of the site
        -   react to the change. The profile, its site and the origin are
            read only
        -   :guilabel:`academic_persons`

The profile editing does not dispatch :php:`ModifyPluginViewEvent` of
:guilabel:`academic_base`, which the plugins of the other academic extensions
dispatch. Its views are rendered once and then driven by the editor script, so
what a project needs to change about a write is offered by the write event
instead.

..  toctree::
    :maxdepth: 1
    :titlesonly:
    :hidden:

    Events/BeforeProfileEditingWriteEvent
    Events/AfterProfileUpdateEvent
