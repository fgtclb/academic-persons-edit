<?php

declare(strict_types=1);

/*
 * This file is part of the "academic_persons_edit" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace FGTCLB\AcademicPersonsEdit\Tests\Unit\Domain\Validator\Fixtures;

/**
 * A class that is not a `ValidatorInterface`. `Settings.yaml` is normalized into
 * class names without ever checking them, so an integrator override can put
 * anything in there - this is what the runtime guard has to catch.
 */
final class NotAValidator {}
