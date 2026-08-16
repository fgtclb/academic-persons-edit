<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Tests\Functional\Plugins;

use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Http\Stream;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

/**
 * Submits the profile edit form of the `academicpersonsedit_profileediting` plugin through a
 * real frontend request and pins the DTO to domain model transformation end to end.
 *
 * The factory tests in `Tests/Functional/Domain/Factory/` drive the property mapper directly,
 * which lets them configure the type converter with an `argumentName` of their choosing. This
 * test does not: it walks the plugin the way a visitor does, so the argument name is the one
 * `AbstractActionController::initializeAction()` derives from the action signature, and the
 * request arguments are the ones `RequestBuilder` assembles from the posted body.
 *
 * That closes the only gap the factory tests leave open — whether the "was this property sent"
 * detection works when nothing about the request is arranged by hand.
 */
final class AcademicPersonsEditProfileFormSubmissionTest extends AbstractProfileEditingPluginTestCase
{
    /**
     * Walks the plugin from the profile list to the profile edit form and returns its URI.
     *
     * The inherited `extractActionLink()` cannot be used: it matches an action name by prefix,
     * so `edit` returns the `editImage` link that is rendered above it.
     */
    private function getProfileEditFormUrl(): string
    {
        preg_match_all('@href="([^"]+)"@', $this->getProfileShowPage(), $matches);
        foreach ($matches[1] as $href) {
            $href = html_entity_decode($href);
            if (!str_contains($href, urlencode('[action]') . '=edit&')) {
                continue;
            }
            return str_starts_with($href, '/') ? 'https://www.acme.com' . $href : $href;
        }
        $this->fail('No link to the "edit" action found on the profile show page.');
    }

    /**
     * Renders the edit page and returns the update form's action URI together with its hidden
     * fields. The action carries controller and action, the hidden fields carry `__referrer`
     * and the `__trustedProperties` HMAC, so neither can be hardcoded.
     *
     * The page renders more than one form, therefore the update form is selected by its action.
     *
     * @return array{action: string, fields: array<string, string>}
     */
    private function renderEditFormAndExtractSubmitData(string $formUrl): array
    {
        $content = $this->getPageAsFrontendUser($formUrl);

        // The action attribute is URL encoded, so the action name appears as `%5Baction%5D=update`.
        $this->assertSame(
            1,
            preg_match(
                '@<form [^>]*action="([^"]*' . urlencode('[action]') . '=update[^"]*)"(.*?)</form>@s',
                $content,
                $formMatch,
            ),
            'The profile edit page does not contain a form posting to the "update" action.',
        );

        $fields = [];
        preg_match_all(
            '@<input[^>]+type="hidden"[^>]+name="([^"]+)"[^>]+value="([^"]*)"@',
            $formMatch[2],
            $matches,
            PREG_SET_ORDER,
        );
        foreach ($matches as $match) {
            $fields[html_entity_decode($match[1])] = html_entity_decode($match[2]);
        }
        $this->assertNotEmpty($fields, 'The profile update form contains no hidden fields.');

        return [
            'action' => html_entity_decode($formMatch[1]),
            'fields' => $fields,
        ];
    }

    /**
     * @param array<string, string> $submittedProperties property name to value, the only
     *                                                   `profileFormData` keys that are posted
     */
    private function submitProfileForm(string $formUrl, array $submittedProperties): ResponseInterface
    {
        $submitData = $this->renderEditFormAndExtractSubmitData($formUrl);

        $parsedBody = $this->pluginArgumentsOfFormAction($submitData['action']);
        foreach ($submitData['fields'] as $name => $value) {
            $this->addFormValue($parsedBody, $name, $value);
        }
        foreach ($submittedProperties as $propertyName => $value) {
            $this->addFormValue(
                $parsedBody,
                sprintf('tx_academicpersonsedit_profileediting[profileFormData][%s]', $propertyName),
                $value,
            );
        }

        // The body is provided explicitly: the testing framework otherwise serialises the parsed
        // body with `GuzzleHttp\Psr7\Query::build()`, which cannot handle the nested plugin
        // arguments and emits an "Array to string conversion" warning.
        $body = new Stream('php://temp', 'rw');
        $body->write(http_build_query($parsedBody));
        $body->rewind();

        $request = (new InternalRequest('https://www.acme.com/home'))
            ->withMethod('POST')
            ->withAddedHeader('Content-Type', 'application/x-www-form-urlencoded')
            ->withBody($body)
            ->withParsedBody($parsedBody);

        return $this->requestAsFrontendUser($request);
    }

    /**
     * @return array{website: string, website_title: string}
     */
    private function getStoredWebsiteFields(): array
    {
        $row = $this->getConnectionPool()
            ->getConnectionForTable('tx_academicpersons_domain_model_profile')
            ->executeQuery(
                'SELECT website, website_title FROM tx_academicpersons_domain_model_profile WHERE uid = ?',
                [self::PROFILE_ID],
            )
            ->fetchAssociative();
        $this->assertIsArray($row, 'The profile record is missing.');

        return [
            'website' => (string)$row['website'],
            'website_title' => (string)$row['website_title'],
        ];
    }

    /**
     * Seeds both website fields so that "kept its stored value" is distinguishable from
     * "was empty all along".
     */
    private function seedWebsiteFields(): void
    {
        $this->getConnectionPool()
            ->getConnectionForTable('tx_academicpersons_domain_model_profile')
            ->update(
                'tx_academicpersons_domain_model_profile',
                ['website' => 'https://stored.example.org', 'website_title' => 'Stored title'],
                ['uid' => self::PROFILE_ID],
            );
    }

    /**
     * Turns `a[b][c]` notation into the nested array the request expects.
     *
     * @param array<string, mixed> $target
     */
    private function addFormValue(array &$target, string $name, string $value): void
    {
        $position = strpos($name, '[');
        if ($position === false) {
            $target[$name] = $value;
            return;
        }
        preg_match_all('@\[([^]]*)]@', $name, $matches);
        $keys = array_merge([substr($name, 0, $position)], $matches[1]);
        $current = &$target;
        foreach ($keys as $key) {
            if (!isset($current[$key]) || !is_array($current[$key])) {
                $current[$key] = [];
            }
            $current = &$current[$key];
        }
        $current = $value;
    }

    /**
     * @return array<string, mixed>
     */
    private function pluginArgumentsOfFormAction(string $action): array
    {
        $query = parse_url($action, PHP_URL_QUERY);
        if (!is_string($query) || $query === '') {
            return [];
        }
        $parsed = [];
        parse_str($query, $parsed);

        $arguments = [];
        foreach ($parsed as $name => $value) {
            $arguments[(string)$name] = $value;
        }

        return $arguments;
    }

    /**
     * The baseline: a submitted property reaches the record. If the argument name were not set
     * on the form data object, `shouldApplyProperty()` would be false for every property and
     * both fields would keep their seeded values.
     *
     * `website` and `websiteTitle` are used rather than the name fields, because the shipped
     * `profile` validation set marks `firstName`, `middleName` and `lastName` as `disabled`,
     * so `mayApplyProperty()` rejects them before the request is consulted at all.
     */
    #[Test]
    public function submittedPropertyIsPersisted(): void
    {
        $this->setUpTestCase();
        $this->seedWebsiteFields();

        $this->submitProfileForm($this->getProfileEditFormUrl(), [
            'website' => 'https://submitted.example.org',
            'websiteTitle' => 'Submitted title',
        ]);

        $this->assertSame(
            ['website' => 'https://submitted.example.org', 'website_title' => 'Submitted title'],
            $this->getStoredWebsiteFields(),
        );
    }

    /**
     * The other half, and the behaviour ACE-33 introduced: a property that is not part of the
     * submitted body keeps its stored value instead of being overwritten with the empty form
     * data default.
     *
     * Both halves together prove that `wasPropertySentInRequest()` discriminates correctly in a
     * request nothing arranged by hand — the argument name resolves from the action signature,
     * and the per-property decision follows the posted keys.
     */
    #[Test]
    public function propertyMissingFromTheSubmissionKeepsItsStoredValue(): void
    {
        $this->setUpTestCase();
        $this->seedWebsiteFields();

        $this->submitProfileForm(
            $this->getProfileEditFormUrl(),
            ['website' => 'https://submitted.example.org'],
        );

        $this->assertSame(
            ['website' => 'https://submitted.example.org', 'website_title' => 'Stored title'],
            $this->getStoredWebsiteFields(),
        );
    }
}
