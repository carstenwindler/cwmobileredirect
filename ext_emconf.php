<?php
$EM_CONF[$_EXTKEY] = array (
  'title' => 'Mobile Redirect',
  'description' => 'Your all-in-one mobile device detection and redirection solution! Detects mobile browsers andredirects to other Typo3 sites in your setup (most likely optimized for mobiles). Allows to easily switch back to the normal version, with Cookie support to remember the users choice. The browser detection can be access via TypoScript or in your own extension.',
  'author' => 'Carsten Windler',
  'author_email' => 'carsten@carstenwindler.de',
  'author_company' => '',
  'category' => 'fe',
  'version' => '1.5.0',
  'state' => 'stable',
  'uploadfolder' => 0,
  'createDirs' => '',
  'shy' => '',
  'dependencies' => '',
  'conflicts' => '',
  'suggests' => '',
  'priority' => '',
  'module' => '',
  'internal' => '',
  'modify_tables' => '',
  'clearCacheOnLoad' => 1,
  'lockType' => '',
  'constraints' => 
  array (
    'depends' => 
    array (
      'typo3' => '7.5.0-8.99.99',
    ),
    'conflicts' => 
    array (
    ),
    'suggests' => 
    array (
    ),
  ),
  'autoload' => 
  array (
    'psr-4' => 
    array (
      'CarstenWindler\\Cwmobileredirect\\' => 'Classes',
    ),
  ),
);
