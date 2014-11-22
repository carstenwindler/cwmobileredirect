<?php
/***************************************************************
* Copyright notice
*
* (c) 2011-2014 Carsten Windler (carsten@windler-online.de)
* All rights reserved
*
* This script is part of the TYPO3 project. The TYPO3 project is
* free software; you can redistribute it and/or modify
* it under the terms of the GNU General Public License as published by
* the Free Software Foundation; either version 2 of the License, or
* (at your option) any later version.
*
* The GNU General Public License can be found at
* http://www.gnu.org/copyleft/gpl.html.
* A copy is found in the textfile GPL.txt and important notices to the license
* from the author is found in LICENSE.txt distributed with these scripts.
*
*
* This script is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
* GNU General Public License for more details.
*
* This copyright notice MUST APPEAR in all copies of the script!
***************************************************************/

class ext_update {
	/**
	 * The extension key
	 * @var string
	 */
	protected $extKey = 'cwmobileredirect';

	/**
	 * Major Typo3 Version
	 * @var integer
	 */
	protected $majorVersion;
	
	/**
	 * Holds the current localconf
	 * @var array
	 */
	protected $extConf;
	
	/**
	 * Holds the current ext_conf_template
	 * @var array
	 */
	protected $extConfTemplateArr;
	
	/**
	 * The constructor
	 * @return ext_update
	 */
	public function __construct()
	{
		$typo3Version	= explode('.', TYPO3_version);
		$this->majorVersion = intval($typo3Version[0]);

		$this->extConf		= $this->getExtConf();
		$this->extConfTemplateArr   = $this->getExtConfTemplateArr();
	}
	
	/**
	 * Main function, returning the HTML content of the module
	 *
	 * @return string
	 */
	public function main() {
		if(!$this->checkConfigs()) {
			$out = 'Either your local configuration or the ext_conf_template.txt of this extension is empty or broken!';

			return $out;
		}

		$differences = $this->getExtConfDifferences();

		if(count($differences) > 0) {
			if (t3lib_div::_GP('do_update')) {
				if($this->updateExtConf(t3lib_div::_GP('func'))) {
					$out .= 'Your local configuration has been updated. Thanks for chosing cwmobileredirect!<br>';
					$out .= '<button onclick="location.href=\'' 
							. t3lib_div::linkThisScript(array('do_update' => '', 'func' => '')) 
							. '\'" value="" name="do_update" type="submit"><img style="vertical-align:bottom;" ' 
							. t3lib_iconWorks::skinImg($GLOBALS['BACK_PATH'], 'gfx/refresh_n.gif', 'width="18" height="16"') 
							. '>Check for more updates</button><br><br><br>';
				} else {
					$out .= 'Your local configuration has been NOT updated. This most likely means that your cwmobileredirect installation is screwed. You should consider removing and installing it from scratch.';
				}
			} else {
				$out .= 'Your local configuration can be updated:<br><br>';

				if(isset($differences['redirect_exceptions'])) {
					$out .= $differences['redirect_exceptions'];
					$out .= '<button onclick="location.href=\'' 
							. t3lib_div::linkThisScript(array('do_update' => 'update', 'func' => 'redirect_exceptions')) 
							. '\'" value="update" name="do_update" type="submit"><img style="vertical-align:bottom;" ' 
							. t3lib_iconWorks::skinImg($GLOBALS['BACK_PATH'], 'gfx/refresh_n.gif', 'width="18" height="16"') 
							. '>Update redirect_exceptions</button><br><br><br>';
				}
				
				if(isset($differences['regexp1'])) {
					$out .= $differences['regexp1'];
					$out .= 'This will OVERWRITE your existing mobile browser detection settings (First RegExp) in your local '
							 . 'configuration with the current extension default settings.<br><br>';
					$out .= '<button onclick="location.href=\'' 
							. t3lib_div::linkThisScript(array('do_update' => 'update', 'func' => 'regexp1')) 
							. '\'" value="update" name="do_update" type="submit"><img style="vertical-align:bottom;" ' 
							. t3lib_iconWorks::skinImg($GLOBALS['BACK_PATH'], 'gfx/refresh_n.gif', 'width="18" height="16"') 
							. '>Update regexp1</button><br><br><br>';
				}
				
				if(isset($differences['regexp2'])) {
					$out .= $differences['regexp2'];
					$out .= 'This will OVERWRITE your existing mobile browser detection settings (Second RegExp) in your local '
							 . 'configuration with the current extension default settings.<br><br>';
					$out .= '<button onclick="location.href=\'' 
							. t3lib_div::linkThisScript(array('do_update' => 'update', 'func' => 'regexp2')) 
							. '\'" value="update" name="do_update" type="submit"><img style="vertical-align:bottom;" ' 
							. t3lib_iconWorks::skinImg($GLOBALS['BACK_PATH'], 'gfx/refresh_n.gif', 'width="18" height="16"') 
							. '>Update regexp1</button><br><br><br>';
				}
			}
		} else {
			$out .= 'Your localconf does not need to be updated!';
		}
		
		return $out;
	}

	/**
	 * Checks how many rows are found and returns true if there are any
	 * (this function is called from the extension manager)
	 *
	 * @param  string  $what: what should be updated
	 * @return boolean
	 */
	public function access($what = 'all')
	{
		return true;
	}
	
	/**
	 * Checks whether all configs are ok 
	 * 
	 * @return boolean
	 */
	protected function checkConfigs()
	{
		if(!is_array($this->extConf) || count($this->extConf) == 0) {
			return false;
		}

		if(!is_array($this->extConfTemplateArr) || count($this->extConfTemplateArr) == 0) {
			return false;
		}

		return true;
	}
	
	/**
	 * Returns the content of ext_conf_template.txt as handy array
	 * @return boolean|array
	 */
	protected function getExtConfTemplateArr()
	{
		$extConfTemplateArr = array();
		
		// Fetch the current reg exps from the ext_conf_template.txt
		if ($this->majorVersion >= 6) {
			// Typo3 >= 6.0
			$objectManager = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('TYPO3\\CMS\\Extbase\\Object\\ObjectManager');
			$configurationUtility = $objectManager->get('TYPO3\\CMS\\Extensionmanager\\Utility\\ConfigurationUtility');
		
			$extConfTemplateArr = $configurationUtility->getDefaultConfigurationFromExtConfTemplateAsValuedArray($this->extKey);
		} else {
			// Typo3 <= 4.7
			$absPath = tx_em_Tools::getExtPath($this->extKey, 'L');
			$relPath = tx_em_Tools::typeRelPath('L') . $this->extKey . '/';

			if (t3lib_extMgm::isLoaded($this->extKey) 
				&& (@is_file($absPath . 'ext_conf_template.txt') || $this->extensionHasCacheConfiguration($absPath))
			) {
					// Load tsStyleConfig class and parse configuration template:
				$tsStyleConfig = t3lib_div::makeInstance('t3lib_tsStyleConfig');
				$tsStyleConfig->doNotSortCategoriesBeforeMakingForm = TRUE;
				$extConfTemplateArr = $tsStyleConfig->ext_initTSstyleConfig(
					t3lib_div::getUrl($absPath . 'ext_conf_template.txt'),
					$relPath,
					$absPath,
					$GLOBALS['BACK_PATH']
				);
			}
		}

		return $extConfTemplateArr;
	}
	
	/**
	 * Checks whether certain localconf whether 
	 * @return string|boolean
	 */
	protected function getExtConfDifferences()
	{
		$retArray = array();

		if($this->extConf['regexp1'] != $this->extConfTemplateArr['regexp1']['value']) {
			$retArray['regexp1'] = '<p><strong>regexp1 field differs from default!</strong><br><strong>local:</strong> ' . $this->extConf['regexp1']
					. '<br><strong>default:</strong> ' . $this->extConfTemplateArr['regexp1']['value'] . '</p>';
		}

		if($this->extConf['regexp2'] != $this->extConfTemplateArr['regexp2']['value']) {
			$retArray['regexp2'] = '<p><strong>regexp2 field differs from default!</strong><br><strong>local:</strong> ' . $this->extConf['regexp2']
					. '<br><strong>default:</strong> ' . $this->extConfTemplateArr['regexp2']['value'] . '</p>';
		}
		
		if(empty($this->extConf['redirect_exceptions'])) {
			$retArray['redirect_exceptions'] = '<p><strong>redirect_exceptions field is empty!</strong>'
					. '<br><strong>default:</strong> ' . $this->extConfTemplateArr['redirect_exceptions']['value'] . '</p>';
		}

		return $retArray;
	}
	
	/**
	 * Upate the extConf
	 * 
	 * @return boolean
	 */
	protected function updateExtConf($func) 
	{
		// Get the value from the ext_conf_template and store it in the local conf
		$this->extConf[$func] = $this->extConfTemplateArr[$func]['value'];

		// Rewrite local configuration according to Typo3 version
		if ($this->majorVersion >= 6) {
			// Typo3 >= 6.0
			$configurationManager = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(
				'TYPO3\\CMS\\Core\\Configuration\\ConfigurationManager'
				);

			$configurationManager->setLocalConfigurationValueByPath('EXT/extConf/' . $this->extKey, serialize($this->extConf));

			\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::removeCacheFiles();
		} else {
			// Typo3 <= 4.7
			$install = new t3lib_install();
			$install->allowUpdateLocalConf = 1;
			$install->updateIdentity = 'cwmobileredirect Updater';

			$lines = $install->writeToLocalconf_control();
			$install->setValueInLocalconfFile(
				$lines, 
				'$TYPO3_CONF_VARS[\'EXT\'][\'extConf\'][\'' . $this->extKey . '\']', 
				serialize($this->extConf)
				);

			$install->writeToLocalconf_control($lines);

			t3lib_extMgm::removeCacheFiles();
		}   

		return true;
	}
	
	/**
	 * Get the extension configuration
	 *
	 * @return array
	 */
	protected function getExtConf() {
		$extConf = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf'][$this->extKey]);

		if (!$extConf) {
			$extConf = array();
		}

		return $extConf;
	}
}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/cwmobileredirect/class.ext_update.php']) {
	include_once ($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/cwmobileredirect/class.ext_update.php']);
}

?>