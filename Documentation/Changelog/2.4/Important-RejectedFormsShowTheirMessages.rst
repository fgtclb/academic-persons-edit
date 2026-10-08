..  _important-rejected-forms-show-their-messages:

=============================================
Important: Rejected forms show their messages
=============================================

Description
===========

A form of the profile editor that is rejected, a required field left empty or
an invalid email address, is shown again with the values that were entered.
Until now it showed no message at the rejected field. Above the form it showed
only the path of the property, :html:`emailAddressFormData.email`, and above the
page a debug output of the translation key.

Each rejected field now shows a translated message, and a note above the form
says that it could not be saved. Which label a message is taken from is listed
in :ref:`configuration-labels-validation`.

Impact
======

A site that overrides one of these templates or partials receives the change
only after updating its copy:

*   :file:`Partials/Profile/Forms/Errors.html` lists only the errors of the
    form object itself, the messages of the fields are rendered by
    :file:`Partials/Profile/Forms/FieldWrapper.html` through the new
    :file:`Partials/Profile/Forms/ErrorMessage.html`.
*   The templates of the six forms pass the name of their form object to
    :file:`Partials/Profile/Forms/Errors.html` as a string, for example
    :html:`{object: 'emailAddressFormData'}`.

A label override keyed :xml:`form.<field>.error.<code>` was never shown and
has no effect. Use :xml:`<formObject>.<field>.error.<code>` instead, for
example :xml:`emailAddressFormData.email.error.1221559976`.

..  index:: Frontend, Fluid, ext:academic_persons_edit
