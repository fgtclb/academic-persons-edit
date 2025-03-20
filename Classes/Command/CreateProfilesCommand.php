<?php

declare(strict_types=1);

/*
 * This file is part of the "academic_persons_edit" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace Fgtclb\AcademicPersonsEdit\Command;

use Fgtclb\AcademicPersonsEdit\Event\ChooseProfileFactoryEvent;
use Fgtclb\AcademicPersonsEdit\Profile\ProfileFactory;
use Fgtclb\AcademicPersonsEdit\Provider\FrontendUserProvider;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;

final class CreateProfilesCommand extends Command
{
    public function __construct(private readonly FrontendUserProvider $frontendUserProvider, private readonly EventDispatcherInterface $eventDispatcher)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setHelp('This command create profiles for all frontend users that do not have a profile yet but should have one.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $frontendUsers = $this->frontendUserProvider->getUsersWithoutProfile();

        foreach ($frontendUsers as $frontendUser) {
            $frontendUserAuthentication = GeneralUtility::makeInstance(FrontendUserAuthentication::class);
            $frontendUserAuthentication->user = $frontendUser;
            $frontendUserAuthentication->fetchGroupData(new ServerRequest());

            /** @var ChooseProfileFactoryEvent $chooseProfileFactoryEvent */
            $chooseProfileFactoryEvent = $this->eventDispatcher->dispatch(new ChooseProfileFactoryEvent($frontendUserAuthentication));
            $profileFactory = $chooseProfileFactoryEvent->getProfileFactory();
            if ($profileFactory === null) {
                $profileFactory = GeneralUtility::makeInstance(ProfileFactory::class);
            }

            if ($profileFactory->shouldCreateProfileForUser($frontendUserAuthentication)) {
                $profileFactory->createProfileForUser($frontendUserAuthentication);
            }
        }

        return Command::SUCCESS;
    }
}
