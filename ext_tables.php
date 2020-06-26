<?php
if (!defined('TYPO3_MODE')) {
    die('Access denied.');
}



\TYPO3\CMS\Extbase\Utility\ExtensionUtility::registerPlugin(
    'PlusB.PbSocial',
    'Socialfeed',
    'SocialFeed',
    'EXT:pb_social/Resources/Public/Icons/Extension.svg'
);

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addStaticFile('pb_social', 'Configuration/TypoScript', 'Social Media Stream');

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addLLrefForTCAdescr('tx_pbsocial_domain_model_item', 'EXT:pb_social/Resources/Private/Language/locallang_csh_tx_pbsocial_domain_model_item.xlf');
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::allowTableOnStandardPages('tx_pbsocial_domain_model_item');


\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addLLrefForTCAdescr('tx_pbsocial_domain_model_credential', 'EXT:pb_social/Resources/Private/Language/locallang_csh_tx_pbsocial_domain_model_credential.xlf');
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::allowTableOnStandardPages('tx_pbsocial_domain_model_credential');

$pluginSignature_feed = 'pbsocial_socialfeed';

$TCA['tt_content']['types']['list']['subtypes_excludelist'][$pluginSignature_feed] = 'layout,select_key,pages,recursive';
$TCA['tt_content']['types']['list']['subtypes_addlist'][$pluginSignature_feed] = 'pi_flexform';
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addPiFlexFormValue($pluginSignature_feed, 'FILE:EXT:pb_social/Configuration/FlexForms/socialfeed.xml');
