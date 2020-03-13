<?php
/**
 * This file is part of Contao EstateManager.
 *
 * @link      https://www.contao-estatemanager.com/
 * @source    https://github.com/contao-estatemanager/wib-import
 * @copyright Copyright (c) 2019  Oveleon GbR (https://www.oveleon.de)
 * @license   https://www.contao-estatemanager.com/lizenzbedingungen.html
 */

if(ContaoEstateManager\OnOfficeApiImport\AddonManager::valid()) {

    // Add type option
    $GLOBALS['TL_DCA']['tl_interface']['fields']['type']['options'][] = 'onofficeapi';

    // Add fields
    $GLOBALS['TL_DCA']['tl_interface']['fields']['onOfficeApiView'] = array
    (
        'label'                   => &$GLOBALS['TL_LANG']['tl_interface']['onOfficeApiView'],
        'exclude'                 => true,
        'inputType'               => 'select',
        'foreignKey'              => 'tl_onoffice_api_view.title',
        'options_callback'        => array('tl_onoffice_api_interface', 'getApiViews'),
        'eval'                    => array('mandatory'=>true, 'chosen'=>true, 'includeBlankOption'=>true, 'tl_class'=>'w50'),
        'sql'                     => "int(10) unsigned NOT NULL default '0'",
        'relation'                => array('type'=>'hasOne', 'load'=>'eager')
    );

    // Add palettes
    $GLOBALS['TL_DCA']['tl_interface']['palettes']['onofficeapi'] = '{title_legend},title,type;{oi_field_legend},provider,anbieternr,uniqueProviderField,uniqueField
    import_interface_mappings.php,importPath,filesPath,filesPathContactPerson;{related_records_legend},contactPersonActions,contactPersonUniqueField,importThirdPartyRecords;{sync_legend},autoSync,deleteFilesOlderThen;{skip_legend},skipRecords;{expert_legend:hide},onOfficeApiView';
}


/**
 * Provide miscellaneous methods that are used by the data configuration array.
 *
 * @author Fabian Ekert <fabian@oveleon.de>
 */
class tl_onoffice_api_interface extends Backend
{

    /**
     * Import the back end user object
     */
    public function __construct()
    {
        parent::__construct();
        $this->import('BackendUser', 'User');
    }

    /**
     * Return all api views
     *
     * @return array
     */
    public function getApiViews()
    {
        $objApiViews = $this->Database->execute("SELECT * FROM tl_onoffice_api_view WHERE onOfficeApiImportView=1");

        if ($objApiViews->numRows < 1)
        {
            return array();
        }

        $return = array();

        // Check permissions for frontend users
        if (FE_USER_LOGGED_IN)
        {
            $this->import('FrontendUser', 'FrontendUser');

            while ($objApiViews->next())
            {
                if ($objApiViews->protected)
                {
                    $groups = \StringUtil::deserialize($objApiViews->groups);

                    if (empty($groups) || !\is_array($groups) || !\count(array_intersect($groups, $this->FrontendUser->groups)))
                    {
                        continue;
                    }
                }

                $return[$objApiViews->id] = $objApiViews->title;
            }

            return $return;
        }

        while ($objApiViews->next())
        {
            $return[$objApiViews->id] = $objApiViews->title;
        }

        return $return;
    }
}
