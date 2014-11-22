<?php

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2011-2014 Carsten Windler (carsten@windler-online.de)
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

/**
 * Simple user_func that uses tx_mobileredirect to determine if the
 * page should be displayed in mobile mode, regardless of the used device
 * (i.e. the Cookie and/or the GET parameters are taken into account)
 *
 * Examples:
 *
 * [userFunc = user_isMobileForced]
 *    page.config.headerComment (
Thanks to cwmobileredirect we know that you want this page to be displayed in mobile mode!
 *    )
 * [end]
 *
 * [userFunc = user_isMobileForced(Android)]
 *    page.config.headerComment (
 *       Thanks to cwmobileredirect we know that you want this page to be displayed in mobile mode,
 *       and Android is used!
 *    )
 * [end]
 *
 * [userFunc = user_isMobileForced(Safari,Android)]
 *    page.config.headerComment (
 *       Thanks to cwmobileredirect we know that hip devices are supported!
 *    )
 * [end]
 *
 * @param string $browserId - (Optional) if set, it is checked if the detected browser equals the given Id
 *                            Note: multiple ids are possible, just pass them comma-separated
 *
 * @return boolean - true mobile mode is forced by GET parameter or by Cookie
 *
 */
function user_isMobileForced($browserId = null)
{
    $forced = (tx_cwmobileredirect::getInstance()->isMobileForced() || tx_cwmobileredirect::getInstance()->isMobile());

    if($forced && !empty($browserId)) {
        if(strpos($browserId, tx_cwmobileredirect::getInstance()->getDetectedMobileBrowser()) !== false) {
            return true;
        } else {
            return false;
        }
    } else {
        return $forced;
    }
}



/**
 * Simple user_func that uses tx_mobileredirect to determine if the
 * page should be displayed in standard mode, regardless of the used device
 * (i.e. the Cookie and/or the GET parameters are taken into account)
 *
 * Examples:
 *
 * [userFunc = user_isStandardForced]
 *    page.config.headerComment (
Thanks to cwmobileredirect we know that you want this page to be displayed in mobile mode!
 *    )
 * [end]
 *
 * @return boolean - true mobile mode is forced by GET parameter or by Cookie
 *
 */
function user_isStandardForced()
{
    return tx_cwmobileredirect::getInstance()->isStandardForced();
}



/**
 * Simple user_func that uses tx_mobileredirect to determine if mobile
 * browser is used or not, or to detect if a special browser is used
 *
 * Examples:
 *
 * [userFunc = user_isMobile]
 *    page.config.headerComment (
Thanks to cwmobileredirect we know that you called this page using a mobile!
 *    )
 * [end]
 *
 * [userFunc = user_isMobile(Safari)]
 *    page.config.headerComment (
 *      Thanks to cwmobileredirect we know that you called this page using a Safari mobile!
 *    )
 * [end]
 *
 * Pls see the constants MOBILEREDIRECT_USERAGENT_* below to find out which Ids are recognized!
 *
 * @param string $browserId - (Optional) if set, it is checked if the detected browser equals the given Id
 *                               Note: multiple ids are possible, just pass them comma-separated
 *
 * @return boolean - true if current browser is detected as a mobile, false otherwise
 *
 */
function user_isMobile($browserId = null)
{
    if(!empty($browserId)) {
        if(strpos($browserId, tx_cwmobileredirect::getInstance()->getDetectedMobileBrowser()) !== FALSE) {
            return true;
        } else {
            return false;
        }
    } else {
        return tx_cwmobileredirect::getInstance()->isMobile();
    }
}



/**
 * Detect mobile device and redirect
 *
 * This class is the main part of the 'cwmobileredirect' extension.
 *
 * @author  Carsten Windler (carsten@windler-online.de)
 *
 */
class tx_cwmobileredirect
{
    /**
     * User agent constants
     */
    const MOBILEREDIRECT_USERAGENT_SAFARI       = 'Safari';
    const MOBILEREDIRECT_USERAGENT_ANDROID      = 'Android';
    const MOBILEREDIRECT_USERAGENT_OPERA        = 'Opera';
    const MOBILEREDIRECT_USERAGENT_OPERA_MINI   = 'Opera Mini';
    const MOBILEREDIRECT_USERAGENT_MSIE         = 'MSIE';
    const MOBILEREDIRECT_USERAGENT_BLACKBERRY   = 'Blackberry';
    const MOBILEREDIRECT_USERAGENT_BOLT         = 'BOLT';
    const MOBILEREDIRECT_USERAGENT_NETFRONT     = 'NetFront';

    /**
     * Cookie values
     */
    const MOBILEREDIRECT_COOKIE_STANDARD        = 'standard';
    const MOBILEREDIRECT_COOKIE_MOBILE          = 'mobile';

    /**
     * The extension key
     * @var string
     */
    protected $extKey                   = 'cwmobileredirect';

    /**
     * Instance
     * @var tx_cwmobileredirect
     */
    protected static $_instance         = null;

    /**
     * The configuration array
     * @var array
     */
    protected $_conf                    = null;

    /**
     * Debug log collector
     * @var array
     */
    protected $_debugLogArray           = null;

    /**
     * The requests URL
     * @var string
     */
    protected $selfUrl                  = null;

    /**
     * The requested params
     * @var string
     */
    protected $requestParams            = null;

    /**
     * Protocol (http/https)
     * @var string
     */
    protected $protocol                 = '';

    /**
     * HTTP status to use for the redirect
     * @var string
     */
    protected $httpStatus               = '';

    /**
     * Whether mobile is used or not
     * @var boolean
     */
    protected $isMobileStatus           = null;

    /**
     * Stores the detected browser (if detection is active) or false
     * @var string|boolean
     */
    protected $detectedMobileBrowser    = null;

    /**
     * An array with the user agent (key) and names (value) of all supported browsers
     * (known by this extension ;-)
     * @var array
     */
    protected $knownMobileBrowsersArr   = array(
        self::MOBILEREDIRECT_USERAGENT_ANDROID       => 'Android',
        self::MOBILEREDIRECT_USERAGENT_SAFARI        => 'Safari Mobile',
        self::MOBILEREDIRECT_USERAGENT_OPERA         => 'Opera Mobile',
        self::MOBILEREDIRECT_USERAGENT_OPERA_MINI    => 'Opera Mini',
        self::MOBILEREDIRECT_USERAGENT_MSIE          => 'Internet Explorer Mobile',
        self::MOBILEREDIRECT_USERAGENT_BLACKBERRY    => 'Blackberry',
        self::MOBILEREDIRECT_USERAGENT_BOLT          => 'BOLT',
        self::MOBILEREDIRECT_USERAGENT_NETFRONT      => 'NetFront'
    );



    /**
     * Returns instance of this model
     *
     * @return tx_cwmobileredirect
     */
    public static function getInstance()
    {
        if (!isset(self::$_instance)) {
            $c = __CLASS__;
            self::$_instance = new $c;
        }

        return self::$_instance;
    }



    /**
     * Constructor
     *
     * @return tx_cwmobileredirect
     */
    public function __construct()
    {
        global $TYPO3_CONF_VARS;

        self::$_instance    = $this;

        if(isset($TYPO3_CONF_VARS['EXT']['extConf'][$this->extKey])) {
            $this->_conf = unserialize($TYPO3_CONF_VARS['EXT']['extConf'][$this->extKey]);
        }

        // @TODO error_log path & file configurable

        if(!$this->_conf) {
            $this->debugLog('Configuration array not loaded!');

            // we need this to enable debug logging into the header comment!
            $this->_conf['use_typoscript'] = 1;
        }

        $this->selfUrl = $this->getSelfUrl();

        // Configuration check, if debugging is activated
        if($this->_conf['debug']) {
            // try to create log file, if not existing
            if(file_exists($this->_conf['error_log'])) {
                touch($this->_conf['error_log']);
            }

            // check if we can use the log file
            if(!is_writable($this->_conf['error_log']))
            {
                $this->debugLog('error_log file given, but not writable');

                $this->_conf['error_log'] = null;
            }

            // @TODO configuration sanitation
        }

        $this->debugLog(print_r($this->_conf,1));

        $this->debugLog($this->extKey . ' loaded successfully');
    }



    /**
     * Destructor
     *
	 * @return void
     */
    public function __destruct()
    {
        // Write the debug log before destruction
        $this->writeDebugLogArray();
    }



    /**
     * First entry point - is always called by preprocessRequest hook to check usage of Typo Script
     *
     * @return void
     *
     */
    public function firstEntryPoint()
    {
        global $TYPO3_CONF_VARS;

        $this->debugLog('First entry point called');

        // Check if TypoScript usage is inactive
        // If debugging is active, do not unset the second entry point
        // because we need it for the logging
        if(empty($this->_conf['use_typoscript']) && empty($this->_conf['debug'])) {
            // Remove hook to second entry point because we don't want to parse TS
            unset($TYPO3_CONF_VARS['SC_OPTIONS']['tslib/class.tslib_fe.php']['configArrayPostProc']['tx_mobileredirect']);
            $this->checkRedirect();
        }

        // if active, do nothing here - the action continues in secondEntryPoint then!
    }



    /**
     * Second entry point - is called by configArrayPostProc hook if TypoScript usage is active
     *
     * We merge the TypoScript setup with the configuration array here
     *
     * @param array  $params - the params from the configArrayPostProc hook
     * @param object $ref    - a reference to the parent object
     *
     * @return void
     *
     */
    public function secondEntryPoint(&$params, &$ref)
    {
        // Only log this if typoscript usage is activated
        // otherwise this entry point is only called because debugging is enabled
        if(!empty($this->_conf['use_typoscript'])) {
            $this->debugLog('Second entry point called');
        }

        // Merge TS configuration with other configuration, if available
        if(isset($params['config']['tx_cwmobileredirect.'])) {
            $this->_conf = array_merge($this->_conf, $params['config']['tx_cwmobileredirect.']);
        }

        $this->checkRedirect();
    }



    /**
     * Check if redirect conditions apply
     *
     * @return void
     *
     */
    public function checkRedirect()
    {
        // Don't do anything in this case
        if(!$this->isMobileUrlRequested() &&
            !$this->isStandardUrlRequested()
        ) {
            $this->debugLog('Neither mobile nor standard URL requested');

            return;
        }

        $this->setHttpStatus();

        // don't redirect in case of configured exceptions
		// e.g. (rest\/news|events)|typo3conf
        if (!empty($this->_conf['redirect_exceptions']) &&
			preg_match('/'.$this->_conf['redirect_exceptions'].'/', t3lib_div::getIndpEnv('REQUEST_URI')))
        {
            return;
        }

        // check if mobile version is forced
        if($this->isMobileForced()) {
            $this->debugLog('Mobile version forced');

            $this->setExtensionCookie(self::MOBILEREDIRECT_COOKIE_MOBILE);

            // Check if we need to redirect to the mobile page
            if(!$this->isMobileUrlRequested()) {
                $this->redirectToMobileUrl();
            }

            return;
        }

        // check if standard version is forced
        if($this->isStandardForced()) {
            $this->debugLog('Standard version forced');

            $this->setExtensionCookie(self::MOBILEREDIRECT_COOKIE_STANDARD);

            // Check if we need to redirect to the standard page
            if(!$this->isStandardUrlRequested()) {
                $this->redirectToStandardUrl();
            }

            return;
        }

        // end here if mobile detection disabled or mobile URL is already used
        if(!$this->_conf['detection_enabled'] || $this->isMobileUrlRequested()) {
            $this->debugLog('Mobile detection disabled or mobile URL already used');

            return;
        }

        // here the real detection begins
        if($this->detectMobile() && $this->_conf['redirection_enabled']) {
            $this->redirectToMobileUrl(false);
        }

        return;
    }



    /**
     * Redirect to mobile URL
     *
     * @param boolean $addParam - If true, is_mobile_name will be added to mobile_url
     *
     * @return void
     */
    public function redirectToMobileUrl($addParam = true)
    {
        if($addParam) {
            $this->redirectTo($this->_conf['mobile_url'], $this->_conf['is_mobile_name']);
        } else {
            $this->redirectTo($this->_conf['mobile_url']);
        }
    }



    /**
     * Redirect to standard URL
     *
     * @param boolean $addParam - If true, no_mobile_name will be added to standard_url
     *
     * @return void
     */
    public function redirectToStandardUrl($addParam = true)
    {
        if($addParam) {
            $this->redirectTo($this->_conf['standard_url'], $this->_conf['no_mobile_name']);
        } else {
            $this->redirectTo($this->_conf['standard_url']);
        }
    }


	/**
	 * Sets the header location to redirect to given URL and exits directly afterwards
	 *
	 * @param string $url      - The URL to redirect to
	 * @param bool   $addParam - If set, this param will be added (e.g. www.url.com?paramName)
	 *
	 * @return void
	 */
    protected function redirectTo($url, $addParam = false)
    {
        // add =1 to param if needed to solve problems with RealUrl and pageHandling
        $urlParam = '';

        // maintain requested URI, if available and configured
        if(!empty($this->_conf['maintain_url']) && !empty($this->requestParams)) {
            $url .= $this->requestParams;
        }

        // Add params
        if($addParam) {
            if(!is_array($addParam)) {
                $addParam = array($addParam);
            }

            foreach($addParam as $param) {
                // check if param is already given, skip if yes
                if(strpos($url, $param) !== FALSE) {
                    continue;
                }

                // is it the first parameter?
                if(strpos($url, "?") !== FALSE) {
                    $urlParam .= "&";
                } else {
                    $urlParam .= "?";
                }

                $urlParam .= $param;

                // add =1 to param if needed to solve problems with RealUrl and pageHandling
                if(!empty($this->_conf['add_value_to_params'])) {
                    $urlParam .= '=1';
                }
            }
        }

        if(strpos($url, "/") === FALSE) {
            $url .= "/";
        }

        $this->debugLog('Redirecting to ' . $url);

        $this->writeDebugLogArray();

        t3lib_utility_Http::redirect($this->protocol . $url . $urlParam, $this->_conf['httpStatus']);
    }



    /**
     * Set the HTTP status used for redirects
     *
     * @return void
     */
    protected function setHttpStatus()
    {
        // set default HTTP Status code, if not defined
        if ('' == $this->_conf['httpStatus'] || !defined('t3lib_utility_Http::'. $this->_conf['httpStatus'])) {
            $this->_conf['httpStatus'] = t3lib_utility_Http::HTTP_STATUS_303;
        } else {
            $this->_conf['httpStatus'] = constant('t3lib_utility_Http::'. $this->_conf['httpStatus']);
        }

        $this->debugLog('Setting HTTP status', array('http_status' => $this->_conf['httpStatus']));
    }



    /**
     * Set the extension cookie
     *
     * @param string $cookieValue - The cookie value to be set
     *
     * @return boolean
     */
    protected function setExtensionCookie($cookieValue)
    {
        if($this->_conf['use_cookie']) {
            $this->debugLog('Setting cookie', array('cookie_value' => $cookieValue));

            return setcookie($this->_conf['cookie_name'], $cookieValue, time()+$this->_conf['cookie_lifetime'], "/");
        } else {
            return false;
        }
    }



    /**
     * Determine if the requested URL is the mobile one
     *
     * @return boolean
     */
    public function isMobileUrlRequested()
    {
        return (strpos($this->selfUrl, $this->_conf['mobile_url']) === 0);
    }



    /**
     * Determine if the requested URL is the standard one
     *
     * @return boolean
     */
    public function isStandardUrlRequested()
    {
        return (strpos($this->selfUrl, $this->_conf['standard_url']) === 0);
    }



    /**
     * Determine if the standard mode is forced
     * (checks Cookie and GET params)
     *
     * @return boolean - true if standard mode is forced, false otherwise
     *
     */
    public function isStandardForced()
    {
        $this->debugLog("--------------- isStandardForced BEGIN  ----------------------");
        $this->debugLog(print_r($_COOKIE,1));
        $this->debugLog(print_r($_GET,1));
        $this->debugLog("--------------- isStandardForced END ----------------------");

        return ((isset($_COOKIE[$this->_conf['cookie_name']]) && $_COOKIE[$this->_conf['cookie_name']] == self::MOBILEREDIRECT_COOKIE_STANDARD && !isset($_GET[$this->_conf['is_mobile_name']])) ||
            (!empty($this->_conf['no_mobile_name']) && isset($_GET[$this->_conf['no_mobile_name']])))
            ? true
            : false;
    }



    /**
     * Determine if the mobile mode is forced
     * (checks Cookie and GET params)
     *
     * @return boolean - true if mobile mode is forced, false otherwise
     */
    public function isMobileForced()
    {
        $this->debugLog("--------------- isMobileForced BEGIN  ----------------------");
        $this->debugLog(print_r($_COOKIE,1));
        $this->debugLog(print_r($_GET,1));
        $this->debugLog("--------------- isMobileForced END ----------------------");

        return ((isset($_COOKIE[$this->_conf['cookie_name']]) && $_COOKIE[$this->_conf['cookie_name']] == self::MOBILEREDIRECT_COOKIE_MOBILE && !isset($_GET[$this->_conf['no_mobile_name']])) ||
            (!empty($this->_conf['is_mobile_name']) && isset($_GET[$this->_conf['is_mobile_name']])))
            ? true
            : false;
    }



    /**
     * Retrieve the requested URI
     *
     * @param boolean $prependProtocol - If true, the used protocol is added
     *
     * @return string
     */
    private function getSelfUrl($prependProtocol = false)
    {
        if(!isset($_SERVER['REQUEST_URI'])) {
            $url = $_SERVER['PHP_SELF'];
        } else {
            $url = $_SERVER['REQUEST_URI'];
        }

        // Get the requested params for direct forwarding
        $this->getRequestedParams();

        // store used protocol for later use
        $s              = empty($_SERVER["HTTPS"]) ? '' : ($_SERVER["HTTPS"] == "on") ? "s" : "";
        $this->protocol = substr(strtolower($_SERVER["SERVER_PROTOCOL"]), 0, strpos(strtolower($_SERVER["SERVER_PROTOCOL"]), "/")) . $s . "://";

        if($prependProtocol) {
            $returnValue = $this->protocol . $_SERVER['HTTP_HOST'] . $url;
        } else {
            $returnValue = $_SERVER['HTTP_HOST'] . $url;
        }

        $this->debugLog('Getting Self Url', array('self_url' => $returnValue));

        return $returnValue;
    }



    /**
     * Parse useragent to detect mobile
     *
     * @param string $useragent - (optional) string to parse, if not given HTTP_USER_AGENT will be used
     *
     * @return boolean - true if mobile detect, false otherwise
     *
     */
    protected function detectMobile($useragent = NULL)
    {
        // Regular expressions for mobile detection by
        // http://detectmobilebrowser.com/
        // Thanks a lot!

        if(!$useragent) {
            $useragent = t3lib_div::getIndpEnv('HTTP_USER_AGENT');
        }

        // Run detection - if true, store status
        $rx1 = '/'. $this->_conf['regexp1'] .'/i';
        $rx2 = '/'. $this->_conf['regexp2'] .'/i';

        if((preg_match($rx1, $useragent) || preg_match($rx2, substr($useragent, 0, 4))))  {
            $this->debugLog('Mobile device detected', 0, array('useragent' => $useragent));

            $this->setIsMobile(true);

            return true;
        } else {
            $this->debugLog('No mobile device detected');

            $this->setIsMobile(false);

            return false;
        }
    }



    /**
     * Try to detect the used browser
     *
     * The result is also stored in $this->detectedBrowser
     *
     * @param string $useragent - (optional) string to parse, if not given HTTP_USER_AGENT will be used
     *
     * @return string|boolean - The browser name or false
     */
    protected function detectMobileBrowser($useragent = NULL)
    {
        if(!$useragent) {
            $useragent = t3lib_div::getIndpEnv('HTTP_USER_AGENT');
        }

        // go through the array of known mobile browsers and check
        // if the current useragent is recognized
        foreach($this->knownMobileBrowsersArr as $browserId => $browserName) {
            if(stripos($useragent, $browserId) !== FALSE) {
                $this->setDetectedMobileBrowser($browserId);

                return $browserId;
            }
        }

        return false;
    }



    /**
     * Returns the detected browser name
     *
     * @return string|boolean - The detected browser name or false
     */
    public function getDetectedMobileBrowserName()
    {
        // Check if there is a name available for the detected user agent
        if(isset($this->knownMobileBrowsersArr[$this->getDetectedMobileBrowser()]))  {
            return $this->knownMobileBrowsersArr[$this->getDetectedMobileBrowser()];
        }

        return false;
    }



    /**
     * Returns the detected browser (mainly just a part of the user agent)
     *
     * @see Constants of this class
     *
     * @return string|boolean - The detected browser or false
     */
    public function getDetectedMobileBrowser()
    {
        // Run detection once, if not done already
        if($this->detectedMobileBrowser === NULL) {
            $this->detectMobileBrowser();
        }

        return $this->detectedMobileBrowser;
    }



    /**
     * Setter for $detectedMobileBrowser
     *
     * @param string $detectedMobileBrowser - Detected mobile browswer
     *
     * @return void
     */
    protected function setDetectedMobileBrowser($detectedMobileBrowser)
    {
        $this->detectedMobileBrowser = $detectedMobileBrowser;
    }



    /**
     * Setter for $isMobileStatus
     *
     * @param boolean $isMobile - the mobile status
     *
     * @return void
     */
    protected function setIsMobile($isMobile)
    {
        $this->isMobileStatus = $isMobile;
    }



    /**
     * Getter for $isMobile
     *
     * Calls $this->detectMobile(), if not done already
     *
     * @return boolean - true if current browser was detected as mobile, otherwise false
     */
    public function isMobile()
    {
        if($this->isMobileStatus === null) {
            $this->detectMobile();
        }

        return $this->isMobileStatus;
    }



    /**
     * Debug Logging
     *
     * depends on debug-Setting in Configuration
     *
     * @param string 		$messageString - The message
     * @param array|boolean $dataVar       - An array to collect messages in
     *
     * @return void
     */
    protected function debugLog($messageString, $dataVar = false)
    {
        // debugging activated?
        if($this->_conf && empty($this->_conf['debug'])) {
            return;
        }

        // yes, collect message
        if(is_array($dataVar)) {
            $tempArray = array();

            foreach($dataVar as $key => $value) {
                $tempArray[] = $key . ' => ' . $value;
            }

            $messageString .= ' ( ' . implode(', ', $tempArray) . ' )';
        }

        // store this now, write it later (see writeDebugLogArray)
        $this->_debugLogArray[] = $messageString;

        // classic error log
        $logString = $this->extKey . ': ' . $messageString;

        if(!empty($this->_conf['error_log'])) {
            error_log($logString . "\n", 3, $this->_conf['error_log']);
        } else {
            error_log($logString);
        }
    }



    /**
     * Write the cumulated debug log into the header comment
     *
     * @return void
     */
    protected function writeDebugLogArray()
    {
        // Anything to log?
        if(!isset($this->_debugLogArray) || count($this->_debugLogArray) == 0) {
            return;
        }

        $log = $this->extKey . ' Debug log:' . "\n\r" . implode(",\r", $this->_debugLogArray);

        // write all log comments into the header
        $GLOBALS['TSFE']->config['config']['headerComment'] = $log;

        // free some memory
        $this->_debugLogArray = array();
    }



    /**
     * Get requested params (if needed for keeping the requested URL)
     *
     * @return void
     */
    protected function getRequestedParams()
    {
        $queryString = (!empty($_SERVER['QUERY_STRING'])) ? $_SERVER['QUERY_STRING'] : http_build_query($_GET);

        // First guess (Apache only, no IIS)
        if(!empty($_SERVER['REQUEST_URI'])) {
            $this->requestParams = $_SERVER['REQUEST_URI'];
            // I hope this is working, should work on IIS
        } else if(!empty($_SERVER['REDIRECT_SCRIPT_URL'])) {
            $this->requestParams = $_SERVER['REDIRECT_SCRIPT_URL'] . $queryString;
            // Fallback
        } else {
            $this->requestParams = $_SERVER['PHP_SELF'] . $queryString;
        }

        $this->debugLog("----------- SERVER BEGIN -----------");
        $this->debugLog(print_r($_SERVER,1));
        $this->debugLog("----------- SERVER END -----------");

        $this->debugLog("Requested params: " . $this->requestParams);
    }
}