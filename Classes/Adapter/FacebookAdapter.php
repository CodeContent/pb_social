<?php

namespace PlusB\PbSocial\Adapter;

$extensionPath = \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath('pb_social') . 'Resources/Private/Libs/';
require $extensionPath . 'facebook/src/Facebook/autoload.php';


use Facebook\Facebook;
use PlusB\PbSocial\Domain\Model\Feed;
use PlusB\PbSocial\Domain\Model\Item;

/***************************************************************
 *
 *  Copyright notice
 *
 *  (c) 2016 Ramon Mohi <rm@plusb.de>, plus B
 *  (c) 2019 Arend Maubach <am@plusb.de>, plus B
 *  (c) 2019 Sergej Junker <sj@plusb.de>, plus B
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
class FacebookAdapter extends SocialMediaAdapter
{

    const TYPE = 'facebook';
    const API_VERSION = "v5.0";

    private $api;

    public $isValid = false;
    private $apiId, $apiSecret, $pageAccessToken;

    /**
     * @param string $apiId
     */
    public function setApiId($apiId)
    {
        $this->apiId = $apiId;
    }

    /**
     * @param string $apiSecret
     */
    public function setApiSecret($apiSecret)
    {
        $this->apiSecret = $apiSecret;
    }

    /**
     * @param string $pageAccessToken
     */
    private function setPageAccessToken(string $pageAccessToken)
    {
        $this->pageAccessToken = $pageAccessToken;
    }

    /**
     * @return string
     */
    private function getPageAccessToken() : string
    {
        return $this->pageAccessToken;
    }



    public function __construct(
        $apiId,
        $apiSecret,
        $pageAccessToken,
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
                    'pageAccessToken' => $pageAccessToken,
                    'options' => $options
                ]
            )
        )
        {
            throw new \Exception( self::TYPE . ' ' . $this->getValidation("validationMessage"), 1574088320);
        }
        /* validated */

        $this->api = new Facebook(['app_id' => $this->apiId,'app_secret' => $this->apiSecret,'default_graph_version' => self::API_VERSION]);
        //$this->access_token =  $this->api->getApp()->getAccessToken();
        $this->api->setDefaultAccessToken($pageAccessToken);
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
        $this->setPageAccessToken($parameter['pageAccessToken']);
        $pageAccessToken = $this->getPageAccessToken();

        $this->setOptions($parameter['options']);

        if (empty($this->apiId)
            || empty($this->apiSecret)
            || empty($pageAccessToken)
            || $pageAccessToken === ''
            || empty($this->options->settings['facebookPageID'])
        ) {
            $validationMessage =
                'credentials not set: '
                . (empty($this->apiId)?'apiId, ':'')
                . (empty($this->apiSecret)?'apiSecret, ':'')
                . (empty($pageAccessToken)?'pageAccessToken, ':'')
                . (empty($this->options->settings['facebookPageID'])?'facebookPageID ':'')
            ;
        } elseif (strstr($this->options->settings['facebookPageID'],',')) {
            $validationMessage = 'facebookPageID contains "," - please use only one facebook Page ID("Facebook facebookPageID" in flexform settings) ';
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
        $item = null;

        $facebookPageID = $options->settings['facebookPageID']?:null;
        $facebookPageID = trim($facebookPageID);
        $facebookEdge = $options->settings['facebookEdge']?:null;
        $apiContent = null;

        if (empty($facebookPageID)) {
            //log a warning, return null
            $this->logAdapterWarning('no facebookPageID', 1573467383);
            return null;
        } elseif (strstr($facebookPageID,',')){
            $this->logAdapterWarning('facebookPageID contains "," - please use only one facebook Page ID', 1573467387);
            return null;
        }

        /* **************
         *  CRUD Create Read Update Delete, no Delete...)
         ************** */
            $itemIdentifier = $this->composeItemIdentifierForListItem($this->cacheIdentifier , $facebookPageID); //new for every foreach round up
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
                    $apiContent = $this->callApiContentFromFacebook($facebookPageID, $options->feedRequestLimit, $facebookEdge);
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
                    throw new \Exception($e->getMessage(), 1573562350);
                }
            } else {
                $result[] = $item;
            }



        return $this->composeFeedArrayFromItemArrayForFrontEndView($result, $options);
    }

    /** Make API request via Facebook sdk function
     *
     * @param string $facebookPageID
     * @param int $limit
     * @param string $edge endpoint of facebook
     * @return string  A Json String that starts with {"data":[{"id":"
     *
     */
    public function callApiContentFromFacebook($facebookPageID, $limit, $edge)
    {
        //endpoint
        switch ($edge){
            case 'feed': $request = 'feed'; break;
            case 'posts': $request = 'posts'; break;
            case 'tagged ': $request = 'tagged '; break;
                default: $request = 'feed';
            }

        $endpoint = '/' . $facebookPageID . '/' . $request;

        //params
        //set default parameter list in case s.b messes up with TypoScript
        $faceBookRequestParameter =
            'picture,
               
                created_time,
                full_picture';

        //overwritten by Typoscript
        if(isset($this->options->settings['facebook']['requestParameterList']) && is_string($this->options->settings['facebook']['requestParameterList'])){
            $faceBookRequestParameter =  $this->options->settings['facebook']['requestParameterList'];
        }

        //always prepending id and message
        $faceBookRequestParameter = 'id,message,' . $faceBookRequestParameter;

        $params = [
            'fields' => $faceBookRequestParameter,
            'limit' => $limit
        ];

        try {
            /** @var \Facebook\FacebookResponse $facebookResponse */
            $facebookResponse = $this->api->sendRequest(
                'GET',
                $endpoint,
                $params
            );


        } catch (\Facebook\Exceptions\FacebookSDKException $e) {
            throw new \Exception($e->getMessage(), 1558515214);
        }

        if (empty(json_decode($facebookResponse->getBody())->data) || json_encode($facebookResponse->getBody()->data) == null) {
            throw new \Exception( 'no posts found for facebook page id: ' . $facebookPageID, 1558515218);
        }

        return $facebookResponse->getBody();
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

                foreach ($item->getResult()->data as $facebookData) {
                    if ($options->onlyWithPicture && (empty($facebookData->picture) || empty($facebookData->full_picture))) {
                        continue;
                    }
                    $feed = new Feed(self::TYPE, $facebookData);
                    $feed->setId($facebookData->id);
                    $feed->setText($this->trim_text($facebookData->message, $options->textTrimLength, true));
                    if (property_exists($facebookData, 'picture')) {
                        $feed->setImage(urldecode($facebookData->picture));
                    }

                    $feed->setLink('https://facebook.com/' . $facebookData->id);

                    $date = new \DateTime($facebookData->created_time);
                    $feed->setTimeStampTicks($date->getTimestamp());

                    $feedArray[] = $feed;
                }
            }
        }

        return $this->setCacheContentData($rawFeeds, $feedArray);
    }
}
