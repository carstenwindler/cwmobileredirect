<?php
use CarstenWindler\Cwmobileredirect\Main;

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
    $forced = (Main::getInstance()->isMobileForced() || Main::getInstance()->isMobile());

    if ($forced && !empty($browserId)) {
        if (strpos($browserId, Main::getInstance()->getDetectedMobileBrowser()) !== false) {
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
    return Main::getInstance()->isStandardForced();
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
    if (!empty($browserId)) {
        if (strpos($browserId, Main::getInstance()->getDetectedMobileBrowser()) !== false) {
            return true;
        } else {
            return false;
        }
    } else {
        return Main::getInstance()->isMobile();
    }
}
