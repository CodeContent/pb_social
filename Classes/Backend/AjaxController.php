<?php
namespace PlusB\PbSocial\Backend;

use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2019 Arend Maubach <am@plusb.de>, Plus B
 *
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

/**
 * Handling of Ajax requests
 */
class AjaxController
{


    /**
     * itemRepository
     *
     * @var \PlusB\PbSocial\Domain\Repository\ItemRepository
     * @inject
     */
    protected $itemRepository = null;


    public function clearCache()
    {
        $user = "unkown";
        if ($GLOBALS['BE_USER']) {
            $user = $GLOBALS['BE_USER']->user['username'];
            //write syslog in case of be_user is in globals,
            // like "01:51:02 		admin LIVE 	CACHE 	Clear Cache 	User admin has cleared the cache (cacheCmd=pb_social_cache)"
            $GLOBALS['BE_USER']->writelog(3, 1, 0, 0, 'User %s has cleared the cache (cacheCmd=%s)', [$user, "pb_social_cache"]);
        }

        try {
            //get caching backend
            $cache = GeneralUtility::makeInstance(CacheManager::class)->getCache('pb_social_cache');
            //flush all pb_scoial cache from caching framework cf_pb_social_cache_tags and cf_pb_social_cache
            $cache->flushByTag('pb_social');

        } catch ( \Exception $e){
            $this->showWarningMessage('Delete Cache of pb_social failed',
                "User $user wanted to clear the cache (cacheCmd=pb_social_cache) - but flushing of caching framework not possible. 1560430642");
        }

        if ($GLOBALS['TYPO3_DB']){
            //empty all api call results from tx_pbsocial_domain_model_item,
            // redone by plugin-page reload or scheduler task (in case request limit of some api is not up)
            $GLOBALS['TYPO3_DB']->exec_TRUNCATEquery('tx_pbsocial_domain_model_item');
        }else{
            $this->showWarningMessage('Delete Cache of pb_social failed',
                "User $user wanted to clear the cache (cacheCmd=pb_social_cache)  - but no TYPO3 backend loaded. 1560430430");
        }
    }

    private function showWarningMessage($header, $message){
        if ($GLOBALS['BE_USER']) {
            //write syslog in case of be_user is in globals
            $GLOBALS['BE_USER']->writelog(3, 1, 0, 0, $message, []);
        }

        //show flash message after next page reload in backend
        $message = GeneralUtility::makeInstance(FlashMessage::class,
            $message,
            $header, // [optional] the header
            FlashMessage::WARNING, // [optional] the severity defaults to FlashMessage::OK
            true // [optional] whether the message should be stored in the session or only in the FlashMessageQueue object (default is false)
        );
        $flashMessageService = GeneralUtility::makeInstance(FlashMessageService::class);
        $messageQueue = $flashMessageService->getMessageQueueByIdentifier();
        $messageQueue->addMessage($message);
    }

}
