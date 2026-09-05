<?php

declare(strict_types=1);

/*
 * This file is part of the "academic_persons_edit" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

use FGTCLB\AcademicBase\TcaManipulator;

(static function (): void {
    // Note that tca select group does not need to be registered here, because that is done in `academic-persons`
    // which is a hard dependency for this extension extending that dependency with additional features.
    (new TcaManipulator())->addContentElementPlugin(
        [
            'label' => 'LLL:EXT:academic_persons_edit/Resources/Private/Language/locallang_be.xlf:plugin.profile_editing.label',
            'value' => 'academicpersonsedit_profileediting',
            'icon' => 'persons_edit_icon',
            'group' => 'academic',
        ],
        'academic_persons_edit'
    );
})();
