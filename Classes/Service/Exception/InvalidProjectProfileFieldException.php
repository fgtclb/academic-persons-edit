<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Service\Exception;

/**
 * A project field of the persons settings names a column the editor must not use.
 * The message names the field, the column and the reason.
 */
final class InvalidProjectProfileFieldException extends \UnexpectedValueException {}
