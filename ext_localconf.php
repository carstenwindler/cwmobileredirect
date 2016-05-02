<?php

if (!defined ("TYPO3_MODE"))     die ("Access denied.");

$TYPO3_CONF_VARS['SC_OPTIONS']['tslib/index_ts.php']['preprocessRequest'][] = \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath($_EXTKEY) . 'class.tx_cwmobileredirect.php:&tx_cwmobileredirect->firstEntryPoint';

$TYPO3_CONF_VARS['SC_OPTIONS']['tslib/class.tslib_fe.php']['configArrayPostProc']['tx_cwmobileredirect'] = \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath($_EXTKEY) . 'class.tx_cwmobileredirect.php:&tx_cwmobileredirect->secondEntryPoint';
