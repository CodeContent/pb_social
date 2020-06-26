<?php
namespace PlusB\PbSocial\Backend;

use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Toolbar\ClearCacheActionsHookInterface;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;


/***************************************************************
 *  Copyright notice
 *
 *  (c) 2019 Arend Maubach <am@plusb.de>, Plus B
 *
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
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


class ClearCacheCommand implements SingletonInterface, ClearCacheActionsHookInterface
{
    /**
     * Adds a menu entry to the clear cache menu to detect Solr connections.
     *
     * @param array $cacheActions Array of CacheMenuItems
     * @param array $optionValues Array of AccessConfigurations-identifiers (typically  used by userTS with options.clearCache.identifier)
     */
    public function manipulateCacheActions(&$cacheActions, &$optionValues)
    {
        if ($GLOBALS['BE_USER']->isAdmin()) {
            $uriBuilder = GeneralUtility::makeInstance(UriBuilder::class);
            $optionValues[] = 'clearPbSocialCache';
            $cacheActions[] = [
                'id' => 'clearPbSocialCache',
                'title' => 'LLL:EXT:pb_social/Resources/Private/Language/locallang.xlf:tx_pbsocial_clear_cache',
                'href' => $uriBuilder->buildUriFromRoute('ajax_pbsocial_clearCache'),
                'description' => 'LLL:EXT:pb_social/Resources/Private/Language/locallang.xlf:tx_pbsocial_clear_cache_description',
                'iconIdentifier' => 'pbsocial_socialfeed'
            ];
        }
    }



}