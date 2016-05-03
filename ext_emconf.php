<?php

/***************************************************************
 * Extension Manager/Repository config file for ext "cwmobileredirect".
 *
 * Auto generated 29-09-2014 16:57
 *
 * Manual updates:
 * Only the data in the array - everything else is removed by next
 * writing. "version" and "dependencies" must not be touched!
 ***************************************************************/

$EM_CONF[$_EXTKEY] = array (
    'title' => 'Mobile Redirect',
    'description' => 'Your all-in-one mobile device detection and redirection solution! Detects mobile browsers and' .
        'redirects to other Typo3 sites in your setup (most likely optimized for mobiles). Allows to easily switch ' .
        'back to the normal version, with Cookie support to remember the users choice. The browser detection can ' .
        'be access via TypoScript or in your own extension.',
    'category' => 'fe',
    'version' => '1.4.4',
    'state' => 'stable',
    'uploadfolder' => 0,
    'createDirs' => '',
    'clearcacheonload' => 0,
    'author' => 'Carsten Windler',
    'author_email' => 'carsten@carstenwindler.de',
    'author_company' => '',
    'constraints' =>
    array (
        'depends' =>
        array (
            'typo3' => '7.5.0-7.99.99',
        ),
        'conflicts' =>
        array (
        ),
        'suggests' =>
        array (
        ),
    ),
    'autoload' =>
        array(
            'psr-4' =>
                array(
                    'CarstenWindler\\Cwmobileredirect\\' => 'Classes',
                ),
        ),
);
