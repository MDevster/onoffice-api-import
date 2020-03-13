<?php
/**
 * This file is part of Contao EstateManager.
 *
 * @link      https://www.contao-estatemanager.com/
 * @source    https://github.com/contao-estatemanager/wib-import
 * @copyright Copyright (c) 2019  Oveleon GbR (https://www.oveleon.de)
 * @license   https://www.contao-estatemanager.com/lizenzbedingungen.html
 */

// ESTATEMANAGER
$GLOBALS['TL_ESTATEMANAGER_ADDONS'][] = array('ContaoEstateManager\\OnOfficeApiImport', 'AddonManager');

if(ContaoEstateManager\OnOfficeApiImport\AddonManager::valid()) {
// HOOKS
    $GLOBALS['TL_HOOKS']['realEstateImportBeforeSync'][]       = array('ContaoEstateManager\\OnOfficeApiImport\\OnOfficeApiImport', 'manuallyDownloadApiStream');
    $GLOBALS['TL_HOOKS']['realEstateImportBeforeLoadData'][]   = array('ContaoEstateManager\\OnOfficeApiImport\\OnOfficeApiImport', 'startSync');
}
