<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Service;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\SingletonInterface;

class UserSessionService implements SingletonInterface
{
    public function saveRefererToSession(ServerRequestInterface $request): void
    {
        $this->saveToSession('referrer', (string)$request->getUri(), $request);
    }

    /**
     * @return mixed
     */
    public function loadRefererFromSession(ServerRequestInterface $request)
    {
        return $this->loadFromSession('referrer', $request);
    }

    /**
     * @param mixed $value
     */
    public function saveToSession(string $key, $value, ?ServerRequestInterface $request = null): void
    {
        $request = $request ? $request : $GLOBALS['TYPO3_REQUEST'];
        $frontendUser = $request->getAttribute('frontend.user');
        $frontendUser->setKey('ses', $key, $value);
        $frontendUser->storeSessionData();
    }

    /**
     * @return mixed
     */
    public function loadFromSession(string $key, ?ServerRequestInterface $request = null)
    {
        $request = $request ? $request : $GLOBALS['TYPO3_REQUEST'];
        $frontendUser = $request->getAttribute('frontend.user');
        return $frontendUser->getKey('ses', $key);
    }
}
