<?php

namespace PlusB\PbSocial\Adapter;

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

class YoutubeAdapter extends SocialMediaAdapter
{

    const TYPE = 'youtube';

    const YT_LINK = 'https://www.youtube.com/watch?v=';

    const YT_SEARCH = 'https://www.googleapis.com/youtube/v3/search?q=';

    // get items from playlist api call
    const YT_SEARCH_PLAYLIST = 'https://www.googleapis.com/youtube/v3/playlistItems?part=snippet&playlistId=';

    // get items from channel api call
    const YT_SEARCH_CHANNEL = 'https://www.googleapis.com/youtube/v3/search?channelId=';

    public $isValid = false, $validationMessage = "";
    private $appKey;

    /**
     * @param mixed $appKey
     */
    public function setAppKey($appKey)
    {
        $this->appKey = $appKey;
    }



    public function __construct(
        $appKey,
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
                    'appKey' => $appKey,
                    'options' => $options
                ]
            )
        )
        {
            throw new \Exception( self::TYPE . ' ' . $this->getValidation("validationMessage"), 1573565268);
        }
        /* validated */
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

        $this->setAppKey($parameter['appKey']);
        $this->setOptions($parameter['options']);

        if (empty($this->appKey)) {
            $validationMessage = self::TYPE . ' credentials not set';
        } elseif (empty($this->options->youtubeSearch)  && empty($this->options->youtubePlaylist) && empty($this->options->youtubeChannel) ) {
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

        $fields = array(
            'key' => $this->appKey,
            'maxResults' => $options->feedRequestLimit,
            'part' => 'snippet'
        );

        if ($options->youtubeType != '') {
            $fields['type'] = $options->youtubeType;
        }
        if ($options->youtubeLanguage != '') {
            $fields['relevanceLanguage'] = $options->youtubeLanguage;
        }
        if ($options->youtubeOrder != 'relevance') {
            $fields['order'] = $options->youtubeOrder;
        }

        $searchTerms = explode(',', $options->youtubeSearch);
        if ($options->youtubePlaylist) {
            $searchTerms = explode(',', $options->youtubePlaylist);
        }
        if ($options->youtubeChannel) {
            $searchTerms = explode(',', $options->youtubeChannel);
        }

        /***************
         * loop a CRUD over list of search ids (Create Read Update Delete, no Delete...)
         ***************/
        foreach ($searchTerms as $searchString) {
            $searchString = trim(urlencode($searchString));

            $apiContent = null;

            $itemIdentifier = $this->composeItemIdentifierForListItem($this->cacheIdentifier , $searchString); //new for every foreach round up
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
                    $apiContent = $this->callApi($searchString, $fields, $options);

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
                    throw new \Exception($e->getMessage(), 1559547942);
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
            foreach ($result as $yt_feed) {
                $rawFeeds[self::TYPE . '_' . $yt_feed->getItemIdentifier() . '_raw'] = $yt_feed->getResult();
                foreach ($yt_feed->getResult()->items as $rawFeed) {
                    $feed = new Feed(self::TYPE, $rawFeed);
                    if ($options->youtubePlaylist) {
                        $id = $rawFeed->snippet->resourceId->videoId;
                    } else {
                        $id = $rawFeed->id->videoId;
                    }
                    $feed->setId($id);
                    $feed->setText($this->trim_text($rawFeed->snippet->title, $options->textTrimLength, true));
                    $feed->setImage($rawFeed->snippet->thumbnails->standard->url);
                    $feed->setLink(self::YT_LINK . $id);
                    $d = new \DateTime($rawFeed->snippet->publishedAt);
                    $feed->setTimeStampTicks($d->getTimestamp());
                    $feedArray[] = $feed;
                }
            }
        }

        return $this->setCacheContentData($rawFeeds, $feedArray);
    }

    /**
     * @param $searchString
     * @param $fields
     * @return mixed
     * @throws \Exception
     */
    public function callApi($searchString, $fields, $options)
    {
        $headers = array('Content-Type: application/json');

        // use different api call for channel
        if ($options->youtubeChannel) {
            $url = self::YT_SEARCH_CHANNEL . $searchString . '&' . http_build_query($fields);
        } // use different api call for playlist
        else if ($options->youtubePlaylist) {
            $url = self::YT_SEARCH_PLAYLIST . $searchString . '&' . http_build_query($fields);
        } else {
            $url = self::YT_SEARCH . $searchString . '&' . http_build_query($fields);
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $curl_response = curl_exec($ch);

        if (property_exists(json_decode($curl_response), 'error')) {
            throw new \Exception($curl_response, 1558521095);
        }

        return $curl_response;
    }
}
