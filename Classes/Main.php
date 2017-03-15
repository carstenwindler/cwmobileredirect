<?php

namespace CarstenWindler\Cwmobileredirect;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2011-2016 Carsten Windler (carsten@carstenwindler.de)
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

use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\HttpUtility;

/**
 * Detect mobile device and redirect
 *
 * This class is the main part of the 'cwmobileredirect' extension.
 *
 * @author  Carsten Windler (carsten@windler-online.de)
 *
 */
class Main
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
     * @var Main
     */
    protected static $instance         = null;

    /**
     * The configuration array
     * @var array
     */
    protected $conf                    = null;

    /**
     * Debug log collector
     * @var array
     */
    protected $debugLogArray           = null;

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
     * @return Main
     */
    public static function getInstance()
    {
        if (!isset(self::$instance)) {
            $c = __CLASS__;
            self::$instance = new $c;
        }

        return self::$instance;
    }

    /**
     * Constructor
     *
     * @return Main
     */
    public function __construct()
    {
        global $TYPO3_CONF_VARS;

        self::$instance    = $this;

        if (isset($TYPO3_CONF_VARS['EXT']['extConf'][$this->extKey])) {
            $this->conf = unserialize($TYPO3_CONF_VARS['EXT']['extConf'][$this->extKey]);
        }

        // @TODO error_log path & file configurable

        if (!$this->conf) {
            $this->debugLog('Configuration array not loaded!');

            // we need this to enable debug logging into the header comment!
            $this->conf['use_typoscript'] = 1;
        }

        $this->selfUrl = $this->getSelfUrl();

        // Configuration check, if debugging is activated
        if ($this->conf['debug']) {
            // try to create log file, if not existing
            if (file_exists($this->conf['error_log'])) {
                touch($this->conf['error_log']);
            }

            // check if we can use the log file
            if (!is_writable($this->conf['error_log'])) {
                $this->debugLog('error_log file given, but not writable');

                $this->conf['error_log'] = null;
            }

            // @TODO configuration sanitation
        }

        $this->debugLog(print_r($this->conf, 1));

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
        if (empty($this->conf['use_typoscript']) && empty($this->conf['debug'])) {
            // Remove hook to second entry point because we don't want to parse TS
            unset(
                $TYPO3_CONF_VARS['SC_OPTIONS']['tslib/class.tslib_fe.php']['configArrayPostProc']['tx_mobileredirect']
            );
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
     *
     * @return void
     *
     */
    public function secondEntryPoint(&$params)
    {
        // Only log this if typoscript usage is activated
        // otherwise this entry point is only called because debugging is enabled
        if (!empty($this->conf['use_typoscript'])) {
            $this->debugLog('Second entry point called');
        }

        // Merge TS configuration with other configuration, if available
        if (isset($params['config']['tx_cwmobileredirect.'])) {
            $this->conf = array_merge($this->conf, $params['config']['tx_cwmobileredirect.']);
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
        if (!$this->isMobileUrlRequested() &&
            !$this->isStandardUrlRequested()
        ) {
            $this->debugLog('Neither mobile nor standard URL requested');

            return;
        }

        $this->setHttpStatus();

        // don't redirect in case of configured exceptions
        // e.g. (rest\/news|events)|typo3conf
        if (!empty($this->conf['redirect_exceptions']) &&
            preg_match('/' . $this->conf['redirect_exceptions'] . '/', GeneralUtility::getIndpEnv('REQUEST_URI'))
        ) {
            return;
        }

        // check if mobile version is forced
        if ($this->isMobileForced()) {
            $this->debugLog('Mobile version forced');

            $this->setExtensionCookie(self::MOBILEREDIRECT_COOKIE_MOBILE);

            // Check if we need to redirect to the mobile page
            if (!$this->isMobileUrlRequested()) {
                $this->redirectToMobileUrl();
            }

            return;
        }

        // check if standard version is forced
        if ($this->isStandardForced()) {
            $this->debugLog('Standard version forced');

            $this->setExtensionCookie(self::MOBILEREDIRECT_COOKIE_STANDARD);

            // Check if we need to redirect to the standard page
            if (!$this->isStandardUrlRequested()) {
                $this->redirectToStandardUrl();
            }

            return;
        }

        // end here if mobile detection disabled or mobile URL is already used
        if (!$this->conf['detection_enabled'] || $this->isMobileUrlRequested()) {
            $this->debugLog('Mobile detection disabled or mobile URL already used');

            return;
        }

        // here the real detection begins
        if ($this->conf['redirection_enabled'] && !$this->isStandardAccepted() && $this->detectMobile()) {
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
        if ($addParam) {
            $this->redirectTo($this->conf['mobile_url'], $this->conf['is_mobile_name']);
        } else {
            $this->redirectTo($this->conf['mobile_url']);
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
        if ($addParam) {
            $this->redirectTo($this->conf['standard_url'], $this->conf['no_mobile_name']);
        } else {
            $this->redirectTo($this->conf['standard_url']);
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
        if (!empty($this->conf['maintain_url']) && !empty($this->requestParams)) {
            $url .= $this->requestParams;
        }

        // Add params
        if ($addParam) {
            if (!is_array($addParam)) {
                $addParam = array($addParam);
            }

            foreach ($addParam as $param) {
                // check if param is already given, skip if yes
                if (strpos($url, $param) !== false) {
                    continue;
                }

                // is it the first parameter?
                if (strpos($url, "?") !== false) {
                    $urlParam .= "&";
                } else {
                    $urlParam .= "?";
                }

                $urlParam .= $param;

                // add =1 to param if needed to solve problems with RealUrl and pageHandling
                if (!empty($this->conf['add_value_to_params'])) {
                    $urlParam .= '=1';
                }
            }
        }

        if (strpos($url, "/") === false) {
            $url .= "/";
        }

        $this->debugLog('Redirecting to ' . $url);

        $this->writeDebugLogArray();

        HttpUtility::redirect($this->protocol . $url . $urlParam, $this->conf['httpStatus']);
    }

    /**
     * Set the HTTP status used for redirects
     *
     * @return void
     */
    protected function setHttpStatus()
    {
        // set default HTTP Status code, if not defined
        if ('' == $this->conf['httpStatus'] || !defined('t3lib_utility_Http::'. $this->conf['httpStatus'])) {
            $this->conf['httpStatus'] = HttpUtility::HTTP_STATUS_303;
        } else {
            $this->conf['httpStatus'] = constant('t3lib_utility_Http::'. $this->conf['httpStatus']);
        }

        $this->debugLog('Setting HTTP status', array('http_status' => $this->conf['httpStatus']));
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
        if ($this->conf['use_cookie']) {
            $this->debugLog('Setting cookie', array('cookie_value' => $cookieValue));
            return setcookie(
                $this->conf['cookie_name'], 
                $cookieValue, 
                time()+$this->conf['cookie_lifetime'], 
                "/", // path
                trim($this->conf['cookie_domain']) // domain
            );
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
        return (strpos($this->selfUrl, $this->conf['mobile_url']) === 0);
    }

    /**
     * Determine if the requested URL is the standard one
     *
     * @return boolean
     */
    public function isStandardUrlRequested()
    {
        return (strpos($this->selfUrl, $this->conf['standard_url']) === 0);
    }

    /**
     * Determine if the standard mode is forced
     * (checks GET params)
     *
     * @return boolean - true if standard mode is forced, false otherwise
     *
     */
    public function isStandardForced()
    {
        $this->debugLog("--------------- isStandardForced BEGIN  ----------------------");
        $this->debugLog(print_r($_COOKIE, 1));
        $this->debugLog(print_r($_GET, 1));
        $this->debugLog("--------------- isStandardForced END ----------------------");

        return (!empty($this->conf['no_mobile_name']) && isset($_GET[$this->conf['no_mobile_name']])) ? true : false;
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
        $this->debugLog(print_r($_COOKIE, 1));
        $this->debugLog(print_r($_GET, 1));
        $this->debugLog("--------------- isMobileForced END ----------------------");

        return (!empty($this->conf['is_mobile_name']) && isset($_GET[$this->conf['is_mobile_name']])) ? true : false;
    }



    /**
     * Determine if the standard mode is accepted
     * (checks Cookie)
     *
     * @return boolean - true if the standard is forced OR a standard cookie is set
     */
    public function isStandardAccepted()
    {
        $this->debugLog("--------------- isMobileAccepted BEGIN  ----------------------");
        $this->debugLog(print_r($_COOKIE,1));
        $this->debugLog("--------------- isMobileAccepted END ----------------------");

        return 
            $this->isStandardForced()
            || (
                !$this->isMobileForced()
                && ((isset($_COOKIE[$this->conf['cookie_name']]) && $_COOKIE[$this->conf['cookie_name']] == self::MOBILEREDIRECT_COOKIE_STANDARD))
            )
        ;
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
        if (!isset($_SERVER['REQUEST_URI'])) {
            $url = $_SERVER['PHP_SELF'];
        } else {
            $url = $_SERVER['REQUEST_URI'];
        }

        // Get the requested params for direct forwarding
        $this->getRequestedParams();

        // store used protocol for later use
        $s              = empty($_SERVER["HTTPS"]) ? '' : ($_SERVER["HTTPS"] == "on") ? "s" : "";
        $this->protocol = substr(
            strtolower($_SERVER["SERVER_PROTOCOL"]),
            0,
            strpos(strtolower($_SERVER["SERVER_PROTOCOL"]), "/")
        ) . $s . "://";

        if ($prependProtocol) {
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
    protected function detectMobile($useragent = null)
    {
        // Regular expressions for mobile detection by
        // http://detectmobilebrowser.com/
        // Thanks a lot!

        if (!$useragent) {
            $useragent = GeneralUtility::getIndpEnv('HTTP_USER_AGENT');
        }

        // Run detection - if true, store status
        $rx1 = '/'. $this->conf['regexp1'] .'/i';
        $rx2 = '/'. $this->conf['regexp2'] .'/i';

        if ((preg_match($rx1, $useragent) || preg_match($rx2, substr($useragent, 0, 4)))) {
            $this->debugLog('Mobile device detected', array('useragent' => $useragent));

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
    protected function detectMobileBrowser($useragent = null)
    {
        if (!$useragent) {
            $useragent = GeneralUtility::getIndpEnv('HTTP_USER_AGENT');
        }

        // go through the array of known mobile browsers and check
        // if the current useragent is recognized
        foreach ($this->knownMobileBrowsersArr as $browserId => $browserName) {
            if (stripos($useragent, $browserId) !== false) {
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
        if (isset($this->knownMobileBrowsersArr[$this->getDetectedMobileBrowser()])) {
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
        if ($this->detectedMobileBrowser === null) {
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
        if ($this->isMobileStatus === null) {
            $this->detectMobile();
        }

        return $this->isMobileStatus;
    }

    /**
     * Debug Logging
     *
     * depends on debug-Setting in Configuration
     *
     * @param string        $messageString - The message
     * @param array|boolean $dataVar       - An array to collect messages in
     *
     * @return void
     */
    protected function debugLog($messageString, $dataVar = false)
    {
        // debugging activated?
        if ($this->conf && empty($this->conf['debug'])) {
            return;
        }

        // yes, collect message
        if (is_array($dataVar)) {
            $tempArray = array();

            foreach ($dataVar as $key => $value) {
                $tempArray[] = $key . ' => ' . $value;
            }

            $messageString .= ' ( ' . implode(', ', $tempArray) . ' )';
        }

        // store this now, write it later (see writeDebugLogArray)
        $this->debugLogArray[] = $messageString;

        // classic error log
        $logString = $this->extKey . ': ' . $messageString;

        if (!empty($this->conf['error_log'])) {
            error_log($logString . "\n", 3, $this->conf['error_log']);
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
        if (!isset($this->debugLogArray) || count($this->debugLogArray) == 0) {
            return;
        }

        $log = $this->extKey . ' Debug log:' . "\n\r" . implode(",\r", $this->debugLogArray);

        // write all log comments into the header
        $GLOBALS['TSFE']->config['config']['headerComment'] = $log;

        // free some memory
        $this->debugLogArray = array();
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
        if (!empty($_SERVER['REQUEST_URI'])) {
            $this->requestParams = $_SERVER['REQUEST_URI'];
            // I hope this is working, should work on IIS
        } elseif (!empty($_SERVER['REDIRECT_SCRIPT_URL'])) {
            $this->requestParams = $_SERVER['REDIRECT_SCRIPT_URL'] . $queryString;
            // Fallback
        } else {
            $this->requestParams = $_SERVER['PHP_SELF'] . $queryString;
        }

        $this->debugLog("----------- SERVER BEGIN -----------");
        $this->debugLog(print_r($_SERVER, 1));
        $this->debugLog("----------- SERVER END -----------");

        $this->debugLog("Requested params: " . $this->requestParams);
    }
}
