<?php
namespace PlusB\PbSocial\Command;

use PlusB\PbSocial\Domain\Model\Content;


/***************************************************************
 *
 *  Copyright notice
 *
 *  (c) 2014 Mikolaj Jedrzejewski <mj@plusb.de>, plus B
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

class PBSocialCommandController extends \TYPO3\CMS\Extbase\Mvc\Controller\CommandController
{
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
     * @var \PlusB\PbSocial\Domain\Repository\CredentialRepository
     * @inject
     */
    protected $credentialRepository;

    /**
     * @var \PlusB\PbSocial\Domain\Repository\ContentRepository
     * @inject
     */
    protected $contentRepository;

    /**
     * Reference to a scheduler object, just for having ->log()
     *
     * @var \TYPO3\CMS\Scheduler\Scheduler
     * @inject
     */
    protected $scheduler;



    /**
     * @var bool Verbose output
     */
    protected $verbose = false;


    /**
     * @var bool Silent output, nothing is displayed, but still log in general typo3 log file
     */
    protected $silent = false;

    /**
     * @var string $callnetwork string that contains social network constant string, default is all - or just one network
     */
    protected $callnetwork = 'all';

    /**
     * @var string $sysLogWarnings String collected for syslog
     */
    private $sysLogWarnings = "";

    /**
     * @var bool $isSyslogWarning Bool whether syslogs are to be pulled out or not
     */
    private $isSyslogWarning = false;

    /**
     * @return bool
     */
    public function isSyslogWarning()
    {
        return $this->isSyslogWarning;
    }

    /**
     * @param bool $isSyslogWarning
     */
    public function setIsSyslogWarning($isSyslogWarning)
    {
        $this->isSyslogWarning = $isSyslogWarning;
    }

    /**
     * @return string
     */
    public function getSysLogWarnings()
    {
        return $this->sysLogWarnings;
    }

    /**
     * @param string $sysLogWarnings
     */
    public function setSysLogWarnings($sysLogWarnings)
    {
        $this->sysLogWarnings .= $sysLogWarnings;
    }

    /**
     *
     */
    public function resetSysLogWarnings()
    {
        $this->sysLogWarnings = "";
        $this->setIsSyslogWarning(false);
    }


    /**
     * @return bool
     */
    protected function isVerbose()
    {
        return $this->verbose;
    }

    /**
     * @param bool $verbose
     */
    protected function setVerbose($verbose)
    {
        $this->verbose = $verbose;
    }

    /**
     * @return bool
     */
    public function isSilent()
    {
        return $this->silent;
    }

    /**
     * @param bool $silent
     */
    public function setSilent($silent)
    {
        $this->silent = $silent;
    }

    /**
     * @return string
     */
    public function getCallnetwork()
    {
        return $this->callnetwork;
    }

    /**
     * @param string $callnetwork
     */
    public function setCallnetwork($callnetwork)
    {
        $this->callnetwork = $callnetwork;
    }

    /** @var $logger \TYPO3\CMS\Core\Log\Logger */
    protected $logger;

    /**
     * @var \TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface
     * @inject
     */
    protected $configurationManager;

    /**
     * @var array
     */
    protected $typoscriptSettings = array();

    /**
     * initializing
     *
     * @param string $verbose
     * @param string $silent
     * @param string $callnetwork
     */
    private function initializeUpdateFeedDataCommand($verbose, $silent, $callnetwork) {

        $this->setTyposcriptSettings($this->configurationManager->getConfiguration(
            \TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface::CONFIGURATION_TYPE_SETTINGS
        ));

        /*
        * using --verbose to get json return values and more
        */
        $this->setVerbose($verbose);

        /*
        * using --silent to get simply no output
        */
        $this->setSilent($silent);

        /*
        * it's a radio button situation: if you have silent, you do not want to have verbose
        */
        if($this->isSilent() === true){
            $this->setVerbose(false);
        }

        $this->setCallnetwork($callnetwork);

        # Initialize logger
        $this->logger = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('TYPO3\CMS\Core\Log\LogManager')->getLogger(__CLASS__);
    }


    /**
     * @return array
     */
    public function getTyposcriptSettings()
    {
        return $this->typoscriptSettings;
    }

    /**
     * @param array $typoscriptSettings
     */
    public function setTyposcriptSettings($typoscriptSettings)
    {
        $this->typoscriptSettings = $typoscriptSettings;
    }


    /**
     * @var \TYPO3\CMS\Extbase\Service\FlexFormService
     * @inject
     */
    protected $flexformService;

    /**
     * Updates database with feeds
     * Use this in TYPO3 backend scheduler or in command line  ./your-path-to-typo3/cli_dispatch.phpsh extbase pbsocial:updatefeeddata --verbose
     *
     * @param bool $verbose Enter verbose output
     * @param bool $silent Silent mode outputs nothing, but logs still into general typo3 log file
     * @param string $callnetwork - just call one network - default is all, according to flexform settings
     * @return string message
     */
    public function updateFeedDataCommand($verbose = false, $silent = false, $callnetwork = 'all')
    {
        $this->initializeUpdateFeedDataCommand($verbose, $silent, $callnetwork);

        # Setup database connection and fetch all flexform settings #
        /**
         * @var $xmlStr Content
         */
        $xml_settings = $this->contentRepository->findFlexforms("list", "pbsocial_socialfeed")->toArray();
        $message = "";

        # Convert flexform settings into usable array structure #
        if (!empty($xml_settings)) {

            # Update feeds #
            foreach ($xml_settings as $xmlStr) {
                /* initializing procedural request */
                $flexformSettings = $this->flexformService->convertFlexFormContentToArray($xmlStr->getPiFlexform());
                $flexformSettings = $flexformSettings['settings'];

                /* starting procedural list of requests */
                if ($flexformSettings['facebookEnabled'] === '1' && ($this->getCallnetwork() === 'all' || $this->getCallnetwork() === self::TYPE_FACEBOOK)) {
                    $message .= $this->feedSyncService->syncFeed(self::TYPE_FACEBOOK, $flexformSettings, $xmlStr->getUid(),
                        $xmlStr->getPid(), $this->isVerbose());
                }
                if ($flexformSettings['instagramEnabled'] === '1' && ($this->getCallnetwork() === 'all' || $this->getCallnetwork() === self::TYPE_INSTAGRAM)) {
                    $message .= $this->feedSyncService->syncFeed(self::TYPE_INSTAGRAM, $flexformSettings, $xmlStr->getUid(),
                        $xmlStr->getPid(), $this->isVerbose());
                }
                if ($flexformSettings['linkedinEnabled'] === '1' && ($this->getCallnetwork() === 'all' || $this->getCallnetwork() === self::TYPE_LINKEDIN)) {
                    $message .= $this->feedSyncService->syncFeed(self::TYPE_LINKEDIN, $flexformSettings, $xmlStr->getUid(),
                        $xmlStr->getPid(), $this->isVerbose());
                }
                if ($flexformSettings['pinterestEnabled'] === '1' && ($this->getCallnetwork() === 'all' || $this->getCallnetwork() === self::TYPE_PINTEREST)) {
                    $message .= $this->feedSyncService->syncFeed(self::TYPE_PINTEREST, $flexformSettings, $xmlStr->getUid(),
                        $xmlStr->getPid(), $this->isVerbose());
                }
                if ($flexformSettings['twitterEnabled'] === '1' && ($this->getCallnetwork() === 'all' || $this->getCallnetwork() === self::TYPE_TWITTER)) {
                    $message .= $this->feedSyncService->syncFeed(self::TYPE_TWITTER, $flexformSettings, $xmlStr->getUid(),
                        $xmlStr->getPid(), $this->isVerbose());
                }
                if ($flexformSettings['youtubeEnabled'] === '1' && ($this->getCallnetwork() === 'all' || $this->getCallnetwork() === self::TYPE_YOUTUBE)) {
                    $message .= $this->feedSyncService->syncFeed(self::TYPE_YOUTUBE, $flexformSettings, $xmlStr->getUid(),
                        $xmlStr->getPid(), $this->isVerbose());
                }
                if ($flexformSettings['vimeoEnabled'] === '1' && ($this->getCallnetwork() === 'all' || $this->getCallnetwork() === self::TYPE_VIMEO)) {
                    $message .= $this->feedSyncService->syncFeed(self::TYPE_VIMEO, $flexformSettings, $xmlStr->getUid(),
                        $xmlStr->getPid(), $this->isVerbose());
                }
                if ($flexformSettings['tumblrEnabled'] === '1' && ($this->getCallnetwork() === 'all' || $this->getCallnetwork() === self::TYPE_TUMBLR)) {
                    $message .= $this->feedSyncService->syncFeed(self::TYPE_TUMBLR, $flexformSettings, $xmlStr->getUid(),
                        $xmlStr->getPid(), $this->isVerbose());
                }
                if ($flexformSettings['imgurEnabled'] === '1' && ($this->getCallnetwork() === 'all' || $this->getCallnetwork() === self::TYPE_IMGUR)) {
                    $message .= $this->feedSyncService->syncFeed(self::TYPE_IMGUR, $flexformSettings, $xmlStr->getUid(),
                        $xmlStr->getPid(), $this->isVerbose());
                }
                if ($flexformSettings['newsEnabled'] === '1' && ($this->getCallnetwork() === 'all' || $this->getCallnetwork() === self::TYPE_TX_NEWS)) {
                    $message .= $this->feedSyncService->syncFeed(self::TYPE_TX_NEWS, $flexformSettings, $xmlStr->getUid(),
                        $xmlStr->getPid(), $this->isVerbose());
                }

            }//foreach
        }//if
        return "\e[1;33;40m".$message ."\e[0m\n";
    }


}
