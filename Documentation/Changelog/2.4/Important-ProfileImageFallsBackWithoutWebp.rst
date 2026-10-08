.. _important-ace-848-academic-persons-edit:

=======================================================================
Important: Profile image keeps its own format where WebP is not allowed
=======================================================================

Description
===========

The profile detail view of the ``academicpersonsedit_profileediting`` plugin
rendered every source of the profile image with ``fileExtension: 'webp'``. The
image view helpers of TYPO3 throw for a file extension that
:php:`$GLOBALS['TYPO3_CONF_VARS']['GFX']['imagefile_ext']` does not list:

..  code-block:: text

    Unable to render image uri in "tt_content:1": The extension webp is not
    specified in $GLOBALS['TYPO3_CONF_VARS']['GFX']['imagefile_ext'] as a valid
    image file extension and can not be processed.

The default list of TYPO3 v12 does not contain ``webp``, so on such an
installation every profile with an image failed to render.

The partial :file:`Resources/Private/Partials/Profile/Show/Image.html` now asks
the new view helper ``<pe:imageFileExtension preferred="webp" />`` of the
namespace ``FGTCLB\AcademicPersonsEdit\ViewHelpers`` for the format. It returns
``webp`` where the list contains it, and an empty string otherwise. Then TYPO3
picks the format as for any processed image: the format of the original file,
or PNG for a format a browser cannot show.

Impact
======

Nothing changes where the list contains ``webp``, which is the default on
TYPO3 v13. On TYPO3 v12 without ``webp`` the profile image is rendered in its
own format, or as PNG, instead of failing.

Affected Installations
======================

Installations that override :file:`Partials/Profile/Show/Image.html` of this
extension keep requesting WebP unconditionally. Replace ``fileExtension: 'webp'``
there as the shipped partial does:

..  code-block:: html

    <html xmlns:pe="http://typo3.org/ns/FGTCLB/AcademicPersonsEdit/ViewHelpers"
          data-namespace-typo3-fluid="true">

    <f:variable name="fileExtension" value="{pe:imageFileExtension(preferred: 'webp')}" />
    <img src="{f:uri.image(image: profile.image, maxWidth: 690, fileExtension: fileExtension)}" alt="">

.. index:: Fluid, Frontend, ext:academic_persons_edit
