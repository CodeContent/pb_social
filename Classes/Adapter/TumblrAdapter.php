<?php

namespace PlusB\PbSocial\Adapter;

$extensionPath = \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath('pb_social') . 'Resources/Private/Libs/';
@include 'phar://' .  $extensionPath . 'tumblr.phar/vendor/autoload.php';

use PlusB\PbSocial\Domain\Model\Feed;
use PlusB\PbSocial\Domain\Model\Item;
use Tumblr\API\Client;

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

class TumblrAdapter extends SocialMediaAdapter
{

    const TYPE = 'tumblr';

    private $apiId, $apiSecret, $token, $tokenSecret;

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
     * @param mixed $token
     */
    public function setToken($token)
    {
        $this->token = $token;
    }

    /**
     * @param mixed $tokenSecret
     */
    public function setTokenSecret($tokenSecret)
    {
        $this->tokenSecret = $tokenSecret;
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
        $token,
        $tokenSecret,
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
                    'token' => $token,
                    'tokenSecret' => $tokenSecret,
                    'options' => $options
                ]
            )
        )
        {
            throw new \Exception( self::TYPE . ' ' . $this->getValidation("validationMessage"), 1573563427);
        }
        /* validated */


        $this->api =  new Client($this->apiId, $this->apiSecret);
        $this->api->setToken($this->token, $this->tokenSecret);
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
        $this->setToken($parameter['token']);
        $this->setTokenSecret($parameter['tokenSecret']);
        $this->setOptions($parameter['options']);

        if (empty($this->apiId) || empty($this->apiSecret) ||  empty($this->token)||  empty($this->tokenSecret)) {
            $validationMessage = self::TYPE . ' credentials not set';
        } elseif (empty($this->options->tumblrBlogNames) ) {
            $validationMessage = self::TYPE . ' - no blog names for search term defined';
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

        // search for users
        /***************
         * loop a CRUD over list of search ids (Create Read Update Delete, no Delete...)
         ***************/
        foreach (explode(',', $options->tumblrBlogNames) as $blogName) {
            $blogName = trim($blogName);
            $apiContent = null;

            $itemIdentifier = $this->composeItemIdentifierForListItem($this->cacheIdentifier , $blogName); //new for every foreach round up
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
                    $apiContent = $this->callApi($blogName, $options);
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
                }catch (\Exception $e) {
                    throw new \Exception($e->getMessage(), 1573563729);
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
            foreach ($result as $tblr_feed) {
                $rawFeeds[self::TYPE . '_' . $tblr_feed->getItemIdentifier() . '_raw'] = $tblr_feed->getResult();
                foreach ($tblr_feed->getResult()->posts as $rawFeed) {
                    if ($options->onlyWithPicture && empty($rawFeed->photos[0]->original_size->url)) {
                        continue;
                    }
                    $feed = new Feed(self::TYPE, $rawFeed);
                    $feed->setId($rawFeed->id);
                    $text = '';
                    if ($rawFeed->caption) {
                        $text = $rawFeed->caption;
                    } elseif ($rawFeed->body) {
                        $text = $rawFeed->body;
                    } elseif ($rawFeed->description) {
                        $text = $rawFeed->description;
                    } elseif ($rawFeed->text) {
                        $text = $rawFeed->text;
                    } elseif ($rawFeed->summary) {
                        $text = $rawFeed->summary;
                    }
                    $feed->setText($this->trim_text(strip_tags($text), $options->textTrimLength, true));
                    if ($rawFeed->photos[0]->original_size->url) {
                        $feed->setImage($rawFeed->photos[0]->original_size->url);
                    } elseif ($rawFeed->thumbnail_url) {
                        $feed->setImage($rawFeed->thumbnail_url);
                    }
                    $feed->setLink($rawFeed->post_url);
                    $feed->setTimeStampTicks($rawFeed->timestamp);
                    $feedArray[] = $feed;
                }
            }
        }

        return $this->setCacheContentData($rawFeeds, $feedArray);
    }

    public function callApi($blogName, $options)
    {
        $posts = '';

        if ($blogName == 'MYDASHBOARD') {
            if ($options->tumblrShowOnlyImages) {
                $posts = (json_encode($this->api->getDashboardPosts(array('limit' => $options->feedRequestLimit, 'type' => 'photo'))));
            } else {
                $posts = (json_encode($this->api->getDashboardPosts(array('limit' => $options->feedRequestLimit))));
            }
        } else {
            if ($options->tumblrHashtag !== '') {
                $options->tumblrHashtag = trim($options->tumblrHashtag);
                $options->tumblrHashtag = ltrim($options->tumblrHashtag, '#'); //strip hastags
                if ($options->tumblrShowOnlyImages) {
                    $posts = (json_encode($this->api->getBlogPosts($blogName, array('limit' => $options->feedRequestLimit, 'type' => 'photo', 'tag' => $options->tumblrHashtag, 'filter' => 'text'))));
                } else {
                    $posts = (json_encode($this->api->getBlogPosts($blogName, array('limit' => $options->feedRequestLimit, 'tag' => $options->tumblrHashtag, 'filter' => 'text'))));
                }
            } else {
                if ($options->tumblrShowOnlyImages) {
                    $posts = (json_encode($this->api->getBlogPosts($blogName, array('limit' => $options->feedRequestLimit, 'type' => 'photo', 'filter' => 'text'))));
                } else {
                    $posts = (json_encode($this->api->getBlogPosts($blogName, array('limit' => $options->feedRequestLimit, 'filter' => 'text'))));
                }
            }
        }

        return $posts;
    }
}
