<?php

namespace PlusB\PbSocial\Adapter;

$extensionPath = \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath('pb_social') . 'Resources/Private/Libs/';
@include 'phar://' .  $extensionPath . 'twitteroauth.phar/autoload.php';
use Abraham\TwitterOAuth\TwitterOAuth;
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

class TwitterAdapter extends SocialMediaAdapter
{

    const TYPE = 'twitter';

    private $consumerKey, $consumerSecret, $accessToken, $accessTokenSecret;

    /**
     * @param mixed $consumerKey
     */
    public function setConsumerKey($consumerKey)
    {
        $this->consumerKey = $consumerKey;
    }

    /**
     * @param mixed $consumerSecret
     */
    public function setConsumerSecret($consumerSecret)
    {
        $this->consumerSecret = $consumerSecret;
    }

    /**
     * @param mixed $accessToken
     */
    public function setAccessToken($accessToken)
    {
        $this->accessToken = $accessToken;
    }

    /**
     * @param mixed $accessTokenSecret
     */
    public function setAccessTokenSecret($accessTokenSecret)
    {
        $this->accessTokenSecret = $accessTokenSecret;
    }

    /**
     * @param mixed $options
     */
    public function setOptions($options)
    {
        $this->options = $options;
    }

    private $api;

    //private $api_url = 'statuses/user_timeline';

    public function __construct(
        $consumerKey,
        $consumerSecret,
        $accessToken,
        $accessTokenSecret,
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
                array(
                    'consumerKey' => $consumerKey,
                    'consumerSecret' => $consumerSecret,
                    'accessToken' => $accessToken,
                    'accessTokenSecret' => $accessTokenSecret,
                    'options' => $options
                )))
        {
            throw new \Exception( self::TYPE . ' ' .$this->getValidation("validationMessage"), 1558515175);
        }
        /* validated */

        $this->api =  new TwitterOAuth($this->consumerKey, $this->consumerSecret, $this->accessToken, $this->accessTokenSecret);
        $this->api->setTimeouts(10, 10);
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

        $this->setConsumerKey($parameter['consumerKey']);
        $this->setConsumerSecret($parameter['consumerSecret']);
        $this->setAccessToken($parameter['accessToken']);
        $this->setAccessTokenSecret($parameter['accessTokenSecret']);
        $this->setOptions($parameter['options']);

        if (empty($this->consumerKey) || empty($this->consumerSecret) ||  empty($this->accessToken)||  empty($this->accessTokenSecret)) {
            $validationMessage = self::TYPE . ' credentials not set';
        } elseif (empty($this->options->twitterSearchFieldValues)  && empty($this->options->twitterProfilePosts) ) {
            $validationMessage = self::TYPE . ' no search term defined';
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
        $apiMethod = '';

        // because of the amount of data twitter is sending, the database can only carry 20 tweets.
        // 20 Tweets = ~86000 Character
        $apiParameters = array();
//        if ($options->feedRequestLimit > 20) {
//            $options->feedRequestLimit = 20;
//        }
        if ($options->twitterLanguage) {
            $apiParameters['lang'] = $options->twitterLanguage;
        }
        if ($options->twitterGeoCode) {
            $apiParameters['geocode'] = $options->twitterGeoCode;
        }

        if ($options->twitterSearchFieldValues) {
            $this->api_url = 'search/tweets';

            /***************
             * loop a CRUD over list of search ids (Create Read Update Delete, no Delete...)
             ***************/
            foreach (explode(',', $options->twitterSearchFieldValues) as $searchValue) {
                $searchValue = trim($searchValue);
                $apiContent = null;

                $itemIdentifier = $this->composeItemIdentifierForListItem($this->cacheIdentifier, $searchValue); //new for every foreach round up
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
                        $apiContent = $this->callApi($apiParameters, $options, $searchValue);

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
                    } catch (\Exception $e) {
                        throw new \Exception($e->getMessage(), 1573564188);
                    }
                } else {
                    $result[] = $item;
                }

            }
        }


        if ($options->twitterProfilePosts) {
            $this->api_url = 'statuses/user_timeline';

            foreach (explode(',', $options->twitterProfilePosts) as $searchValue) {
                $searchValue = trim($searchValue);
                $feeds = $this->itemRepository->findByTypeAndCacheIdentifier(self::TYPE, $searchValue);
                //https://dev.twitter.com/rest/reference/get/search/tweets
                //include_entities=false => The entities node will be disincluded when set to false.
                $apiParameters['screen_name'] = $searchValue;

                $itemIdentifier = $this->composeItemIdentifierForListItem($this->cacheIdentifier, $searchValue); //new for every foreach round up
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
                        $apiContent = $this->callApi($apiParameters, $options, $searchValue);

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
                    } catch (\Exception $e) {
                        throw new \Exception($e->getMessage(), 1573564188);
                    }
                } else {
                    $result[] = $item;
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
            foreach ($result as $twt_feed) {
                if ($this->api_url == 'search/tweets') {
                    $twitterResult = $twt_feed->getResult()->statuses;
                } else {
                    $twitterResult = $twt_feed->getResult();
                }

                if (empty($twitterResult)) {
                    throw new \Exception( "status empty", 1558435615);
                    break;
                }
                $rawFeeds[self::TYPE . '_' . $twt_feed->getItemIdentifier() . '_raw'] = $twt_feed->getResult();
                foreach ($twitterResult as $rawFeed) {
                    if ($options->twitterShowOnlyImages && null == $rawFeed->entities->media) {
                        continue;
                    }
                    $feed = new Feed($twt_feed->getType(), $rawFeed);
                    $feed->setId($rawFeed->id);
                    $feed->setText($this->trim_text($rawFeed->full_text, $options->textTrimLength, true));

                    if ($rawFeed->entities->media[0]->type == 'photo') {
                        if ($options->twitterHTTPS) {
                            $feed->setImage($rawFeed->entities->media[0]->media_url_https);
                        } else {
                            $feed->setImage($rawFeed->entities->media[0]->media_url);
                        }
                    }
                    if ($rawFeed->entities->media[0]->url) {
                        $feed->setLink($rawFeed->entities->media[0]->url);
                    } elseif ($rawFeed->entities->urls[0]->expanded_url) {
                        $feed->setLink($rawFeed->entities->urls[0]->expanded_url);
                    } else {
                        $feed->setLink('https://twitter.com/' . $rawFeed->user->screen_name . '/status/' . $rawFeed->id_str);
                    }
                    $dateTime = new \DateTime($rawFeed->created_at);
                    $feed->setTimeStampTicks($dateTime->getTimestamp());
                    $feedArray[] = $feed;
                }
            }
        }

        return $this->setCacheContentData($rawFeeds, $feedArray);
    }

    public function callApi($apiParameters, $options, $searchValue)
    {
        $requestParameters = $apiParameters;
        $include_entities = false;

        if ($options->twitterHideRetweets) {
            $searchValue = $searchValue . ' -filter:retweets';
            $include_entities = true;
        }
        if ($options->twitterShowOnlyImages) {
            $searchValue = $searchValue . ' filter:images';
            $include_entities = true;
        }
        if ($include_entities) {
            $requestParameters['include_entities'] = 'true';
        }

        $requestParameters['tweet_mode'] = 'extended';
        $requestParameters['q'] = $searchValue;
        $requestParameters['count'] = $options->feedRequestLimit;
        $tweets = json_encode($this->api->get($this->api_url, $requestParameters));

        return $tweets;
    }
}
