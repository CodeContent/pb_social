<?php

namespace PlusB\PbSocial\Adapter;

$extensionPath = \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath('pb_social') . 'Resources/Private/Libs/';
@include 'phar://' .  $extensionPath . 'pinterest.phar/autoload.php';
use DirkGroenen\Pinterest;
use PlusB\PbSocial\Domain\Model\Credential;
use PlusB\PbSocial\Domain\Model\Feed;
use PlusB\PbSocial\Domain\Model\Item;

/***************************************************************
 *
 *  Copyright notice
 *
 *  (c) 2016 Ramon Mohi <rm@plusb.de>, plus B
 *  (c) 2019 Arend Maubach <am@plusb.de>, plus B
 *
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
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

class PinterestAdapter extends SocialMediaAdapter
{

    const TYPE = 'pinterest';

    private $api;
    private $credentialRepository;

    private $appId, $appSecret, $accessCode;

    /**
     * @param mixed $appId
     */
    public function setAppId($appId)
    {
        $this->appId = $appId;
    }

    /**
     * @param mixed $appSecret
     */
    public function setAppSecret($appSecret)
    {
        $this->appSecret = $appSecret;
    }

    /**
     * @param mixed $accessCode
     */
    public function setAccessCode($accessCode)
    {
        $this->accessCode = $accessCode;
    }

    /**
     * @param mixed $options
     */
    public function setOptions($options)
    {
        $this->options = $options;
    }

    public function __construct(
        $appId,
        $appSecret,
        $accessCode,
        $itemRepository,
        $credentialRepository,
        $options,
        $ttContentUid,
        $ttContentPid,
        $cacheIdentifier
    )
    {
        parent::__construct($itemRepository, $cacheIdentifier, $ttContentUid, $ttContentPid);
        /**
         * todo: quick fix - but we'd better add a layer for adapter in between, here after "return $this" instance is not completed but existing (AM)
         */
        /* validation - interrupt instanciating if invalid */
        if(!$this->validateAdapterSettings(
                [
                    'appId' => $appId,
                    'appSecret' => $appSecret,
                    'accessCode' => $accessCode,
                    'options' => $options
                ]
            )
        )
        {
            throw new \Exception( self::TYPE . ' ' .$this->getValidation("validationMessage"), 1573562733);
        }

        /* validated */
        $this->api = new Pinterest\Pinterest($this->appId, $this->appSecret);
        $this->credentialRepository = $credentialRepository;
        $code = $this->extractCode($this->accessCode);
        $this->getAccessToken($code);
    }


    /**
     * validates constructor input parameters in an individual way just for the adapter
     *
     * @param $parameter
     * @return bool
     */
    public function validateAdapterSettings($parameter) : bool
    {
        $isValid = false;
        $validationMessage = "";

        $this->setAppId($parameter['appId']);
        $this->setAppSecret($parameter['appSecret']);
        $this->setAccessCode($parameter['accessCode']);
        $this->setOptions($parameter['options']);

        if (empty($this->appId) || empty($this->appSecret) ||  empty($this->accessCode)) {
            $validationMessage = self::TYPE . ' credentials not set: '. (empty($this->appId)?'appId ':''). (empty($this->appSecret)?'appSecret ':''). (empty($this->accessCode)?'accessCode ':'');
        } elseif (empty($this->options->pinterest_username) || empty($this->options->pinterest_username)) {
            $validationMessage = self::TYPE . ' username or board name not defined in flexform settings';
        } else {
            $isValid = true;
        }

        $this->setValidation($isValid, $validationMessage);
        return $isValid;
    }

    /**
     * loops over search id from flexform, reads records form PlusB\PbSocial\Domain\Model\Item(),
     * updates them, writes new one if expired or invalid, calls composeFeedArrayFromItemArrayForFrontEndView
     *
     * @return array array of 'rawFeeds' => $rawFeeds, 'feedItems' => $feedArray []PlusB\PbSocial\Domain\Model\Feed
     */
    public function getResultFromApi() : array
    {
        $options = $this->options;
        $result = array();

        $boardname = $options->pinterest_username . '/' . $options->pinterest_boardname;

        /***************
         * loop a CRUD over list of search ids (Create Read Update Delete, no Delete...)
         ***************/
        foreach (explode(',', $options->username) as $searchId) {
            $searchId = trim($searchId);
            $apiContent = null;

            $itemIdentifier = $this->composeItemIdentifierForListItem($this->cacheIdentifier , $searchId); //new for every foreach round up
            //looking for items in model (C *R* UD) - not in cache
            /**
             * @var $item Item
             */
            $item = $this->itemRepository->findByTypeAndItemIdentifier(self::TYPE, $itemIdentifier);

            //if dev mod, or time is up OR there are simply no items in database, then you will need a new api request
            if (
                // found nothing in item model
                ($item === null)
                ||
                //dev Mode is on or time from flexform was up
                ($options->devMod || $this->isFlexformRefreshTimeUp($item->getDate()->getTimestamp(), $options->refreshTimeInMin))
            ) {
                try {
                    //make a request on api
                    $apiContent = $this->callApi($boardname);
                    // $apiContent like this: {"data":[{"id":"167276393322010_22579494909213 .... "message":"Thank you 3pc ....

                    //if apiContent from api call have some content and I already have it in database model items: update item in model (CR *U* D)
                    if ($apiContent !== null && ($item !== null)) {

                        $item->setDate(new \DateTime('now'));
                        //apiContent from api call included in item-model and updated in database
                        $item->setResult($apiContent);
                        $this->itemRepository->updateItem($item);

                        //taking item to result
                        $result[] = $item;

                        //if api content it there and item is empty, you write new item to model in database (*C* RUD)
                    } elseif ($apiContent !== null) {
                        //insert new item
                        $item = new Item(self::TYPE);
                        $item->setItemIdentifier($itemIdentifier);
                        $item->setResult($apiContent);
                        // save to DB and return current item
                        $this->itemRepository->saveItem($item);
                        //taking item to result
                        $result[] = $item;

                    }
                }catch (\Exception $e) {
                    throw new \Exception($e->getMessage(), 1573563245);
                }
            } else {
                $result[] = $item;
            }
        }

        return $this->composeFeedArrayFromItemArrayForFrontEndView($result, $options);
    }

    /**
     * takes up result from api getResultFromApi() (even it was from database or fresh from api, whatever), maps array of
     * PlusB\PbSocial\Domain\Model\Item() to array of PlusB\PbSocial\Domain\Model\Feed() for front end to show.
     *
     * @param $result array of items PlusB\PbSocial\Domain\Model\Item
     * @param $options object all seetings from flexform and typoscript
     * @return array of 'rawFeeds' => $rawFeeds, 'feedItems' => $feedArray []PlusB\PbSocial\Domain\Model\Feed
     */
    public function composeFeedArrayFromItemArrayForFrontEndView($result, $options) : array
    {
        /*
          $result => array(2 items)
            0 => PlusB\PbSocial\Domain\Model\Item
            1 => PlusB\PbSocial\Domain\Model\Item
        */

        $rawFeeds = array();
        $feedArray = array(); //[]PlusB\PbSocial\Domain\Model\Feed

        if (!empty($result)) {
            foreach ($result as $pin_feed) {
                $rawFeeds[self::TYPE . '_' . $pin_feed->getItemIdentifier() . '_raw'] = $pin_feed->getResult();
                $i = 0;
                foreach ($pin_feed->getResult()->data as $pin) {
                    if ($pin->image && ($i < $options->feedRequestLimit)) {
                        $i++;
                        $feed = new Feed(self::TYPE, $pin);
                        $feed->setText($this->trim_text($pin->note, $options->textTrimLength, true));
                        $feed->setImage($pin->image->original->url);
                        $link = $pin->link ? $pin->link : $pin->url;
                        $feed->setLink($link);
                        $d = new \DateTime($pin->created_at);
                        $feed->setTimeStampTicks($d->getTimestamp());
                        $feedArray[] = $feed;
                    }
                }
            }
        }

        return $this->setCacheContentData($rawFeeds, $feedArray);
    }

    public function callApi($boardname)
    {
        $fields = array(
            'fields' => 'id,link,counts,note,created_at,image[small],url'
        );

        /**
         * todo: throw in Request.php line 220 stops script... stop stopping, please (AM)
         */
        return json_encode($this->api->pins->fromBoard($boardname, $fields));
    }

    private function getAccessToken($code)
    {
        $apiKey = $this->appId;

        # get access token from database #
        $credentials = $this->credentialRepository->findByTypeAndAppId(self::TYPE, $apiKey);

        if ($credentials->count() > 1) {
            foreach ($credentials as $c) {
                if ($c->getAccessToken != '') {
                    $credential = $c;
                } else {
                    $this->credentialRepository->remove($c);
                }
            }
        } else {
            $credential = $credentials->getFirst();
        }

        if (!isset($credential) || !$credential->isValid()) {
            # validate code to get access token #
            $token = $this->api->auth->getOAuthToken($code);
            $access_token = $token->access_token;
            if ($access_token) {
                if (isset($credential)) {
                    $credential->setAccessToken($access_token);
                    $this->credentialRepository->update($credential);
                } else {
                    # create new credential #
                    $credential = new Credential(self::TYPE, $apiKey);
                    $credential->setAccessToken($access_token);
                    $this->credentialRepository->saveCredential($credential);
                }
            } else {
                throw new \Exception('access code expired. Please provide new code in pb_social extension configuration.',
                    1558435580);
                return null;
            }
        }

        $this->api->auth->setOAuthToken($credential->getAccessToken());

        //testrequest
        try {
            $this->api->request->get('me');
        } catch (\Exception $e) {
            $this->credentialRepository->deleteCredential($credential);
            throw new \Exception('exception: ' . $e->getMessage(), 1558435586);
        }
    }

    /** Converts url-encoded code
     * @param $accessCode
     * @return string
     */
    public function extractCode($accessCode)
    {
        $accessCode = urldecode($accessCode);

        if (strpos($accessCode, '&state=')) {
            $accessCode = explode('&state=', $accessCode)[0];
        }

        if (strpos($accessCode, 'code=') > -1) {
            $parts = explode('code=', $accessCode);
            $code = strpos($parts[0], 'http') > -1 || $parts[0] == '' ? $parts[1] : $parts[0];
        } elseif (strpos($accessCode, '=') == 0) {
            $code = ltrim($accessCode, '=');
        } else {
            $code = $accessCode;
        }

        return $code;
    }
}
