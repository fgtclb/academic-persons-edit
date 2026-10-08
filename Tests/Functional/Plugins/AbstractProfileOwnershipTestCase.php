<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Tests\Functional\Plugins;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Http\Stream;
use TYPO3\CMS\Core\Http\UploadedFile;
use TYPO3\CMS\Frontend\Page\CacheHashCalculator;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

/**
 * Walks the actions of the profile editing plugin as the logged in frontend user 1, once
 * for records of their own profile 1 and once for the same kind of record of profile 2,
 * which belongs to frontend user 2.
 *
 * A record of another user is named the way a form can name it: in the body of a POST
 * request, which the cHash does not cover and which Extbase merges over the query
 * arguments. A refused request is answered with the 403 response of the site and writes
 * nothing, which every test of a foreign record pins with a snapshot of all records the
 * editor can reach.
 *
 * Own uids are 1 and 2 of every child table, foreign uids 3 and 4, and profile 2 is the
 * foreign profile, see `Fixtures/ProfileOwnership/records.csv`. A concrete test case names
 * the controller and provides the requests, the tests here are the same for every one.
 */
abstract class AbstractProfileOwnershipTestCase extends AbstractProfileEditingPluginTestCase
{
    protected const PLUGIN_NAMESPACE = 'tx_academicpersonsedit_profileediting';

    protected const FOREIGN_PROFILE_ID = 2;

    /**
     * The controller alias the plugin knows the controller by.
     */
    protected const CONTROLLER = '';

    /**
     * A value of a foreign record that a page rendering it would show.
     */
    protected const FOREIGN_VALUE = '';

    /**
     * Valid values for the create and the update form, below the plugin namespace.
     *
     * @var array<string, string>
     */
    protected const FORM_VALUES = [];

    /**
     * @var list<string>
     */
    private const SNAPSHOT_TABLES = [
        'tx_academicpersons_domain_model_profile',
        'tx_academicpersons_feuser_mm',
        'tx_academicpersons_domain_model_contract',
        'tx_academicpersons_domain_model_email',
        'tx_academicpersons_domain_model_phone_number',
        'tx_academicpersons_domain_model_address',
        'tx_academicpersons_domain_model_profile_information',
        'sys_file',
        'sys_file_reference',
    ];

    /**
     * Every action, each with an argument naming a record of the foreign profile.
     *
     * @return \Generator<string, array{0: string, 1: array<string, mixed>}>
     */
    abstract public static function foreignRecordRequestsDataProvider(): \Generator;

    /**
     * The actions that do not need a form, each with an own record and whether it writes.
     *
     * @return \Generator<string, array{0: string, 1: array<string, mixed>, 2: bool}>
     */
    abstract public static function ownRecordRequestsDataProvider(): \Generator;

    /**
     * The action rendering a form for an own record with its arguments, the action the form
     * posts to, and the arguments naming a foreign record instead.
     *
     * @return \Generator<string, array{0: string, 1: array<string, string>, 2: string, 3: array<string, string>}>
     */
    abstract public static function formSubmissionsDataProvider(): \Generator;

    /**
     * @param array<string, mixed> $arguments
     */
    #[DataProvider('foreignRecordRequestsDataProvider')]
    #[Test]
    public function requestForAForeignRecordIsRefused(string $action, array $arguments): void
    {
        $this->setUpOwnershipTestCase();
        $snapshot = $this->getRecordSnapshot();

        $response = $this->requestAction(static::CONTROLLER, $action, $arguments);

        $this->assertAccessDenied($response);
        $this->assertStringNotContainsString(static::FOREIGN_VALUE, (string)$response->getBody());
        $this->assertNothingWritten($snapshot);
    }

    /**
     * @param array<string, mixed> $arguments
     */
    #[DataProvider('ownRecordRequestsDataProvider')]
    #[Test]
    public function requestForAnOwnRecordIsServed(string $action, array $arguments, bool $writes): void
    {
        $this->setUpOwnershipTestCase();
        $snapshot = $this->getRecordSnapshot();

        $response = $this->requestAction(static::CONTROLLER, $action, $arguments);

        $this->assertServed($response, $snapshot, $writes);
    }

    /**
     * @param array<string, string> $formPageArguments
     * @param array<string, string> $foreignArguments
     */
    #[DataProvider('formSubmissionsDataProvider')]
    #[Test]
    public function ownFormSubmittedForAForeignRecordIsRefused(
        string $formPageAction,
        array $formPageArguments,
        string $formAction,
        array $foreignArguments,
    ): void {
        $this->setUpOwnershipTestCase();
        $formPage = (string)$this->requestAction(static::CONTROLLER, $formPageAction, $formPageArguments)->getBody();
        $snapshot = $this->getRecordSnapshot();

        $response = $this->submitForm($formPage, $formAction, static::FORM_VALUES, $foreignArguments);

        $this->assertAccessDenied($response);
        $this->assertNothingWritten($snapshot);
    }

    /**
     * @param array<string, string> $formPageArguments
     */
    #[DataProvider('formSubmissionsDataProvider')]
    #[Test]
    public function ownFormSubmittedForAnOwnRecordIsPersisted(
        string $formPageAction,
        array $formPageArguments,
        string $formAction,
    ): void {
        $this->setUpOwnershipTestCase();
        $formPage = (string)$this->requestAction(static::CONTROLLER, $formPageAction, $formPageArguments)->getBody();
        $snapshot = $this->getRecordSnapshot();

        $response = $this->submitForm($formPage, $formAction, static::FORM_VALUES);

        $this->assertServed($response, $snapshot, true);
    }

    protected function setUpOwnershipTestCase(): void
    {
        $this->setUpTestCase();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/ProfileOwnership/records.csv');
        // The write actions redirect to the referrer the read actions store in the session,
        // as they do for a visitor who reached the form through the profile view.
        $this->assertSame(200, $this->requestAction('Profile', 'show', ['profile' => '1'])->getStatusCode());
    }

    /**
     * Requests an action with its arguments in the body of a POST request.
     *
     * @param array<string, mixed> $arguments
     */
    protected function requestAction(string $controller, string $action, array $arguments): ResponseInterface
    {
        return $this->postPluginArguments([
            self::PLUGIN_NAMESPACE => [
                'controller' => $controller,
                'action' => $action,
                ...$arguments,
            ],
        ]);
    }

    /**
     * Requests an action with its arguments in the query string of a GET request, with a
     * valid cHash, the way a link the plugin renders carries them.
     *
     * @param array<string, mixed> $arguments
     */
    protected function requestActionByQueryString(string $controller, string $action, array $arguments): ResponseInterface
    {
        $query = http_build_query([
            self::PLUGIN_NAMESPACE => [
                'controller' => $controller,
                'action' => $action,
                ...$arguments,
            ],
        ]);
        $cacheHash = $this->get(CacheHashCalculator::class)->generateForParameters('&id=2&' . $query);
        return $this->requestAsFrontendUser(
            new InternalRequest(sprintf('https://www.acme.com/home?%s&cHash=%s', $query, $cacheHash)),
        );
    }

    /**
     * Submits the form posting to `$formAction` that the page renders, with the values a
     * visitor entered and its record arguments replaced by `$argumentOverrides`. That is
     * the form a visitor gets for their own record, with another uid put in, and the only
     * way to reach the write actions with a valid `__trustedProperties` value.
     *
     * @param array<string, string> $formValues field name below the plugin namespace => value
     * @param array<string, string> $argumentOverrides argument name => record uid
     * @param array<string, mixed> $uploadedFiles nested below the plugin namespace
     */
    protected function submitForm(
        string $pageContent,
        string $formAction,
        array $formValues,
        array $argumentOverrides = [],
        array $uploadedFiles = [],
    ): ResponseInterface {
        $this->assertSame(
            1,
            preg_match(
                '@<form [^>]*action="([^"]*' . preg_quote(urlencode('[action]'), '@') . '=' . preg_quote($formAction, '@') . '(?=&|")[^"]*)"(.*?)</form>@s',
                $pageContent,
                $formMatch,
            ),
            sprintf('The page does not contain a form posting to the "%s" action.', $formAction),
        );
        $parsedBody = $this->pluginArgumentsOfFormAction(html_entity_decode($formMatch[1]));
        preg_match_all(
            '@<input[^>]+type="hidden"[^>]+name="([^"]+)"[^>]+value="([^"]*)"@',
            $formMatch[2],
            $matches,
            PREG_SET_ORDER,
        );
        foreach ($matches as $match) {
            $this->addFormValue($parsedBody, html_entity_decode($match[1]), html_entity_decode($match[2]));
        }
        foreach ($formValues as $name => $value) {
            $this->addFormValue($parsedBody, sprintf('%s%s', self::PLUGIN_NAMESPACE, $name), $value);
        }
        foreach ($argumentOverrides as $argumentName => $recordUid) {
            if (is_array($parsedBody[self::PLUGIN_NAMESPACE][$argumentName] ?? null)) {
                $parsedBody[self::PLUGIN_NAMESPACE][$argumentName]['__identity'] = $recordUid;
            } else {
                $parsedBody[self::PLUGIN_NAMESPACE][$argumentName] = $recordUid;
            }
        }
        return $this->postPluginArguments($parsedBody, $uploadedFiles);
    }

    /**
     * @param array<string, mixed> $parsedBody
     * @param array<string, mixed> $uploadedFiles
     */
    private function postPluginArguments(array $parsedBody, array $uploadedFiles = []): ResponseInterface
    {
        // The body is provided explicitly, see `AbstractProfileEditingPluginTestCase::submitProfileForm()`.
        $body = new Stream('php://temp', 'rw');
        $body->write(http_build_query($parsedBody));
        $body->rewind();

        $request = (new InternalRequest('https://www.acme.com/home'))
            ->withMethod('POST')
            ->withAddedHeader('Content-Type', 'application/x-www-form-urlencoded')
            ->withBody($body)
            ->withParsedBody($parsedBody);
        if ($uploadedFiles !== []) {
            $request = $request->withUploadedFiles([self::PLUGIN_NAMESPACE => $uploadedFiles]);
        }
        return $this->requestAsFrontendUser($request);
    }

    protected function createUploadedProfileImage(): UploadedFile
    {
        $temporaryFile = $this->instancePath . '/typo3temp/profile-image-upload.png';
        copy(__DIR__ . '/Fixtures/Uploads/profile-image.png', $temporaryFile);
        return new UploadedFile($temporaryFile, (int)filesize($temporaryFile), UPLOAD_ERR_OK, 'upload.png', 'image/png');
    }

    /**
     * Every row of every table the editor reads or writes, so that "nothing was written"
     * covers all of them rather than the one row a test expects to be targeted.
     *
     * @return array<string, list<array<string, mixed>>>
     */
    protected function getRecordSnapshot(): array
    {
        $snapshot = [];
        foreach (self::SNAPSHOT_TABLES as $tableName) {
            $orderBy = $tableName === 'tx_academicpersons_feuser_mm' ? 'uid_local, uid_foreign' : 'uid';
            $snapshot[$tableName] = $this->getConnectionPool()
                ->getConnectionForTable($tableName)
                ->executeQuery(sprintf('SELECT * FROM %s ORDER BY %s', $tableName, $orderBy))
                ->fetchAllAssociative();
        }
        return $snapshot;
    }

    protected function assertAccessDenied(ResponseInterface $response): void
    {
        $this->assertSame(403, $response->getStatusCode(), 'A record of another frontend user was not refused.');
    }

    /**
     * @param array<string, list<array<string, mixed>>> $snapshotBefore
     */
    protected function assertNothingWritten(array $snapshotBefore): void
    {
        $this->assertSame($snapshotBefore, $this->getRecordSnapshot(), 'A refused request wrote to the database.');
    }

    /**
     * A read renders the page, a write changes a record. The status of a write is not
     * checked beyond "neither refused nor failed": both supported core versions send the
     * redirect of an Extbase plugin with `header()`, so a sub request sees the rendered
     * page instead, see `AcademicPersonsEditProfileImageRemoveTest`.
     *
     * @param array<string, list<array<string, mixed>>> $snapshotBefore
     */
    protected function assertServed(ResponseInterface $response, array $snapshotBefore, bool $writes): void
    {
        if (!$writes) {
            $this->assertSame(200, $response->getStatusCode(), 'The request on an own record was not served.');
            $this->assertSame($snapshotBefore, $this->getRecordSnapshot(), 'A read request wrote to the database.');
            return;
        }
        $this->assertNotSame(403, $response->getStatusCode(), 'The request on an own record was refused.');
        $this->assertNotSame(500, $response->getStatusCode(), 'The request on an own record failed.');
        $this->assertNotSame($snapshotBefore, $this->getRecordSnapshot(), 'The request on an own record wrote nothing.');
    }
}
