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

namespace TYPO3\CMS\Impexp;

use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Configuration\FlexForm\FlexFormTools;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Exception;
use TYPO3\CMS\Core\Resource\Exception\InsufficientFolderAccessPermissionsException;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Resource\ResourceStorage;
use TYPO3\CMS\Core\Resource\Security\FileNameValidator;
use TYPO3\CMS\Core\Resource\StorageRepository;
use TYPO3\CMS\Core\Service\FlexFormService;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\MathUtility;
use TYPO3\CMS\Core\Utility\PathUtility;
use TYPO3\CMS\Core\Utility\StringUtility;
use TYPO3\CMS\Impexp\Exception\ImportFailedException;
use TYPO3\CMS\Impexp\Exception\LoadingFileFailedException;
use TYPO3\CMS\Impexp\Exception\PrerequisitesNotMetException;

/**
 * T3D file Import library (TYPO3 Record Document)
 *
 * @internal This class is not considered part of the public TYPO3 API.
 */
class Import extends ImportExport
{
    /**
     * @var string
     */
    protected $mode = 'import';

    /**
     * Used to register the forged UID values for imported records that we want
     * to create with the same UIDs as in the import file. Admin-only feature.
     *
     * @var array
     */
    protected $suggestedInsertUids = [];

    /**
     * Disable logging when importing
     *
     * @var bool
     */
    protected $enableLogging = false;

    /**
     * Keys are [tablename]:[new NEWxxx ids (or when updating it is uids)]
     * while values are arrays with table/uid of the original record it is based on.
     * With the array keys the new ids can be looked up inside DataHandler
     *
     * @var array
     */
    protected $importNewId = [];

    /**
     * Page id map for page tree (import)
     *
     * @var array
     */
    protected $importNewIdPids = [];

    /**
     * Internal data accumulation for writing records during import
     *
     * @var array
     */
    protected $importData = [];

    /**
     * Temporary files stack
     *
     * @var array
     */
    protected $unlinkFiles = [];

    /**
     * @var bool
     */
    protected $decompressionAvailable = false;

    /**
     * @var array
     */
    private $supportedFileExtensions = [];

    /**
     * The constructor
     */
    public function __construct()
    {
        parent::__construct();
        $this->decompressionAvailable = function_exists('gzuncompress');
    }

    /**************************
     * File Input
     *************************/

    /**
     * Loads the TYPO3 import file $fileName into memory.
     *
     * @param string $fileName File path, has to be within the TYPO3's base folder
     * @param bool $all If set, all information is loaded (header, records and files). Otherwise the default is to read only the header information
     * @throws LoadingFileFailedException
     */
    public function loadFile(string $fileName, bool $all = false): void
    {
        $filePath = GeneralUtility::getFileAbsFileName($fileName);

        if (empty($filePath)) {
            $this->addError('File path is not valid: ' . $fileName);
        } elseif (!@is_file($filePath)) {
            $this->addError('File not found: ' . $filePath);
        }

        if ($this->hasErrors()) {
            throw new LoadingFileFailedException(
                sprintf('Loading of the import file "%s" failed.', $fileName), 1484484619
            );
        }

        $pathInfo = pathinfo($filePath);
        $fileExtension = strtolower($pathInfo['extension']);

        if (!in_array($fileExtension, $this->getSupportedFileExtensions())) {
            $this->addError(
                sprintf(
                    'File extension "%s" is not valid. Supported file extensions are %s.',
                    $fileExtension,
                    implode(', ', array_map(function($supportedFileExtension){
                        return '"' . $supportedFileExtension . '"';
                    }, $this->getSupportedFileExtensions()))
                )
            );
        }

        if ($this->hasErrors() === false) {
            if (@is_dir($filePath . '.files')) {
                if (GeneralUtility::isAllowedAbsPath($filePath . '.files')) {
                    // copy the folder lowlevel to typo3temp, because the files would be deleted after import
                    GeneralUtility::copyDirectory($filePath . '.files', $this->getOrCreateTemporaryFolderName());
                } else {
                    $this->addError('External import files for the given import source is currently not supported.');
                }
            }
            if ($fileExtension === 'xml') {
                $xmlContent = (string)file_get_contents($filePath);
                if (strlen($xmlContent)) {
                    $this->dat = GeneralUtility::xml2array($xmlContent, '', true);
                    if (is_array($this->dat)) {
                        if ($this->dat['_DOCUMENT_TAG'] === 'T3RecordDocument' && is_array($this->dat['header']) && is_array($this->dat['records'])) {
                            $this->loadInit();
                        } else {
                            $this->addError('XML file did not contain proper XML for TYPO3 Import');
                        }
                    } else {
                        $this->addError('XML could not be parsed: ' . $this->dat);
                    }
                } else {
                    $this->addError('Error opening file: ' . $filePath);
                }
            } elseif ($fileExtension === 't3d') {
                if ($fd = fopen($filePath, 'rb')) {
                    $this->dat['header'] = $this->getNextFilePart($fd, true, 'header');
                    if ($all) {
                        $this->dat['records'] = $this->getNextFilePart($fd, true, 'records');
                        $this->dat['files'] = $this->getNextFilePart($fd, true, 'files');
                        $this->dat['files_fal'] = $this->getNextFilePart($fd, true, 'files_fal');
                    }
                    $this->loadInit();
                    fclose($fd);
                } else {
                    $this->addError('Error opening file: ' . $filePath);
                }
            }
        }

        if ($this->hasErrors()) {
            throw new LoadingFileFailedException(
                sprintf('Loading of the import file "%s" failed.', $fileName), 1484484619
            );
        }
    }

    /**
     * @return array
     */
    public function getSupportedFileExtensions(): array
    {
        if (empty($this->supportedFileExtensions)) {
            $supportedFileExtensions = [];
            $supportedFileExtensions[] = 'xml';
            $supportedFileExtensions[] = 't3d';
            $this->supportedFileExtensions = $supportedFileExtensions;
        }
        return $this->supportedFileExtensions;
    }

    /**
     * Extracts the next content part of the T3D file
     *
     * @param resource $fd Import file pointer
     * @param bool $unserialize If set, the returned content is deserialized into an array, otherwise you get the raw string
     * @param string $name For error messages this indicates the section of the problem.
     * @return string|null Data string or NULL in case of an error
     *
     * @see loadFile()
     */
    protected function getNextFilePart($fd, bool $unserialize = false, string $name = ''): ?string
    {
        $headerLength = 32 + 1 + 1 + 1 + 10 + 1;
        $headerString = fread($fd, $headerLength);
        if (empty($headerString)) {
            $this->addError('File does not contain data for "' . $name . '"');
            return null;
        }

        $header = explode(':', $headerString);
        if (strpos($header[0], 'Warning') !== false) {
            $this->addError('File read error: Warning message in file. (' . $headerString . fgets($fd) . ')');
            return null;
        }
        if ((string)$header[3] !== '') {
            $this->addError('File read error: InitString had a wrong length. (' . $name . ')');
            return null;
        }

        $dataString = (string)fread($fd, (int)$header[2]);
        $isDataCompressed = $header[1] === '1';
        fread($fd, 1);
        if (!hash_equals($header[0], md5($dataString))) {
            $this->addError('MD5 check failed (' . $name . ')');
            return null;
        }

        if ($isDataCompressed) {
            if ($this->decompressionAvailable) {
                $dataString = (string)gzuncompress($dataString);
            } else {
                $this->addError('Content read error: This file requires decompression, ' .
                    'but this server does not offer gzcompress()/gzuncompress() functions.');
                return null;
            }
        }

        return $unserialize ? (string)unserialize($dataString, ['allowed_classes' => false]) : $dataString;
    }

    /**
     * Setting up the object based on the recently loaded ->dat array
     */
    protected function loadInit(): void
    {
        $this->relStaticTables = (array)$this->dat['header']['relStaticTables'];
        $this->excludeMap = (array)$this->dat['header']['excludeMap'];
        $this->softrefCfg = (array)$this->dat['header']['softrefCfg'];
    }

    public function getMetaData(): array
    {
        return $this->dat['header']['meta'] ?? [];
    }

    /***********************
     * Import
     ***********************/

    /**
     * Checks all requirements that must be met before import.
     *
     * @throws PrerequisitesNotMetException
     */
    public function checkImportPrerequisites(): void
    {
        // Check #1: Extension dependencies
        $extKeysToInstall = [];
        foreach ($this->dat['header']['extensionDependencies'] as $extKey) {
            if (!empty($extKey) && !ExtensionManagementUtility::isLoaded($extKey)) {
                $extKeysToInstall[] = $extKey;
            }
        }
        if (!empty($extKeysToInstall)) {
            $this->addError(
                sprintf(
                    'Before you can import this file you need to install the extensions "%s".',
                    implode('", "', $extKeysToInstall)
                )
            );
        }

        // Check #2: Presence of imported storage paths
        if (!empty($this->dat['header']['records']['sys_file_storage'])) {
            foreach ($this->dat['header']['records']['sys_file_storage'] as $sysFileStorageUid => &$_) {
                $storageRecord = &$this->dat['records']['sys_file_storage:' . $sysFileStorageUid]['data'];
                if ($storageRecord['driver'] === 'Local'
                    && $storageRecord['is_writable']
                    && $storageRecord['is_online']
                ) {
                    $storageMapUid = -1;
                    foreach ($this->storages as &$storage) {
                        if ($this->isEquivalentStorage($storage, $storageRecord)) {
                            $storageMapUid = $storage->getUid();
                            break;
                        }
                    }
                    // The storage from the import does not have an equivalent storage
                    // in the current instance (same driver, same path, etc.). Before
                    // the storage record can get inserted later on take care the path
                    // it points to really exists and is accessible.
                    if ($storageMapUid === -1) {
                        // Unset the storage record UID when trying to create the storage object
                        // as the record does not already exist in DB. The constructor of the
                        // storage object will check whether the target folder exists and set the
                        // isOnline flag depending on the outcome.
                        $storageRecordWithUid0 = $storageRecord;
                        $storageRecordWithUid0['uid'] = 0;
                        $storageObject = $this->getStorageRepository()->createStorageObject($storageRecordWithUid0);
                        if (!$storageObject->isOnline()) {
                            $configuration = $storageObject->getConfiguration();
                            $this->addError(
                                sprintf(
                                    'The file storage "%s" does not exist. ' .
                                    'Please create the directory prior to starting the import!',
                                    $storageObject->getName() . $configuration['basePath']
                                )
                            );
                        }
                    }
                }
            }
        }

        if ($this->hasErrors()) {
            throw new PrerequisitesNotMetException(
                'Prerequisites for file import are not met.', 1484484612
            );
        }
    }

    /**
     * Imports the memory data into the TYPO3 database.
     *
     * @throws ImportFailedException
     */
    public function importData(): void
    {
        $this->initializeImport();

        // Write sys_file_storages first
        $this->writeSysFileStorageRecords();
        // Write sys_file records and write the binary file data
        $this->writeSysFileRecords();
        // Write records, first pages, then the rest
        // Fields with "hard" relations to database, files and flexform fields are kept empty during this run
        $this->writeRecordsPages();
        $this->writeRecordsRecords();
        // Finally all the file and DB record references must be fixed. This is done after all records have supposedly
        // been written to database. $this->importMapId will indicate two things:
        // 1) that a record WAS written to db and
        // 2) that it has got a new id-number.
        $this->setRelations();
        // And when all DB relations are in place, we can fix file and DB relations in flexform fields
        // - since data structures often depend on relations to a DS record:
        $this->setFlexFormRelations();
        // Unlink temporary files:
        $this->unlinkTempFiles();
        // Finally, traverse all records and process soft references with substitution attributes.
        $this->processSoftReferences();

        if ($this->hasErrors()) {
            throw new ImportFailedException('The import has failed.', 1484484613);
        }
    }

    /**
     * Initialize all settings for the import
     */
    protected function initializeImport(): void
    {
        $this->doesImport = 1;
        $this->importMapId = [];
        $this->importNewId = [];
        $this->importNewIdPids = [];
        $this->unlinkFiles = [];
    }

    /**
     * Imports the sys_file_storage records from memory data.
     */
    protected function writeSysFileStorageRecords(): void
    {
        if (!isset($this->dat['header']['records']['sys_file_storage'])) {
            return;
        }

        $sysFileStorageUidsToBeResetToDefaultStorage = [];

        foreach ($this->dat['header']['records']['sys_file_storage'] as $sysFileStorageUid => &$_) {
            $storageRecord = &$this->dat['records']['sys_file_storage:' . $sysFileStorageUid]['data'];
            if ($storageRecord['driver'] === 'Local'
                && $storageRecord['is_writable']
                && $storageRecord['is_online']
            ) {
                foreach ($this->storages as &$storage) {
                    if ($this->isEquivalentStorage($storage, $storageRecord)) {
                        $this->importMapId['sys_file_storage'][$sysFileStorageUid] = $storage->getUid();
                        break;
                    }
                }

                if (!isset($this->importMapId['sys_file_storage'][$sysFileStorageUid])) {
                    // Local, writable and online storage. May be used later for writing files.
                    // Does not currently exist, mark the storage for import.
                    $this->addSingle('sys_file_storage', $sysFileStorageUid, 0);
                }
            } else {
                // Storage with non-local drivers can be imported, but must not be used to save files as you cannot
                // be sure that this is supported. In this case the default storage is used. Non-writable and
                // non-online storage may be created as duplicates because you were unable to check the detailed
                // configuration options at that time.
                $this->addSingle('sys_file_storage', $sysFileStorageUid, 0);
                $sysFileStorageUidsToBeResetToDefaultStorage[] = $sysFileStorageUid;
            }
        }

        // Now write to database
        $tce = $this->getNewTCE();
        // Because all records are submitted in the correct order with positive pid numbers,
        // we should internally reverse the order of submission.
        $tce->reverseOrder = 1;
        $tce->isImporting = true;
        $tce->start($this->importData, []);
        $tce->process_datamap();
        $this->addToMapId($tce->substNEWwithIDs);

        $defaultStorageUid = $this->defaultStorage !== null ? $this->defaultStorage->getUid() : null;
        foreach ($sysFileStorageUidsToBeResetToDefaultStorage as $sysFileStorageUidToBeResetToDefaultStorage) {
            $this->importMapId['sys_file_storage'][$sysFileStorageUidToBeResetToDefaultStorage] = $defaultStorageUid;
        }

        // Unset the sys_file_storage records to prevent an import in writeRecordsRecords()
        unset($this->dat['header']['records']['sys_file_storage']);
    }

    /**
     * Determines whether the passed storage object and the storage record (sys_file_storage) can be considered
     * equivalent during the import.
     *
     * @param ResourceStorage $storageObject The storage object which should get compared
     * @param array $storageRecord The storage record which should get compared
     * @return bool Returns TRUE if both storage representations can be considered equal
     */
    protected function isEquivalentStorage(ResourceStorage &$storageObject, array &$storageRecord): bool
    {
        if ($storageObject->getDriverType() === $storageRecord['driver']
            && (bool)$storageObject->isWritable() === (bool)$storageRecord['is_writable']
            && (bool)$storageObject->isOnline() === (bool)$storageRecord['is_online']
        ) {
            $storageRecordConfiguration = GeneralUtility::makeInstance(FlexFormService::class)
                ->convertFlexFormContentToArray($storageRecord['configuration'] ?? '');
            $storageObjectConfiguration = $storageObject->getConfiguration();
            if ($storageRecordConfiguration['pathType'] === $storageObjectConfiguration['pathType']
                && $storageRecordConfiguration['basePath'] === $storageObjectConfiguration['basePath']
            ) {
                return true;
            }
        }
        return false;
    }

    /**
     * Imports the sys_file records and the binary files data from internal data array.
     */
    protected function writeSysFileRecords(): void
    {
        if (!isset($this->dat['header']['records']['sys_file'])) {
            return;
        }
        $this->addGeneralErrorsByTable('sys_file');

        $this->fetchStorages();

        $sanitizedFolderMappings = [];

        foreach ($this->dat['header']['records']['sys_file'] as $sysFileUid => $_) {
            $fileRecord = $this->dat['records']['sys_file:' . $sysFileUid]['data'];

            $temporaryFile = null;
            $temporaryFolder = $this->getTemporaryFolderName();
            // check if there is the right file already in the local folder
            if ($temporaryFolder !== null) {
                if (is_file($temporaryFolder . '/' . $fileRecord['sha1']) && sha1_file($temporaryFolder . '/' . $fileRecord['sha1']) === $fileRecord['sha1']) {
                    $temporaryFile = $temporaryFolder . '/' . $fileRecord['sha1'];
                }
            }

            // save file to disk
            if ($temporaryFile === null) {
                $fileId = md5($fileRecord['storage'] . ':' . $fileRecord['identifier_hash']);
                $temporaryFile = $this->writeTemporaryFileFromData($fileId);
                if ($temporaryFile === null) {
                    // error on writing the file. Error message was already added
                    continue;
                }
            }

            $originalStorageUid = $fileRecord['storage'];
            $useStorageFromStorageRecords = false;

            // replace storage id, if an alternative one was registered
            if (isset($this->importMapId['sys_file_storage'][$fileRecord['storage']])) {
                $fileRecord['storage'] = $this->importMapId['sys_file_storage'][$fileRecord['storage']];
                $useStorageFromStorageRecords = true;
            }

            if (empty($fileRecord['storage']) && !$this->isFallbackStorage($fileRecord['storage'])) {
                // no storage for the file is defined, mostly because of a missing default storage.
                $this->addError('Error: No storage for the file "' . $fileRecord['identifier'] . '" with storage uid "' . $originalStorageUid . '"');
                continue;
            }

            // using a storage from the local storage is only allowed, if the uid is present in the
            // mapping. Only in this case we could be sure, that it's a local, online and writable storage.
            if ($useStorageFromStorageRecords && isset($this->storages[$fileRecord['storage']])) {
                $storage = $this->storages[$fileRecord['storage']];
            } elseif ($this->isFallbackStorage($fileRecord['storage'])) {
                $storage = $this->getStorageRepository()->findByUid(0);
            } elseif ($this->defaultStorage !== null) {
                $storage = $this->defaultStorage;
            } else {
                $this->addError('Error: No storage available for the file "' . $fileRecord['identifier'] . '" with storage uid "' . $fileRecord['storage'] . '"');
                continue;
            }

            $newFile = null;

            // check, if there is an identical file
            try {
                if ($storage->hasFile($fileRecord['identifier'])) {
                    /** @var File $file */
                    $file = $storage->getFile($fileRecord['identifier']);
                    if ($file->getSha1() === $fileRecord['sha1']) {
                        $newFile = $file;
                    }
                }
            } catch (Exception $e) {
            }

            if ($newFile === null) {
                $folderName = PathUtility::dirname(ltrim($fileRecord['identifier'], '/'));
                if (in_array($folderName, $sanitizedFolderMappings)) {
                    $folderName = $sanitizedFolderMappings[$folderName];
                }
                if (!$storage->hasFolder($folderName)) {
                    try {
                        $importFolder = $storage->createFolder($folderName);
                        if ($importFolder->getIdentifier() !== $folderName && !in_array($folderName, $sanitizedFolderMappings)) {
                            $sanitizedFolderMappings[$folderName] = $importFolder->getIdentifier();
                        }
                    } catch (Exception $e) {
                        $this->addError('Error: Folder "' . $folderName . '" could not be created for file "' . $fileRecord['identifier'] . '" with storage uid "' . $fileRecord['storage'] . '"');
                        continue;
                    }
                } else {
                    $importFolder = $storage->getFolder($folderName);
                }

                $this->callHook('before_addSysFileRecord', [
                    'fileRecord' => $fileRecord,
                    'importFolder' => $importFolder,
                    'temporaryFile' => $temporaryFile
                ]);

                try {
                    /** @var File $newFile */
                    $newFile = $storage->addFile($temporaryFile, $importFolder, $fileRecord['name']);
                } catch (Exception $e) {
                    $this->addError('Error: File could not be added to the storage: "' . $fileRecord['identifier'] . '" with storage uid "' . $fileRecord['storage'] . '"');
                    continue;
                }

                if ($newFile->getSha1() !== $fileRecord['sha1']) {
                    $this->addError('Error: The hash of the written file is not identical to the import data! File could be corrupted! File: "' . $fileRecord['identifier'] . '" with storage uid "' . $fileRecord['storage'] . '"');
                }
            }

            // save the new uid in the import id map
            $this->importMapId['sys_file'][$fileRecord['uid']] = $newFile->getUid();
            $this->fixUidLocalInSysFileReferenceRecords((int)$fileRecord['uid'], $newFile->getUid());
        }

        // unset the sys_file records to prevent an import in writeRecordsRecords
        unset($this->dat['header']['records']['sys_file']);
        // remove all sys_file_reference records that point to file records which are unknown
        // in the system to prevent exceptions
        $this->removeSysFileReferenceRecordsFromImportDataWithRelationToMissingFile();
    }

    /**
     * Removes all sys_file_reference records from the import data array that are pointing to sys_file records which
     * are missing not in the import data to prevent exceptions on checking the related file started by the Datahandler.
     */
    protected function removeSysFileReferenceRecordsFromImportDataWithRelationToMissingFile(): void
    {
        if (!isset($this->dat['header']['records']['sys_file_reference'])) {
            return;
        }

        foreach ($this->dat['header']['records']['sys_file_reference'] as $sysFileReferenceUid => $_) {
            $fileReferenceRecord = $this->dat['records']['sys_file_reference:' . $sysFileReferenceUid]['data'];
            if (!in_array($fileReferenceRecord['uid_local'], $this->importMapId['sys_file'])) {
                unset($this->dat['header']['records']['sys_file_reference'][$sysFileReferenceUid]);
                unset($this->dat['records']['sys_file_reference:' . $sysFileReferenceUid]);
                $this->addError(
                    'Error: sys_file_reference record ' . (int)$sysFileReferenceUid
                    . ' with relation to sys_file record ' . (int)$fileReferenceRecord['uid_local']
                    . ', which is not part of the import data, was not imported.'
                );
            }
        }
    }

    /**
     * Checks if the $storageId is the id of the fallback storage
     *
     * @param int|string $storageId
     * @return bool
     */
    protected function isFallbackStorage($storageId): bool
    {
        return $storageId === 0 || $storageId === '0';
    }

    /**
     * Normally the importer works like the following:
     * Step 1: import the records with cleared field values of relation fields (see addSingle())
     * Step 2: update the records with the right relation ids (see setRelations())
     *
     * In step 2 the saving fields of type "relation to sys_file_reference" checks the related sys_file_reference
     * record (created in step 1) with the FileExtensionFilter for matching file extensions of the related file.
     * To make this work correct, the uid_local of sys_file_reference records has to be not empty AND has to
     * relate to the correct (imported) sys_file record uid!!!
     *
     * This is fixed here.
     *
     * @param int $oldFileUid
     * @param int $newFileUid
     */
    protected function fixUidLocalInSysFileReferenceRecords(int $oldFileUid, int $newFileUid): void
    {
        if (!isset($this->dat['header']['records']['sys_file_reference'])) {
            return;
        }

        foreach ($this->dat['header']['records']['sys_file_reference'] as $sysFileReferenceUid => $_) {
            if (!isset($this->dat['records']['sys_file_reference:' . $sysFileReferenceUid]['hasBeenMapped'])
                && $this->dat['records']['sys_file_reference:' . $sysFileReferenceUid]['data']['uid_local'] == $oldFileUid
            ) {
                $this->dat['records']['sys_file_reference:' . $sysFileReferenceUid]['hasBeenMapped'] = true;
                $this->dat['records']['sys_file_reference:' . $sysFileReferenceUid]['data']['uid_local'] = $newFileUid;
            }
        }
    }

    /**
     * Writes the file from import array to temp dir and returns the filename of it.
     *
     * @param string $fileId
     * @param string $dataKey
     * @return string Absolute filename of the temporary filename of the file
     */
    protected function writeTemporaryFileFromData(string $fileId, string $dataKey = 'files_fal'): ?string
    {
        $temporaryFilePath = null;
        if (is_array($this->dat[$dataKey][$fileId])) {
            $temporaryFilePathInternal = GeneralUtility::tempnam('import_temp_');
            GeneralUtility::writeFile($temporaryFilePathInternal, $this->dat[$dataKey][$fileId]['content']);
            clearstatcache();
            if (@is_file($temporaryFilePathInternal)) {
                $this->unlinkFiles[] = $temporaryFilePathInternal;
                $temporaryFilePath = $temporaryFilePathInternal;
            } else {
                $this->addError('Error: temporary file ' . $temporaryFilePathInternal . ' was not written as it should have been!');
            }
        } else {
            $this->addError('Error: No file found for ID ' . $fileId);
        }
        return $temporaryFilePath;
    }

    /**
     * Writing page tree / pages to database:
     * If the operation is an update operation, the root of the page tree inside will be moved to $this->pid
     * unless it is the same as the root page from the import.
     *
     * @see writeRecordsRecords()
     */
    protected function writeRecordsPages(): void
    {
        // First, write page structure if any:
        if (is_array($this->dat['header']['records']['pages'])) {
            $this->addGeneralErrorsByTable('pages');
            // $pageRecords is a copy of the pages array in the imported file. Records here are unset one by one when the addSingle function is called.
            $pageRecords = $this->dat['header']['records']['pages'];
            $this->importData = [];
            // First add page tree if any
            if (is_array($this->dat['header']['pagetree'])) {
                $pageList = $this->flatInversePageTree($this->dat['header']['pagetree']);
                foreach ($pageList as $uid) {
                    $thisRec = $this->dat['header']['records']['pages'][$uid];
                    // PID: Set the main $this->pid, unless a NEW-id is found
                    $setPid = $this->importNewIdPids[$thisRec['pid']] ?? $this->pid;
                    $this->addSingle('pages', (int)$uid, $setPid);
                    unset($pageRecords[$uid]);
                }
            }
            // Then add all remaining pages not in tree on root level:
            if (!empty($pageRecords)) {
                $remainingPageUids = array_keys($pageRecords);
                foreach ($remainingPageUids as $pUid) {
                    $this->addSingle('pages', (int)$pUid, $this->pid);
                }
            }
            // Now write to database:
            $tce = $this->getNewTCE();
            $tce->isImporting = true;
            $this->callHook('before_writeRecordsPages', [
                'tce' => &$tce,
                'data' => &$this->importData
            ]);
            $tce->suggestedInsertUids = $this->suggestedInsertUids;
            $tce->start($this->importData, []);
            $tce->process_datamap();
            $this->callHook('after_writeRecordsPages', [
                'tce' => &$tce
            ]);
            // post-processing: Registering new ids (end all DataHandler sessions with this)
            $this->addToMapId($tce->substNEWwithIDs);
            // In case of an update, order pages from the page tree correctly:
            if ($this->update && is_array($this->dat['header']['pagetree'])) {
                $this->writeRecordsPagesOrder();
            }
        }
    }

    /**
     * Organize all updated pages in page tree so they are related like in the import file
     * Only used for updates and when $this->dat['header']['pagetree'] is an array.
     *
     * @see writeRecordsPages()
     * @see writeRecordsRecordsOrder()
     */
    protected function writeRecordsPagesOrder(): void
    {
        $cmd_data = [];
        // Get uid-pid relations and traverse them in order to map to possible new IDs
        $pageList = $this->flatInversePageTreePid($this->dat['header']['pagetree']);
        foreach ($pageList as $origPid => $newPid) {
            if ($newPid >= 0 && $this->dontIgnorePid('pages', $origPid)) {
                // If the page had a new id (because it was created) use that instead!
                if (strpos($this->importNewIdPids[$origPid], 'NEW') === 0) {
                    if ($this->importMapId['pages'][$origPid]) {
                        $mappedPid = $this->importMapId['pages'][$origPid];
                        $cmd_data['pages'][$mappedPid]['move'] = $newPid;
                    }
                } else {
                    $cmd_data['pages'][$origPid]['move'] = $newPid;
                }
            }
        }
        // Execute the move commands if any:
        if (!empty($cmd_data)) {
            $tce = $this->getNewTCE();
            $this->callHook('before_writeRecordsPagesOrder', [
                'tce' => &$tce,
                'data' => &$cmd_data
            ]);
            $tce->start([], $cmd_data);
            $tce->process_cmdmap();
            $this->callHook('after_writeRecordsPagesOrder', [
                'tce' => &$tce
            ]);
        }
    }

    /**
     * Recursively flattening the $pageTree array, setting PIDs as values
     *
     * @param array $pageTree Page uid hierarchy
     * @param array $list List of pages (internal, don't set from outside)
     * @param int $pid PID value (internal)
     * @return array List with uid-pid pairs for all pages in the page tree.
     * @see ImportExport::flatInversePageTree()
     */
    protected function flatInversePageTreePid(array $pageTree, array $list = [], int $pid = -1): array
    {
        $pageTree = array_reverse($pageTree);
        foreach ($pageTree as $page) {
            $list[$page['uid']] = $pid;
            if (is_array($page['subrow'])) {
                $list = $this->flatInversePageTreePid($page['subrow'], $list, $page['uid']);
            }
        }
        return $list;
    }

    /**
     * Checks if the position of an updated record is configured to be corrected. This can be disabled globally and changed for elements individually.
     *
     * @param string $table Table name
     * @param int $uid Uid or record
     * @return bool TRUE if the position of the record should be updated to match the one in the import structure
     */
    protected function dontIgnorePid(string $table, int $uid): bool
    {
        return $this->importMode[$table . ':' . $uid] !== 'ignore_pid' && (!$this->globalIgnorePid || $this->importMode[$table . ':' . $uid] === 'respect_pid');
    }

    /**
     * Write all database records except pages (written in writeRecordsPages())
     *
     * @see writeRecordsPages()
     */
    protected function writeRecordsRecords(): void
    {
        // Write the rest of the records
        $this->importData = [];
        if (is_array($this->dat['header']['records'])) {
            foreach ($this->dat['header']['records'] as $table => $recs) {
                $table = (string)$table;
                $this->addGeneralErrorsByTable($table);
                if ($table !== 'pages') {
                    foreach ($recs as $uid => $thisRec) {
                        // PID: Set the main $this->pid, unless a NEW-id is found
                        $setPid = isset($this->importMapId['pages'][$thisRec['pid']])
                            ? (int)$this->importMapId['pages'][$thisRec['pid']]
                            : (int)$this->pid;
                        if (is_array($GLOBALS['TCA'][$table]) && isset($GLOBALS['TCA'][$table]['ctrl']['rootLevel'])) {
                            $rootLevelSetting = (int)$GLOBALS['TCA'][$table]['ctrl']['rootLevel'];
                            if ($rootLevelSetting === 1) {
                                $setPid = 0;
                            } elseif ($rootLevelSetting === 0 && $setPid === 0) {
                                $this->addError('Error: Record type ' . $table . ' is not allowed on pid 0');
                                continue;
                            }
                        }
                        // Add record:
                        $this->addSingle($table, $uid, $setPid);
                    }
                }
            }
        } else {
            $this->addError('Error: No records defined in internal data array.');
        }
        // Now write to database:
        $tce = $this->getNewTCE();
        $this->callHook('before_writeRecordsRecords', [
            'tce' => &$tce,
            'data' => &$this->importData
        ]);
        $tce->suggestedInsertUids = $this->suggestedInsertUids;
        // Because all records are being submitted in their correct order with positive pid numbers - and so we should reverse submission order internally.
        $tce->reverseOrder = 1;
        $tce->isImporting = true;
        $tce->start($this->importData, []);
        $tce->process_datamap();
        $this->callHook('after_writeRecordsRecords', [
            'tce' => &$tce
        ]);
        // post-processing: Removing files and registering new ids (end all DataHandler sessions with this)
        $this->addToMapId($tce->substNEWwithIDs);
        // In case of an update, order pages from the page tree correctly:
        if ($this->update) {
            $this->writeRecordsRecordsOrder($this->pid);
        }
    }

    /**
     * Organize all updated record to their new positions.
     * Only used for updates
     *
     * @param int $mainPid Main PID into which we import.
     *
     * @see writeRecordsRecords()
     * @see writeRecordsPagesOrder()
     */
    protected function writeRecordsRecordsOrder(int $mainPid): void
    {
        $cmd_data = [];
        if (is_array($this->dat['header']['pagetree'])) {
            $pageList = $this->flatInversePageTree($this->dat['header']['pagetree']);
        } else {
            $pageList = [];
        }
        if (is_array($this->dat['header']['pid_lookup'])) {
            foreach ($this->dat['header']['pid_lookup'] as $pid => $recList) {
                $newPid = $this->importMapId['pages'][$pid] ?? $mainPid;
                if (MathUtility::canBeInterpretedAsInteger($newPid)) {
                    foreach ($recList as $tableName => $uidList) {
                        // If $mainPid===$newPid then we are on root level and we can consider to move pages as well!
                        // (they will not be in the page tree!)
                        if (($tableName !== 'pages' || !$pageList[$pid]) && is_array($uidList)) {
                            $uidList = array_reverse(array_keys($uidList));
                            foreach ($uidList as $uid) {
                                if ($this->dontIgnorePid($tableName, $uid)) {
                                    $cmd_data[$tableName][$uid]['move'] = $newPid;
                                }
                            }
                        }
                    }
                }
            }
        }
        // Execute the move commands if any:
        if (!empty($cmd_data)) {
            $tce = $this->getNewTCE();
            $this->callHook('before_writeRecordsRecordsOrder', [
                'tce' => &$tce,
                'data' => &$cmd_data
            ]);
            $tce->start([], $cmd_data);
            $tce->process_cmdmap();
            $this->callHook('after_writeRecordsRecordsOrder', [
                'tce' => &$tce
            ]);
        }
    }

    /**
     * Adds a single record to the $importData array. Also copies files to tempfolder.
     * However all File/DB-references and flexform field contents are set to blank for now!
     * That is done with setRelations() later
     *
     * @param string $table Table name (from import memory)
     * @param int $uid Record UID (from import memory)
     * @param int|string $pid Page id or NEW-id, e.g. "NEW5fb3c2641281c885267727"
     * @see writeRecords()
     */
    protected function addSingle(string $table, int $uid, $pid): void
    {
        if ($this->importMode[$table . ':' . $uid] === 'exclude') {
            return;
        }
        $record = $this->dat['records'][$table . ':' . $uid]['data'];
        if (is_array($record)) {
            $databaseRecord = $this->getRecordFromDatabase($table, $uid);
            if ($this->update && $databaseRecord !== null && $this->importMode[$table . ':' . $uid] !== 'as_new') {
                $ID = $uid;
            } elseif ($table === 'sys_file_metadata' && $record['sys_language_uid'] == '0' && $this->importMapId['sys_file'][$record['file']]) {
                // on adding sys_file records the belonging sys_file_metadata record was also created
                // if there is one the record need to be overwritten instead of creating a new one.
                $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
                    ->getQueryBuilderForTable('sys_file_metadata');
                $recordInDatabase = $queryBuilder->select('uid')
                    ->from('sys_file_metadata')
                    ->where(
                        $queryBuilder->expr()->eq(
                            'file',
                            $queryBuilder->createNamedParameter(
                                $this->importMapId['sys_file'][$record['file']],
                                \PDO::PARAM_INT
                            )
                        ),
                        $queryBuilder->expr()->eq(
                            'sys_language_uid',
                            $queryBuilder->createNamedParameter(0, \PDO::PARAM_INT)
                        ),
                        $queryBuilder->expr()->eq(
                            'pid',
                            $queryBuilder->createNamedParameter(0, \PDO::PARAM_INT)
                        )
                    )
                    ->execute()
                    ->fetch();
                // if no record could be found, $this->importMapId['sys_file'][$record['file']] is pointing
                // to a file, that was already there, thus a new metadata record should be created
                if (is_array($recordInDatabase)) {
                    $this->importMapId['sys_file_metadata'][$record['uid']] = $recordInDatabase['uid'];
                    $ID = $recordInDatabase['uid'];
                } else {
                    $ID = StringUtility::getUniqueId('NEW');
                }
            } else {
                $ID = StringUtility::getUniqueId('NEW');
            }
            $this->importNewId[$table . ':' . $ID] = ['table' => $table, 'uid' => $uid];
            if ($table === 'pages') {
                $this->importNewIdPids[$uid] = $ID;
            }
            // Set main record data:
            $this->importData[$table][$ID] = $record;
            $this->importData[$table][$ID]['tx_impexp_origuid'] = $this->importData[$table][$ID]['uid'];
            // Reset permission data:
            if ($table === 'pages') {
                // Have to reset the user/group IDs so pages are owned by importing user. Otherwise strange things may happen for non-admins!
                unset($this->importData[$table][$ID]['perms_userid']);
                unset($this->importData[$table][$ID]['perms_groupid']);
            }
            // PID and UID:
            unset($this->importData[$table][$ID]['uid']);
            // Updates:
            if (MathUtility::canBeInterpretedAsInteger($ID)) {
                unset($this->importData[$table][$ID]['pid']);
            } else {
                // Inserts:
                $this->importData[$table][$ID]['pid'] = $pid;
                if (($this->importMode[$table . ':' . $uid] === 'force_uid' && $this->update || $this->forceAllUids) && $this->getBackendUser()->isAdmin()) {
                    $this->importData[$table][$ID]['uid'] = $uid;
                    $this->suggestedInsertUids[$table . ':' . $uid] = 'DELETE';
                }
            }
            // Setting db/file blank:
            foreach ($this->dat['records'][$table . ':' . $uid]['rels'] as $field => $config) {
                switch ((string)$config['type']) {
                    case 'db':

                    case 'file':
                        // Fixed later in ->setRelations() [because we need to know ALL newly created IDs before we can map relations!]
                        // In the meantime we set NO values for relations.
                        //
                        // BUT for field uid_local of table sys_file_reference the relation MUST not be cleared here,
                        // because the value is already the uid of the right imported sys_file record.
                        // @see fixUidLocalInSysFileReferenceRecords()
                        // If it's empty or a uid to another record the FileExtensionFilter will throw an exception or
                        // delete the reference record if the file extension of the related record doesn't match.
                        if ($table !== 'sys_file_reference' && $field !== 'uid_local') {
                            $this->importData[$table][$ID][$field] = '';
                        }
                        break;
                    case 'flex':
                        // Fixed later in setFlexFormRelations()
                        // In the meantime we set NO value for flexforms - this is mainly because file references
                        // inside will not be processed properly; In fact references will point to no file
                        // or existing files (in which case there will be double-references which is a big problem of course!)
                        $this->importData[$table][$ID][$field] = '';
                        break;
                }
            }
        } elseif ($table . ':' . $uid != 'pages:0') {
            // On root level we don't want this error message.
            $this->addError('Error: no record was found in data array!');
        }
    }

    /**
     * Registers the substNEWids in memory.
     *
     * @param array $substNEWwithIDs From DataHandler to be merged into internal mapping variable in this object
     * @see writeRecords()
     */
    protected function addToMapId(array $substNEWwithIDs): void
    {
        foreach ($this->importData as $table => $recs) {
            foreach ($recs as $id => $value) {
                $old_uid = $this->importNewId[$table . ':' . $id]['uid'];
                if (isset($substNEWwithIDs[$id])) {
                    $this->importMapId[$table][$old_uid] = $substNEWwithIDs[$id];
                } elseif ($this->update) {
                    // Map same ID to same ID....
                    $this->importMapId[$table][$old_uid] = $id;
                } else {
                    // if $this->importMapId contains already the right mapping, skip the error msg.
                    // See special handling of sys_file_metadata in addSingle() => nothing to do
                    if (!($table === 'sys_file_metadata' && isset($this->importMapId[$table][$old_uid]) && $this->importMapId[$table][$old_uid] == $id)) {
                        $this->addError('Possible error: ' . $table . ':' . $old_uid . ' had no new id assigned to it. This indicates that the record was not added to database during import. Please check changelog!');
                    }
                }
            }
        }
    }

    /**
     * Returns a new $TCE object
     *
     * @return DataHandler $TCE object
     */
    protected function getNewTCE(): DataHandler
    {
        $tce = GeneralUtility::makeInstance(DataHandler::class);
        $tce->dontProcessTransformations = 1;
        $tce->enableLogging = $this->enableLogging;
        return $tce;
    }

    /**
     * Cleaning up all the temporary files stored in typo3temp/ folder
     */
    protected function unlinkTempFiles(): void
    {
        foreach ($this->unlinkFiles as $fileName) {
            if (GeneralUtility::isFirstPartOfStr($fileName, Environment::getPublicPath() . '/typo3temp/')) {
                GeneralUtility::unlink_tempfile($fileName);
                clearstatcache();
                if (is_file($fileName)) {
                    $this->addError('Error: ' . $fileName . ' was NOT unlinked as it should have been!');
                }
            } else {
                $this->addError('Error: ' . $fileName . ' was not in temp-path. Not removed!');
            }
        }
        $this->unlinkFiles = [];
    }

    /***************************
     * Import / Relations setting
     ***************************/
    /**
     * At the end of the import process all file and DB relations should be set properly (that is relations
     * to imported records are all re-created so imported records are correctly related again)
     * Relations in flexform fields are processed in setFlexFormRelations() after this function
     *
     * @see setFlexFormRelations()
     */
    protected function setRelations(): void
    {
        $updateData = [];
        // importNewId contains a register of all records that was in the import memory's "records" key
        foreach ($this->importNewId as $nId => $dat) {
            $table = $dat['table'];
            $uid = $dat['uid'];
            // original UID - NOT the new one!
            // If the record has been written and received a new id, then proceed:
            if (is_array($this->importMapId[$table]) && isset($this->importMapId[$table][$uid])) {
                $thisNewUid = BackendUtility::wsMapId($table, $this->importMapId[$table][$uid]);
                if (is_array($this->dat['records'][$table . ':' . $uid]['rels'])) {
                    // Traverse relation fields of each record
                    foreach ($this->dat['records'][$table . ':' . $uid]['rels'] as $field => $config) {
                        // uid_local of sys_file_reference needs no update because the correct reference uid was already written
                        // @see ImportExport::fixUidLocalInSysFileReferenceRecords()
                        if ($table === 'sys_file_reference' && $field === 'uid_local') {
                            continue;
                        }
                        switch ((string)$config['type']) {
                            case 'db':
                                if (is_array($config['itemArray']) && !empty($config['itemArray'])) {
                                    $itemConfig = $GLOBALS['TCA'][$table]['columns'][$field]['config'];
                                    $valArray = $this->setRelationsDb($config['itemArray'], $itemConfig);
                                    $updateData[$table][$thisNewUid][$field] = implode(',', $valArray);
                                }
                                break;
                            case 'file':
                                if (is_array($config['newValueFiles']) && !empty($config['newValueFiles'])) {
                                    $valArr = [];
                                    foreach ($config['newValueFiles'] as $fileInfo) {
                                        $valArr[] = $this->importAddFileNameToBeCopied($fileInfo);
                                    }
                                    $updateData[$table][$thisNewUid][$field] = implode(',', $valArr);
                                }
                                break;
                        }
                    }
                } else {
                    $this->addError('Error: no record was found in data array!');
                }
            } else {
                $this->addError('Error: this records is NOT created it seems! (' . $table . ':' . $uid . ')');
            }
        }
        if (!empty($updateData)) {
            $tce = $this->getNewTCE();
            $tce->isImporting = true;
            $this->callHook('before_setRelation', [
                'tce' => &$tce,
                'data' => &$updateData
            ]);
            $tce->start($updateData, []);
            $tce->process_datamap();
            $this->callHook('after_setRelations', [
                'tce' => &$tce
            ]);
        }
    }

    /**
     * Maps relations for database
     *
     * @param array $itemArray Array of item sets (table/uid) from a dbAnalysis object
     * @param array $itemConfig Array of TCA config of the field the relation to be set on
     * @return array Array with values [table]_[uid] or [uid] for field of type group / internal_type file_reference. These values have the regular DataHandler-input group/select type which means they will automatically be processed into a uid-list or MM relations.
     */
    protected function setRelationsDb(array $itemArray, array $itemConfig): array
    {
        $valArray = [];
        foreach ($itemArray as $relDat) {
            if (is_array($this->importMapId[$relDat['table']]) && isset($this->importMapId[$relDat['table']][$relDat['id']])) {
                if ($itemConfig['type'] === 'input' && isset($itemConfig['wizards']['link'])) {
                    // If an input field has a relation to a sys_file record this need to be converted back to
                    // the public path. But use getPublicUrl here, because could normally only be a local file path.
                    $fileUid = $this->importMapId[$relDat['table']][$relDat['id']];
                    // Fallback value
                    $value = 'file:' . $fileUid;
                    try {
                        $file = GeneralUtility::makeInstance(ResourceFactory::class)->retrieveFileOrFolderObject($fileUid);
                    } catch (\Exception $e) {
                        $file = null;
                    }
                    if ($file instanceof FileInterface) {
                        $value = $file->getPublicUrl();
                    }
                } else {
                    $value = $relDat['table'] . '_' . $this->importMapId[$relDat['table']][$relDat['id']];
                }
                $valArray[] = $value;
            } elseif ($this->isTableStatic($relDat['table']) || $this->isRecordExcluded($relDat['table'], (int)$relDat['id']) || $relDat['id'] < 0) {
                // Checking for less than zero because some select types could contain negative values,
                // eg. fe_groups (-1, -2) and sys_language (-1 = ALL languages). This must be handled on both export and import.
                $valArray[] = $relDat['table'] . '_' . $relDat['id'];
            } else {
                $this->addError('Lost relation: ' . $relDat['table'] . ':' . $relDat['id']);
            }
        }
        return $valArray;
    }

    /**
     * Writes the file from import array to temp dir and returns the filename of it.
     *
     * @param array $fileInfo File information with three keys: "filename" = filename without path, "ID_absFile" = absolute filepath to the file (including the filename), "ID" = md5 hash of "ID_absFile
     * @return string|null Absolute filename of the temporary filename of the file.
     */
    protected function importAddFileNameToBeCopied(array $fileInfo): ?string
    {
        if (is_array($this->dat['files'][$fileInfo['ID']])) {
            $tmpFile = null;
            $tmpFolder = $this->getTemporaryFolderName();
            // check if there is the right file already in the local folder
            if ($tmpFolder !== null) {
                if (is_file($tmpFolder . '/' . $this->dat['files'][$fileInfo['ID']]['content_md5']) &&
                    md5_file($tmpFolder . '/' . $this->dat['files'][$fileInfo['ID']]['content_md5']) === $this->dat['files'][$fileInfo['ID']]['content_md5']) {
                    $tmpFile = $tmpFolder . '/' . $this->dat['files'][$fileInfo['ID']]['content_md5'];
                }
            }
            if ($tmpFile === null) {
                $tmpFile = GeneralUtility::tempnam('import_temp_');
                GeneralUtility::writeFile($tmpFile, $this->dat['files'][$fileInfo['ID']]['content']);
            }
            clearstatcache();
            if (@is_file($tmpFile)) {
                $this->unlinkFiles[] = $tmpFile;
                return $tmpFile;
            }
            $this->addError('Error: temporary file ' . $tmpFile . ' was not written as it should have been!');
        } else {
            $this->addError('Error: No file found for ID ' . $fileInfo['ID']);
        }
        return null;
    }

    /**
     * After all DB relations has been set in the end of the import (see setRelations()) then it is time to correct all relations inside of FlexForm fields.
     * The reason for doing this after is that the setting of relations may affect (quite often!) which data structure is used for the flexforms field!
     *
     * @see setRelations()
     */
    protected function setFlexFormRelations(): void
    {
        $updateData = [];
        // importNewId contains a register of all records that were in the import memory's "records" key
        foreach ($this->importNewId as $nId => $dat) {
            $table = $dat['table'];
            $uid = $dat['uid'];
            // original UID - NOT the new one!
            // If the record has been written and received a new id, then proceed:
            if (!isset($this->importMapId[$table][$uid])) {
                $this->addError('Error: this records is NOT created it seems! (' . $table . ':' . $uid . ')');
                continue;
            }

            if (!is_array($this->dat['records'][$table . ':' . $uid]['rels'])) {
                $this->addError('Error: no record was found in data array!');
                continue;
            }
            $thisNewUid = BackendUtility::wsMapId($table, $this->importMapId[$table][$uid]);
            // Traverse relation fields of each record
            foreach ($this->dat['records'][$table . ':' . $uid]['rels'] as $field => $config) {
                switch ((string)$config['type']) {
                    case 'flex':
                        // Get XML content and set as default value (string, non-processed):
                        $updateData[$table][$thisNewUid][$field] = $this->dat['records'][$table . ':' . $uid]['data'][$field];
                        // If there has been registered relations inside the flex form field, run processing on the content:
                        if (!empty($config['flexFormRels']['db']) || !empty($config['flexFormRels']['file'])) {
                            $origRecordRow = BackendUtility::getRecord($table, $thisNewUid, '*');
                            // This will fetch the new row for the element (which should be updated with any references to data structures etc.)
                            $fieldTca = $GLOBALS['TCA'][$table]['columns'][$field];
                            if (is_array($origRecordRow) && is_array($fieldTca['config']) && $fieldTca['config']['type'] === 'flex') {
                                // Get current data structure and value array:
                                $flexFormTools = GeneralUtility::makeInstance(FlexFormTools::class);
                                $dataStructureIdentifier = $flexFormTools->getDataStructureIdentifier(
                                    $fieldTca,
                                    $table,
                                    $field,
                                    $origRecordRow
                                );
                                $dataStructureArray = $flexFormTools->parseDataStructureByIdentifier($dataStructureIdentifier);
                                $currentValueArray = GeneralUtility::xml2array($updateData[$table][$thisNewUid][$field]);
                                // Do recursive processing of the XML data:
                                $iteratorObj = GeneralUtility::makeInstance(DataHandler::class);
                                $iteratorObj->callBackObj = $this;
                                $currentValueArray['data'] = $iteratorObj->checkValue_flex_procInData(
                                    $currentValueArray['data'],
                                    [],
                                    [],
                                    $dataStructureArray,
                                    [$table, $thisNewUid, $field, $config],
                                    'remapListedDbRecordsFlexFormCallBack'
                                );
                                // The return value is set as an array which means it will be processed by DataHandler for file and DB references!
                                if (is_array($currentValueArray['data'])) {
                                    $updateData[$table][$thisNewUid][$field] = $currentValueArray;
                                }
                            }
                        }
                        break;
                }
            }
        }
        if (!empty($updateData)) {
            $tce = $this->getNewTCE();
            $tce->isImporting = true;
            $this->callHook('before_setFlexFormRelations', [
                'tce' => &$tce,
                'data' => &$updateData
            ]);
            $tce->start($updateData, []);
            $tce->process_datamap();
            $this->callHook('after_setFlexFormRelations', [
                'tce' => &$tce
            ]);
        }
    }

    /**
     * Callback function for traversing the FlexForm structure in relation to remapping database relations
     *
     * @param array $pParams Set of parameters in numeric array: table, uid, field
     * @param array $dsConf TCA config for field (from Data Structure of course)
     * @param string $dataValue Field value (from FlexForm XML)
     * @param string $dataValue_ext1 Not used
     * @param string $dataValue_ext2 Not used
     * @param string $path Path of where the data structure of the element is found
     * @return array Array where the "value" key carries the value.
     * @see setFlexFormRelations()
     */
    public function remapListedDbRecordsFlexFormCallBack(array $pParams, array $dsConf, string $dataValue, string $dataValue_ext1, string $dataValue_ext2, string $path): array
    {
        // Extract parameters:
        [, , , $config] = $pParams;
        // In case the $path is used as index without a trailing slash we will remove that
        if (!is_array($config['flexFormRels']['db'][$path]) && is_array($config['flexFormRels']['db'][rtrim($path, '/')])) {
            $path = rtrim($path, '/');
        }
        if (is_array($config['flexFormRels']['db'][$path])) {
            $valArray = $this->setRelationsDb($config['flexFormRels']['db'][$path], $dsConf);
            $dataValue = implode(',', $valArray);
        }
        if (is_array($config['flexFormRels']['file'][$path])) {
            $valArr = [];
            foreach ($config['flexFormRels']['file'][$path] as $fileInfo) {
                $valArr[] = $this->importAddFileNameToBeCopied($fileInfo);
            }
            $dataValue = implode(',', $valArr);
        }
        return ['value' => $dataValue];
    }

    /**************************
     * Import / Soft References
     *************************/

    /**
     * Processing of soft references
     */
    protected function processSoftReferences(): void
    {
        // Initialize:
        $inData = [];
        // Traverse records:
        if (is_array($this->dat['header']['records'])) {
            foreach ($this->dat['header']['records'] as $table => $recs) {
                foreach ($recs as $uid => $thisRec) {
                    // If there are soft references defined, traverse those:
                    if (isset($GLOBALS['TCA'][$table]) && is_array($thisRec['softrefs'])) {
                        // First traversal is to collect softref configuration and split them up based on fields.
                        // This could probably also have been done with the "records" key instead of the header.
                        $fieldsIndex = [];
                        foreach ($thisRec['softrefs'] as $softrefDef) {
                            // If a substitution token is set:
                            if ($softrefDef['field'] && is_array($softrefDef['subst']) && $softrefDef['subst']['tokenID']) {
                                $fieldsIndex[$softrefDef['field']][$softrefDef['subst']['tokenID']] = $softrefDef;
                            }
                        }
                        // The new id:
                        $thisNewUid = BackendUtility::wsMapId($table, $this->importMapId[$table][$uid]);
                        // Now, if there are any fields that require substitution to be done, lets go for that:
                        foreach ($fieldsIndex as $field => $softRefCfgs) {
                            if (is_array($GLOBALS['TCA'][$table]['columns'][$field])) {
                                if ($GLOBALS['TCA'][$table]['columns'][$field]['config']['type'] === 'flex') {
                                    // This will fetch the new row for the element (which should be updated with any references to data structures etc.)
                                    $origRecordRow = BackendUtility::getRecord($table, $thisNewUid, '*');
                                    if (is_array($origRecordRow)) {
                                        // Get current data structure and value array:
                                        $flexFormTools = GeneralUtility::makeInstance(FlexFormTools::class);
                                        $dataStructureIdentifier = $flexFormTools->getDataStructureIdentifier(
                                            $GLOBALS['TCA'][$table]['columns'][$field],
                                            $table,
                                            $field,
                                            $origRecordRow
                                        );
                                        $dataStructureArray = $flexFormTools->parseDataStructureByIdentifier($dataStructureIdentifier);
                                        $currentValueArray = GeneralUtility::xml2array($origRecordRow[$field]);
                                        // Do recursive processing of the XML data:
                                        /** @var DataHandler $iteratorObj */
                                        $iteratorObj = GeneralUtility::makeInstance(DataHandler::class);
                                        $iteratorObj->callBackObj = $this;
                                        $currentValueArray['data'] = $iteratorObj->checkValue_flex_procInData($currentValueArray['data'], [], [], $dataStructureArray, [$table, $uid, $field, $softRefCfgs], 'processSoftReferencesFlexFormCallBack');
                                        // The return value is set as an array which means it will be processed by DataHandler for file and DB references!
                                        if (is_array($currentValueArray['data'])) {
                                            $inData[$table][$thisNewUid][$field] = $currentValueArray;
                                        }
                                    }
                                } else {
                                    // Get tokenizedContent string and proceed only if that is not blank:
                                    $tokenizedContent = $this->dat['records'][$table . ':' . $uid]['rels'][$field]['softrefs']['tokenizedContent'];
                                    if (strlen($tokenizedContent) && is_array($softRefCfgs)) {
                                        $inData[$table][$thisNewUid][$field] = $this->processSoftReferencesSubstTokens($tokenizedContent, $softRefCfgs, $table, (string)$uid);
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
        // Now write to database:
        $tce = $this->getNewTCE();
        $tce->isImporting = true;
        $this->callHook('before_processSoftReferences', [
            'tce' => $tce,
            'data' => &$inData
        ]);
        $tce->enableLogging = true;
        $tce->start($inData, []);
        $tce->process_datamap();
        $this->callHook('after_processSoftReferences', [
            'tce' => $tce
        ]);
    }

    /**
     * Callback function for traversing the FlexForm structure in relation to remapping soft reference relations
     *
     * @param array $pParams Set of parameters in numeric array: table, uid, field
     * @param array $dsConf TCA config for field (from Data Structure of course)
     * @param string $dataValue Field value (from FlexForm XML)
     * @param string $dataValue_ext1 Not used
     * @param string $dataValue_ext2 Not used
     * @param string $path Path of where the data structure where the element is found
     * @return array Array where the "value" key carries the value.
     * @see setFlexFormRelations()
     */
    public function processSoftReferencesFlexFormCallBack(array $pParams, array $dsConf, string $dataValue, string $dataValue_ext1, string $dataValue_ext2, string $path): array
    {
        // Extract parameters:
        [$table, $origUid, $field, $softRefCfgs] = $pParams;
        if (is_array($softRefCfgs)) {
            // First, find all soft reference configurations for this structure path (they are listed flat in the header):
            $thisSoftRefCfgList = [];
            foreach ($softRefCfgs as $sK => $sV) {
                if ($sV['structurePath'] === $path) {
                    $thisSoftRefCfgList[$sK] = $sV;
                }
            }
            // If any was found, do processing:
            if (!empty($thisSoftRefCfgList)) {
                // Get tokenizedContent string and proceed only if that is not blank:
                $tokenizedContent = $this->dat['records'][$table . ':' . $origUid]['rels'][$field]['flexFormRels']['softrefs'][$path]['tokenizedContent'];
                if (strlen($tokenizedContent)) {
                    $dataValue = $this->processSoftReferencesSubstTokens($tokenizedContent, $thisSoftRefCfgList, $table, $origUid);
                }
            }
        }
        // Return
        return ['value' => $dataValue];
    }

    /**
     * Substition of soft reference tokens
     *
     * @param string $tokenizedContent Content of field with soft reference tokens in.
     * @param array $softRefCfgs Soft reference configurations
     * @param string $table Table for which the processing occurs
     * @param string $uid UID of record from table
     * @return string The input content with tokens substituted according to entries in softRefCfgs
     */
    protected function processSoftReferencesSubstTokens(string $tokenizedContent, array $softRefCfgs, string $table, string $uid): string
    {
        // traverse each softref type for this field:
        foreach ($softRefCfgs as $cfg) {
            // Get token ID:
            $tokenID = $cfg['subst']['tokenID'];
            // Default is current token value:
            $insertValue = $cfg['subst']['tokenValue'];
            // Based on mode:
            switch ((string)$this->softrefCfg[$tokenID]['mode']) {
                case 'exclude':
                    // Exclude is a simple passthrough of the value
                    break;
                case 'editable':
                    // Editable always picks up the value from this input array:
                    $insertValue = $this->softrefInputValues[$tokenID];
                    break;
                default:
                    // Mapping IDs/creating files: Based on type, look up new value:
                    switch ((string)$cfg['subst']['type']) {
                        case 'file':
                            // Create / Overwrite file:
                            $insertValue = $this->processSoftReferencesSaveFile($cfg['subst']['relFileName'], $cfg, $table, $uid);
                            break;
                        case 'db':
                        default:
                            // Trying to map database element if found in the mapID array:
                            [$tempTable, $tempUid] = explode(':', (string)$cfg['subst']['recordRef']);
                            if (isset($this->importMapId[$tempTable][$tempUid])) {
                                $insertValue = BackendUtility::wsMapId($tempTable, $this->importMapId[$tempTable][$tempUid]);
                                if (strpos($cfg['subst']['tokenValue'], ':') !== false) {
                                    [$tokenKey] = explode(':', $cfg['subst']['tokenValue']);
                                    $insertValue = $tokenKey . ':' . $insertValue;
                                }
                            }
                    }
            }
            // Finally, swap the soft reference token in tokenized content with the insert value:
            $tokenizedContent = str_replace('{softref:' . $tokenID . '}', $insertValue, $tokenizedContent);
        }
        return $tokenizedContent;
    }

    /**
     * Process a soft reference file
     *
     * @param string $relFileName Old Relative filename
     * @param array $cfg soft reference configuration array
     * @param string $table Table for which the processing occurs
     * @param string $uid UID of record from table
     * @return string New relative filename (value to insert instead of the softref token)
     */
    protected function processSoftReferencesSaveFile(string $relFileName, array $cfg, string $table, string $uid): string
    {
        if ($this->dat['header']['files'][$cfg['file_ID']]) {
            // Initialize; Get directory prefix for file and find possible RTE filename
            $dirPrefix = PathUtility::dirname($relFileName) . '/';
            if (GeneralUtility::isFirstPartOfStr($dirPrefix, $this->getFileadminFolderName() . '/')) {
                // File in fileadmin/ folder:
                // Create file (and possible resources)
                $newFileName = $this->processSoftReferencesSaveFileCreateRelFile($dirPrefix, PathUtility::basename($relFileName), $cfg['file_ID'], $table, $uid) ?: '';
                if (strlen($newFileName)) {
                    $relFileName = $newFileName;
                } else {
                    $this->addError('ERROR: No new file created for "' . $relFileName . '"');
                }
            } else {
                $this->addError('ERROR: Sorry, cannot operate on non-RTE files which are outside the fileadmin folder.');
            }
        } else {
            $this->addError('ERROR: Could not find file ID in header.');
        }
        // Return (new) filename relative to public web path
        return $relFileName;
    }

    /**
     * Create file in directory and return the new (unique) filename
     *
     * @param string $origDirPrefix Directory prefix, relative, with trailing slash
     * @param string $fileName Filename (without path)
     * @param string $fileID File ID from import memory
     * @param string $table Table for which the processing occurs
     * @param string $uid UID of record from table
     * @return string|null New relative filename, if any
     */
    protected function processSoftReferencesSaveFileCreateRelFile(string $origDirPrefix, string $fileName, string $fileID, string $table, string $uid): ?string
    {
        // If the fileID map contains an entry for this fileID then just return the relative filename of that entry;
        // we don't want to write another unique filename for this one!
        if (isset($this->fileIdMap[$fileID])) {
            return PathUtility::stripPathSitePrefix($this->fileIdMap[$fileID]);
        }
        // Verify FileMount access to dir-prefix. Returns the best alternative relative path if any
        $dirPrefix = $this->resolveStoragePath($origDirPrefix);
        if ($dirPrefix !== null && (!$this->update || $origDirPrefix === $dirPrefix) && $this->checkOrCreateDir($dirPrefix)) {
            $fileHeaderInfo = $this->dat['header']['files'][$fileID];
            $updMode = $this->update && $this->importMapId[$table][$uid] === $uid && $this->importMode[$table . ':' . $uid] !== 'as_new';
            // Create new name for file:
            // Must have same ID in map array (just for security, is not really needed) and NOT be set "as_new".

            // Write main file:
            if ($updMode) {
                $newName = Environment::getPublicPath() . '/' . $dirPrefix . $fileName;
            } else {
                // Create unique filename:
                $fileProcObj = $this->getFileProcObj();
                $newName = (string)$fileProcObj->getUniqueName($fileName, Environment::getPublicPath() . '/' . $dirPrefix);
            }
            if ($this->writeFileVerify($newName, $fileID)) {
                // If the resource was an HTML/CSS file with resources attached, we will write those as well!
                if (is_array($fileHeaderInfo['EXT_RES_ID'])) {
                    $tokenizedContent = $this->dat['files'][$fileID]['tokenizedContent'];
                    $tokenSubstituted = false;
                    $fileProcObj = $this->getFileProcObj();
                    if ($updMode) {
                        foreach ($fileHeaderInfo['EXT_RES_ID'] as $res_fileID) {
                            if ($this->dat['files'][$res_fileID]['filename']) {
                                // Resolve original filename:
                                $relResourceFileName = $this->dat['files'][$res_fileID]['parentRelFileName'];
                                $absResourceFileName = GeneralUtility::resolveBackPath(Environment::getPublicPath() . '/' . $origDirPrefix . $relResourceFileName);
                                $absResourceFileName = GeneralUtility::getFileAbsFileName($absResourceFileName);
                                if ($absResourceFileName && GeneralUtility::isFirstPartOfStr($absResourceFileName, Environment::getPublicPath() . '/' . $this->getFileadminFolderName() . '/')) {
                                    $destDir = PathUtility::stripPathSitePrefix(PathUtility::dirname($absResourceFileName) . '/');
                                    if ($this->resolveStoragePath($destDir, false) !== null && $this->checkOrCreateDir($destDir)) {
                                        $this->writeFileVerify($absResourceFileName, $res_fileID);
                                    } else {
                                        $this->addError('ERROR: Could not create file in directory "' . $destDir . '"');
                                    }
                                } else {
                                    $this->addError('ERROR: Could not resolve path for "' . $relResourceFileName . '"');
                                }
                                $tokenizedContent = str_replace('{EXT_RES_ID:' . $res_fileID . '}', $relResourceFileName, $tokenizedContent);
                                $tokenSubstituted = true;
                            }
                        }
                    } else {
                        // Create the ressource's directory name (filename without extension, suffixed "_FILES")
                        $resourceDir = PathUtility::dirname($newName) . '/' . preg_replace('/\\.[^.]*$/', '', PathUtility::basename($newName)) . '_FILES';
                        if (GeneralUtility::mkdir($resourceDir)) {
                            foreach ($fileHeaderInfo['EXT_RES_ID'] as $res_fileID) {
                                if ($this->dat['files'][$res_fileID]['filename']) {
                                    $absResourceFileName = (string)$fileProcObj->getUniqueName($this->dat['files'][$res_fileID]['filename'], $resourceDir);
                                    $relResourceFileName = substr($absResourceFileName, strlen(PathUtility::dirname($resourceDir)) + 1);
                                    $this->writeFileVerify($absResourceFileName, $res_fileID);
                                    $tokenizedContent = str_replace('{EXT_RES_ID:' . $res_fileID . '}', $relResourceFileName, $tokenizedContent);
                                    $tokenSubstituted = true;
                                }
                            }
                        }
                    }
                    // If substitutions has been made, write the content to the file again:
                    if ($tokenSubstituted) {
                        GeneralUtility::writeFile($newName, $tokenizedContent);
                    }
                }
                return PathUtility::stripPathSitePrefix($newName);
            }
        }
        return null;
    }

    /**
     * Writes a file from the import memory having $fileID to file name $fileName which must be an absolute path inside public web path
     *
     * @param string $fileName Absolute filename inside public web path to write to
     * @param string $fileID File ID from import memory
     * @param bool $bypassMountCheck Bypasses the checking against file mounts - only for RTE files!
     * @return bool Returns TRUE if it went well. Notice that the content of the file is read again, and md5 from import memory is validated.
     */
    protected function writeFileVerify(string $fileName, string $fileID, bool $bypassMountCheck = false): bool
    {
        $fileProcObj = $this->getFileProcObj();
        if (!$fileProcObj->actionPerms['addFile']) {
            $this->addError('ERROR: You did not have sufficient permissions to write the file "' . $fileName . '"');
            return false;
        }
        // Just for security, check again. Should actually not be necessary.
        if (!$bypassMountCheck) {
            try {
                GeneralUtility::makeInstance(ResourceFactory::class)->getFolderObjectFromCombinedIdentifier(PathUtility::dirname($fileName));
            } catch (InsufficientFolderAccessPermissionsException $e) {
                $this->addError('ERROR: Filename "' . $fileName . '" was not allowed in destination path!');
                return false;
            }
        }
        $pathInfo = GeneralUtility::split_fileref($fileName);
        if (!GeneralUtility::makeInstance(FileNameValidator::class)->isValid($pathInfo['file'])) {
            $this->addError('ERROR: Filename "' . $fileName . '" failed against extension check or deny-pattern!');
            return false;
        }
        if (!GeneralUtility::getFileAbsFileName($fileName)) {
            $this->addError('ERROR: Filename "' . $fileName . '" was not a valid relative file path!');
            return false;
        }
        if (!$this->dat['files'][$fileID]) {
            $this->addError('ERROR: File ID "' . $fileID . '" could not be found');
            return false;
        }
        GeneralUtility::writeFile($fileName, $this->dat['files'][$fileID]['content']);
        $this->fileIdMap[$fileID] = $fileName;
        if (hash_equals(md5((string)file_get_contents($fileName)), $this->dat['files'][$fileID]['content_md5'])) {
            return true;
        }
        $this->addError('ERROR: File content "' . $fileName . '" was corrupted');
        return false;
    }

    /**
     * Returns TRUE if directory exists  and if it doesn't it will create directory and return TRUE if that succeeded.
     *
     * @param string $dirPrefix Directory to create. Having a trailing slash. Must be in fileadmin/. Relative to public web path
     * @return bool TRUE, if directory exists (was created)
     */
    protected function checkOrCreateDir(string $dirPrefix): bool
    {
        // Split dir path and remove first directory (which should be "fileadmin")
        $filePathParts = explode('/', $dirPrefix);
        $firstDir = array_shift($filePathParts);
        if ($firstDir === $this->getFileadminFolderName() && GeneralUtility::getFileAbsFileName($dirPrefix)) {
            $pathAcc = '';
            foreach ($filePathParts as $dirname) {
                $pathAcc .= '/' . $dirname;
                if (strlen($dirname)) {
                    if (!@is_dir(Environment::getPublicPath() . '/' . $this->getFileadminFolderName() . $pathAcc)) {
                        if (!GeneralUtility::mkdir(Environment::getPublicPath() . '/' . $this->getFileadminFolderName() . $pathAcc)) {
                            $this->addError('ERROR: Directory could not be created....B');
                            return false;
                        }
                    }
                } elseif ($dirPrefix === $this->getFileadminFolderName() . $pathAcc) {
                    return true;
                } else {
                    $this->addError('ERROR: Directory could not be created....A');
                }
            }
        }
        return false;
    }

    /**
     * Call Hook
     *
     * @param string $name Name of the hook
     * @param array $params Array with params
     */
    protected function callHook(string $name, array $params): void
    {
        foreach ($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['ext/impexp/class.tx_impexp.php'][$name] ?? [] as $hook) {
            GeneralUtility::callUserFunction($hook, $params, $this);
        }
    }

    /**************************
     * Getters and Setters
     *************************/

    /**
     * @return bool
     */
    public function isEnableLogging(): bool
    {
        return $this->enableLogging;
    }

    /**
     * @param bool $enableLogging
     */
    public function setEnableLogging(bool $enableLogging): void
    {
        $this->enableLogging = $enableLogging;
    }

    /**
     * @return bool
     */
    public function isDecompressionAvailable(): bool
    {
        return $this->decompressionAvailable;
    }
}
