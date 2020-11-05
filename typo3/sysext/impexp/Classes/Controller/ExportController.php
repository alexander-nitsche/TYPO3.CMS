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
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Backend\Tree\View\PageTreeView;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Exception;
use TYPO3\CMS\Core\Http\HtmlResponse;
use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Page\PageRenderer;
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
     * @var string
     */
    protected $moduleName = 'tx_impexp_export';

    /**
     * @var Export
     */
    protected $export;

    /**
     * @var PresetRepository
     */
    protected $presetRepository;

    public function __construct(
        IconFactory $iconFactory,
        PageRenderer $pageRenderer,
        UriBuilder $uriBuilder,
        ModuleTemplateFactory $moduleTemplateFactory
    ) {
        parent::__construct($iconFactory, $pageRenderer, $uriBuilder, $moduleTemplateFactory);
        $this->presetRepository = GeneralUtility::makeInstance(PresetRepository::class);
    }

    /**
     * Incoming array has syntax:
     *
     * file[] = file
     * dir[] = dir
     * list[] = table:pid
     * record[] = table:uid
     *
     * pagetree[id] = (single id)
     * pagetree[levels]=1,2,3, -1 = currently unpacked tree, -2 = only tables on page
     * pagetree[tables][]=table/_ALL
     *
     * external_ref[tables][]=table/_ALL
     *
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     * @throws Exception
     * @throws ExistingTargetFileNameException
     */
    public function mainAction(ServerRequestInterface $request): ResponseInterface
    {
        parent::main($request);

        // Input data
        $inData = $request->getParsedBody()['tx_impexp'] ?? $request->getQueryParams()['tx_impexp'] ?? [];

        // Perform export
        $this->processPresets($inData);
        $this->exportData($inData);

        // Prepare view
        $this->registerDocHeaderButtons();
        $this->makeConfigurationForm($inData);
        $this->makeSaveForm($inData);
        $this->makeAdvancedOptionsForm();
        $this->standaloneView->assign('inData', $inData);
        $this->standaloneView->setTemplate('Export.html');
        $this->moduleTemplate->setContent($this->standaloneView->render());

        return new HtmlResponse($this->moduleTemplate->renderContent());
    }

    /**
     * Process export preset
     *
     * @param array $inData
     */
    public function processPresets(array &$inData): void
    {
        // Flag doesn't exist initially; state is on by default
        if (!array_key_exists('excludeDisabled', $inData)) {
            $inData['excludeDisabled'] = 1;
        }
        // Set exclude fields in export object:
        $inData['exclude'] ??= [];
        // Saving / Loading / Deleting presets:
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
        $this->export->setExcludeMap((array)($inData['exclude'] ?? []));
        $this->export->setSoftrefCfg((array)($inData['softrefCfg'] ?? []));
        $this->export->setExtensionDependencies((($inData['extension_dep'] ?? '') === '') ? [] : (array)$inData['extension_dep']);
        $this->export->setShowStaticRelations($inData['showStaticRelations'] ?? false);
        $this->export->setIncludeExtFileResources(!($inData['excludeHTMLfileResources'] ?? false));
        $this->export->setExcludeDisabledRecords((bool)($inData['excludeDisabled'] ?? false));
        if (!empty($inData['filetype'])) {
            $this->export->setExportFileType((string)$inData['filetype']);
        }
        $this->export->setExportFileName((string)$inData['filename']);

        // Static tables:
        if (is_array($inData['external_static']['tables'] ?? null)) {
            $this->export->setRelStaticTables($inData['external_static']['tables']);
        }
        // Configure which tables external relations are included for:
        if (is_array($inData['external_ref']['tables'] ?? null)) {
            $this->export->setRelOnlyTables($inData['external_ref']['tables']);
        }
        if (isset($inData['save_export'], $inData['saveFilesOutsideExportFile']) && $inData['saveFilesOutsideExportFile'] === '1') {
            $this->export->setSaveFilesOutsideExportFile(true);
        }
        if (is_array($inData['meta'])) {
            if (isset($inData['meta']['title'])) {
                $this->export->setTitle($inData['meta']['title']);
            }
            if (isset($inData['meta']['description'])) {
                $this->export->setDescription($inData['meta']['description']);
            }
            if (isset($inData['meta']['notes'])) {
                $this->export->setNotes($inData['meta']['notes']);
            }
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

        // Perform export:
        if (($inData['download_export'] ?? null) || ($inData['save_export'] ?? null)) {

            // Export by download:
            if ($inData['download_export'] ?? null) {
                $fileName = $this->export->getOrGenerateExportFileNameWithFileExtension();
                $fileContent = $this->export->render();
                $mimeType = 'application/octet-stream';
                header('Content-Type: ' . $mimeType);
                header('Content-Length: ' . strlen($fileContent));
                header('Content-Disposition: attachment; filename=' . PathUtility::basename($fileName));
                echo $fileContent;
                die;
            }

            // Export by saving on server:
            if ($inData['save_export'] ?? null) {
                try {
                    $saveFile = $this->export->saveToFile();
                    $saveFileSize = $saveFile->getProperty('size');
                    $this->moduleTemplate->addFlashMessage(
                        sprintf($this->lang->getLL('exportdata_savedInSBytes'), $saveFile->getPublicUrl(), GeneralUtility::formatSize($saveFileSize)),
                        $this->lang->getLL('exportdata_savedFile')
                    );
                } catch (\Exception $e) {
                    $saveFolder = $this->export->getOrCreateDefaultImportExportFolder();
                    $this->moduleTemplate->addFlashMessage(
                        sprintf($this->lang->getLL('exportdata_badPathS'), $saveFolder->getPublicUrl()),
                        $this->lang->getLL('exportdata_problemsSavingFile'),
                        FlashMessage::ERROR
                    );
                }
            }
        }

        $this->standaloneView->assign('errors', $this->export->getErrorLog());
        $this->standaloneView->assign('contentOverview', $this->export->displayContentOverview());
    }

    /**
     * Create configuration form
     *
     * @param array $inData Form configuration data
     */
    protected function makeConfigurationForm(array $inData): void
    {
        // Page tree export:
        if (MathUtility::canBeInterpretedAsInteger($inData['pagetree']['id'] ?? '')) {
            $opt = [
                Export::LEVELS_RECORDS_ON_THIS_PAGE => $this->lang->getLL('makeconfig_tablesOnThisPage'),
                Export::LEVELS_EXPANDED_TREE => $this->lang->getLL('makeconfig_expandedTree'),
                0 => $this->lang->sL('LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:labels.depth_0'),
                1 => $this->lang->sL('LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:labels.depth_1'),
                2 => $this->lang->sL('LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:labels.depth_2'),
                3 => $this->lang->sL('LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:labels.depth_3'),
                4 => $this->lang->sL('LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:labels.depth_4'),
                Export::LEVELS_INFINITE => $this->lang->sL('LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:labels.depth_infi'),
            ];
            $this->standaloneView->assign('levelSelectOptions', $opt);
            $this->standaloneView->assign('tableSelectOptions', $this->getTableSelectOptions('pages'));
            $this->standaloneView->assign('treeHTML', $this->export->getTreeHTML());
        }

        // Single records export:
        if (is_array($inData['record'] ?? null)) {
            $records = [];
            foreach ($inData['record'] as $ref) {
                $rParts = explode(':', $ref);
                [$tName, $rUid] = $rParts;
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

        // Single tables export:
        if (is_array($inData['list'] ?? false)) {
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
    }

    /**
     * Create advanced options form
     */
    protected function makeAdvancedOptionsForm(): void
    {
        $loadedExtensions = ExtensionManagementUtility::getLoadedExtensionListArray();
        $loadedExtensions = array_combine($loadedExtensions, $loadedExtensions);
        $this->standaloneView->assign('extensions', $loadedExtensions);
    }

    /**
     * Create configuration form
     *
     * @param array $inData Form configuration data
     */
    protected function makeSaveForm(array $inData): void
    {
        $presetOptions = $this->presetRepository->getPresets((int)($inData['pagetree']['id'] ?? 0));

        $fileTypeOptions = [];
        foreach ($this->export->getSupportedFileTypes() as $supportedFileType) {
            $fileTypeOptions[$supportedFileType] = $this->lang->getLL('makesavefo_' . $supportedFileType);
        }

        $saveFolder = $this->export->getOrCreateDefaultImportExportFolder();
        if ($saveFolder) {
            $this->standaloneView->assign('saveFolder', $saveFolder->getPublicUrl());
            $this->standaloneView->assign('hasSaveFolder', true);
        }

        $this->standaloneView->assign('fileName', '');
        $this->standaloneView->assign('presetSelectOptions', $presetOptions);
        $this->standaloneView->assign('filetypeSelectOptions', $fileTypeOptions);
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
}
