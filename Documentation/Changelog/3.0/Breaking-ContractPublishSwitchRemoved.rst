..  _breaking-contract-publish-switch-removed:

===================================================
Breaking: The contract form has no "Publish" switch
===================================================

Description
===========

The contract form of the :ref:`profile-editing` view had a :guilabel:`Publish`
switch. It wrote the contract field :sql:`publish` of
:composer:`fgtclb/academic-persons`, which no public view has ever read, so
switching it off hid nothing. That field is removed in 3.0, see the Breaking
entry "The contract field publish has been removed" of academic_persons.

Whether a contract is shown is its visibility. The hide action of the contract
row writes it, marks a hidden contract with :guilabel:`Hidden` in the list and
leaves it out of the public views, see :ref:`feature-hide-document-rows`.

Removed with the switch:

*   :php:`\FGTCLB\AcademicPersonsEdit\Domain\Model\Dto\ContractFormData::$publish`,
    its constructor parameter ``$publish`` and :php:`isPublish()`,
*   the label ``contract.publish.label`` of :file:`locallang.xlf` and
    :file:`de.locallang.xlf`.

Impact
======

The contract form lists its fields without the switch. A save that still sends
``publish``, from an overridden template, a cached script or a client of its
own, is refused with the answer of any field the form does not have:
HTTP 422 and ``This field cannot be changed.`` for ``publish``. Nothing of
that request is stored.

Code that constructs a :php:`ContractFormData` with the named argument
``publish:`` fails with ``Unknown named parameter $publish``. A ninth argument
passed by position is ignored without an error. A call of :php:`isPublish()`
is a fatal error.

Affected Installations
======================

Installations that override the contract form or its field partials, send
their own save requests to the editor, or use :php:`ContractFormData` in code
of their own, for example in a listener of
:php:`\FGTCLB\AcademicPersonsEdit\Event\BeforeProfileEditingWriteEvent`.

Migration
=========

Drop ``publish`` from the fields an override renders or a client sends, and
the ``publish`` argument from a :php:`ContractFormData` constructor call. To
show or hide a contract, use the hide action of its row.
