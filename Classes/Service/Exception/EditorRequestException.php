<?php

declare(strict_types=1);

/*
 * This file is part of the "academic_persons_edit" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace FGTCLB\AcademicPersonsEdit\Service\Exception;

use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * A service of the profile editor refuses what a request asked for: field values
 * that do not validate, or an order that does not name every record. It carries
 * what the JSON answer of the endpoint consists of, and the controller turns it
 * into that answer, so that no service builds an HTTP response.
 *
 * @internal
 */
#[Exclude]
final class EditorRequestException extends \RuntimeException
{
    /**
     * @param string $error The machine-readable error identifier of the answer.
     * @param int $statusCode The HTTP status of the answer.
     * @param string|null $responseMessage The human-readable message of the answer, none when null.
     * @param array<string, list<string>> $fieldErrors The validation errors keyed by field name.
     */
    public function __construct(
        public readonly string $error,
        public readonly int $statusCode,
        public readonly ?string $responseMessage,
        public readonly array $fieldErrors,
        int $code,
    ) {
        parent::__construct($responseMessage ?? $error, $code);
    }
}
