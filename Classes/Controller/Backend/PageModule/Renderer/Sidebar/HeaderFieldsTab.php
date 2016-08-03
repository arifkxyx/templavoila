<?php
/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

namespace Extension\Templavoila\Controller\Backend\PageModule\Renderer\Sidebar;

use Extension\Templavoila\Controller\Backend\PageModule\MainController;
use Extension\Templavoila\Controller\Backend\PageModule\Renderer\Renderable;
use Extension\Templavoila\Controller\Backend\PageModule\Renderer\SidebarRenderer;
use Extension\Templavoila\Traits\LanguageService;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class Extension\Templavoila\Controller\Backend\PageModule\Renderer\Sidebar\HeaderFieldsTab
 */
class HeaderFieldsTab implements Renderable
{

    use LanguageService;

    /**
     * @var PageModuleController
     */
    private $controller;

    /**
     * @return SidebarRenderer
     *
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     *
     * @param MainController $controller
     */
    public function __construct(MainController $controller)
    {
        $this->controller = $controller;
    }

    /**
     * @return string
     *
     * @throws \InvalidArgumentException
     * @throws \TYPO3\CMS\Core\Type\Exception\InvalidEnumerationValueException
     */
    public function render()
    {
        $output = '';
        if ($this->controller->rootElementTable !== 'pages') {
            return '';
        }

        $conf = $GLOBALS['TCA']['pages']['columns']['tx_templavoila_flex']['config'];

        $dataStructureArr = BackendUtility::getFlexFormDS($conf, $this->controller->rootElementRecord, 'pages');

        if (is_array($dataStructureArr) && is_array($dataStructureArr['ROOT']['tx_templavoila']['pageModule'])) {
            $headerTablesAndFieldNames = GeneralUtility::trimExplode(chr(10), str_replace(chr(13), '', $dataStructureArr['ROOT']['tx_templavoila']['pageModule']['displayHeaderFields']), 1);
            if (is_array($headerTablesAndFieldNames)) {
                $fieldNames = [];
                $headerFieldRows = [];
                $headerFields = [];

                foreach ($headerTablesAndFieldNames as $tableAndFieldName) {
                    list($table, $field) = explode('.', $tableAndFieldName);
                    $fieldNames[$table][] = $field;
                    $headerFields[] = [
                        'table' => $table,
                        'field' => $field,
                        'label' => static::getLanguageService()->sL(BackendUtility::getItemLabel('pages', $field)),
                        'value' => BackendUtility::getProcessedValue('pages', $field, $this->controller->rootElementRecord[$field], 200)
                    ];
                }
                if (count($headerFields)) {
                    foreach ($headerFields as $headerFieldArr) {
                        if ($headerFieldArr['table'] === 'pages') {
                            $onClick = BackendUtility::editOnClick('&edit[pages][' . $this->controller->getId() . ']=edit&columnsOnly=' . implode(',', $fieldNames['pages']));
                            $linkedValue = '<a style="text-decoration: none;" href="#" onclick="' . htmlspecialchars($onClick) . '">' . htmlspecialchars($headerFieldArr['value']) . '</a>';
                            $linkedLabel = '<a style="text-decoration: none;" href="#" onclick="' . htmlspecialchars($onClick) . '">' . htmlspecialchars($headerFieldArr['label']) . '</a>';
                            $headerFieldRows[] = '
                                <tr>
                                    <td class="bgColor4-20" style="width: 10%; vertical-align:top">' . $linkedLabel . '</td><td class="bgColor4" style="vertical-align:top"><em>' . $linkedValue . '</em></td>
                                </tr>
                            ';
                        }
                    }
                    $output = '
                        <table border="0" cellpadding="0" cellspacing="1" width="100%" class="lrPadding">
                            <tr>
                                <td colspan="2" class="bgColor4-20">' . static::getLanguageService()->getLL('pagerelatedinformation') . ':</td>
                            </tr>
                            ' . implode('', $headerFieldRows) . '
                        </table>
                    ';
                }
            }
        }

        return $output;
    }

}
