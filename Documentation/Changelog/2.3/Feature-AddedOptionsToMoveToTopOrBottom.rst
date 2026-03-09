.. include:: /Includes.rst.txt

.. _feature-1752000000:

===================================================================
Feature: Added options to move items to top or bottom in lists
===================================================================

Description
===========

Profile related lists now provide additional actions to quickly move
entries directly to the first or last position without having to step
through them item by item. This applies to contracts and profile
information on a profile as well as email addresses, phone numbers
and physical addresses on a contract.

Instead of only being able to move an item one step *up* or *down*,
editors can now use dedicated "move to top" and "move to bottom"
actions to rearrange the order of items more efficiently. The sorting
values of affected records are normalized while reordering to keep a
clean, strictly increasing sorting sequence.

Impact
======

Editors working with the `EXT:academic_persons_edit` extension gain
more convenient tools for managing the order of:

* profile contracts
* profile information
* contract email addresses
* contract phone numbers
* contract physical addresses

The new options reduce the number of clicks required to bring a record
to the top or bottom of a list and make it easier to maintain a stable
and predictable display order.

Affected Installations
======================

All installations using the `EXT:academic_persons_edit` extension
starting with version 2.3.

Migration
=========

No explicit migration is required. Existing records keep their current
order and sorting values. The new actions can be used immediately to
rearrange items as needed and will normalize sorting values on change.

.. index:: Frontend, Backend
