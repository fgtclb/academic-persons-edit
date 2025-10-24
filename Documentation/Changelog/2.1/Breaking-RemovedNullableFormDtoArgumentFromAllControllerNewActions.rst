.. include:: /Includes.rst.txt

.. _breaking-1746708500:

===============================================================================
Breaking: Removed nullable form dto argument from all controller `newActions()`
===============================================================================

Description
===========

The `newAction()` of the `EXT:academic_persons_edit` controllers only
displays the initial form for create a new entity submitting data
submission to the `createAction()` [POST] and never handles the form
again. Following that, there is no need to have form data as optional
argument for the `newAction()` and beside that this would allow to
prefill data used for the form using link manipulations as the action
is a GET action, which we do not want to work.

Not verified if having the form object as nullable for action in place
has been required in earlier extbase days, but today that is absolutely
not the case and **could** only be uses to prefill form data calling
that action with corresponding get arguments, which can be considered
dangerous and needs to be omitted in the first place.

In case the validation for the `createAction()` is invalid extbase
calls the `errorAction()` of the controller forwarding the request
internally to the `newAction()` along with the validationResult,
already omitting to send the form data argument and being null in
any-case. Using the `<f:form.* />` fluid ViewHelpers to render the
form elements takes care of this and keep the entered values as
values even if the form dto object is initialized with empty values,
which means that we do not have to take care of that ourself.


Impact
======

The optional (nullable) form arguments are removed from following actions:

* `ContractController->newAction()`
* `EmailAdressController->newAction()`
* `PhoneNumberController->newAction()`
* `PhysicalAddressController->newAction()`
* `ProfileInformationController->newAction()`

Affected Installations
======================

All installations using the `EXT:academic_persons_edit` extension version
prior V2.1.

Migration
=========

Making this visible, for example this

.. code-block:: php
    public function newAction(
        Profile $profile,
        ?ContractFormData $contractFormData = null,
    ): ResponseInterface { /* ... */ }

to

.. code-block:: php
    public function newAction(
        Profile $profile,
    ): ResponseInterface { /* ... */ }


Important Notes
---------------

There may be use-cases in projects to provide kind of prefilled
actions, albeit this should be only a edge-case. In these cases,
the project should implement a custom controller and action to
create the entity with the prefilled data and display (redirect)
to the `editAction()`. If that case rises up in projects, we may
revisit this here and eventually come up with another solution,
considering security aspects more seriously in these cases.

.. index:: Frontend
