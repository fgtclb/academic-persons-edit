<?php

declare(strict_types=1);

/*
 * This file is part of the "academic_persons_edit" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace FGTCLB\AcademicPersonsEdit\Service;

use FGTCLB\AcademicPersonsEdit\Service\Exception\EditorRequestException;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Http\PropagateResponseException;
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Extbase\Mvc\RequestInterface;

/**
 * What every JSON endpoint of the profile editor shares, whichever controller
 * serves it: the check that the editor sent the request, the JSON error answer,
 * the readers of the payload the document and the contact endpoints have in
 * common, and the logging of a failure.
 *
 * It is the one service of the editor that builds a response, because it is the
 * part of the endpoints the three controllers would otherwise have inherited
 * from a base controller. A base controller was not used: its constructor would
 * be inherited API, and a subclass overriding `initializeAction()` without
 * calling the parent would drop the header check without a sound.
 *
 * @internal
 */
final readonly class EditorEndpoint
{
    public function __construct(
        private LogManager $logManager,
    ) {}

    /**
     * Every writing endpoint requires the `X-Requested-With: XMLHttpRequest` header
     * the editor sends on each of its requests.
     *
     * The JSON endpoints are already protected by their content type - a foreign
     * page cannot send `application/json` without a CORS preflight - but the image
     * upload is `multipart/form-data`, which is a simple request any page can submit
     * with a plain form. A custom header cannot be set cross-origin without that
     * preflight either, so requiring one closes the upload without relying on the
     * `SameSite` attribute of the session cookie, which an installation may change.
     * It is demanded on all of them so that one rule covers the whole surface.
     *
     * @throws PropagateResponseException When the header is absent.
     */
    public function assertRequestWasSentByTheEditor(RequestInterface $request): void
    {
        if ($request->getHeaderLine('X-Requested-With') !== 'XMLHttpRequest') {
            throw new PropagateResponseException(
                $this->jsonError(
                    'invalid_request',
                    400,
                    'The request must carry the "X-Requested-With: XMLHttpRequest" header.',
                ),
                1777046203,
            );
        }
    }

    /**
     * Creates a JSON error response payload for failed requests.
     *
     * @param string $error Machine-readable error identifier.
     * @param int $statusCode HTTP status code to return in the response.
     * @param string|null $message Optional human-readable message describing the error.
     * @param array<string, list<string>> $errors Optional field-specific validation errors keyed by field name.
     * @return JsonResponse The generated JSON error response.
     */
    public function jsonError(
        string $error,
        int $statusCode,
        ?string $message = null,
        array $errors = [],
    ): JsonResponse {
        $body = [
            'success' => false,
            'error' => $error,
        ];
        if ($message !== null) {
            $body['message'] = $message;
        }
        if ($errors !== []) {
            $body['errors'] = $errors;
        }
        return new JsonResponse($body, $statusCode);
    }

    /**
     * Throws a JSON error response as a propagated exception.
     *
     * This method creates a JSON error payload and wraps it in a
     * PropagateResponseException so the framework can handle the response
     * without continuing the normal controller flow.
     *
     * @param string $error The machine-readable error code.
     * @param int $statusCode The HTTP status code to send with the response.
     * @param string|null $message An optional human-readable error message.
     * @param array<string, list<string>> $errors Optional field-specific validation errors keyed by field name.
     * @return never This method does not return normally because it always throws.
     * @throws PropagateResponseException Thrown when the JSON error response should be propagated.
     */
    public function throwJsonError(
        string $error,
        int $statusCode,
        ?string $message = null,
        array $errors = [],
    ): never {
        throw new PropagateResponseException(
            $this->jsonError($error, $statusCode, $message, $errors),
            1776760205,
        );
    }

    /**
     * Answers a request a service of the editor refused with the JSON error the
     * refusal describes, exactly as {@see self::throwJsonError()} answers one the
     * controller refused itself.
     */
    public function answerRefusal(EditorRequestException $exception): never
    {
        $this->throwJsonError(
            $exception->error,
            $exception->statusCode,
            $exception->responseMessage,
            $exception->fieldErrors,
        );
    }

    /**
     * Validates the document payload and ensures that only allowed keys are present
     * and all required keys are available.
     *
     * @param array<string, mixed> $data The payload to validate.
     * @param list<string> $allowedKeys A list of allowed property names.
     * @param list<string> $requiredKeys A list of property names that must be present.
     */
    public function assertDocumentPayload(array $data, array $allowedKeys, array $requiredKeys): void
    {
        foreach (array_keys($data) as $key) {
            if (!is_string($key) || !in_array($key, $allowedKeys, true)) {
                $this->throwJsonError('invalid_payload', 400, 'The document payload contains an unknown property.');
            }
        }
        foreach ($requiredKeys as $requiredKey) {
            if (!array_key_exists($requiredKey, $data)) {
                $this->throwJsonError('invalid_payload', 400, 'The document payload is incomplete.');
            }
        }
    }

    /**
     * Validates that a required property exists and is a positive integer.
     *
     * @param array<string, mixed> $data The payload to validate.
     * @param string $property The property name to read.
     * @return int<1, max> The validated positive integer value.
     */
    public function getRequiredPositiveInteger(array $data, string $property): int
    {
        $value = $data[$property] ?? null;
        if (!is_int($value) || $value <= 0) {
            $this->throwJsonError('invalid_payload', 400, sprintf('%s must be a positive integer.', $property));
        }
        return $value;
    }

    /**
     * Gets an optional positive integer property from the payload.
     *
     * Returns null when the property is missing, null, or zero.
     *
     * @param array<string, mixed> $data The payload to validate.
     * @param string $property The property name to read.
     * @return int|null The validated positive integer value or null when not set.
     */
    public function getOptionalPositiveInteger(array $data, string $property): ?int
    {
        if (!array_key_exists($property, $data) || $data[$property] === null || $data[$property] === 0) {
            return null;
        }
        return $this->getRequiredPositiveInteger($data, $property);
    }

    /**
     * Gets the required document mode from the payload.
     *
     * Valid modes are: "add", "view", "edit", and "delete".
     *
     * @param array<string, mixed> $data The payload to validate.
     * @return 'add'|'view'|'edit'|'delete' The validated document mode.
     */
    public function getRequiredDocumentMode(array $data): string
    {
        $mode = $data['mode'] ?? null;
        if (!is_string($mode) || !in_array($mode, ['add', 'view', 'edit', 'delete'], true)) {
            $this->throwJsonError('invalid_payload', 400, 'The document mode is invalid.');
        }
        return $mode;
    }

    /**
     * Validates and returns the submitted document field payload.
     *
     * @param array<string, mixed> $data The raw request payload.
     * @return array<string, mixed> The validated document fields as an associative array.
     * @throws \RuntimeException Thrown when the payload is invalid or field names are not strings,
     *                           which results in a JSON error response.
     */
    public function getSubmittedDocumentFields(array $data): array
    {
        $fields = $data['fields'] ?? null;
        if (!is_array($fields)) {
            $this->throwJsonError('invalid_payload', 400, 'Document fields must be a JSON object.');
        }
        foreach (array_keys($fields) as $fieldName) {
            if (!is_string($fieldName)) {
                $this->throwJsonError('invalid_payload', 400, 'Document field names must be strings.');
            }
        }
        return $fields;
    }

    /**
     * Logs a failed document operation and terminates the request with a JSON error response.
     *
     * @param string $loggerName The class the log entry is written for, the controller of the endpoint
     * @param string $logMessage Message to log for diagnosing the failure
     * @param \Throwable $exception The exception that caused the document operation to fail
     */
    public function handleDocumentFailure(string $loggerName, string $logMessage, \Throwable $exception): never
    {
        $this->logManager
            ->getLogger($loggerName)
            ->error($logMessage, ['exception' => $exception]);
        $this->throwJsonError(
            'internal_server_error',
            500,
            'The document operation could not be completed.',
        );
    }
}
