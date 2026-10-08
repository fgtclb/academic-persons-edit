<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Tests\Functional\Plugins;

use FGTCLB\AcademicBase\Extbase\Property\TypeConverter\FileUploadConverter;
use FGTCLB\AcademicPersonsEdit\Controller\ProfileController;
use FGTCLB\AcademicPersonsEdit\Service\ProfileOwnershipService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\UserAspect;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Http\PropagateResponseException;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Extbase\Mvc\Controller\Arguments;
use TYPO3\CMS\Extbase\Mvc\ExtbaseRequestParameters;
use TYPO3\CMS\Extbase\Mvc\Request;
use TYPO3\CMS\Extbase\Reflection\ReflectionService;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;

/**
 * Pins the file name `ProfileController::initializeAddImageAction()` configures for the
 * profile image upload.
 *
 * The converter stores the upload while Extbase maps the `profile` argument. Without a
 * configured name it keeps the name the client sent, in the folder all profiles share, and
 * replaces a file of that name. The name therefore has to be resolved for every profile the
 * user owns, hidden, scheduled and group restricted ones included, and from the argument
 * the way Extbase reads it, wherever the request carried it. When no owned profile resolves,
 * the request is refused before anything is mapped.
 *
 * A functional request cannot observe the configured name: the storage only accepts files
 * that `is_uploaded_file()` confirms, so no upload completes in a test. The initialization
 * method is therefore called on the controller directly, with the collaborators it uses.
 */
final class ProfileImageUploadTargetNameTest extends AbstractProfileEditingPluginTestCase
{
    public static function ownProfileDataProvider(): \Generator
    {
        yield 'identity in the body, as the form posts it' => [['profile' => ['__identity' => '1']], false, 'Max-Müllermann-1'];
        yield 'identity in the query string' => [['profile' => ['__identity' => '1']], true, 'Max-Müllermann-1'];
        yield 'plain uid in the query string' => [['profile' => '1'], true, 'Max-Müllermann-1'];
        yield 'hidden own profile' => [['profile' => ['__identity' => '5']], false, 'Hidden-Owner-5'];
        yield 'scheduled own profile' => [['profile' => ['__identity' => '6']], false, 'Scheduled-Owner-6'];
        yield 'group restricted own profile' => [['profile' => ['__identity' => '7']], false, 'Restricted-Owner-7'];
    }

    /**
     * @param array<string, mixed> $arguments
     */
    #[DataProvider('ownProfileDataProvider')]
    #[Test]
    public function uploadForAnOwnProfileIsNamedAfterTheProfile(array $arguments, bool $inQueryString, string $expectedName): void
    {
        $this->setUpUploadTestCase();

        $this->assertSame($expectedName, $this->initializeAddImageAction($arguments, $inQueryString));
    }

    public static function unresolvableProfileDataProvider(): \Generator
    {
        yield 'no profile argument' => [[]];
        yield 'profile argument without identity' => [['profile' => ['image' => '']]];
        yield 'identity of no record' => [['profile' => ['__identity' => '99']]];
        yield 'foreign profile' => [['profile' => ['__identity' => '2']]];
    }

    /**
     * @param array<string, mixed> $arguments
     */
    #[DataProvider('unresolvableProfileDataProvider')]
    #[Test]
    public function uploadWithoutAnOwnProfileIsRefused(array $arguments): void
    {
        $this->setUpUploadTestCase();

        try {
            $targetName = $this->initializeAddImageAction($arguments);
        } catch (PropagateResponseException $exception) {
            $this->assertSame(403, $exception->getResponse()->getStatusCode());
            return;
        }
        $this->fail(sprintf('The upload was not refused, its file would be named "%s".', $targetName));
    }

    private function setUpUploadTestCase(): void
    {
        $this->setUpTestCase();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/ProfileOwnership/records.csv');
        $this->importCSVDataSet(__DIR__ . '/Fixtures/ProfileOwnership/uploadTargetProfiles.csv');
        $frontendUser = new FrontendUserAuthentication();
        $frontendUser->user = ['uid' => self::FRONTEND_USER_ID, 'username' => 'editor'];
        $this->get(Context::class)->setAspect('frontend.user', new UserAspect($frontendUser));
    }

    /**
     * Runs `initializeAddImageAction()` for a request carrying the given plugin arguments,
     * in the body or in the query string, and returns the file name it configured for the
     * converter. The Extbase request arguments are the merged ones, as `RequestBuilder`
     * hands them on.
     *
     * @param array<string, mixed> $arguments
     */
    private function initializeAddImageAction(array $arguments, bool $inQueryString = false): string
    {
        $extbaseParameters = (new ExtbaseRequestParameters(ProfileController::class))
            ->setPluginName('ProfileEditing')
            ->setControllerExtensionName('AcademicPersonsEdit')
            ->setControllerName('Profile')
            ->setControllerActionName('addImage')
            ->setArguments($arguments);
        $serverRequest = new ServerRequest('https://www.acme.com/home', 'POST');
        $serverRequest = $inQueryString
            ? $serverRequest->withQueryParams(['tx_academicpersonsedit_profileediting' => $arguments])
            : $serverRequest->withParsedBody(['tx_academicpersonsedit_profileediting' => $arguments]);
        $request = new Request(
            $serverRequest
                ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_FE)
                ->withAttribute('site', $this->get(SiteFinder::class)->getSiteByIdentifier('acme'))
                ->withAttribute('extbase', $extbaseParameters),
        );
        $controller = (new \ReflectionClass(ProfileController::class))->newInstanceWithoutConstructor();
        $collaborators = [
            'reflectionService' => $this->get(ReflectionService::class),
            'context' => $this->get(Context::class),
            'profileOwnershipService' => $this->get(ProfileOwnershipService::class),
        ];
        /** @var Arguments $controllerArguments */
        $controllerArguments = (function () use ($request, $collaborators): Arguments {
            foreach ($collaborators as $property => $collaborator) {
                $this->{$property} = $collaborator;
            }
            $this->arguments = new Arguments();
            $this->settings = [];
            $this->request = $request;
            $this->actionMethodName = 'addImageAction';
            $this->initializeActionMethodArguments();
            $this->initializeAddImageAction();
            return $this->arguments;
        })->call($controller);

        return (string)$controllerArguments
            ->getArgument('profile')
            ->getPropertyMappingConfiguration()
            ->forProperty('image')
            ->getConfigurationValue(
                FileUploadConverter::class,
                FileUploadConverter::CONFIGURATION_TARGET_FILE_NAME_WITHOUT_EXTENSION,
            );
    }
}
