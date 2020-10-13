<?php

declare(strict_types=1);

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

namespace TYPO3\CMS\Impexp\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Routing\Exception\RouteNotFoundException;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Exception;
use TYPO3\CMS\Core\Http\HtmlResponse;
use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Resource\Exception\ExistingTargetFileNameException;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\MathUtility;
use TYPO3\CMS\Core\Utility\PathUtility;
use TYPO3\CMS\Impexp\Domain\Repository\PresetRepository;
use TYPO3\CMS\Impexp\Export;

/**
 * Main script class for the Export facility
 *
 * @internal this is a TYPO3 Backend controller implementation and not part of TYPO3's Core API.
 */
class ExportController extends ImportExportController
{
    /**
     * The name of the module
     *
     * @var string
     */
    protected $moduleName = 'tx_impexp_export';

    /**
     * @var Export
     */
    protected $export;

    /**
     * preset repository
     *
     * @var PresetRepository
     */
    protected $presetRepository;

    public function __construct()
    {
        parent::__construct();

        $this->presetRepository = GeneralUtility::makeInstance(PresetRepository::class);
    }

    /**
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     * @throws Exception
     * @throws ExistingTargetFileNameException
     * @throws RouteNotFoundException
     */
    public function mainAction(ServerRequestInterface $request): ResponseInterface
    {
        $this->lang->includeLLFile('EXT:impexp/Resources/Private/Language/locallang.xlf');

        $this->pageinfo = BackendUtility::readPageAccess($this->id, $this->perms_clause);
        if (is_array($this->pageinfo)) {
            $this->moduleTemplate->getDocHeaderComponent()->setMetaInformation($this->pageinfo);
        }
        // Setting up the context sensitive menu:
        $this->moduleTemplate->getPageRenderer()->loadRequireJsModule('TYPO3/CMS/Backend/ContextMenu');
        $this->moduleTemplate->getPageRenderer()->loadRequireJsModule('TYPO3/CMS/Impexp/ImportExport');
        $this->moduleTemplate->addJavaScriptCode(
            'ImpexpInLineJS',
            'if (top.fsMod) top.fsMod.recentIds["web"] = ' . (int)$this->id . ';'
        );

        // Input data grabbed:
        $inData = $request->getParsedBody()['tx_impexp'] ?? $request->getQueryParams()['tx_impexp'] ?? [];
        if (!array_key_exists('excludeDisabled', $inData)) {
            // flag doesn't exist initially; state is on by default
            $inData['excludeDisabled'] = 1;
        }
        $uriBuilder = GeneralUtility::makeInstance(UriBuilder::class);
        $this->standaloneView->assign('moduleUrl', (string)$uriBuilder->buildUriFromRoute($this->moduleName));
        $this->standaloneView->assign('id', $this->id);
        $this->standaloneView->assign('inData', $inData);

        // Call export interface
        $this->processPresets($inData);
        $this->exportData($inData);

        // Prepare view
        $this->makeConfigurationForm($inData);
        $this->makeSaveForm($inData);
        $this->makeAdvancedOptionsForm($inData);
        $this->standaloneView->assign('errors', $this->export->errorLog);
        $this->standaloneView->assign(
            'contentOverview',
            $this->export->displayContentOverview()
        );
        $this->standaloneView->setTemplate('Export.html');

        // Setting up the buttons and markers for docheader
        $this->getButtons();

        $this->moduleTemplate->setContent($this->standaloneView->render());
        return new HtmlResponse($this->moduleTemplate->renderContent());
    }

    /**
     * Process export preset
     *
     * @param array $inData
     */
    protected function processPresets(array &$inData): void
    {
        // Set exclude fields in export object:
        if (!is_array($inData['exclude'])) {
            $inData['exclude'] = [];
        }
        // Saving/Loading/Deleting presets:
        $this->presetRepository->processPresets($inData);
    }

    /**
     * Export part of module
     *
     * @param array $inData
     * @throws ExistingTargetFileNameException
     * @throws Exception
     */
    protected function exportData(array &$inData): void
    {
        // Create export object and configure it:
        $this->export = GeneralUtility::makeInstance(Export::class);
        $this->export->init();
        $this->export->excludeMap = (array)$inData['exclude'];
        $this->export->softrefCfg = (array)$inData['softrefCfg'];
        $this->export->extensionDependencies = ($inData['extension_dep'] === '') ? [] : (array)$inData['extension_dep'];
        $this->export->showStaticRelations = $inData['showStaticRelations'];
        $this->export->includeExtFileResources = !$inData['excludeHTMLfileResources'];
        $this->export->setExcludeDisabledRecords((bool)$inData['excludeDisabled']);
        if (!empty($inData['filetype'])) {
            $this->export->setExportFileType((string)$inData['filetype']);
        }
        $this->export->setExportFileName((string)$inData['filename']);

        // Static tables:
        if (is_array($inData['external_static']['tables'])) {
            $this->export->relStaticTables = $inData['external_static']['tables'];
        }
        // Configure which tables external relations are included for:
        if (is_array($inData['external_ref']['tables'])) {
            $this->export->relOnlyTables = $inData['external_ref']['tables'];
        }
        if (isset($inData['save_export'], $inData['saveFilesOutsideExportFile']) && $inData['saveFilesOutsideExportFile'] === '1') {
            $this->export->setSaveFilesOutsideExportFile(true);
        }

        if (is_array($inData['meta'])) {
            $this->export->setMeta($inData['meta']);
        }
        if (is_array($inData['record'])) {
            $this->export->setRecord($inData['record']);
        }
        if (is_array($inData['list'])) {
            $this->export->setList($inData['list']);
        }
        if (MathUtility::canBeInterpretedAsInteger($inData['pagetree']['id'])) {
            $this->export->setPid((int)$inData['pagetree']['id']);
        }
        if (MathUtility::canBeInterpretedAsInteger($inData['pagetree']['levels'])) {
            $this->export->setLevels((int)$inData['pagetree']['levels']);
        }
        if (is_array($inData['pagetree']['tables'])) {
            $this->export->setTables($inData['pagetree']['tables']);
        }

        $this->export->process();

        $inData['filename'] = $this->export->getExportFileName();

        // If the download button is clicked, return file
        if ($inData['download_export'] || $inData['save_export']) {
            // File content:
            $out = $this->export->compileMemoryToFileContent();

            // Filename:
            if ($this->export->getExportFileName() !== '') {
                $dlFile = $this->export->getExportFileName()
                            . $this->export->getFileExtensionByFileType();
            } else {
                $dlFile = $this->export->generateExportFileName($inData['download_export_name'])
                            . $this->export->getFileExtensionByFileType();
            }

            // Export for download:
            if ($inData['download_export']) {
                $mimeType = 'application/octet-stream';
                header('Content-Type: ' . $mimeType);
                header('Content-Length: ' . strlen($out));
                header('Content-Disposition: attachment; filename=' . PathUtility::basename($dlFile));
                echo $out;
                die;
            }

            // Export by saving:
            if ($inData['save_export']) {
                $lang = $this->getLanguageService();

                try {
                    $saveFile = $this->export->saveToFile($dlFile, $out);
                    /** @var FlashMessage $flashMessage */
                    $flashMessage = GeneralUtility::makeInstance(
                        FlashMessage::class,
                        sprintf($lang->getLL('exportdata_savedInSBytes'), $saveFile->getPublicUrl(), GeneralUtility::formatSize(strlen($out))),
                        $lang->getLL('exportdata_savedFile'),
                        FlashMessage::OK
                    );
                } catch (\Exception $e) {
                    $saveFolder = $this->export->getOrCreateDefaultImportExportFolder();
                    /** @var FlashMessage $flashMessage */
                    $flashMessage = GeneralUtility::makeInstance(
                        FlashMessage::class,
                        sprintf($lang->getLL('exportdata_badPathS'), $saveFolder->getPublicUrl()),
                        $lang->getLL('exportdata_problemsSavingFile'),
                        FlashMessage::ERROR
                    );
                }

                $flashMessageService = GeneralUtility::makeInstance(FlashMessageService::class);
                $defaultFlashMessageQueue = $flashMessageService->getMessageQueueByIdentifier();
                $defaultFlashMessageQueue->enqueue($flashMessage);
            }
        }
    }

    /**
     * Create configuration form
     *
     * @param array $inData Form configuration data
     */
    protected function makeConfigurationForm(array $inData): void
    {
        $nameSuggestion = '';
        // Page tree export options:
        if (MathUtility::canBeInterpretedAsInteger($inData['pagetree']['id'])) {
            $this->standaloneView->assign('treeHTML', $this->export->getTreeHTML());

            $opt = [
                -2 => $this->lang->getLL('makeconfig_tablesOnThisPage'),
                -1 => $this->lang->getLL('makeconfig_expandedTree'),
                0 => $this->lang->sL('LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:labels.depth_0'),
                1 => $this->lang->sL('LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:labels.depth_1'),
                2 => $this->lang->sL('LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:labels.depth_2'),
                3 => $this->lang->sL('LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:labels.depth_3'),
                4 => $this->lang->sL('LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:labels.depth_4'),
                999 => $this->lang->sL('LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:labels.depth_infi'),
            ];
            $this->standaloneView->assign('levelSelectOptions', $opt);
            $this->standaloneView->assign('tableSelectOptions', $this->getTableSelectOptions('pages'));
            $nameSuggestion .= 'tree_PID' . $inData['pagetree']['id'] . '_L' . $inData['pagetree']['levels'];
        }
        // Single record export:
        if (is_array($inData['record'])) {
            $records = [];
            foreach ($inData['record'] as $ref) {
                $rParts = explode(':', $ref);
                [$tName, $rUid] = $rParts;
                $nameSuggestion .= $tName . '_' . $rUid;
                $rec = BackendUtility::getRecordWSOL((string)$tName, (int)$rUid);
                if (!empty($rec)) {
                    $records[] = [
                        'icon' => $this->iconFactory->getIconForRecord($tName, $rec, Icon::SIZE_SMALL)->render(),
                        'title' => BackendUtility::getRecordTitle($tName, $rec, true),
                        'tableName' => $tName,
                        'recordUid' => $rUid
                    ];
                }
            }
            $this->standaloneView->assign('records', $records);
        }

        // Single tables/pids:
        if (is_array($inData['list'])) {
            // Display information about pages from which the export takes place
            $tableList = [];
            foreach ($inData['list'] as $reference) {
                $referenceParts = explode(':', $reference);
                $tableName = $referenceParts[0];
                if ($this->getBackendUser()->check('tables_select', $tableName)) {
                    // If the page is actually the root, handle it differently
                    // NOTE: we don't compare integers, because the number actually comes from the split string above
                    if ($referenceParts[1] === '0') {
                        $iconAndTitle = $this->iconFactory->getIcon('apps-pagetree-root', Icon::SIZE_SMALL)->render() . $GLOBALS['TYPO3_CONF_VARS']['SYS']['sitename'];
                    } else {
                        $record = BackendUtility::getRecordWSOL('pages', (int)$referenceParts[1]);
                        $iconAndTitle = $this->iconFactory->getIconForRecord('pages', $record, Icon::SIZE_SMALL)->render()
                            . BackendUtility::getRecordTitle('pages', $record, true);
                    }

                    $tableList[] = [
                        'iconAndTitle' => sprintf($this->lang->getLL('makeconfig_tableListEntry'), $tableName, $iconAndTitle),
                        'reference' => $reference
                    ];
                }
            }
            $this->standaloneView->assign('tableList', $tableList);
        }

        $this->standaloneView->assign('externalReferenceTableSelectOptions', $this->getTableSelectOptions());
        $this->standaloneView->assign('externalStaticTableSelectOptions', $this->getTableSelectOptions());
        $this->standaloneView->assign('nameSuggestion', $nameSuggestion);
    }

    /**
     * Create advanced options form
     *
     * @param array $inData Form configuration data
     */
    protected function makeAdvancedOptionsForm(array $inData): void
    {
        $loadedExtensions = ExtensionManagementUtility::getLoadedExtensionListArray();
        $loadedExtensions = array_combine($loadedExtensions, $loadedExtensions);
        $this->standaloneView->assign('extensions', $loadedExtensions);
        $this->standaloneView->assign('inData', $inData);
    }

    /**
     * Create configuration form
     *
     * @param array $inData Form configuration data
     */
    protected function makeSaveForm(array $inData): void
    {
        $opt = $this->presetRepository->getPresets((int)$inData['pagetree']['id']);

        $this->standaloneView->assign('presetSelectOptions', $opt);

        $saveFolder = $this->export->getOrCreateDefaultImportExportFolder();
        if ($saveFolder) {
            $this->standaloneView->assign('saveFolder', $saveFolder->getCombinedIdentifier());
        }

        // Add file options:
        $opt = [];
        foreach ($this->export->getSupportedFileTypes() as $supportedFileType) {
            $opt[$supportedFileType] = $this->lang->getLL('makesavefo_' . $supportedFileType);
        }
        $this->standaloneView->assign('filetypeSelectOptions', $opt);

        $fileName = '';
        if ($saveFolder) {
            $this->standaloneView->assign('saveFolder', $saveFolder->getPublicUrl());
            $this->standaloneView->assign('hasSaveFolder', true);
        }
        $this->standaloneView->assign('fileName', $fileName);
    }

    /**
     * Returns option array to be used in Fluid
     *
     * @param string $excludeList Table names (and the string "_ALL") to exclude. Comma list
     * @return array
     */
    protected function getTableSelectOptions(string $excludeList = ''): array
    {
        $optValues = [];
        if (!GeneralUtility::inList($excludeList, '_ALL')) {
            $optValues['_ALL'] = '[' . $this->lang->getLL('ALL_tables') . ']';
        }
        foreach ($GLOBALS['TCA'] as $table => $_) {
            if (!GeneralUtility::inList($excludeList, $table) && $this->getBackendUser()->check('tables_select', $table)) {
                $optValues[$table] = $table;
            }
        }
        return $optValues;
    }

    /**
     * @return BackendUserAuthentication
     */
    protected function getBackendUser(): BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'];
    }
}
