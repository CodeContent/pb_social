<?php
if (!defined('TYPO3_MODE')) {
    die('Access denied.');
}

return [
    'ctrl' => [
        'title' => 'LLL:EXT:pb_social/Resources/Private/Language/locallang_db.xlf:tx_pbsocial_domain_model_item',
        'label' => 'type',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'cruser_id' => 'cruser_id',
        'dividers2tabs' => true,

        'hideTable' => true,
        'adminOnly' => 1,

        'versioningWS' => 2,
        'versioning_followPages' => true,

        'languageField' => 'sys_language_uid',
        'transOrigPointerField' => 'l10n_parent',
        'transOrigDiffSourceField' => 'l10n_diffsource',
        'delete' => 'deleted',
        'enablecolumns' => array(
            'disabled' => 'hidden',
            'starttime' => 'starttime',
            'endtime' => 'endtime',
        ),
        'searchFields' => 'type,item_identifier,date,result,',
        'typeicon_classes' => [
            'default' => 'pbsocial_socialfeed'
        ],

    ],

    'interface' => [
        'showRecordFieldList' => 'sys_language_uid, l10n_parent, l10n_diffsource, hidden, type, item_identifier, date, result',
    ],
    'types' => [
        '1' => ['showitem' => 'sys_language_uid;;;;1-1-1, l10n_parent, l10n_diffsource, hidden;;1, type, item_identifier, date, result, --div--;LLL:EXT:cms/locallang_ttc.xlf:tabs.access, starttime, endtime']
    ],
    'palettes' => array(
        '1' => array('showitem' => ''),
    ),
    'columns' => array(

        'sys_language_uid' => array(
            'exclude' => 1,
            'label' => 'LLL:EXT:lang/locallang_general.xlf:LGL.language',
            'config' => array(
                'type' => 'select',
                'foreign_table' => 'sys_language',
                'foreign_table_where' => 'ORDER BY sys_language.title',
                'items' => array(
                    array('LLL:EXT:lang/locallang_general.xlf:LGL.allLanguages', -1),
                    array('LLL:EXT:lang/locallang_general.xlf:LGL.default_value', 0)
                ),
            ),
        ),
        'l10n_parent' => array(
            'displayCond' => 'FIELD:sys_language_uid:>:0',
            'exclude' => 1,
            'label' => 'LLL:EXT:lang/locallang_general.xlf:LGL.l18n_parent',
            'config' => array(
                'type' => 'select',
                'items' => array(
                    array('', 0),
                ),
                'foreign_table' => 'tx_pbsocial_domain_model_item',
                'foreign_table_where' => 'AND tx_pbsocial_domain_model_item.pid=###CURRENT_PID### AND tx_pbsocial_domain_model_item.sys_language_uid IN (-1,0)',
            ),
        ),
        'l10n_diffsource' => array(
            'config' => array(
                'type' => 'passthrough',
            ),
        ),

        't3ver_label' => array(
            'label' => 'LLL:EXT:lang/locallang_general.xlf:LGL.versionLabel',
            'config' => array(
                'type' => 'input',
                'size' => 30,
                'max' => 255,
            )
        ),

        'hidden' => array(
            'exclude' => 1,
            'label' => 'LLL:EXT:lang/locallang_general.xlf:LGL.hidden',
            'config' => array(
                'type' => 'check',
            ),
        ),
        'starttime' => array(
            'exclude' => 1,
            'l10n_mode' => 'mergeIfNotBlank',
            'label' => 'LLL:EXT:lang/locallang_general.xlf:LGL.starttime',
            'config' => array(
                'type' => 'input',
                'size' => 13,
                'max' => 20,
                'eval' => 'datetime',
                'checkbox' => 0,
                'default' => 0,
                'range' => array(
                    'lower' => mktime(0, 0, 0, date('m'), date('d'), date('Y'))
                ),
            ),
        ),
        'endtime' => array(
            'exclude' => 1,
            'l10n_mode' => 'mergeIfNotBlank',
            'label' => 'LLL:EXT:lang/locallang_general.xlf:LGL.endtime',
            'config' => array(
                'type' => 'input',
                'size' => 13,
                'max' => 20,
                'eval' => 'datetime',
                'checkbox' => 0,
                'default' => 0,
                'range' => array(
                    'lower' => mktime(0, 0, 0, date('m'), date('d'), date('Y'))
                ),
            ),
        ),

        'type' => array(
            'exclude' => 1,
            'label' => 'LLL:EXT:pb_social/Resources/Private/Language/locallang_db.xlf:tx_pbsocial_domain_model_item.type',
            'config' => array(
                'type' => 'input',
                'size' => 30,
                'eval' => 'trim'
            ),
        ),
        'item_identifier' => array(
            'exclude' => 1,
            'label' => 'LLL:EXT:pb_social/Resources/Private/Language/locallang_db.xlf:tx_pbsocial_domain_model_item.item_identifier',
            'config' => array(
                'type' => 'input',
                'size' => 30,
                'eval' => 'trim'
            ),
        ),
        'date' => array(
            'exclude' => 1,
            'label' => 'LLL:EXT:pb_social/Resources/Private/Language/locallang_db.xlf:tx_pbsocial_domain_model_item.date',
            'config' => array(
                'type' => 'input',
                'size' => 10,
                'eval' => 'datetime',
                'checkbox' => 1,
                'default' => time()
            ),
        ),
        'result' => array(
            'exclude' => 1,
            'label' => 'LLL:EXT:pb_social/Resources/Private/Language/locallang_db.xlf:tx_pbsocial_domain_model_item.result',
            'config' => array(
                'type' => 'text',
                'cols' => 40,
                'rows' => 15,
                'eval' => 'trim'
            )
        ),

    )
];
