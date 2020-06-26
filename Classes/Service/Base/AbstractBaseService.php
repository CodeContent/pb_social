<?php

namespace PlusB\PbSocial\Service\Base;

use PlusB\PbSocial\Service\LogTrait;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\HttpUtility;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;

/***************************************************************
 *
 *  Copyright notice
 *
 *  (c) 2018 Arend Maubach <am@plusb.de>, plus B
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

abstract class AbstractBaseService implements SingletonInterface
{
    use LogTrait;

    const EXTKEY = 'pb_social';

    const TYPE_FACEBOOK = 'facebook';
    const TYPE_INSTAGRAM = 'instagram';
    const TYPE_LINKEDIN = 'linkedin';
    const TYPE_PINTEREST = 'pinterest';
    const TYPE_TWITTER = 'twitter';
    const TYPE_YOUTUBE = 'youtube';
    const TYPE_VIMEO = 'vimeo';
    const TYPE_TUMBLR = 'tumblr';
    const TYPE_IMGUR = 'imgur';
    const TYPE_TX_NEWS = 'tx_news';

    /**
     * @var \TYPO3\CMS\Extbase\Mvc\Controller\CommandController
     * @inject
     */
    protected $commandController;

    /**
     * @var \TYPO3\CMS\Core\Log\LogManagerInterface
     * @inject
     */
    protected $logManager;

    /**
     * @var \TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface
     * @inject
     */
    protected $configurationManager;

    /**
     * Plugin typoscript settings
     *
     * @var array
     */
    protected $settings;

    /**
     * Typoscript extension configuration
     *
     * @var array
     */
    protected $tsConfig;


    /**
     * Extension Configuration from localconf
     *
     * @var array
     */
    protected $extConf;


    public function initializeObject()
    {
        $this->initializeConfiguration();
    }

    protected function initializeConfiguration()
    {
        $configFull = $this->configurationManager->getConfiguration(
            ConfigurationManagerInterface::CONFIGURATION_TYPE_FULL_TYPOSCRIPT
        );

        $this->tsConfig = $configFull['plugin.']['tx_pbsocial.'];
        $this->settings = $this->tsConfig['settings.'];

        $this->extConf = @unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf'][self::EXTKEY]);
    }

    /**
     * Takes settings and returns options
     * 2014 Mikolaj Jedrzejewski <mj@plusb.de>, plus B
     *
     * @param array $settings
     * @return object
     */
    public function convertFlexformSettings($settings)
    {
        $options = (object)array();

        $options->twitterHideRetweets = empty($settings['twitterHideRetweets']) ? false : ($settings['twitterHideRetweets'] == '1' ? true : false);
        $options->twitterShowOnlyImages = empty($settings['twitterShowOnlyImages']) ? false : ($settings['twitterShowOnlyImages'] == '1' ? true : false);
        $options->twitterHTTPS = empty($settings['twitterHTTPS']) ? false : ($settings['twitterHTTPS'] == '1' ? true : false);
        $options->tumblrShowOnlyImages = empty($settings['tumblrShowOnlyImages']) ? false : ($settings['tumblrShowOnlyImages'] == '1' ? true : false);

        $options->feedRequestLimit = intval(empty($settings['feedRequestLimit']) ? '10' : $settings['feedRequestLimit']);
        $refreshTimeInMin = intval(empty($settings['refreshTimeInMin']) ? '10' : $settings['refreshTimeInMin']);
        if ($refreshTimeInMin == 0) {
            $refreshTimeInMin = 10;
        } //reset to 10 if intval() cant convert
        $options->refreshTimeInMin = $refreshTimeInMin;

        $options->settings = $settings;
        $options->onlyWithPicture = $settings['onlyWithPicture'] === '1' ? true : false;
        $options->textTrimLength = intval($settings['textTrimLength']) > 0 ? intval($settings['textTrimLength']) : 130;
        $options->feedRequestLimit = intval(empty($settings['feedRequestLimit']) ? 10 : $settings['feedRequestLimit']);

        $options->devMod = $this->extConf['socialfeed.']['devmod']; //enable devmode: database cache will refresh every pageload
        return $options;
    }

    
    /**
     * @param $pid
     * @return string
     */
    protected function buildUriInBackendContext($pid){
        $domain = BackendUtility::firstDomainRecord(BackendUtility::BEgetRootLine($pid));
        $pageRecord = BackendUtility::getRecord('pages', $pid);
        $scheme = is_array($pageRecord) && isset($pageRecord['url_scheme']) && $pageRecord['url_scheme'] == HttpUtility::SCHEME_HTTPS ? 'https' : 'http';
        $siteUrl = $domain ? $scheme . '://' . $domain . '/' : GeneralUtility::getIndpEnv('TYPO3_SITE_URL');

        //TODO: REAL-URL like handling
        if(isset($pageRecord['tx_realurl_pathsegment'])){
            $url = $siteUrl.$pageRecord['tx_realurl_pathsegment'];
        }else{
            $url = $siteUrl.'index.php?id='.$pid;
        }
        return $url;
    }


    
}