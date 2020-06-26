<?php

namespace PlusB\PbSocial\Service;

use PlusB\PbSocial\Service\Base\AbstractBaseService;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;

/***************************************************************
 *
 *  Copyright notice
 *
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

class CacheService extends AbstractBaseService
{

    const EXTKEY = 'pb_social';

    /**
     * @var \PlusB\PbSocial\Service\FeedSyncService
     * @inject
     */
    protected $feedSyncService;


    /**
     * @var \TYPO3\CMS\Core\Cache\CacheManager
     * @inject
     */
    protected $cacheManager = null;

    /**
     * @var int
     */
    protected $cacheLifetime = 0;

    /**
     * @param int $cacheLifetime
     */
    public function setCacheLifetime($cacheLifetime)
    {
        $this->cacheLifetime = intval($cacheLifetime);
    }

    /**
     * @return int
     */
    public function getCacheLifetime()
    {
        return $this->cacheLifetime;
    }



    /**
     * @var FrontendInterface $cache
     */
    private $cache;

    protected function initializeConfiguration(){
        parent::initializeConfiguration();

        //merge cache lifetime settings
        $this->setCacheLifetime(
            intval(
                $this->settings['cacheLifetime']?:$GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['pb_social_cache']['options']['defaultLifetime']?:0
            )
        );

        //get caching backend
        $this->cache = $this->cacheManager->getCache('pb_social_cache');
    }

    /**
     * Gets name of social network, returns cacheIdentifierElementsArray by its options
     *
     * @param $socialNetworkTypeString
     * @return array
     */
    private function getCacheIdentifierElementsArray($socialNetworkTypeString, $settings){
        $array = array();

        switch ($socialNetworkTypeString){
            case self::TYPE_FACEBOOK:
                $array =  array(
                    "facebook_" . $this->convertFlexformSettings($settings)->settings['facebookSearchIds'],
                    "facebook_" . $this->convertFlexformSettings($settings)->settings['facebookEdge'],
                    "facebook_" . $this->convertFlexformSettings($settings)->settings['facebookPluginKeyfieldEnabled'],
                );
                break;
            case self::TYPE_IMGUR:
                $array =  array(
                    "imgur_" . $this->convertFlexformSettings($settings)->settings['imgurTags'],
                    "imgur_" . $this->convertFlexformSettings($settings)->settings['imgurUsers']
                );
                break;
            case self::TYPE_INSTAGRAM:
                $array =  array(
                    "instagram_" . $this->convertFlexformSettings($settings)->settings['instagramSearchIds'],
                    "instagram_" . $this->convertFlexformSettings($settings)->settings['instagramHashTag'],
                    "instagram_" . $this->convertFlexformSettings($settings)->settings['instagramPostFilter']
                );
                break;
            case self::TYPE_LINKEDIN:
                $array =  array(
                    "linkedin_" . $this->convertFlexformSettings($settings)->settings['linkedinCompanyIds'],
                    "linkedin_" . $this->convertFlexformSettings($settings)->settings['linkedinFilterChoice']
                );
                break;
            case self::TYPE_PINTEREST:
                $array =  array(
                    "pinterest_" . $this->convertFlexformSettings($settings)->settings['username'],
                    "pinterest_" . $this->convertFlexformSettings($settings)->settings['boardname']
                );
                break;
            case self::TYPE_TUMBLR:
                $array =  array(
                    "tumblr_" . $this->convertFlexformSettings($settings)->settings['tumblrBlogNames']
                );
                break;
            case self::TYPE_TWITTER:
                $array =  array(
                    "twitter_" . $this->convertFlexformSettings($settings)->settings['twitterSearchFieldValues'],
                    "twitter_" . $this->convertFlexformSettings($settings)->settings['twitterProfilePosts'],
                    "twitter_" . $this->convertFlexformSettings($settings)->settings['twitterLanguage'],
                    "twitter_" . $this->convertFlexformSettings($settings)->settings['twitterGeoCode'],
                    "twitter_" . $this->convertFlexformSettings($settings)->settings['twitterHideRetweets'],
                    "twitter_" . $this->convertFlexformSettings($settings)->settings['twitterShowOnlyImages']
                );
                break;
            case self::TYPE_YOUTUBE:
                $array =  array(
                    "youtube_" . $this->convertFlexformSettings($settings)->settings['youtubeSearch'],
                    "youtube_" . $this->convertFlexformSettings($settings)->settings['youtubePlaylist'],
                    "youtube_" . $this->convertFlexformSettings($settings)->settings['youtubeChannel'],
                    "youtube_" . $this->convertFlexformSettings($settings)->settings['youtubeType'],
                    "youtube_" . $this->convertFlexformSettings($settings)->settings['youtubeLanguage'],
                    "youtube_" . $this->convertFlexformSettings($settings)->settings['youtubeOrder']
                );
                break;
            case self::TYPE_VIMEO:
                $array =  array(
                    "vimeo_" . $this->convertFlexformSettings($settings)->settings['vimeoChannel']
                );
                break;
            case self::TYPE_TX_NEWS:
                $array =  array(
                    "txnews_" . $this->convertFlexformSettings($settings)->settings['newsCategories']
                );
                break;
        }

        return $array;
    }

    /**
     * combines array of strings which are different by their configuration issues
     * - calculating a crypted string to be able to find this again in cache for FE
     *
     * @param $socialNetworkTypeString
     * @param $settings
     * @param integer $ttContentUid uid of plugin, for logging purpose - and for registering in cache identifier
     * @return string
     */
    public function calculateCacheIdentifier($socialNetworkTypeString, $settings, $ttContentUid){
        $cacheIdentifierElementsArray =  $this->getCacheIdentifierElementsArray($socialNetworkTypeString, $settings);

        array_walk($cacheIdentifierElementsArray, function (&$item, $key, $ttContentUid) {
            $item .= "_tt_content_uid". $ttContentUid ;
        }, $ttContentUid);

        return sha1(json_encode($cacheIdentifierElementsArray)); // in average json_encode is four times faster than serialize()
    }


    /**
     * getCacheContent - reads cache content by calculated cacheIdentifier
     *
     * @param $socialNetworkTypeString string
     * @param $flexformAndTyposcriptSettings array
     * @param $ttContentUid int uid of plugin, for logging purpose - and for registering in cache identifier
     * @param $ttContentPid int page uid in which plugin is located, for logging purpose, only
     * @param $results array - getting results, appending results if success
     * @return array
     */
    public function getCacheContent(
        $socialNetworkTypeString,
        $flexformAndTyposcriptSettings,
        $ttContentUid,
        $ttContentPid,
        &$results
    ){

        try {

            $cacheIdentifier = $this->calculateCacheIdentifier($socialNetworkTypeString, $flexformAndTyposcriptSettings, $ttContentUid);

            //if there is not already a cache, try to get a api sync and get a filled cache, but it only gets this requested network type
            if($this->cache->has($cacheIdentifier) === false){
                $this->feedSyncService->syncFeed(
                    $socialNetworkTypeString,
                    $flexformAndTyposcriptSettings,
                    $ttContentUid,
                    $ttContentPid,
                    $isVerbose = false
                );
            }
            //here cache exists, no matter what (or exception)

            //it reads content from cache
            if($content = $this->cache->get($cacheIdentifier)){
                $results[] = $content;
            }
        } catch (\Exception $e) {
            $this->logWarning($e->getMessage(), $ttContentUid, $ttContentPid, $socialNetworkTypeString, $e->getCode());
        }

        /**
         * results => array(1 item)
                0 => array(2 items)
                    rawFeeds => array
                    feedItems => array (of PlusB\PbSocial\Domain\Model\Feed)
                        0 => PlusB\PbSocial\Domain\Model\Feed
                        1 => PlusB\PbSocial\Domain\Model\Feed
                        2 => PlusB\PbSocial\Domain\Model\Feed
         *
         * array of return value of \PlusB\PbSocial\Adapter\SocialMediaAdapter::setCacheContentData
         */
        return $results;
    }

    /**
     * Sets given content to cache by calculated cacheIdentifier
     *
     * @param string $socialNetworkTypeString
     * @param array $settings
     * @param integer $ttContentUid uid of plugin, for logging purpose - and for registering in cache identifier
     * @param $content  'rawFeeds' => $rawFeeds, 'feedItems' => $feedArray PlusB\PbSocial\Domain\Model\Feed
     */
    public function setCacheContent(
        $socialNetworkTypeString,
        $settings,
        $ttContentUid,
        $content
    ){
        $cacheIdentifier = $this->calculateCacheIdentifier($socialNetworkTypeString, $settings, $ttContentUid);

        $this->cache->set(
            $cacheIdentifier,
            $data = $content,
            $tags = [self::EXTKEY, self::EXTKEY ."_". $socialNetworkTypeString ."_". $ttContentUid],
            $lifetime = $this->getCacheLifetime()
        );
    }
}