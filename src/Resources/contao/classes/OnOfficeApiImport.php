<?php
/**
 * This file is part of Contao EstateManager.
 *
 * @link      https://www.contao-estatemanager.com/
 * @source    https://github.com/contao-estatemanager/project
 * @copyright Copyright (c) 2019  Oveleon GbR (https://www.oveleon.de)
 * @license   https://www.contao-estatemanager.com/lizenzbedingungen.html
 */

namespace ContaoEstateManager\OnOfficeApiImport;

use ContaoEstateManager\InterfaceModel;
use ContaoEstateManager\ProviderModel;
use Oveleon\ContaoOnofficeApiBundle\OnOfficeRead;

/**
 * Class OnOfficeApiImport
 * @package ContaoEstateManager\Project
 * @author  Fabian Ekert <fabian@oveleon.de>
 */
class OnOfficeApiImport extends \System
{

    /**
     * Importer
     * @var RealEstateImporter
     */
    protected $objImporter;

    /**
     * Interface
     * @var InterfaceModel
     */
    protected $objInterface;

    /**
     * Import folder model
     * @var \Model\Collection
     */
    protected $objInterfaceMapping;

    /**
     * @var array
     */
    protected $data;

    /**
     * Download an onOffice API stream file manually
     *
     * @param $context
     */
    public function manuallyDownloadApiStream($context)
    {
        if ($context->interface->type === 'onofficeapi') {
            if (\Input::get('downloadOnOfficeApiStream')) {
                $this->downloadApiStream($context);
            }

            $context->updateSyncTime = false;
        }
    }

    /**
     * Download an onOffice API stream file
     *
     * @param $context
     */
    public function downloadApiStream($context)
    {
        $objInterface = $context->interface;

        $syncTime = time();
        $fileName = 'export_' . $syncTime . '.data';

        $objReadController = new OnOfficeRead();

        $params = array('listlimit'=>25, 'listoffset'=>0);

        $arrData = $objReadController->run('estates', null, intval($objInterface->onOfficeApiView), $params, true);

        if ($arrData['status']['errorcode'] === 0)
        {
            \File::putContent($context->importFolder->path . '/' . $fileName, serialize($arrData['data']));

            $objInterface->lastSync = $syncTime;
            $objInterface->save();

            $context->updateSyncTime = false;
        }
        else
        {
            //\Message::addInfo('The downloaded file was empty and has been skipped.');
        }
    }

    /**
     * Syncs onOffice API stream data with database
     *
     * @param $importer
     */
    public function startSync($importer)
    {
        $this->objImporter = $importer;
        $this->objInterface = $this->objImporter->interface;

        if ($this->objInterface->type !== 'onofficeapi')
        {
            return false;
        }

        if (($this->loadData()))
        {
            $this->syncData();
        }

        return true;
    }

    /**
     * Sync onOffice API stream data with database
     *
     * @return boolean
     */
    protected function syncData()
    {
        $skipRecords = \StringUtil::deserialize($this->objInterface->skipRecords, true);

        $contactPersonMeta = $this->getTableMetaData('tl_contact_person');
        $realEstateMeta = $this->getTableMetaData('tl_real_estate');

        $contactPersonRecords = array();
        $realEstateRecords = array();

        $this->objImporter->uniqueProviderValue = $this->objInterface->getRelated('provider')->{$this->objInterface->uniqueProviderField};

        if (!$this->objInterface->importThirdPartyRecords && $this->objInterface->anbieternr !== $this->objImporter->uniqueProviderValue)
        {
            return;
        }

        if ($this->objInterface->importThirdPartyRecords === 'import')
        {
            $objProvider = ProviderModel::findOneByAnbieternr($this->objImporter->uniqueProviderValue);

            if ($objProvider === null)
            {
                $this->importStatus = 2;
                $this->importMessage = 'File partially imported.';

                return;
            }
        }

        foreach ($this->data['records'] as $realEstate)
        {
            $this->uniqueValue = $realEstate['elements']['Id'];

            $contactPerson = array();
            $re = array
            (
                'ANBIETER' => $this->objImporter->uniqueProviderValue,
                'AKTIONART' => 'UPDATE'
            );

            while ($this->objImporter->interfaceMapping->next())
            {
                $interfaceMapping = $this->objImporter->interfaceMapping->current();

                if ($interfaceMapping->forceActive)
                {
                    switch ($interfaceMapping->type)
                    {
                        case 'tl_contact_person':
                            $contactPerson[$interfaceMapping->attribute] = $interfaceMapping->forceValue;
                            break;
                        case 'tl_real_estate':
                            $re[$interfaceMapping->attribute] = $interfaceMapping->forceValue;
                            break;
                    }
                }

                $value = $realEstate['elements'][$interfaceMapping->oiFieldGroup];

                // Skip if value is not set
                if ($value === null)
                {
                    continue;
                }

                if (is_array($value) && $interfaceMapping->oiField !== '')
                {
                    $value = $value[0][$interfaceMapping->oiField];
                }

                $value = $this->formatValue($value);

                if (in_array($interfaceMapping->oiFieldGroup, $skipRecords))
                {
                    if ($value == '')
                    {
                        $this->objImporter->interfaceMapping->reset();
                        continue 2;
                    }
                }

                if (!$value)
                {
                    switch ($interfaceMapping->type)
                    {
                        case 'tl_contact_person':
                            $contactPerson[$interfaceMapping->attribute] = $contactPersonMeta[$interfaceMapping->attribute]['default'];
                            break;
                        case 'tl_real_estate':
                            $re[$interfaceMapping->attribute] = $realEstateMeta[$interfaceMapping->attribute]['default'];
                            break;
                    }

                    continue;
                }

                $value = $interfaceMapping->serialize ? serialize($value) : $value;

                if (is_array($value))
                {
                    if (count($value))
                    {
                        $value = $value[0];
                    }
                    else
                    {
                        $value = '';
                    }
                }

                switch ($interfaceMapping->type)
                {
                    case 'tl_contact_person':
                        $contactPerson[$interfaceMapping->attribute] = $value;
                        break;
                    case 'tl_real_estate':
                        $re[$interfaceMapping->attribute] = $value;
                        break;
                }
            }

            $this->objImporter->interfaceMapping->reset();

            $contactPersonRecords[] = $contactPerson;
            $realEstateRecords[] = $re;
        }
    }

    /**
     * Loads the stream in a sync file
     *
     * @return boolean
     */
    protected function loadData()
    {
        $data = file_get_contents(TL_ROOT . '/' . $this->objImporter->syncFile);

        try {
            $this->data = \StringUtil::deserialize($data, true);
        } catch (\Exception $e) {
            return false;
        }

        return true;
    }

    protected function formatValue($value)
    {
        switch ($this->objImporter->interfaceMapping->formatType)
        {
            case 'number':
                $value = number_format(floatval($value), $this->objImporter->interfaceMapping->decimals, '.', '');

                if ($this->objImporter->interfaceMapping->decimals == 0)
                {
                    $value = intval($value);
                }
                break;
            case 'date':
                $value = strtotime($value);
                if ($value < 0)
                {
                    $value = 0;
                }
                break;
            case 'text':
                switch ($this->objImporter->interfaceMapping->textTransform)
                {
                    case 'lowercase';
                        $value = strtolower($value);
                        break;
                    case 'uppercase';
                        $value = strtoupper($value);
                        break;
                    case 'capitalize';
                        $value = ucfirst($value);
                        break;
                    case 'removespecialchar':
                        $value = $this->standardizeSpecialChars($value);
                        break;
                }
                if ($this->objImporter->interfaceMapping->trim)
                {
                    $value = trim($value);
                }
                break;
            case 'boolean':
                if ($this->objImporter->interfaceMapping->booleanCompareValue)
                {
                    if ($this->objImporter->interfaceMapping->booleanCompareValue === $value)
                    {
                        $value = '1';
                    }
                    else
                    {
                        $value = '0';
                    }
                }
                elseif ($value && ($value === '1' || $value === 'true'))
                {
                    $value = '1';
                }
                else
                {
                    $value = '0';
                }
                break;
        }

        return $value;
    }

    protected function standardizeSpecialChars($content)
    {
        // Convert microsoft special characters
        $replace = array(
            "‘" => "'",
            "’" => "'",
            "”" => '"',
            "“" => '"',
            "" => '"',
            "" => '"',
            "–" => "-",
            "—" => "-",
            "" => "-",
            "…" => "&#8230;"
        );

        foreach($replace as $k => $v)
        {
            $content = str_replace($k, $v, $content);
        }

        // Remove any non-ascii character
        // $content = preg_replace('/[^\x20-\x7E]*/','', $content);

        return $content;
    }

    /**
     * Retrieve meta data of a specific database table.
     *
     * string $strTable  Name of the database table
     *
     * @return array
     */
    protected function getTableMetaData($strTable)
    {
        $arrReturn = array();

        $objDatabase = \Database::getInstance();
        $arrFields = $objDatabase->listFields($strTable);

        foreach ($arrFields as $key => $meta)
        {
            if (is_int($key))
            {
                $arrReturn[$meta['name']] = $meta;
            }
        }

        return $arrReturn;
    }
}
