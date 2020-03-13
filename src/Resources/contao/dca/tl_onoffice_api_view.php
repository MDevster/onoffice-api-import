<?php
/**
 * This file is part of Contao EstateManager.
 *
 * @link      https://www.contao-estatemanager.com/
 * @source    https://github.com/contao-estatemanager/wib-import
 * @copyright Copyright (c) 2019  Oveleon GbR (https://www.oveleon.de)
 * @license   https://www.contao-estatemanager.com/lizenzbedingungen.html
 */

// Extend the default palette
Contao\CoreBundle\DataContainer\PaletteManipulator::create()
    ->addField(array('onOfficeApiImportView'), 'publish_legend', Contao\CoreBundle\DataContainer\PaletteManipulator::POSITION_APPEND)
    ->applyToPalette('estate', 'tl_onoffice_api_view')
;

// Add fields to tl_onoffice_api_view
$GLOBALS['TL_DCA']['tl_onoffice_api_view']['fields']['onOfficeApiImportView'] = array
(
    'label'                   => &$GLOBALS['TL_LANG']['tl_onoffice_api_view']['onOfficeApiImportView'],
    'exclude'                 => true,
    'inputType'               => 'checkbox',
    'eval'                    => array('tl_class'=>'m12'),
    'sql'                     => "char(1) NOT NULL default ''"
);
