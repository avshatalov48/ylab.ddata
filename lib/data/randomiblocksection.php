<?php

namespace Ylab\Ddata\Data;

use Bitrix\Main\Localization\Loc;
use Ylab\Ddata\Interfaces\DataUnitClass;
use Bitrix\Main\HttpRequest;
use Ylab\Ddata\Helpers;
use Ylab\Ddata\Orm\EntityUnitProfileTable;
use Bitrix\Main\Web\Json;

Loc::loadMessages(__FILE__);

/**
 * Генерация случайной категории инфоблока
 *
 * Class RandomIBlockSection
 * @package Ylab\Ddata\Data
 */
class RandomIBlockSection extends DataUnitClass
{
    protected $sRandom = 'Y';

    /** @var array $arSectionsRandom */
    protected $arSectionsRandom = [];

    /** @var array $arSelectedSections */
    protected $arSelectedSections = [];

    /**
     * RandomIBlockSection constructor.
     * @param $sProfileID - ID профиля
     * @param $sFieldCode - Симфольный код свойства
     * @param $sGeneratorID - ID уже сохраненного генератора
     * @throws \Bitrix\Main\ArgumentException
     * @throws \Exception
     */
    public function __construct(string $sProfileID = '', string $sFieldCode = '', string $sGeneratorID = '')
    {
        parent::__construct($sProfileID, $sFieldCode, $sGeneratorID);

        if (!empty($this->options['selected-sections'])) {
            $this->arSelectedSections = $this->options['selected-sections'];
        }

        if (!empty($this->options['random'])) {
            $this->sRandom = $this->options['random'];

            if ($this->sRandom == 'Y') {
                $iIblockId = $this->getIblockId($sProfileID);
                if (empty($iIblockId)) {
                    throw new \Exception(Loc::getMessage('YLAB_DDATA_DATA_IBLOCK_SECTION_EXCEPTION_IBLOC_ID'));
                }

                if (count($this->arSelectedSections) == 1) {
                    $this->arSectionsRandom = $this->getSectionList($iIblockId, $this->arSelectedSections[0]);
                } else {
                    $this->arSectionsRandom = $this->getSectionList($iIblockId);
                }
            }
        } else {
            $iIblockId = $this->getIblockId($sProfileID);
            if (empty($iIblockId)) {
                throw new \Exception(Loc::getMessage('YLAB_DDATA_DATA_IBLOCK_SECTION_EXCEPTION_IBLOC_ID'));
            }

            if (count($this->arSelectedSections) == 1) {
                $this->arSectionsRandom = $this->getSectionList($iIblockId, $this->arSelectedSections[0]);
            } else {
                $this->arSectionsRandom = $this->getSectionList($iIblockId);
            }
        }
    }

    /**
     * Метод возврящает массив описывающий тип данных. ID, Имя, scalar type php
     *
     * @return array
     */
    public function getDescription()
    {
        return [
            'ID' => 'iblock.section',
            'NAME' => Loc::getMessage('YLAB_DDATA_DATA_IBLOCK_SECTION_NAME'),
            'DESCRIPTION' => Loc::getMessage('YLAB_DDATA_DATA_IBLOCK_SECTION_DESCRIPTION'),
            'TYPE' => 'iblock.section',
            'CLASS' => __CLASS__
        ];
    }

    /**
     * @param HttpRequest $request
     * @return false|mixed|string
     * @throws \Bitrix\Main\ArgumentException
     */
    public function getOptionForm(HttpRequest $request)
    {
        $arRequest = $request->toArray();
        $arOptions = (array)$arRequest['option'];
        $sGeneratorID = $request->get('generator');
        $sProfileID = $request->get('profile_id');
        $sPropertyName = $request->get('property-name');
        $arClassVars = get_class_vars(__CLASS__);
        $arDefaultOptions = [
            'random' => $arClassVars['sRandom'],
            'selected-sections' => $arClassVars['arSelectedSections']
        ];
        if (!is_array($arOptions)) {
            $arOptions = [];
        }

        $arOptions = array_merge($arDefaultOptions, $arOptions);

        $arIblockId = $request->get('prepare');
        $iIblockId = $arIblockId['iblock_id'];

        if (empty($iIblockId)) {
            $iIblockId = $this->getIblockId($sProfileID);
            if (empty($iIblockId)) {
                throw new \Exception(Loc::getMessage('YLAB_DDATA_DATA_IBLOCK_SECTION_EXCEPTION_IBLOC_ID'));
            }
        }

        $arSection = $this->getSectionList($iIblockId);

        ob_start();
        include Helpers::getModulePath() . '/admin/fragments/random_iblock_section_settings_form.php';
        $tpl = ob_get_contents();
        ob_end_clean();

        return $tpl;
    }

    /**
     * Метод проверяет на валидность данные настройки генератора
     *
     * @param HttpRequest $request
     * @return bool
     */
    public  function isValidateOptions(HttpRequest $request)
    {
        $arPrepareRequest = $request->get('option');

        if ($arPrepareRequest) {
            $sRandom = $arPrepareRequest['random'];
            $arSelectedSections = $arPrepareRequest['selected-sections'];

            if (!empty($sRandom) || !empty($arSelectedSections)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Возвращает случайную запись соответствующего типа
     *
     * @return mixed|string
     * @throws \Exception
     */
    public function getValue()
    {
        if ($this->sRandom === 'Y') {
            if ($this->arSectionsRandom) {
                $sResult = array_rand($this->arSectionsRandom);
                return $sResult;
            }
        } else {
            if ($this->arSelectedSections) {
                $sResult = array_rand($this->arSelectedSections);

                return $this->arSelectedSections[$sResult];
            }
        }

        return '';
    }

    /**
     * Получим список секций ИБ
     *
     * @param int $iIblockId ID инфолбока
     * @param int $iParendID ID родительской секции
     * @return array
     */
    private function getSectionList($iIblockId = 0, $iParendID = 0)
    {
        $arSection = [];

        If ($iIblockId && \CModule::IncludeModule('iblock')) {
            $arFilter = [
                'IBLOCK_ID' => $iIblockId
            ];

            if ($iParendID) {
                $arFilter['SECTION_ID'] = $iParendID;
            }

            $oSection = \CIBlockSection::GetList(["left_margin" => "asc"], $arFilter);
            while ($arSectionRes = $oSection->Fetch()) {
                $arSection[$arSectionRes['ID']][] = $arSectionRes['NAME'];
                $arSection[$arSectionRes['ID']][] = $arSectionRes['DEPTH_LEVEL'];
            }
        }

        return $arSection;
    }

    /**
     * Получение ID инфоблока из настроек профиля
     *
     * @param $sProfileID
     * @return bool
     * @throws \Bitrix\Main\ArgumentException
     */
    private function getIblockId($sProfileID)
    {
        if ($sProfileID) {
            $optionsJSON = EntityUnitProfileTable::getList([
                'select' => [
                    'OPTIONS'
                ],
                'filter' => [
                    'ID' => $sProfileID
                ]
            ])->Fetch();

            if ($optionsJSON) {
                $optionsJSON = Json::decode($optionsJSON['OPTIONS']);

                return $optionsJSON['iblock_id'];
            }
        }

        return false;
    }
}