<?php
if (!defined('TYPO3_MODE')) {
    die('Access denied.');
}


call_user_func(function () {

        \TYPO3\CMS\Extbase\Utility\ExtensionUtility::configurePlugin(
            'PlusB.PbSocial',
            'Socialfeed',
            array(
                'Item' => 'showSocialFeed',
            ),
            // non-cacheable actions
            array(
                'Item' => 'showSocialFeed',
            )
        );

        if(TYPO3_MODE === 'BE') {
            // Constants
            \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTypoScript('pb_social','constants',' <INCLUDE_TYPOSCRIPT: source="FILE:EXT:pb_social/Configuration/TypoScript/constants.txt">');
            // Setup
            \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTypoScript('pb_social','setup',' <INCLUDE_TYPOSCRIPT: source="FILE:EXT:pb_social/Configuration/TypoScript/setup.txt">');

            $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['extbase']['commandControllers'][] = 'PlusB\\PbSocial\\Command\\PBSocialCommandController';

            // Include new content elements to modWizards
            \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addPageTSConfig(
                '<INCLUDE_TYPOSCRIPT: source="FILE:EXT:pb_social/Configuration/PageTSconfig/PbSocialSocialfeed.ts">'
            );

            $iconRegistry = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\Imaging\IconRegistry::class);
            $iconRegistry->registerIcon(
                    'pbsocial_socialfeed',
                    \TYPO3\CMS\Core\Imaging\IconProvider\SvgIconProvider::class,
                    ['source' => 'EXT:pb_social/Resources/Public/Icons/Extension.svg']
                );
        }

    /**
     * Setting up logging
     */
        $date = new \DateTime();
        $week = $date->format("W");
        $year = $date->format("Y");

        /*
         * input $GLOBALS['TYPO3_CONF_VARS']['SYS']['systemLogLevel']
         * return $loglevel (Type: one of integer values of \Log\LogLevel from ERROR to INFO) or WARNING as default.

         normalize LocalConfiguration ['SYS']['systemLogLevel']
            0: info
            1: notice
            2: warning
            3: error
            4: fatal error

        to \Log\LogLevel
            EMERGENCY = 0;
            ALERT = 1;
            CRITICAL = 2;
            ERROR = 3;
            WARNING = 4;
            NOTICE = 5;
            INFO = 6;
            DEBUG = 7;

        If "2: warning" is set in LocalConfiguration, filewriter is defined for {DEBUG(7)-1-warning(2)=4} WARNING = 4 and worse.
        If "0: info" is set, filewriter pours out {DEBUG(7)-1-warning(0)=6} INFO = 6 and worse.

        If "4: fatal error" is set, filewriter is defined for {DEBUG(7)-1-warning(4)=2} CRITICAL = 2 and worse.
         */
        $loglevel = call_user_func(function ($systemLoglevel) {
            $systemLoglevel = $systemLoglevel!==""?$systemLoglevel:2;
            $minimumLogLevel = \TYPO3\CMS\Core\Log\LogLevel::DEBUG - 1 - $systemLoglevel; //{DEBUG(7)-1-warning(2) = 4}
                return
                    TYPO3\CMS\Core\Utility\MathUtility::isIntegerInRange($minimumLogLevel,\TYPO3\CMS\Core\Log\LogLevel::EMERGENCY,\TYPO3\CMS\Core\Log\LogLevel::DEBUG)
                        /* validating it in range of allowed integers */
                        ?/*return ERROR to INFO*/ $minimumLogLevel: /* or default in case of trouble */ \TYPO3\CMS\Core\Log\LogLevel::WARNING;
            }, /*input */ $GLOBALS['TYPO3_CONF_VARS']['SYS']['systemLogLevel']);

        /*
         * and using validated loglevel for writer configuration
         */
        $GLOBALS['TYPO3_CONF_VARS']['LOG']['PlusB']['PbSocial']['writerConfiguration'] = array(
            // configuration for level log entries
            $loglevel => array(
                // add a FileWriter
                'TYPO3\\CMS\\Core\\Log\\Writer\\FileWriter' => array(
                    // configuration for the writer
                    'logFile' => "typo3temp/var/logs/typo3_pb_social_$year-$week.log"
                )
            )
        );

    /**
     * register cache
     */
    // Register cache frontend for proxy class generation
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['pb_social_cache'] = array(
        'groups' => array(
            'system'
        ),
        'options' => array(
            'defaultLifetime' => 3600,
        )
    );

    // register Clear Cache Menu hook
    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['additionalBackendItems']['cacheActions']['clearPbSocialCache'] = \PlusB\PbSocial\Backend\ClearCacheCommand::class;
});
