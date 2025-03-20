<?php

declare(strict_types=1);

/*
 * This file is part of the "academic_persons_edit" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace Fgtclb\AcademicPersonsEdit\Middleware;

use Fgtclb\AcademicPersonsEdit\Context\ProfileAspect;
use Fgtclb\AcademicPersonsEdit\Event\ChooseProfileFactoryEvent;
use Fgtclb\AcademicPersonsEdit\Profile\ProfileFactory;
use Fgtclb\AcademicPersonsEdit\Provider\ProfileProvider;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\Exception\AspectNotFoundException;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;

final class ProfileLoader implements MiddlewareInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function __construct(private Context $context, private ProfileProvider $profileProvider, private EventDispatcherInterface $eventDispatcher) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            $userAspect = $this->context->getAspect('frontend.user');
        } catch (AspectNotFoundException) {
            $this->logger?->warning('No context for aspect frontend.user found');
            return $handler->handle($request);
        }

        if (!$userAspect->isLoggedIn()) {
            $this->logger?->debug('No frontend user is logged in - not loading any profiles');
            return $handler->handle($request);
        }

        $currentUserUid = $userAspect->get('id');
        $allProfileUids = [];
        $activeProfileUid = 0;
        if (!$this->profileProvider->userHasProfile($currentUserUid)) {
            /** @var FrontendUserAuthentication $frontendUserAuthentication */
            $frontendUserAuthentication = $request->getAttribute('frontend.user');
            /** @var ChooseProfileFactoryEvent $chooseProfileFactoryEvent */
            $chooseProfileFactoryEvent = $this->eventDispatcher->dispatch(new ChooseProfileFactoryEvent($frontendUserAuthentication));
            $profileFactory = $chooseProfileFactoryEvent->getProfileFactory();
            if ($profileFactory === null) {
                $profileFactory = GeneralUtility::makeInstance(ProfileFactory::class);
            }
            if ($profileFactory->shouldCreateProfileForUser($frontendUserAuthentication)) {
                $profileUid = $profileFactory->createProfileForUser($frontendUserAuthentication);
                if ($profileUid !== null) {
                    $activeProfileUid = $profileUid;
                    $allProfileUids[] = $activeProfileUid;
                }
            }
        } else {
            $allProfileUids = $this->profileProvider->getProfileUidsFromUserUid($currentUserUid);
            $activeProfileUid = $this->profileProvider->getActiveProfileUidFromRequest($request);
        }

        if ($activeProfileUid === 0) {
            $this->logger?->debug('No active profile uid given, using first one found.');
            $activeProfileUid = $allProfileUids[0] ?? 0;
        }

        $this->context->setAspect('frontend.profile', new ProfileAspect($allProfileUids, $activeProfileUid));

        return $handler->handle($request->withAttribute('frontend.profileUid', $activeProfileUid));
    }
}
