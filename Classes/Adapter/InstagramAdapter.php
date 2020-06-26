<?php

namespace PlusB\PbSocial\Adapter;

$extensionPath = \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath('pb_social') . 'Resources/Private/Libs/';
@include 'phar://' .  $extensionPath . 'instagram.phar/src/Instagram.php';

use MetzWeb\Instagram\Instagram;
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

class InstagramAdapter extends SocialMediaAdapter
{

    const TYPE = 'instagram';

    private $api;
    private $apiKey, $apiSecret, $apiCallback, $code, $token;

    /**
     * @param mixed $apiKey
     */
    public function setApiKey($apiKey)
    {
        $this->apiKey = $apiKey;
    }

    /**
     * @param mixed $apiSecret
     */
    public function setApiSecret($apiSecret)
    {
        $this->apiSecret = $apiSecret;
    }

    /**
     * @param mixed $apiCallback
     */
    public function setApiCallback($apiCallback)
    {
        $this->apiCallback = $apiCallback;
    }

    /**
     * @param mixed $code
     */
    public function setCode($code)
    {
        $this->code = $code;
    }

    /**
     * @param mixed $token
     */
    public function setToken($token)
    {
        $this->token = $token;
    }

    /**
     * @param mixed $options
     */
    public function setOptions($options)
    {
        $this->options = $options;
    }

    /**
     * InstagramAdapter constructor.
     * @param $apiKey
     * @param $apiSecret
     * @param $apiCallback
     * @param $code
     * @param $token
     * @param $itemRepository
     * @param $options
     * @param $ttContentUid
     * @param $ttContentPid
     * @param $cacheIdentifier
     * @throws \MetzWeb\Instagram\InstagramException
     */
    public function __construct(
        $apiKey,
        $apiSecret,
        $apiCallback,
        $code,
        $token,
        $itemRepository,
        $options,
        $ttContentUid,
        $ttContentPid,
        $cacheIdentifier)
    {
        parent::__construct($itemRepository, $cacheIdentifier, $ttContentUid, $ttContentPid);

        /* validation - interrupt instanciating if invalid */
        if(!$this->validateAdapterSettings(
                [
                    'apiKey' => $apiKey,
                    'apiSecret' => $apiSecret,
                    'apiCallback' => $apiCallback,
                    'code' => $code,
                    'token' => $token,
                    'options' => $options
                ]
            )
        )
        {
            throw new \Exception( self::TYPE . ' ' . $this->getValidation("validationMessage"), 1573551217);
        }
        /* validated */

        $this->api =  new Instagram(array('apiKey' => $this->apiKey, 'apiSecret' => $this->apiSecret, 'apiCallback' => $this->apiCallback));
        $this->api->setAccessToken($this->token);
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

        $this->setApiKey($parameter['apiKey']);
        $this->setApiSecret($parameter['apiSecret']);
        $this->setApiCallback($parameter['apiCallback']);
        $this->setCode($parameter['code']);
        $this->setToken($parameter['token']);
        $this->setOptions($parameter['options']);

        if (empty($this->apiKey) || empty($this->apiSecret) ||  empty($this->apiCallback)||  empty($this->code)||  empty($this->token)) {
            $validationMessage = 'credentials not set: '
                . (empty($this->apiKey)?'apiKey ':'')
                . (empty($this->apiSecret)?'apiSecret ':'')
                . (empty($this->apiCallback)?'apiCallback ':'')
                . (empty($this->code)?'code ':'')
                . (empty($this->token)?'token ':'')
            ;
        } elseif (empty($this->options->instagramSearchIds) && empty($this->options->instagramHashTags)) {
            $validationMessage = 'no search term defined: '
                . (empty($this->instagramSearchIds)?'instagramSearchIds ':'')
                . (empty($this->instagramHashTags)?'instagramHashTags ':'')
            ;
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

        // If search ID is given and hashtag is given and filter is checked, only show posts with given hashtag
        $filterByHastags = $options->instagramPostFilter && $options->instagramSearchIds && $options->instagramHashTags;

        if (!$filterByHastags) {
            /***************
             * loop a CRUD over list of search ids (Create Read Update Delete, no Delete...)
             ***************/
            foreach (explode(',', $options->instagramSearchIds) as $searchId) {
                $searchId = trim($searchId);
                $apiContent = null;

                if ($searchId != ""){
                    //defining
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
                            $apiContent = $this->api->getUserMedia($searchId, $options->feedRequestLimit);

                            //if apiContent from api call have some content and I already have it in database model items: update item in model (CR *U* D)
                            if ($apiContent !== null && ($item !== null) ) {
                                if ($apiContent->meta->code >= 400) {
                                    throw new \Exception('warning: ' . json_encode($apiContent->meta),1558435723);
                                }
                                $item->setDate(new \DateTime('now'));
                                $item->setResult(json_encode($apiContent));
                                $this->itemRepository->updateItem($item);

                                //taking item to result
                                $result[] = $item;
                                //if api content it there and item is empty, you write new item to model in database (*C* RUD)
                            }elseif($apiContent !== null) {
                                //insert new item
                                $item = new Item(self::TYPE);
                                $item->setItemIdentifier($itemIdentifier);
                                $item->setResult(json_encode($apiContent));
                                // save to DB and return current item
                                $this->itemRepository->saveItem($item);
                                //taking item to result
                                $result[] = $item;
                            }elseif ($apiContent === null){
                                throw new \Exception('user posts empty, this user does not exist (app may be in sandbox mode) ',1559556559);
                            }elseif ($apiContent->meta->code >= 400) {
                                throw new \Exception('warning: ' . json_encode($apiContent->meta),1558435728);
                            }

                        }
                        catch (\Exception $e) {
                                throw new \Exception($e->getMessage(), 1573562359);
                            }
                    } else {
                        $result[] = $item;
                    }
                }
            }
        }

        /***************
         * loop a CRUD over list of search ids (Create Read Update Delete, no Delete...)
         ***************/
        foreach (explode(',', $options->instagramHashTags) as $searchId) {
            $searchId = trim($searchId);
            $searchId = ltrim($searchId, '#'); //strip hastags

            if ($searchId != "") {
                $apiContent = null;

                $itemIdentifier = $this->composeItemIdentifierForListItem($this->cacheIdentifier,
                    $searchId); //new for every foreach round up
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
                    ($options->devMod || $this->isFlexformRefreshTimeUp($item->getDate()->getTimestamp(),
                            $options->refreshTimeInMin))
                ) {
                    try {
                        //make a request on api
                        $apiContent = $this->api->getTagMedia($searchId, $options->feedRequestLimit);
                        //if apiContent from api call have some content and I already have it in database model items: update item in model (CR *U* D)
                        if ($apiContent !== null && ($item !== null)) {
                            if ($apiContent->meta->code >= 400) {
                                throw new \Exception('warning: ' . json_encode($apiContent->meta), 1558435751);
                            }

                            $item->setDate(new \DateTime('now'));
                            $item->setResult(json_encode($apiContent));
                            $this->itemRepository->updateItem($item);

                            //taking item to result
                            $result[] = $item;
                            //if api content it there and item is empty, you write new item to model in database (*C* RUD)
                        } elseif ($apiContent !== null) {
                            //insert new item
                            $item = new Item(self::TYPE);
                            $item->setItemIdentifier($itemIdentifier);
                            $item->setResult(json_encode($apiContent));
                            // save to DB and return current item
                            $this->itemRepository->saveItem($item);
                            //taking item to result
                            $result[] = $item;
                        }

                    } catch (\Exception $e) {
                        throw new \Exception("feeds can't be updated. " . $e->getMessage(), 1573552290);
                    }
                }

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
            foreach ($result as $item) {

                $rawFeeds[self::TYPE . '_' . $item->getItemIdentifier() . '_raw'] = $item->getResult();

                if (is_array($item->getResult()->data)) {
                    foreach ($item->getResult()->data as $rawFeed) {
                        if ($options->onlyWithPicture && empty($rawFeed->images->standard_resolution->url)) {
                            continue;
                        }
                        $feed = new Feed(self::TYPE, $rawFeed);
                        $feed->setId($rawFeed->id);
                        $feed->setText($this->trim_text($rawFeed->caption->text, $options->textTrimLength, true));
                        $feed->setImage($rawFeed->images->standard_resolution->url);
                        $feed->setLink($rawFeed->link);
                        $feed->setTimeStampTicks($rawFeed->created_time);
                        $feedArray[] = $feed;
                    }
                }
            }
        }

        return $this->setCacheContentData($rawFeeds, $feedArray);
    }
}
