<?php

namespace PlusB\PbSocial\Service;

use TYPO3\CMS\Core\Log\Logger;
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\Utility\GeneralUtility;


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

trait LogTrait
{
    /**
     * @var Logger $logger instance
     */
    protected $logger;

    /**
     * @var string $extkey Extension key for loggin information
     */
    private $extkey = 'pb_social';

    private function initializeTrait()
    {
        /** @var $logger Logger */
        $this->logger = GeneralUtility::makeInstance(LogManager::class)->getLogger(__CLASS__);

    }

    /**
     * @param string $message
     * @param integer $ttContentUid actual plugin uid
     * @param integer $ttContentPid actual uid of page, plugin is located
     * @param string $type Name of social media network
     * @param integer $locationInCode timestamp to find in code
     * @return string
     */
    private function initializeMessage($message, $ttContentUid, $ttContentPid, $type, $locationInCode){
        return $this->extkey . " - flexform $ttContentUid on page $ttContentPid tab ".$type. ": $locationInCode " . strval($message);
    }

    /**
     * @param string $message
     * @param integer $ttContentUid actual plugin uid
     * @param integer $ttContentPid actual uid of page, plugin is located
     * @param string $type Name of social media network
     * @param integer $locationInCode timestamp to find in code
     * @param integer $sysLogThisPlugin
     * @return string message
     */
    public function logError($message, $ttContentUid, $ttContentPid, $type, $locationInCode, $sysLogThisPlugin)
    {
        $this->initializeTrait();
        //write log to sys_log
        if(isset($GLOBALS["BE_USER"]) && $sysLogThisPlugin === 1){
            $GLOBALS['BE_USER']->writelog(
                $syslog_type = 4, $syslog_action = 0, $syslog_error = 1, $syslog_details_nr = $locationInCode,
                $syslog_details = $this->initializeMessage($message, $ttContentUid, $ttContentPid, $type, $locationInCode),
                $syslog_data = []);
        }
        //write log to file according to log level
        $this->logger->error($return = $this->initializeMessage($message, $ttContentUid, $ttContentPid, $type, $locationInCode));
        return $return;
    }

    /**
     * @param string $message
     * @param integer $ttContentUid actual plugin uid
     * @param integer $ttContentPid actual uid of page, plugin is located
     * @param string $type Name of social media network
     * @param integer $locationInCode timestamp to find in code
     * @return string message
     */
    public function logWarning($message, $ttContentUid, $ttContentPid, $type, $locationInCode)
    {
        $this->initializeTrait();
        //write log to file according to log level
        $this->logger->warning($return = $this->initializeMessage($message, $ttContentUid, $ttContentPid, $type, $locationInCode));
        return $return;
    }



    /**
     * @param string $message
     * @param integer $ttContentUid actual plugin uid
     * @param integer $ttContentPid actual uid of page, plugin is located
     * @param string $type Name of social media network
     * @param integer $locationInCode timestamp to find in code
     * @return string message
     */
    public function logInfo($message, $ttContentUid, $ttContentPid, $type, $locationInCode)
    {
        $this->initializeTrait();
        //write log to file according to log level
        $this->logger->info($return = $this->initializeMessage($return = $message, $ttContentUid, $ttContentPid, $type, $locationInCode));
        return $return;
    }






}