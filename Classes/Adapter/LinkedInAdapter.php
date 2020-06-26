<?php

namespace PlusB\PbSocial\Adapter;

$extensionPath = \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath('pb_social') . 'Resources/Private/Libs/';
//require_once $extensionPath . 'linkedin/src/Client.php'; # Include provider library
//// ... please, add composer autoloader first
//include_once $extensionPath . 'linkedin' . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
@include 'phar://' .  $extensionPath . 'linkedin.phar/vendor/autoload.php';


use LinkedIn\AccessToken;
use LinkedIn\Client;
use PlusB\PbSocial\Domain\Model\Credential;
use PlusB\PbSocial\Domain\Model\Feed;
use PlusB\PbSocial\Domain\Model\Item;

/***************************************************************
 *
 *  Copyright notice
 *
 *  (c) 2018 Ramon Mohi <rm@plusb.de>, plus B
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

class LinkedInAdapter extends SocialMediaAdapter
{

    const TYPE = 'linkedin';
    const EXTKEY = 'pb_social';
    const linkedin_company_post_uri = "https://www.linkedin.com/feed/update/urn:li:activity:";

    private $apiKey, $apiSecret, $apiCallback, $token;

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

    private $api;

    /**
     * credentialRepository
     *
     * @var \PlusB\PbSocial\Domain\Repository\CredentialRepository
     * @inject
     */
    protected $credentialRepository;

    public function __construct(
        $apiKey,
        $apiSecret,
        $apiCallback,
        $token,
        $itemRepository,
        $credentialRepository,
        $options,
        $ttContentUid,
        $ttContentPid,
        $cacheIdentifier)
    {
        parent::__construct($itemRepository,  $cacheIdentifier, $ttContentUid, $ttContentPid);

        /* validation - interrupt instanciating if invalid */
        if(!$this->validateAdapterSettings(
                [
                    'apiKey' => $apiKey,
                    'apiSecret' => $apiSecret,
                    'apiCallback' => $apiCallback,
                    'token' => $token,
                    'options' => $options
                ]
            )
        )
        {
            throw new \Exception( self::TYPE . ' ' .$this->getValidation("validationMessage"), 1573552578);
        }
        /* validated */

        $this->api =  new Client($this->apiKey,$this->apiSecret);
        #$this->api->setApiRoot('https://api.linkedin.com/v2/');
        $this->credentialRepository = $credentialRepository;
        // get access token from database
        $this->setAccessToken($this->token, $this->apiKey);
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
        $this->setToken($parameter['token']);
        $this->setOptions($parameter['options']);

        if (empty($this->apiKey) || empty($this->apiSecret) ||  empty($this->token) ||  empty($this->apiCallback)) {
            $validationMessage = self::TYPE . ' credentials not set ' . (empty($this->apiKey)?'apiKey ':''). (empty($this->apiSecret)?'apiSecret ':''). (empty($this->token)?'token ':''). (empty($this->apiCallback)?'apiCallback ':'');
        } elseif (empty($this->options->companyIds)) {
            $validationMessage = self::TYPE . ' no search term defined ' . (empty($this->companyIds)?'companyIds ':'');
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

        # set filters
        $filters = (@$options->settings['linkedinFilterChoice'] != '')?'&'.$options->settings['linkedinFilterChoice']:'';

        # get company updates
        # additional filters for job postings, new products and status updates may be applied

        /***************
         * loop a CRUD over list of search ids (Create Read Update Delete, no Delete...)
         ***************/
        foreach (explode(',', $options->companyIds) as $searchId) {

            $searchId = trim($searchId);

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
                        $apiContent = $this->api->get('companies/' . $searchId .'/updates?format=json' . $filters); # filters is empty ("") if no filters are applied..
                        //if apiContent from api call have some content and I already have it in database model items: update item in model (CR *U* D)
                        if ($apiContent !== null && ($item !== null) ) {

                            $item->setDate(new \DateTime('now'));
                            //apiContent from api call included in item-model and updated in database
                            $item->setResult(json_encode($apiContent));
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
            foreach ($result as $linkedin_feed) {
                $rawFeeds[self::TYPE . '_' . $linkedin_feed->getItemIdentifier() . '_raw'] = $linkedin_feed->getResult();
                $i = 0;
                if (is_array($linkedin_feed->getResult()->values)) {
                    foreach ($linkedin_feed->getResult()->values as $rawFeed) {
                        if ($i < $options->feedRequestLimit)
                        {
                            $feed = new Feed(self::TYPE, $rawFeed);
                            $feed->setId($rawFeed->timestamp);
                            $feed->setText($this->trim_text($rawFeed->updateContent->companyStatusUpdate->share->comment, $options->textTrimLength, true));
                            $feed->setImage($rawFeed->updateContent->companyStatusUpdate->share->content->thumbnailUrl);
                            $link = self::linkedin_company_post_uri . array_reverse(explode('-', $rawFeed->updateKey))[0];
                            $feed->setLink($link);
                            $feed->setTimeStampTicks($rawFeed->timestamp);
                            $feedArray[] = $feed;
                            $i++;
                        }
                    }
                }
            }
        }

        return $this->setCacheContentData($rawFeeds, $feedArray);
    }

    private function setAccessToken($token, $apiKey)
    {
        if (empty($token))
        {
            throw new \Exception('Access token empty', 1558515214);
        }
        if (empty($apiKey))
        {
            throw new \Exception('Client ID empty', 1558435560);
        }
        # generate AccessToken class
        try
        {
            $access_token = new AccessToken();
            $access_token->setToken($token);
        }
        catch (\Exception $e)
        {
            throw new \Exception('failed to setup AccessToken' . $e->getMessage(), 1558435565);
        }
        # get access token from database #
        $credentials = $this->credentialRepository->findByTypeAndAppId(self::TYPE, $apiKey);

        if ($credentials->count() > 1)
        {
            foreach ($credentials as $c)
            {
                if ($c->getAccessToken != '')
                {
                    $credential = $c;
                } else {
                    $this->credentialRepository->remove($c);
                }
            }
        }
        else {
            $credential = $credentials->getFirst();
        }

//        if (!empty($this->api->getAccessTokenExpiration()) && $this->api->getAccessTokenExpiration() < strtotime('tomorrow'))
//        {
//            # api doc says you can reuse the old access code.. maybe I misinterpreted something? we'll give it a shot
//            # https://developer.linkedin.com/docs/oauth2
//            # todo: renew LinkedIn access token when $accessToken->getExpiresAt() < strtotime('tomorrow')
//        }

        if (!isset($credential) || !$credential->isValid())
        {
            if (isset($credential))
            {
                $credential->setAccessToken($token);
                $this->credentialRepository->update($credential);
            }
            else {
                # create new credential #
                $credential = new Credential(self::TYPE, $apiKey);
                $credential->setAccessToken($token);
                $this->credentialRepository->saveCredential($credential);
            }
        }

        $this->api->setAccessToken($access_token);

        return $credential->getAccessToken();
    }
}
