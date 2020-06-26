<?php

namespace PlusB\PbSocial\Adapter;

use GeorgRinger\News\Domain\Model\Dto\NewsDemand;
use GeorgRinger\News\Domain\Repository\NewsRepository;
use PlusB\PbSocial\Domain\Model\Feed;
use PlusB\PbSocial\Domain\Model\Item;
use PlusB\PbSocial\Domain\Repository\ItemRepository;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Object\ObjectManager;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;

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

class TxNewsAdapter extends SocialMediaAdapter
{

    const TYPE = 'txnews';

    protected $cObj;
    protected $detailPageUid;

    /**
     * newsRepository
     *
     * @var \GeorgRinger\News\Domain\Repository\NewsRepository
     * @inject
     */
    protected $newsRepository;
    public $newsDemand;

    public function __construct(
        $newsDemand,
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
                    'options' => $options
                ]
            )
        )
        {
            throw new \Exception( self::TYPE . ' ' . $this->getValidation("validationMessage"), 1573564624);
        }
        /* validated */

        $this->cObj = GeneralUtility::makeInstance(ContentObjectRenderer::class);
        $this->newsDemand = $newsDemand;

        $om = new ObjectManager();
        $this->newsRepository = $om->get(NewsRepository::class);
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

        $this->setOptions($parameter['options']);

        if(!ExtensionManagementUtility::isLoaded('news')){
            $validationMessage = self::TYPE . ' extension not loaded.';
        }else{
            if (empty($this->options->newsCategories)) {
                $validationMessage = self::TYPE . ': no news category defined, will output all available news';
            }
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

        $this->detailPageUid = $options->newsDetailPageUid;
        $newsCategories = GeneralUtility::trimExplode(',', $options->newsCategories);

        /***************
         * loop a CRUD over list of search ids (Create Read Update Delete, no Delete...)
         ***************/
        foreach ($newsCategories as $newsCategory) {
            $newsCategory = trim($newsCategory);
            $this->newsDemand->setCategories(array($newsCategory));
            $apiContent = null;

            $itemIdentifier = $this->composeItemIdentifierForListItem($this->cacheIdentifier , $newsCategory); //new for every foreach round up
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
                    if(!empty($newsCategory)){
                        $this->newsDemand->setCategoryConjunction('or');
                    }
                    $demanded = $this->newsRepository->findDemanded($this->newsDemand)->toArray();
                    $apiContent = empty($demanded) ? array() : $this->demandedNewsToString($demanded, $options->useHttps);

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
            foreach ($result as $news_feed) {
                $rawFeeds[self::TYPE . '_' . $news_feed->getItemIdentifier() . '_raw'] = $news_feed->getResult();
                # traverse each single news item
                $i = 0;
                foreach ($news_feed->getResult() as $rawFeed) {
                    if ($i < $options->feedRequestLimit)
                    {
                        $feed = new Feed(self::TYPE, $rawFeed);

                        $feed->setId($rawFeed->id);
                        $feed->setText($this->trim_text($rawFeed->name, $options->textTrimLength, true));
                        $feed->setImage($rawFeed->image);
                        $feed->setLink($rawFeed->link);
                        $feed->setTimeStampTicks($rawFeed->crdate);
                        $feedArray[] = $feed;
                        $i++;
                    }
                }
            }
        }

        return $this->setCacheContentData($rawFeeds, $feedArray);
    }

    private function demandedNewsToString($demanded, $useHttps = false)
    {
        $mapped = array();
        /** @var \GeorgRinger\News\Domain\Model\News $news */
        foreach ($demanded as $news)
        {
            $img_link = '';
            if ($news->getMedia()->count() > 0)
            {
                $img_link = '/' .$news->getMedia()->current()->getOriginalResource()->getPublicUrl();
            }

            $newsItem = array(
                'id' => $news->getUid(),
                'name' => $news->getTitle(),
                'image' => $img_link,
                'link' => $this->detailPageUid,
                'body' => $news->getBodytext(),
                'teaser' => $news->getTeaser(),
                'title' => $news->getTitle(),
                'author' => $news->getAuthor(),
                'dateTime' => $news->getDatetime()->getTimestamp(),
                'crdate' => $news->getCrdate()->getTimestamp()
            );

            $mapped[] = $newsItem;
        }

        return json_encode($mapped);
    }

}
