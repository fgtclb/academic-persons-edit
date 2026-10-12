.. _important-ace-33-academic-persons-edit:

==============================================================
Important: Form data transformation only maps submitted fields
==============================================================

Description
===========

The frontend editing of `EXT:academic_persons_edit` maps submitted form
data transfer objects (:php:`\FGTCLB\AcademicPersonsEdit\Domain\Model\Dto\AbstractFormData`
descendants) onto the domain models through the per-property factory
classes in :php:`\FGTCLB\AcademicPersonsEdit\Domain\Factory\*`.

Until now these factories wrote **every** property on each request, so a
field that was not part of the submitted form was silently overwritten
with the empty default of the form data object, wiping already persisted
data. This is corrected: a property is now only applied when it was
actually sent within the current request.

To make this possible the following additions were made (all of them on
:php:`@internal` classes that are not part of the public API):

* :php:`\FGTCLB\AcademicPersonsEdit\Domain\Model\Dto\AbstractFormData`
  carries the current request and the mapped argument name and exposes
  :php:`wasPropertySentInRequest(string $propertyName): bool` to detect
  which properties were part of the submission. The request and argument
  name are provided by the new
  :php:`\FGTCLB\AcademicPersonsEdit\Property\TypeConverter\AbstractFormDataConverter`,
  which is registered for all :php:`AbstractFormData` based arguments in
  :php:`\FGTCLB\AcademicPersonsEdit\Controller\AbstractActionController::initializeAction()`.
* All factory classes (`Profile`, `ProfileInformation`, `Contract`,
  `Address`, `Email`, `PhoneNumber`) skip properties that were neither
  sent within the request nor registered as override. The existing
  ``readOnly`` / ``disabled`` validation configuration keeps precedence
  and continues to protect persisted data.
* :php:`AbstractFormData` gained a per-property override store with
  :php:`setPropertyOverride(string $propertyName, mixed $value)`,
  :php:`hasPropertyOverride(string $propertyName)` and
  :php:`getPropertyOverride(string $propertyName)`. Registered overrides
  are applied even when the property was not submitted. The store is
  internal to the transformation: nothing in the extension registers an
  override, and no event hands the form data object to a listener.

Additionally
:php:`\FGTCLB\AcademicPersonsEdit\Domain\Factory\ContractFactory::setValidTo()`
was fixed to evaluate the validation configuration of ``validTo`` instead
of ``validFrom``.

Impact
======

The runtime behaviour of the frontend edit forms changes: submitting a
form no longer resets fields that are not contained in that form. Fields
are only written when they were part of the request or were explicitly
registered as override on the form data object. No public method
signature changed in an incompatible way.

Affected Installations
======================

Only installations that extend or replace the internal form data factory
classes or :php:`AbstractFormData`, or that relied on the previous
"always overwrite" behaviour of the transformation, need to take the
changed behaviour into account. All other installations benefit from the
fix without any action required.

Migration
=========

Installations without custom code need no migration. Custom code that
extends or replaces the internal classes and sets a property of a form
data object that was not submitted must register the value with
:php:`AbstractFormData::setPropertyOverride()`, otherwise the
transformation does not apply it.

.. index:: PHP, ext:academic_persons_edit
