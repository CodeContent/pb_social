<?php

namespace PlusB\PbSocial\Adapter;

$extensionPath = \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath('pb_social') . 'Resources/Private/Libs/';
require $extensionPath . 'imgur/Imgur.php';

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

class ImgurAdapter extends SocialMediaAdapter
{

    const TYPE = 'imgur';

    private $apiId, $apiSecret;

    /**
     * @param mixed $apiId
     */
    public function setApiId($apiId)
    {
        $this->apiId = $apiId;
    }

    /**
     * @param mixed $apiSecret
     */
    public function setApiSecret($apiSecret)
    {
        $this->apiSecret = $apiSecret;
    }

    /**
     * @param mixed $options
     */
    public function setOptions($options)
    {
        $this->options = $options;
    }

    private $api;

    public function __construct(
        $apiId,
        $apiSecret,
        $itemRepository,
        $options,
        $ttContentUid,
        $ttContentPid,
        $cacheIdentifier
    )
    {
        parent::__construct($itemRepository, $cacheIdentifier, $ttContentUid, $ttContentPid);

        /* validation - interrupt instanciating if invalid */
        if(!$this->validateAdapterSettings(
                [
                    'apiId' => $apiId,
                    'apiSecret' => $apiSecret,
                    'options' => $options
                ]
            )
        )
        {
            throw new \Exception( self::TYPE . ' ' . $this->getValidation("validationMessage") , 1558521767);
        }
        /* validated */

        $this->api =  new \Imgur($this->apiId, $this->apiSecret);
        //TODO: Implement OAuth authentication (to get a user's images etc)
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

        $this->setApiId($parameter['apiId']);
        $this->setApiSecret($parameter['apiSecret']);
        $this->setOptions($parameter['options']);

        if (empty($this->apiId) || empty($this->apiSecret)) {
            $validationMessage  = 'credentials not set: ' . (empty($this->apiId)?'apiId ':''). (empty($this->apiSecret)?'apiSecret ':'');
        } elseif (empty($this->options->imgSearchUsers) && empty($this->options->imgSearchTags)) {
            $validationMessage = ' no search term defined';
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

        /***************
         * loop a CRUD over list of search ids (Create Read Update Delete, no Delete...)
         ***************/
        foreach (explode(',', $options->imgSearchUsers) as $searchId) {

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
                    $apiContent = json_encode($this->api->account($searchId)->images());
                    // $apiContent like this: {"data":[{"id":"167276393322010_22579494909213 .... "message":"Thank you 3pc ....

                    //if apiContent from api call have some content and I already have it in database model items: update item in model (CR *U* D)
                    if ($apiContent !== null && ($item !== null) ) {

                        $item->setDate(new \DateTime('now'));
                        //apiContent from api call included in item-model and updated in database
                        $item->setResult($apiContent);
                        $this->itemRepository->updateItem($item);

                        //taking item to result
                        $result[] = $item;

                        //if api content it there and item is empty, you write new item to model in database (*C* RUD)
                    }elseif($apiContent !== null) {
                        //insert new item
                        $item = new Item(self::TYPE);
                        $item->setItemIdentifier($itemIdentifier);
                        $item->setResult($apiContent);
                        // save to DB and return current item
                        $this->itemRepository->saveItem($item);
                        //taking item to result
                        $result[] = $item;

                    }
                }
                catch (\Exception $e) {
                    throw new \Exception($e->getMessage(), 1558521819);
                }
            } else {
                $result[] = $item;
            }
        }


        /***************
         * loop a CRUD over list of search ids (Create Read Update Delete, no Delete...)
         ***************/
        // search for tags
        foreach (explode(',', $options->imgSearchTags) as $searchId) {
            //defining
            $searchId = trim($searchId);

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
                    $apiContent = json_encode($this->api->gallery()->search($searchId));

                    // $apiContent like this: {"data":[{"id":"167276393322010_22579494909213 .... "message":"Thank you 3pc ....

                    //if apiContent from api call have some content and I already have it in database model items: update item in model (CR *U* D)
                    if ($apiContent !== null && ($item !== null) ) {

                        $item->setDate(new \DateTime('now'));
                        //apiContent from api call included in item-model and updated in database
                        $item->setResult($apiContent);
                        $this->itemRepository->updateFeed($item);
                        //taking item to result
                        $result[] = $item;

                        //if api content it there and item is empty, you write new item to model in database (*C* RUD)

                    }elseif($apiContent !== null) {
                        //insert new item
                        $item = new Item(self::TYPE);
                        $item->setItemIdentifier($itemIdentifier);
                        $item->setResult($apiContent);
                        // save to DB and return current item
                        $this->itemRepository->saveItem($item);
                        //taking item to result
                        $result[] = $item;

                    }
                }
                catch (\Exception $e) {
                    throw new \Exception($e->getMessage(), 1558521819);
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




        $endingArray = array('.gif', '.jpg', '.png');
        if (!empty($result)) {
            foreach ($result as $item) {

                $rawFeeds[self::TYPE . '_' . $item->getItemIdentifier() . '_raw'] = $item->getResult();
                $i = 0;
                foreach ($item->getResult()->data as $imgurData) {

                    if (is_object($imgurData) && ($i < $options->feedRequestLimit)) {

                        if ($this->check_end($imgurData->link, $endingArray)) {
                            $i++;
                            $feed = new Feed(self::TYPE, $imgurData);
                            $feed->setId($imgurData->id);
                            $feed->setImage($imgurData->link);
                            $feed->setText($this->trim_text($imgurData->title, $options->textTrimLength, true));
                            $feed->setLink('http://imgur.com/gallery/' . $imgurData->id);
                            $feed->setTimeStampTicks($imgurData->datetime);
                            $feedArray[] = $feed;
                        }
                    }
                }
            }
        }

        return $this->setCacheContentData($rawFeeds, $feedArray);
    }
}
