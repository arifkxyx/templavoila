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
use Extension\Templavoila\Traits\DatabaseConnection;
use Extension\Templavoila\Traits\LanguageService;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class for userFuncs within the Extension Manager.
 *
 * @author Steffen Kamper <info@sk-typo3.de>
 */
class tx_templavoila_staticds_wizard
{

    use DatabaseConnection;
    use LanguageService;
    
    /**
     * Step for the wizard. Can be manipulated by internal function
     *
     * @var int
     */
    protected $step = 0;

    /**
     * Static DS wizard
     *
     * @return string
     */
    public function staticDsWizard()
    {
        $this->step = GeneralUtility::_GP('dsWizardDoIt') ? (int)GeneralUtility::_GP('dsWizardStep') : 0;
        $conf = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf'][\Extension\Templavoila\Templavoila::EXTKEY]);

        $title = static::getLanguageService()->sL(
            'LLL:EXT:templavoila/Resources/Private/Language/template_conf.xlf:staticDS.wizard.title.' . $this->step,
            true
        );
        $description = static::getLanguageService()->sL(
            'LLL:EXT:templavoila/Resources/Private/Language/template_conf.xlf:staticDS.wizard.description.' . $this->step
        );
        $out = '<h2>' . $title . '</h2>';

        $controls = '';

        switch ($this->step) {
            case 1:
                $ok = [true, true];
                if (GeneralUtility::_GP('dsWizardDoIt')) {
                    if (!isset($conf['staticDS.']['path_fce']) || !strlen($conf['staticDS.']['path_fce'])) {
                        $ok[0] = false;
                        $description .= sprintf('||' . static::getLanguageService()->sL('LLL:EXT:templavoila/Resources/Private/Language/template_conf.xlf:staticDS.wizard.dircheck.notset'), 'staticDS.path_fce');
                    } else {
                        $ok[0] = $this->checkDirectory($conf['staticDS.']['path_fce']);
                        if ($ok[0]) {
                            $description .= sprintf('||' . static::getLanguageService()->sL('LLL:EXT:templavoila/Resources/Private/Language/template_conf.xlf:staticDS.wizard.dircheck.ok'), htmlspecialchars($conf['staticDS.']['path_fce']));
                        } else {
                            $description .= sprintf('||' . static::getLanguageService()->sL('LLL:EXT:templavoila/Resources/Private/Language/template_conf.xlf:staticDS.wizard.dircheck.notok'), htmlspecialchars($conf['staticDS.']['path_fce']));
                        }
                    }

                    if (!isset($conf['staticDS.']['path_page']) || !strlen($conf['staticDS.']['path_page'])) {
                        $ok[0] = false;
                        $description .= sprintf('||' . static::getLanguageService()->sL('LLL:EXT:templavoila/Resources/Private/Language/template_conf.xlf:staticDS.wizard.dircheck.notset'), 'staticDS.path_page');
                    } else {
                        $ok[1] = $this->checkDirectory($conf['staticDS.']['path_page']);
                        if ($ok[1]) {
                            $description .= sprintf('|' . static::getLanguageService()->sL('LLL:EXT:templavoila/Resources/Private/Language/template_conf.xlf:staticDS.wizard.dircheck.ok'), htmlspecialchars($conf['staticDS.']['path_page']));
                        } else {
                            $description .= sprintf('|' . static::getLanguageService()->sL('LLL:EXT:templavoila/Resources/Private/Language/template_conf.xlf:staticDS.wizard.dircheck.notok'), htmlspecialchars($conf['staticDS.']['path_page']));
                        }
                    }
                    if ($ok == [true, true]) {
                        $controls .= $this->getDsRecords($conf['staticDS.']);
                    }
                }
                if ($ok == [true, true] && $this->step < 3) {
                    $submitText = $conf['staticDS.']['enable']
                        ? static::getLanguageService()->sL('LLL:EXT:templavoila/Resources/Private/Language/template_conf.xlf:staticDS.wizard.submit3')
                        : static::getLanguageService()->sL('LLL:EXT:templavoila/Resources/Private/Language/template_conf.xlf:staticDS.wizard.submit2');
                    $controls .= '<br /><input type="hidden" name="dsWizardStep" value="1" />
                    <input type="submit" name="dsWizardDoIt" value="' . $submitText . '" />';
                }
                break;
            default:
                $controls .= '<input type="hidden" name="dsWizardStep" value="1" />
                <input type="submit" name="dsWizardDoIt" value="' . static::getLanguageService()->sL('LLL:EXT:templavoila/Resources/Private/Language/template_conf.xlf:staticDS.wizard.submit1') . '" />';
                break;
        }

        $out .= '<p style="margin-bottom: 10px;">' . str_replace('|', '<br />', $description) . '</p>' .
            '<p style="margin-top: 5px;">' . $controls . '</p>';

        return '<form action="#" method="POST">' . $out . '</form>';
    }

    /**
     * Check directory
     *
     * @param string $path
     *
     * @return bool TRUE if directory exists and is writable or could be created
     */
    protected function checkDirectory($path)
    {
        $status = false;
        $path = rtrim($path, '/') . '/';
        $absolutePath = GeneralUtility::getFileAbsFileName($path);
        if (!empty($absolutePath)) {
            if (@is_writable($absolutePath)) {
                $status = true;
            }
            if (!is_dir($absolutePath)) {
                try {
                    $errors = GeneralUtility::mkdir_deep(PATH_site, $path);
                    if ($errors === null) {
                        $status = true;
                    }
                } catch (\RuntimeException $e) {
                }
            }
        }

        return $status;
    }

    /**
     * Get DS records
     *
     * @param array $conf
     *
     * @return string
     */
    protected function getDsRecords($conf)
    {
        $updateMessage = '';
        $writeDsIds = [];
        $writeIds = GeneralUtility::_GP('staticDSwizard');
        $options = GeneralUtility::_GP('staticDSwizardoptions');
        $checkAll = GeneralUtility::_GP('sdw-checkall');

        if (count($writeIds)) {
            $writeDsIds = array_keys($writeIds);
        }
        $rows = static::getDatabaseConnection()->exec_SELECTgetRows(
            '*',
            'tx_templavoila_datastructure',
            'deleted=0',
            '',
            'scope, title'
        );
        $out = '<table id="staticDSwizard_getdsrecords"><thead>
            <tr class="bgColor5">
                <td style="vertical-align:middle;">' . static::getLanguageService()->sL('LLL:EXT:templavoila/Resources/Private/Language/template_conf.xlf:staticDS.wizard.uid') . '</td>
                <td style="vertical-align:middle;">' . static::getLanguageService()->sL('LLL:EXT:templavoila/Resources/Private/Language/template_conf.xlf:staticDS.wizard.pid') . '</td>
                <td style="vertical-align:middle;">' . static::getLanguageService()->sL('LLL:EXT:templavoila/Resources/Private/Language/template_conf.xlf:staticDS.wizard.title') . '</td>
                <td style="vertical-align:middle;">' . static::getLanguageService()->sL('LLL:EXT:templavoila/Resources/Private/Language/template_conf.xlf:staticDS.wizard.scope') . '</td>
                <td style="vertical-align:middle;">' . static::getLanguageService()->sL('LLL:EXT:templavoila/Resources/Private/Language/template_conf.xlf:staticDS.wizard.usage') . '</td>
                <td>
                    <label for="sdw-checkall">' . static::getLanguageService()->sL('LLL:EXT:templavoila/Resources/Private/Language/template_conf.xlf:staticDS.wizard.selectall') . '</label>
                    <input type="checkbox" class="checkbox" id="sdw-checkall" name="sdw-checkall" onclick="$$(\'.staticDScheck\').each(function(e){e.checked=$(\'sdw-checkall\').checked;});" value="1" ' .
                    ($checkAll ? 'checked="checked"' : '') . ' /></td>
        </tr></thead><tbody>';
        foreach ($rows as $row) {
            $dirPath = GeneralUtility::getFileAbsFileName($row['scope'] == 2 ? $conf['path_fce'] : $conf['path_page']);
            $dirPath = $dirPath . (substr($dirPath, -1) == '/' ? '' : '/');
            $title = preg_replace('|[/,\."\']+|', '_', $row['title']);
            $path = $dirPath . $title . ' (' . ($row['scope'] == 1 ? 'page' : 'fce') . ').xml';
            $outPath = substr($path, strlen(PATH_site));

            $usage = static::getDatabaseConnection()->exec_SELECTgetRows(
                'count(*)',
                'tx_templavoila_tmplobj',
                'datastructure=' . (int) $row['uid'] . BackendUtility::BEenableFields('tx_templavoila_tmplobj')
            );
            if (count($writeDsIds) && in_array($row['uid'], $writeDsIds)) {
                GeneralUtility::writeFile($path, $row['dataprot']);
                if ($row['previewicon']) {
                    copy(GeneralUtility::getFileAbsFileName('uploads/tx_templavoila/' . $row['previewicon']), $dirPath . $title . ' (' . ($row['scope'] == 1
                            ? 'page' : 'fce') . ').gif');
                }
                if ($options['updateRecords']) {
                    // remove DS records
                    static::getDatabaseConnection()->exec_UPDATEquery(
                        'tx_templavoila_datastructure',
                        'uid="' . $row['uid'] . '"',
                        ['deleted' => 1]
                    );
                    // update TO records
                    static::getDatabaseConnection()->exec_UPDATEquery(
                        'tx_templavoila_tmplobj',
                        'datastructure="' . $row['uid'] . '"',
                        ['datastructure' => $outPath]
                    );
                    // update page records
                    static::getDatabaseConnection()->exec_UPDATEquery(
                        'pages',
                        'tx_templavoila_ds="' . $row['uid'] . '"',
                        ['tx_templavoila_ds' => $outPath]
                    );
                    static::getDatabaseConnection()->exec_UPDATEquery(
                        'pages',
                        'tx_templavoila_next_ds="' . $row['uid'] . '"',
                        ['tx_templavoila_next_ds' => $outPath]
                    );
                    // update tt_content records
                    static::getDatabaseConnection()->exec_UPDATEquery(
                        'tt_content',
                        'tx_templavoila_ds="' . $row['uid'] . '"',
                        ['tx_templavoila_ds' => $outPath]
                    );
                    // delete DS records
                    static::getDatabaseConnection()->exec_UPDATEquery(
                        'tx_templavoila_datastructure',
                        'uid=' . $row['uid'],
                        ['deleted' => 1]
                    );
                    $updateMessage = static::getLanguageService()->sL('LLL:EXT:templavoila/Resources/Private/Language/template_conf.xlf:staticDS.wizard.updated');
                    $this->step = 3;
                }
            }
            $out .= '<tr class="bgColor' . ($row['scope'] == 1 ? 3 : 6) . '">
            <td style="text-align: center;padding: 0,3px;">' . $row['uid'] . '</td>
            <td style="text-align: center;padding: 0,3px;">' . $row['pid'] . '</td>
            <td style="padding: 0,3px;">' . htmlspecialchars($row['title']) . '</td>
            <td style="padding: 0,3px;">' . ($row['scope'] == 1 ? 'Page' : 'FCE') . '</td>
            <td style="text-align: center;padding: 0,3px;">' . $usage[0]['count(*)'] . '</td>';
            if (count($writeDsIds) && in_array($row['uid'], $writeDsIds)) {
                $out .= '<td class="nobr" style="text-align: right;padding: 0,3px;">written to "' . $outPath . '"</td>';
            } else {
                $out .= '<td class="nobr" style="text-align: right;padding: 0,3px;"><input type="checkbox" class="checkbox staticDScheck" name="staticDSwizard[' . $row['uid'] . ']" value="1" /></td>';
            }
            $out .= '</tr>';
        }
        $out .= '</tbody></table>';

        if ($conf['enable']) {
            if ($updateMessage) {
                $out .= '<p>' . $updateMessage . '</p><p><strong>' . static::getLanguageService()->sL('LLL:EXT:templavoila/Resources/Private/Language/template_conf.xlf:staticDS.wizard.clearcache') . '</strong></p>';
            } else {
                $out .= '<h4>' . static::getLanguageService()->sL('LLL:EXT:templavoila/Resources/Private/Language/template_conf.xlf:staticDS.wizard.description2.1') . '</h4>';
                $out .= '<p>
                <input type="checkbox" class="checkbox" name="staticDSwizardoptions[updateRecords]" id="sdw-updateRecords" value="1" />
                <label for="sdw-updateRecords">' . static::getLanguageService()->sL('LLL:EXT:templavoila/Resources/Private/Language/template_conf.xlf:staticDS.wizard.updaterecords') . '</label><br />
                </p>';
            }
        }

        return $out;
    }

    /**
     * Get datastructure count
     *
     * @return int
     */
    protected function datastructureDbCount()
    {
        return static::getDatabaseConnection()->exec_SELECTcountRows(
            '*',
            'tx_templavoila_datastructure',
            'deleted=0'
        );
    }
}
