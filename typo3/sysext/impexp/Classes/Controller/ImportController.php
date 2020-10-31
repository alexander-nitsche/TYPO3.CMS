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
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Exception;
use TYPO3\CMS\Core\Http\HtmlResponse;
use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Resource\DuplicationBehavior;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\Filter\FileExtensionFilter;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Utility\File\ExtendedFileUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Impexp\Import;

/**
 * Main script class for the Import facility
 *
 * @internal this is a TYPO3 Backend controller implementation and not part of TYPO3's Core API.
 */
class ImportController extends ImportExportController
{
    const NO_UPLOAD = 0;
    const UPLOAD_DONE = 1;
    const UPLOAD_FAILED = 2;

    /**
     * The name of the module
     *
     * @var string
     */
    protected $moduleName = 'tx_impexp_import';

    /**
     * @var Import
     */
    protected $import;

    /**
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     * @throws Exception
     * @throws \TYPO3\CMS\Core\Resource\Exception
     * @throws \RuntimeException
     */
    public function mainAction(ServerRequestInterface $request): ResponseInterface
    {
        if ($this->isImportEnabled() === false) {
            throw new \RuntimeException(
                'Import module is disabled for non admin users and '
                . 'userTsConfig options.impexp.enableImportForNonAdminUser is not enabled.',
                1464435459
            );
        }

        parent::main($request);

        // Input data
        $inData = $request->getParsedBody()['tx_impexp'] ?? $request->getQueryParams()['tx_impexp'] ?? [];

        // Handle upload
        $this->handleUpload($request, $inData);

        // Perform import
        $this->importData($inData);

        // Prepare view
        $this->registerDocHeaderButtons();
        $this->makeForm();
        $this->standaloneView->assign('inData', $inData);
        $this->standaloneView->assign('isAdmin', $this->getBackendUser()->isAdmin());
        $this->standaloneView->setTemplate('Import.html');
        $this->moduleTemplate->setContent($this->standaloneView->render());

        return new HtmlResponse($this->moduleTemplate->renderContent());
    }

    /**
     * Check if import functionality is available for current user
     *
     * @return bool
     */
    protected function isImportEnabled(): bool
    {
        return $this->getBackendUser()->isAdmin()
            || (bool)($this->getBackendUser()->getTSConfig()['options.']['impexp.']['enableImportForNonAdminUser'] ?? false);
    }

    /**
     * Handle upload of an export file
     *
     * @param ServerRequestInterface $request
     * @param array $inData
     * @throws Exception
     * @throws \TYPO3\CMS\Core\Resource\Exception
     */
    protected function handleUpload(ServerRequestInterface $request, array &$inData): void
    {
        if ($request->getMethod() !== 'POST') {
            return;
        }

        $parsedBody = $request->getParsedBody() ?? [];

        if (empty($parsedBody)) {
            // This happens if the post request was larger than allowed on the server.
            $this->moduleTemplate->addFlashMessage(
                $this->lang->getLL('importdata_upload_nodata'),
                $this->lang->getLL('importdata_upload_error'),
                FlashMessage::ERROR
            );
            return;
        }

        $uploadStatus = self::NO_UPLOAD;

        if (isset($parsedBody['_upload'])) {
            $file = $parsedBody['file'];
            $conflictMode = empty($parsedBody['overwriteExistingFiles']) ? DuplicationBehavior::CANCEL : DuplicationBehavior::REPLACE;
            $fileProcessor = GeneralUtility::makeInstance(ExtendedFileUtility::class);
            $fileProcessor->setActionPermissions();
            $fileProcessor->setExistingFilesConflictMode(DuplicationBehavior::cast($conflictMode));
            $fileProcessor->start($file);
            $result = $fileProcessor->processData();
            // Finally: If upload went well, set the new file as the import file.
            if (isset($result['upload'][0][0])) {
                /** @var File $uploadedFile */
                $uploadedFile = $result['upload'][0][0];
                if (in_array($uploadedFile->getExtension(), ['t3d', 'xml'])) {
                    $inData['file'] = $uploadedFile->getCombinedIdentifier();
                }
                $this->standaloneView->assign('uploadedFile', $uploadedFile->getName());
                $uploadStatus = self::UPLOAD_DONE;
            } else {
                $uploadStatus = self::UPLOAD_FAILED;
            }
        }

        $this->standaloneView->assign('uploadStatus', $uploadStatus);
    }

    /**
     * Import part of module
     *
     * @param array $inData Content of POST VAR tx_impexp[]..
     * @throws \BadFunctionCallException
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     */
    protected function importData(array &$inData): void
    {
        if ($this->hasPageAccess()) {
            if ($inData['new_import']) {
                unset($inData['import_mode']);
            }

            $this->import = GeneralUtility::makeInstance(Import::class);
            $this->import->init();
            $this->import->setUpdate((bool)$inData['do_update']);
            $this->import->setImportMode((array)$inData['import_mode']);
            $this->import->setEnableLogging((bool)$inData['enableLogging']);
            $this->import->setGlobalIgnorePid((bool)$inData['global_ignore_pid']);
            $this->import->setForceAllUids((bool)$inData['force_all_UIDS']);
            $this->import->setShowDiff(!(bool)$inData['notShowDiff']);
            $this->import->setSoftrefInputValues((array)$inData['softrefInputValues']);

            // Perform import or preview depending:
            if (!empty($inData['file'])) {
                $filePath = $this->getFilePathWithinFileMountBoundaries((string)$inData['file']);
                if ($this->import->loadFile($filePath, true)) {
                    $importInhibitedMessages = $this->import->checkImportPrerequisites();
                    if ($inData['import_file']) {
                        if (empty($importInhibitedMessages)) {
                            $this->import->importData($this->id);
                            BackendUtility::setUpdateSignal('updatePageTree');
                        }
                    }
                    $this->import->setDisplayImportPidRecord($this->pageInfo);
                    $this->standaloneView->assign('metaDataInFileExists', true);
                    $this->standaloneView->assign('contentOverview', $this->import->displayContentOverview());
                    // Compile messages which are inhibiting a proper import and add them to output.
                    if (!empty($importInhibitedMessages)) {
                        $flashMessageQueue = GeneralUtility::makeInstance(FlashMessageService::class)->getMessageQueueByIdentifier('impexp.errors');
                        foreach ($importInhibitedMessages as $message) {
                            $flashMessageQueue->addMessage(GeneralUtility::makeInstance(
                                FlashMessage::class,
                                $message,
                                '',
                                FlashMessage::ERROR
                            ));
                        }
                    }
                }
            }
        }
    }

    protected function getFilePathWithinFileMountBoundaries(string $filePath): string
    {
        try {
            $file = GeneralUtility::makeInstance(ResourceFactory::class)->getFileObjectFromCombinedIdentifier($filePath);
            return $file->getForLocalProcessing(false);
        } catch (\Exception $exception) {
            return '';
        }
    }

    protected function registerDocHeaderButtons(): void
    {
        parent::registerDocHeaderButtons();

        $buttonBar = $this->moduleTemplate->getDocHeaderComponent()->getButtonBar();
        if ($this->hasPageAccess()) {
            if ($this->pageInfo['uid']) {
                // View
                $onClick = BackendUtility::viewOnClick(
                    $this->pageInfo['uid'],
                    '',
                    BackendUtility::BEgetRootLine($this->pageInfo['uid'])
                );
                $viewButton = $buttonBar->makeLinkButton()
                    ->setTitle($this->lang->sL('LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:labels.showPage'))
                    ->setHref('#')
                    ->setIcon($this->iconFactory->getIcon('actions-view-page', Icon::SIZE_SMALL))
                    ->setOnClick($onClick);
                $buttonBar->addButton($viewButton);
            }
        }
    }

    /**
     * Create module forms
     */
    protected function makeForm(): void
    {
        if ($this->hasPageAccess()) {
            $selectOptions = [''];
            foreach ($this->getExportFiles() as $file) {
                $selectOptions[$file->getCombinedIdentifier()] = $file->getPublicUrl();
            }

            $importFolder = $this->import->getOrCreateDefaultImportExportFolder();
            if ($importFolder) {
                $this->standaloneView->assign('importFolder', $importFolder->getCombinedIdentifier());
                $this->standaloneView->assign('importFolderHint', sprintf(
                    $this->lang->getLL('importdata_fromPathS'), $importFolder->getCombinedIdentifier())
                );
            } else {
                $this->standaloneView->assign('importFolderHint',
                    $this->lang->getLL('importdata_no_default_upload_folder')
                );
            }

            $this->standaloneView->assign('fileSelectOptions', $selectOptions);
            $this->standaloneView->assign('import', $this->import);
            $this->standaloneView->assign('errors', $this->import->getErrorLog());
        }
    }

    /**
     * Gets all export files.
     *
     * @return File[]
     * @throws \InvalidArgumentException
     */
    protected function getExportFiles(): array
    {
        $exportFiles = [];

        $folder = $this->import->getOrCreateDefaultImportExportFolder();
        if ($folder !== null) {

            /** @var FileExtensionFilter $filter */
            $filter = GeneralUtility::makeInstance(FileExtensionFilter::class);
            $filter->setAllowedFileExtensions(['t3d', 'xml']);
            $folder->getStorage()->addFileAndFolderNameFilter([$filter, 'filterFileList']);

            $exportFiles = $folder->getFiles();
        }

        return $exportFiles;
    }

    /**
     * Gets a file by combined identifier.
     *
     * @param string $combinedIdentifier
     * @return File|null
     */
    protected function getFile(string $combinedIdentifier): ?File
    {
        try {
            $file = GeneralUtility::makeInstance(ResourceFactory::class)->getFileObjectFromCombinedIdentifier($combinedIdentifier);
        } catch (\Exception $exception) {
            $file = null;
        }

        return $file;
    }
}
