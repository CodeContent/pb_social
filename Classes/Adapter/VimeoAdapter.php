<?php

namespace PlusB\PbSocial\Adapter;

$extensionPath = \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath('pb_social') . 'Resources/Private/Libs/';
require $extensionPath . 'vimeo/autoload.php';
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

class VimeoAdapter extends SocialMediaAdapter
{

    const TYPE = 'vimeo';

    const VIMEO_LINK = 'https://player.vimeo.com';

    private $api, $clientIdentifier, $clientSecret, $accessToken;

    /**
     * @param mixed $clientIdentifier
     */
    public function setClientIdentifier($clientIdentifier)
    {
        $this->clientIdentifier = $clientIdentifier;
    }

    /**
     * @param mixed $clientSecret
     */
    public function setClientSecret($clientSecret)
    {
        $this->clientSecret = $clientSecret;
    }

    /**
     * @param mixed $accessToken
     */
    public function setAccessToken($accessToken)
    {
        $this->accessToken = $accessToken;
    }

    /**
     * @param mixed $options
     */
    public function setOptions($options)
    {
        $this->options = $options;
    }



    public function __construct(
        $clientIdentifier,
        $clientSecret,
        $accessToken,
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
                    'clientIdentifier' => $clientIdentifier,
                    'clientSecret' => $clientSecret,
                    'accessToken' => $accessToken,
                    'options' => $options
                ]
            )
        )
        {
            throw new \Exception( self::TYPE . ' ' . $this->getValidation("validationMessage"), 1573565000);
        }
        /* validated */

        $this->api = new \Vimeo\Vimeo($clientIdentifier, $clientSecret, $accessToken);
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

        $this->setClientIdentifier($parameter['clientIdentifier']);
        $this->setClientSecret($parameter['clientSecret']);
        $this->setAccessToken($parameter['accessToken']);
        $this->setOptions($parameter['options']);

        if (empty($this->clientIdentifier) || empty($this->clientSecret) || empty($this->accessToken)) {
            $validationMessage = self::TYPE . ' credentials not set';
        } elseif (empty($this->options->vimeoChannel)) {
            $validationMessage = self::TYPE . ' no channel defined';
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

        $searchTerms = explode(',', $options->settings['vimeoChannel']);

        /***************
         * loop a CRUD over list of search ids (Create Read Update Delete, no Delete...)
         ***************/
        foreach ($searchTerms as $searchString) {

            $searchString = trim($searchString);
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
                    $apiContent = $this->callApi($searchString, $options);


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
                    throw new \Exception($e->getMessage(), 1573565137);
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
            foreach ($result as $vimeo_feed) {
                /**
                 * todo: invalid cache identifier
                 */
                $rawFeeds[self::TYPE . '_' . $vimeo_feed->getItemIdentifier() . '_raw'] = $vimeo_feed->getResult();
                foreach ($vimeo_feed->getResult()->body->data as $rawFeed) {
                    $feed = new Feed(self::TYPE, $rawFeed);
                    $feed->setId($rawFeed->link);
                    $feed->setText($this->trim_text($rawFeed->name, $options->textTrimLength, true));
                    $feed->setImage($rawFeed->pictures->sizes[5]->link);
                    $feed->setLink(self::VIMEO_LINK . $rawFeed->link);
                    $d = new \DateTime($rawFeed->created_time);

                    $feed->setTimeStampTicks($d->getTimestamp());
                    $feedArray[] = $feed;
                }
            }
        }

        return $this->setCacheContentData($rawFeeds, $feedArray);
    }

    public function callApi($searchString, $options)
    {
        if ($searchString == 'me') {
            $url = '/me/videos';
        } else {
            $url = '/channels/' . $searchString . '/videos';
        }

        $response = $this->api->request($url, array('per_page' => $options->feedRequestLimit), 'GET');
        return json_encode($response);
    }
}
