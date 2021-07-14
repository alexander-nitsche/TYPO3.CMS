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

use TYPO3\CMS\Backend\Routing\PreviewUriBuilder;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Resource\Exception\InsufficientFolderAccessPermissionsException;
use TYPO3\CMS\Core\Resource\Folder;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Resource\Security\FileNameValidator;
use TYPO3\CMS\Core\Type\Bitmask\Permission;
use TYPO3\CMS\Core\Utility\DiffUtility;
use TYPO3\CMS\Core\Utility\File\ExtendedFileUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\PathUtility;

/**
 * T3D file Import / Export library (TYPO3 Record Document)
 *
 * @internal This class is not considered part of the public TYPO3 API.
 */
abstract class ImportExport
{
    /**
     * A WHERE clause for selection records from the pages table based on read-permissions of the current backend user.
     *
     * @var string
     */
    protected $permsClause;

    /**
     * Root page of import or export page tree
     *
     * @var int
     */
    protected $pid = -1;

    /**
     * Root page record of import or of export page tree
     *
     * @var array
     */
    protected $pidRecord = null;

    /**
     * If set, static relations (not exported) will be shown in overview as well
     *
     * @var bool
     */
    protected $showStaticRelations = false;

    /**
     * Whether "import" or "export" mode of object. Set through init() function
     *
     * @var string
     */
    protected $mode = '';

    /**
     * Updates all records that has same UID instead of creating new!
     *
     * @var bool
     */
    protected $update = false;

    /**
     * Is set by importData() when an import has been done.
     *
     * @var bool
     */
    protected $doesImport = false;

    /**
     * Setting import modes during update state: as_new, exclude, force_uid
     *
     * @var array
     */
    protected $importMode = [];

    /**
     * If set, PID correct is ignored globally
     *
     * @var bool
     */
    protected $globalIgnorePid = false;

    /**
     * If set, all UID values are forced! (update or import)
     *
     * @var bool
     */
    protected $forceAllUids = false;

    /**
     * If set, a diff-view column is added to the overview.
     *
     * @var bool
     */
    protected $showDiff = false;

    /**
     * Array of values to substitute in editable softreferences.
     *
     * @var array
     */
    protected $softrefInputValues = [];

    /**
     * Mapping between the fileID from import memory and the final filenames they are written to.
     *
     * @var array
     */
    protected $fileIdMap = [];

    /**
     * Add tables names here which should not be exported with the file.
     * (Where relations should be mapped to same UIDs in target system).
     *
     * @var array
     */
    protected $relStaticTables = [];

    /**
     * Exclude map. Keys are table:uid pairs and if set, records are not added to the export.
     *
     * @var array
     */
    protected $excludeMap = [];

    /**
     * Soft Reference Token ID modes.
     *
     * @var array
     */
    protected $softrefCfg = [];

    /**
     * Listing extension dependencies.
     *
     * @var array
     */
    protected $extensionDependencies = [];

    /**
     * After records are written this array is filled with [table][original_uid] = [new_uid]
     *
     * @var array
     */
    protected $importMapId = [];

    /**
     * Error log.
     *
     * @var array
     */
    protected $errorLog = [];

    /**
     * Cache for record paths
     *
     * @var array
     */
    protected $cacheGetRecordPath = [];

    /**
     * Internal import/export memory
     *
     * @var array
     */
    protected $dat = [];

    /**
     * File processing object
     *
     * @var ExtendedFileUtility
     */
    protected $fileProcObj;

    /**
     * @var array
     */
    protected $remainHeader = [];

    /**
     * @var LanguageService
     */
    protected $lang;

    /**
     * @var IconFactory
     */
    protected $iconFactory;

    /**
     * Name of the "fileadmin" folder where files for export/import should be located
     *
     * @var string
     */
    protected $fileadminFolderName = '';

    /**
     * @var string
     */
    protected $temporaryFolderName;

    /**
     * @var Folder
     */
    protected $defaultImportExportFolder;

    /**
     * Flag to control whether all disabled records and their children are excluded (true) or included (false). Defaults
     * to the old behaviour of including everything.
     *
     * @var bool
     */
    protected $excludeDisabledRecords = false;

    /**
     * The constructor
     */
    public function __construct()
    {
        $this->iconFactory = GeneralUtility::makeInstance(IconFactory::class);
        $this->lang = $this->getLanguageService();
        $this->permsClause = $this->getBackendUser()->getPagePermsClause(Permission::PAGE_SHOW);
    }

    /********************************************************
     * Visual rendering of import/export memory, $this->dat
     ********************************************************/

    /**
     * Displays an overview of the header-content.
     *
     * @return array The view data
     */
    public function displayContentOverview(): array
    {
        if (!isset($this->dat['header'])) {
            return [];
        }

        // Probably this is done to save memory space?
        unset($this->dat['files']);

        $viewData = [];
        // Traverse header:
        $this->remainHeader = $this->dat['header'];
        // If there is a page tree set, show that:
        if (is_array($this->dat['header']['pagetree'] ?? null)) {
            reset($this->dat['header']['pagetree']);
            $lines = [];
            $this->traversePageTree($this->dat['header']['pagetree'], $lines);

            $viewData['dat'] = $this->dat;
            $viewData['update'] = $this->update;
            $viewData['showDiff'] = $this->showDiff;
            if (!empty($lines)) {
                foreach ($lines as &$r) {
                    $r['controls'] = $this->renderControls($r);
                    if (($r['msg'] ?? false) && !$this->doesImport) {
                        $r['message'] = '<span class="text-danger">' . htmlspecialchars($r['msg']) . '</span>';
                    } else {
                        $r['message'] = '';
                    }
                }
                $viewData['pagetreeLines'] = $lines;
            } else {
                $viewData['pagetreeLines'] = [];
            }
        }
        // Print remaining records that were not contained inside the page tree:
        if (is_array($this->remainHeader['records'] ?? null)) {
            $lines = [];
            if (is_array($this->remainHeader['records']['pages'] ?? null)) {
                $this->traversePageRecords($this->remainHeader['records']['pages'], $lines);
            }
            $this->traverseAllRecords($this->remainHeader['records'], $lines);
            if (!empty($lines)) {
                foreach ($lines as &$r) {
                    $r['controls'] = $this->renderControls($r);
                    if (($r['msg'] ?? false) && !$this->doesImport) {
                        $r['message'] = '<span class="text-danger">' . htmlspecialchars($r['msg']) . '</span>';
                    } else {
                        $r['message'] = '';
                    }
                }
                $viewData['remainingRecords'] = $lines;
            }
        }

        return $viewData;
    }

    /**
     * Go through page tree for display
     *
     * @param array $pT Page tree array with uid/subrow (from ->dat[header][pagetree]
     * @param array $lines Output lines array (is passed by reference and modified)
     * @param string $preCode Pre-HTML code
     */
    protected function traversePageTree(array $pT, array &$lines, string $preCode = ''): void
    {
        foreach ($pT as $k => $v) {
            if ($this->excludeDisabledRecords === true && $this->isRecordDisabled('pages', $k)) {
                $this->excludePageAndRecords($k, $v);
                continue;
            }

            // Add this page:
            $this->singleRecordLines('pages', $k, $lines, $preCode);
            // Subrecords:
            if (is_array($this->dat['header']['pid_lookup'][$k])) {
                foreach ($this->dat['header']['pid_lookup'][$k] as $t => $recUidArr) {
                    $t = (string)$t;
                    if ($t !== 'pages') {
                        foreach ($recUidArr as $ruid => $value) {
                            $this->singleRecordLines($t, $ruid, $lines, $preCode . '&nbsp;&nbsp;&nbsp;&nbsp;');
                        }
                    }
                }
                unset($this->remainHeader['pid_lookup'][$k]);
            }
            // Subpages, called recursively:
            if (is_array($v['subrow'])) {
                $this->traversePageTree($v['subrow'], $lines, $preCode . '&nbsp;&nbsp;&nbsp;&nbsp;');
            }
        }
    }

    /**
     * Test whether a record is disabled (i.e. hidden)
     *
     * @param string $table Name of the records' database table
     * @param int $uid Database uid of the record
     * @return bool true if the record is disabled, false otherwise
     */
    protected function isRecordDisabled(string $table, int $uid): bool
    {
        return $this->dat['records'][$table . ':' . $uid]['data'][
            $GLOBALS['TCA'][$table]['ctrl']['enablecolumns']['disabled'] ?? ''
        ] ?? false;
    }

    /**
     * Exclude a page, its sub pages (recursively) and records placed in them from this import/export
     *
     * @param int $pageUid Uid of the page to exclude
     * @param array $pageTree Page tree array with uid/subrow (from ->dat[header][pagetree]
     */
    protected function excludePageAndRecords(int $pageUid, array $pageTree): void
    {
        // Prevent having this page appear in "remaining records" table
        unset($this->remainHeader['records']['pages'][$pageUid]);

        // Subrecords
        if (is_array($this->dat['header']['pid_lookup'][$pageUid])) {
            foreach ($this->dat['header']['pid_lookup'][$pageUid] as $table => $recordData) {
                if ($table !== 'pages') {
                    foreach (array_keys($recordData) as $uid) {
                        unset($this->remainHeader['records'][$table][$uid]);
                    }
                }
            }
            unset($this->remainHeader['pid_lookup'][$pageUid]);
        }
        // Subpages excluded recursively
        if (is_array($pageTree['subrow'])) {
            foreach ($pageTree['subrow'] as $subPageUid => $subPageTree) {
                $this->excludePageAndRecords($subPageUid, $subPageTree);
            }
        }
    }

    /**
     * Go through remaining pages (not in tree)
     *
     * @param array<int, array> $pT Page tree array with uid/subrow (from ->dat[header][pagetree])
     * @param array $lines Output lines array (is passed by reference and modified)
     */
    protected function traversePageRecords(array $pT, array &$lines): void
    {
        foreach ($pT as $k => $rHeader) {
            $this->singleRecordLines('pages', (int)$k, $lines, '', true);
            // Subrecords:
            if (is_array($this->dat['header']['pid_lookup'][$k])) {
                foreach ($this->dat['header']['pid_lookup'][$k] as $t => $recUidArr) {
                    if ($t !== 'pages') {
                        foreach ($recUidArr as $ruid => $value) {
                            $this->singleRecordLines((string)$t, (int)$ruid, $lines, '&nbsp;&nbsp;&nbsp;&nbsp;');
                        }
                    }
                }
                unset($this->remainHeader['pid_lookup'][$k]);
            }
        }
    }

    /**
     * Go through ALL records (if the pages are displayed first, those will not be among these!)
     *
     * @param array $pT Page tree array with uid/subrow (from ->dat[header][pagetree])
     * @param array $lines Output lines array (is passed by reference and modified)
     */
    protected function traverseAllRecords(array $pT, array &$lines): void
    {
        foreach ($pT as $t => $recUidArr) {
            $this->addGeneralErrorsByTable($t);
            if ($t !== 'pages') {
                $preCode = '';
                foreach ($recUidArr as $ruid => $value) {
                    $this->singleRecordLines((string)$t, (int)$ruid, $lines, $preCode, true);
                }
            }
        }
    }

    /**
     * Log general error message for a given table
     *
     * @param string $table database table name
     */
    protected function addGeneralErrorsByTable(string $table): void
    {
        if ($this->update && $table === 'sys_file') {
            $this->addError('Updating sys_file records is not supported! They will be imported as new records!');
        }
        if ($this->forceAllUids && $table === 'sys_file') {
            $this->addError('Forcing uids of sys_file records is not supported! They will be imported as new records!');
        }
    }

    /**
     * Add entries for a single record
     *
     * @param string $table Table name
     * @param int $uid Record uid
     * @param array $lines Output lines array (is passed by reference and modified)
     * @param string $preCode Pre-HTML code
     * @param bool $checkImportInPidRecord If you want import validation, you can set this so it checks if the import can take place on the specified page.
     */
    protected function singleRecordLines(string $table, int $uid, array &$lines, string $preCode, bool $checkImportInPidRecord = false): void
    {
        // Get record:
        $record = $this->dat['header']['records'][$table][$uid];
        unset($this->remainHeader['records'][$table][$uid]);
        if (!is_array($record) && !($table === 'pages' && !$uid)) {
            $this->addError('MISSING RECORD: ' . $table . ':' . $uid);
        }
        // Begin to create the line arrays information record, pInfo:
        $pInfo = [];
        $pInfo['ref'] = $table . ':' . $uid;
        // Unknown table name:
        if ($table === '_SOFTREF_') {
            $pInfo['preCode'] = $preCode;
            $pInfo['title'] = '<em>' . htmlspecialchars($this->lang->getLL('impexpcore_singlereco_softReferencesFiles')) . '</em>';
        } elseif (!isset($GLOBALS['TCA'][$table])) {
            // Unknown table name:
            $pInfo['preCode'] = $preCode;
            $pInfo['msg'] = 'UNKNOWN TABLE \'' . $pInfo['ref'] . '\'';
            $pInfo['title'] = '<em>' . htmlspecialchars((string)$record['title']) . '</em>';
        } else {
            // prepare data attribute telling whether the record is active or hidden, allowing frontend bulk selection
            $pInfo['active'] = !$this->isRecordDisabled($table, $uid) ? 'active' : 'hidden';

            // Otherwise, set table icon and title.
            // Import validation will show messages if import is not possible of various items.
            $pidRecord = $this->getPidRecord();
            if ($this->mode === 'import' && !empty($pidRecord)) {
                if ($checkImportInPidRecord) {
                    if (!$this->getBackendUser()->doesUserHaveAccess($pidRecord, ($table === 'pages' ? 8 : 16))) {
                        $pInfo['msg'] .= '\'' . $pInfo['ref'] . '\' cannot be INSERTED on this page! ';
                    }
                    if (!$this->checkDokType($table, $pidRecord['doktype']) && !$GLOBALS['TCA'][$table]['ctrl']['rootLevel']) {
                        $pInfo['msg'] .= '\'' . $table . '\' cannot be INSERTED on this page type (change page type to \'Folder\'.) ';
                    }
                }
                if (!$this->getBackendUser()->check('tables_modify', $table)) {
                    $pInfo['msg'] .= 'You are not allowed to CREATE \'' . $table . '\' tables! ';
                }
                if ($GLOBALS['TCA'][$table]['ctrl']['readOnly']) {
                    $pInfo['msg'] .= 'TABLE \'' . $table . '\' is READ ONLY! ';
                }
                if ($GLOBALS['TCA'][$table]['ctrl']['adminOnly'] && !$this->getBackendUser()->isAdmin()) {
                    $pInfo['msg'] .= 'TABLE \'' . $table . '\' is ADMIN ONLY! ';
                }
                if ($GLOBALS['TCA'][$table]['ctrl']['is_static']) {
                    $pInfo['msg'] .= 'TABLE \'' . $table . '\' is a STATIC TABLE! ';
                }
                if ((int)$GLOBALS['TCA'][$table]['ctrl']['rootLevel'] === 1) {
                    $pInfo['msg'] .= 'TABLE \'' . $table . '\' will be inserted on ROOT LEVEL! ';
                }
                $diffInverse = false;
                $recInf = null;
                if ($this->update) {
                    // In case of update-PREVIEW we swap the diff-sources.
                    $diffInverse = true;
                    $recInf = $this->doesRecordExist($table, $uid, $this->showDiff ? '*' : '');
                    $pInfo['updatePath'] = $recInf ? htmlspecialchars($this->getRecordPath((int)$recInf['pid'])) : '<strong>NEW!</strong>';
                    // Mode selector:
                    $optValues = [];
                    $optValues[] = $recInf ? $this->lang->getLL('impexpcore_singlereco_update') : $this->lang->getLL('impexpcore_singlereco_insert');
                    if ($recInf) {
                        $optValues['as_new'] = $this->lang->getLL('impexpcore_singlereco_importAsNew');
                    }
                    if ($recInf) {
                        if (!$this->globalIgnorePid) {
                            $optValues['ignore_pid'] = $this->lang->getLL('impexpcore_singlereco_ignorePid');
                        } else {
                            $optValues['respect_pid'] = $this->lang->getLL('impexpcore_singlereco_respectPid');
                        }
                    }
                    if (!$recInf && $this->getBackendUser()->isAdmin()) {
                        $optValues['force_uid'] = sprintf($this->lang->getLL('impexpcore_singlereco_forceUidSAdmin'), $uid);
                    }
                    $optValues['exclude'] = $this->lang->getLL('impexpcore_singlereco_exclude');
                    if ($table === 'sys_file') {
                        $pInfo['updateMode'] = '';
                    } else {
                        $pInfo['updateMode'] = $this->renderSelectBox('tx_impexp[import_mode][' . $table . ':' . $uid . ']', $this->importMode[$table . ':' . $uid], $optValues);
                    }
                }
                // Diff view:
                if ($this->showDiff) {
                    // For IMPORTS, get new id:
                    if ($newUid = $this->importMapId[$table][$uid]) {
                        $diffInverse = false;
                        $recInf = $this->doesRecordExist($table, $newUid, '*');
                        BackendUtility::workspaceOL($table, $recInf);
                    }
                    $importRecord = $this->dat['records'][$table . ':' . $uid]['data'] ?? null;
                    if (is_array($recInf) && is_array($importRecord)) {
                        $pInfo['showDiffContent'] = $this->compareRecords($recInf, $importRecord, $table, $diffInverse);
                    } else {
                        $pInfo['showDiffContent'] = 'ERROR: One of the inputs were not an array!';
                    }
                }
            }
            $pInfo['preCode'] = $preCode . '<span title="' . htmlspecialchars($table . ':' . $uid) . '">'
                . $this->iconFactory->getIconForRecord($table, (array)$this->dat['records'][$table . ':' . $uid]['data'], Icon::SIZE_SMALL)->render()
                . '</span>';
            $pInfo['title'] = htmlspecialchars((string)$record['title']);
            // View page:
            if ($table === 'pages') {
                $viewID = $this->mode === 'export' ? $uid : ($this->doesImport ? $this->importMapId['pages'][$uid] : 0);
                if ($viewID) {
                    $attributes = PreviewUriBuilder::create($viewID)->serializeDispatcherAttributes();
                    $pInfo['title'] = '<a href="#" ' . $attributes . $pInfo['title'] . '</a>';
                }
            }
        }
        $pInfo['type'] = 'record';
        $lines[] = $pInfo;
        // File relations
        if (is_array($record['filerefs'] ?? null)) {
            $this->addFiles($record['filerefs'], $lines, $preCode);
        }
        // DB relations
        if (is_array($record['rels'] ?? null)) {
            $this->addRelations($record['rels'], $lines, $preCode);
        }
        // Soft ref
        if (!empty($record['softrefs'])) {
            $preCode_A = $preCode . '&nbsp;&nbsp;&nbsp;&nbsp;';
            $preCode_B = $preCode . '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;';
            foreach ($record['softrefs'] as $info) {
                $pInfo = [];
                $pInfo['preCode'] = $preCode_A . $this->iconFactory->getIcon('status-reference-soft', Icon::SIZE_SMALL)->render();
                $pInfo['title'] = '<em>' . $info['field'] . ', "' . $info['spKey'] . '" </em>: <span title="' . htmlspecialchars($info['matchString']) . '">' . htmlspecialchars(GeneralUtility::fixed_lgd_cs($info['matchString'], 60)) . '</span>';
                if ($info['subst']['type']) {
                    if (strlen((string)$info['subst']['title'])) {
                        $pInfo['title'] .= '<br/>' . $preCode_B . '<strong>' . htmlspecialchars($this->lang->getLL('impexpcore_singlereco_title')) . '</strong> ' . htmlspecialchars(GeneralUtility::fixed_lgd_cs($info['subst']['title'], 60));
                    }
                    if (strlen((string)$info['subst']['description'])) {
                        $pInfo['title'] .= '<br/>' . $preCode_B . '<strong>' . htmlspecialchars($this->lang->getLL('impexpcore_singlereco_descr')) . '</strong> ' . htmlspecialchars(GeneralUtility::fixed_lgd_cs($info['subst']['description'], 60));
                    }
                    $pInfo['title'] .= '<br/>' . $preCode_B . ($info['subst']['type'] === 'file' ? htmlspecialchars($this->lang->getLL('impexpcore_singlereco_filename')) . ' <strong>' . $info['subst']['relFileName'] . '</strong>' : '') . ($info['subst']['type'] === 'string' ? htmlspecialchars($this->lang->getLL('impexpcore_singlereco_value')) . ' <strong>' . $info['subst']['tokenValue'] . '</strong>' : '') . ($info['subst']['type'] === 'db' ? htmlspecialchars($this->lang->getLL('impexpcore_softrefsel_record')) . ' <strong>' . $info['subst']['recordRef'] . '</strong>' : '');
                }
                $pInfo['ref'] = 'SOFTREF';
                $pInfo['type'] = 'softref';
                $pInfo['_softRefInfo'] = $info;
                $pInfo['type'] = 'softref';
                $mode = $this->softrefCfg[$info['subst']['tokenID']]['mode'];
                if ($info['error'] && $mode !== 'editable' && $mode !== 'exclude') {
                    $pInfo['msg'] .= $info['error'];
                }
                $lines[] = $pInfo;
                // Add relations:
                if ($info['subst']['type'] === 'db') {
                    [$tempTable, $tempUid] = explode(':', $info['subst']['recordRef']);
                    $this->addRelations([['table' => $tempTable, 'id' => $tempUid, 'tokenID' => $info['subst']['tokenID']]], $lines, $preCode_B, [], '');
                }
                // Add files:
                if ($info['subst']['type'] === 'file') {
                    $this->addFiles([$info['file_ID']], $lines, $preCode_B, '', $info['subst']['tokenID']);
                }
            }
        }
    }

    /**
     * Add DB relations entries for a record's rels-array
     *
     * @param array $rels Array of relations
     * @param array $lines Output lines array (is passed by reference and modified)
     * @param string $preCode Pre-HTML code
     * @param array $recurCheck Recursivity check stack
     * @param string $htmlColorClass Alternative HTML color class to use.
     *
     * @see singleRecordLines()
     */
    protected function addRelations(array $rels, array &$lines, string $preCode, array $recurCheck = [], string $htmlColorClass = ''): void
    {
        foreach ($rels as $dat) {
            $table = $dat['table'];
            $uid = $dat['id'];
            $pInfo = [];
            $pInfo['ref'] = $table . ':' . $uid;
            if (in_array($pInfo['ref'], $recurCheck)) {
                continue;
            }
            $iconName = 'status-status-checked';
            $iconClass = '';
            $staticFixed = false;
            $record = null;
            if ($uid > 0) {
                $record = $this->dat['header']['records'][$table][$uid] ?? null;
                if (!is_array($record)) {
                    if ($this->isTableStatic($table) || $this->isExcluded($table, (int)$uid) || ($dat['tokenID'] ?? '') && !$this->includeSoftref($dat['tokenID'] ?? '')) {
                        $pInfo['title'] = htmlspecialchars('STATIC: ' . $pInfo['ref']);
                        $iconClass = 'text-info';
                        $staticFixed = true;
                    } else {
                        $doesRE = $this->doesRecordExist($table, (int)$uid);
                        $lostPath = $this->getRecordPath($table === 'pages' ? (int)$doesRE['uid'] : (int)$doesRE['pid']);
                        $pInfo['title'] = htmlspecialchars($pInfo['ref']);
                        $pInfo['title'] = '<span title="' . htmlspecialchars($lostPath) . '">' . $pInfo['title'] . '</span>';
                        $pInfo['msg'] = 'LOST RELATION' . (!$doesRE ? ' (Record not found!)' : ' (Path: ' . $lostPath . ')');
                        $iconClass = 'text-danger';
                        $iconName = 'status-dialog-warning';
                    }
                } else {
                    $pInfo['title'] = htmlspecialchars((string)$record['title']);
                    $pInfo['title'] = '<span title="' . htmlspecialchars($this->getRecordPath(($table === 'pages' ? (int)$record['uid'] : (int)$record['pid']))) . '">' . $pInfo['title'] . '</span>';
                }
            } else {
                // Negative values in relation fields. This is typically sys_language fields, fe_users fields etc. They are static values. They CAN theoretically be negative pointers to uids in other tables but this is so rarely used that it is not supported
                $pInfo['title'] = htmlspecialchars('FIXED: ' . $pInfo['ref']);
                $staticFixed = true;
            }

            $icon = '<span class="' . $iconClass . '" title="' . htmlspecialchars($pInfo['ref']) . '">' . $this->iconFactory->getIcon($iconName, Icon::SIZE_SMALL)->render() . '</span>';

            $pInfo['preCode'] = $preCode . '&nbsp;&nbsp;&nbsp;&nbsp;' . $icon;
            $pInfo['type'] = 'rel';
            if (!$staticFixed || $this->showStaticRelations) {
                $lines[] = $pInfo;
                if (is_array($record) && is_array($record['rels'])) {
                    $this->addRelations($record['rels'], $lines, $preCode . '&nbsp;&nbsp;', array_merge($recurCheck, [$pInfo['ref']]));
                }
            }
        }
    }

    /**
     * Add file relation entries for a record's rels-array
     *
     * @param array $rels Array of file IDs
     * @param array $lines Output lines array (is passed by reference and modified)
     * @param string $preCode Pre-HTML code
     * @param string $htmlColorClass Alternative HTML color class to use.
     * @param string $tokenID Token ID if this is a softreference (in which case it only makes sense with a single element in the $rels array!)
     *
     * @see singleRecordLines()
     */
    protected function addFiles(array $rels, array &$lines, string $preCode, string $htmlColorClass = '', string $tokenID = ''): void
    {
        foreach ($rels as $ID) {
            // Process file:
            $pInfo = [];
            $fI = $this->dat['header']['files'][$ID];
            if (!is_array($fI)) {
                if (!$tokenID || $this->includeSoftref($tokenID)) {
                    $pInfo['msg'] = 'MISSING FILE: ' . $ID;
                    $this->addError('MISSING FILE: ' . $ID);
                } else {
                    return;
                }
            }
            $pInfo['preCode'] = $preCode . '&nbsp;&nbsp;&nbsp;&nbsp;' . $this->iconFactory->getIcon('status-reference-hard', Icon::SIZE_SMALL)->render();
            $pInfo['title'] = htmlspecialchars($fI['filename']);
            $pInfo['ref'] = 'FILE';
            $pInfo['type'] = 'file';
            // If import mode and there is a non-RTE softreference, check the destination directory:
            if ($this->mode === 'import' && $tokenID && !$fI['RTE_ORIG_ID']) {
                if (isset($fI['parentRelFileName'])) {
                    $pInfo['msg'] = 'Seems like this file is already referenced from within an HTML/CSS file. That takes precedence. ';
                } else {
                    $testDirPrefix = PathUtility::dirname($fI['relFileName']) . '/';
                    $testDirPrefix2 = $this->verifyFolderAccess($testDirPrefix);
                    if (!$testDirPrefix2) {
                        $pInfo['msg'] = 'ERROR: There are no available filemounts to write file in! ';
                    } elseif ($testDirPrefix !== $testDirPrefix2) {
                        $pInfo['msg'] = 'File will be attempted written to "' . $testDirPrefix2 . '". ';
                    }
                }
                // Check if file exists:
                if (file_exists(Environment::getPublicPath() . '/' . $fI['relFileName'])) {
                    if ($this->update) {
                        $pInfo['updatePath'] .= 'File exists.';
                    } else {
                        $pInfo['msg'] .= 'File already exists! ';
                    }
                }
                // Check extension:
                $fileProcObj = $this->getFileProcObj();
                if ($fileProcObj->actionPerms['addFile']) {
                    $testFI = GeneralUtility::split_fileref(Environment::getPublicPath() . '/' . $fI['relFileName']);
                    if (!GeneralUtility::makeInstance(FileNameValidator::class)->isValid($testFI['file'])) {
                        $pInfo['msg'] .= 'File extension was not allowed!';
                    }
                } else {
                    $pInfo['msg'] = 'Your user profile does not allow you to create files on the server!';
                }
            }
            $pInfo['showDiffContent'] = PathUtility::stripPathSitePrefix($this->fileIdMap[$ID]);
            $lines[] = $pInfo;
            unset($this->remainHeader['files'][$ID]);
            // RTE originals:
            if ($fI['RTE_ORIG_ID']) {
                $ID = $fI['RTE_ORIG_ID'];
                $pInfo = [];
                $fI = $this->dat['header']['files'][$ID];
                if (!is_array($fI)) {
                    $pInfo['msg'] = 'MISSING RTE original FILE: ' . $ID;
                    $this->addError('MISSING RTE original FILE: ' . $ID);
                }
                $pInfo['showDiffContent'] = PathUtility::stripPathSitePrefix($this->fileIdMap[$ID]);
                $pInfo['preCode'] = $preCode . '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;' . $this->iconFactory->getIcon('status-reference-hard', Icon::SIZE_SMALL)->render();
                $pInfo['title'] = htmlspecialchars($fI['filename']) . ' <em>(Original)</em>';
                $pInfo['ref'] = 'FILE';
                $pInfo['type'] = 'file';
                $lines[] = $pInfo;
                unset($this->remainHeader['files'][$ID]);
            }
            // External resources:
            if (is_array($fI['EXT_RES_ID'])) {
                foreach ($fI['EXT_RES_ID'] as $extID) {
                    $pInfo = [];
                    $fI = $this->dat['header']['files'][$extID];
                    if (!is_array($fI)) {
                        $pInfo['msg'] = 'MISSING External Resource FILE: ' . $extID;
                        $this->addError('MISSING External Resource FILE: ' . $extID);
                    } else {
                        $pInfo['updatePath'] = $fI['parentRelFileName'];
                    }
                    $pInfo['showDiffContent'] = PathUtility::stripPathSitePrefix($this->fileIdMap[$extID]);
                    $pInfo['preCode'] = $preCode . '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;' . $this->iconFactory->getIcon('actions-insert-reference', Icon::SIZE_SMALL)->render();
                    $pInfo['title'] = htmlspecialchars($fI['filename']) . ' <em>(Resource)</em>';
                    $pInfo['ref'] = 'FILE';
                    $pInfo['type'] = 'file';
                    $lines[] = $pInfo;
                    unset($this->remainHeader['files'][$extID]);
                }
            }
        }
    }

    /**
     * Verifies that a table is allowed on a certain doktype of a page
     *
     * @param string $checkTable Table name to check
     * @param int $doktype doktype value.
     * @return bool TRUE if OK
     */
    protected function checkDokType(string $checkTable, int $doktype): bool
    {
        $allowedTableList = $GLOBALS['PAGES_TYPES'][$doktype]['allowedTables'] ?? $GLOBALS['PAGES_TYPES']['default']['allowedTables'];
        $allowedArray = GeneralUtility::trimExplode(',', $allowedTableList, true);
        // If all tables or the table is listed as an allowed type, return TRUE
        if (strpos($allowedTableList, '*') !== false || in_array($checkTable, $allowedArray)) {
            return true;
        }
        return false;
    }

    /**
     * Render input controls for import or export
     *
     * @param array $r Configuration for element
     * @return string HTML
     */
    protected function renderControls(array $r): string
    {
        if ($this->mode === 'export') {
            if ($r['type'] === 'record') {
                return '<input type="checkbox" class="t3js-exclude-checkbox" name="tx_impexp[exclude][' . $r['ref'] . ']" id="checkExclude' . $r['ref'] . '" value="1" /> <label for="checkExclude' . $r['ref'] . '">' . htmlspecialchars($this->lang->getLL('impexpcore_singlereco_exclude')) . '</label>';
            }
            return  $r['type'] === 'softref' ? $this->softrefSelector($r['_softRefInfo']) : '';
        }
        // During import
        // For softreferences with editable fields:
        if ($r['type'] === 'softref' && is_array($r['_softRefInfo']['subst']) && $r['_softRefInfo']['subst']['tokenID']) {
            $tokenID = $r['_softRefInfo']['subst']['tokenID'];
            $cfg = $this->softrefCfg[$tokenID];
            if ($cfg['mode'] === 'editable') {
                return (strlen((string)$cfg['title']) ? '<strong>' . htmlspecialchars((string)$cfg['title']) . '</strong><br/>' : '') . htmlspecialchars((string)$cfg['description']) . '<br/>
						<input type="text" name="tx_impexp[softrefInputValues][' . $tokenID . ']" value="' . htmlspecialchars($this->softrefInputValues[$tokenID] ?? $cfg['defValue']) . '" />';
            }
        }

        return '';
    }

    /**
     * Selectorbox with export options for soft references
     *
     * @param array $cfg Softref configuration array. An export box is shown only if a substitution scheme is found for the soft reference.
     * @return string Selector box HTML
     */
    protected function softrefSelector(array $cfg): string
    {
        // Looking for file ID if any:
        $fI = $cfg['file_ID'] ? $this->dat['header']['files'][$cfg['file_ID']] : [];
        // Substitution scheme has to be around and RTE images MUST be exported.
        if (is_array($cfg['subst']) && $cfg['subst']['tokenID'] && !$fI['RTE_ORIG_ID']) {
            // Create options:
            $optValues = [];
            $optValues[''] = '';
            $optValues['editable'] = $this->lang->getLL('impexpcore_softrefsel_editable');
            $optValues['exclude'] = $this->lang->getLL('impexpcore_softrefsel_exclude');
            // Get current value:
            $value = (string)$this->softrefCfg[$cfg['subst']['tokenID']]['mode'];
            // Render options selector:
            $selectorbox = $this->renderSelectBox('tx_impexp[softrefCfg][' . $cfg['subst']['tokenID'] . '][mode]', $value, $optValues) . '<br/>';
            if ($value === 'editable') {
                $descriptionField = '';
                // Title:
                if (strlen((string)$cfg['subst']['title'])) {
                    $descriptionField .= '
					<input type="hidden" name="tx_impexp[softrefCfg][' . $cfg['subst']['tokenID'] . '][title]" value="' . htmlspecialchars((string)$cfg['subst']['title']) . '" />
					<strong>' . htmlspecialchars((string)$cfg['subst']['title']) . '</strong><br/>';
                }
                // Description:
                if (!strlen((string)$cfg['subst']['description'])) {
                    $descriptionField .= '
					' . htmlspecialchars($this->lang->getLL('impexpcore_printerror_description')) . '<br/>
					<input type="text" name="tx_impexp[softrefCfg][' . $cfg['subst']['tokenID'] . '][description]" value="' . htmlspecialchars((string)$this->softrefCfg[$cfg['subst']['tokenID']]['description']) . '" />';
                } else {
                    $descriptionField .= '

					<input type="hidden" name="tx_impexp[softrefCfg][' . $cfg['subst']['tokenID'] . '][description]" value="' . htmlspecialchars((string)$cfg['subst']['description']) . '" />' . htmlspecialchars((string)$cfg['subst']['description']);
                }
                // Default Value:
                $descriptionField .= '<input type="hidden" name="tx_impexp[softrefCfg][' . $cfg['subst']['tokenID'] . '][defValue]" value="' . htmlspecialchars($cfg['subst']['tokenValue']) . '" />';
            } else {
                $descriptionField = '';
            }
            return $selectorbox . $descriptionField;
        }
        return '';
    }

    /**
     * Verifies that the input path relative to public web path is found in the backend users filemounts.
     * If it doesn't it will try to find another relative filemount for the user and return an alternative path prefix for the file.
     *
     * @param string $dirPrefix Path relative to public web path
     * @param bool $noAlternative If set, Do not look for alternative path! Just return FALSE
     * @return string If a path is available that will be returned, otherwise NULL.
     * @throws \Exception
     */
    protected function verifyFolderAccess(string $dirPrefix, bool $noAlternative = false): ?string
    {
        // Check the absolute path for public web path, if the user has access - no problem
        try {
            GeneralUtility::makeInstance(ResourceFactory::class)->getFolderObjectFromCombinedIdentifier($dirPrefix);
            return $dirPrefix;
        } catch (InsufficientFolderAccessPermissionsException $e) {
            // Check all storages available for the user as alternative
            if (!$noAlternative) {
                $fileStorages = $this->getBackendUser()->getFileStorages();
                foreach ($fileStorages as $fileStorage) {
                    try {
                        $folder = $fileStorage->getFolder(rtrim($dirPrefix, '/'));
                        return $folder->getPublicUrl();
                    } catch (InsufficientFolderAccessPermissionsException $e) {
                    }
                }
            }
        }
        return null;
    }

    /*****************************
     * Helper functions of kinds
     *****************************/

    /**
     * @return string
     */
    public function getFileadminFolderName(): string
    {
        if (empty($this->fileadminFolderName)) {
            if (!empty($GLOBALS['TYPO3_CONF_VARS']['BE']['fileadminDir'])) {
                $this->fileadminFolderName = rtrim($GLOBALS['TYPO3_CONF_VARS']['BE']['fileadminDir'], '/');
            } else {
                $this->fileadminFolderName = 'fileadmin';
            }
        }
        return $this->fileadminFolderName;
    }

    /**
     * @return string|null
     */
    protected function getTemporaryFolderName(): ?string
    {
        return $this->temporaryFolderName;
    }

    /**
     * @return string
     */
    public function getOrCreateTemporaryFolderName(): string
    {
        if (empty($this->temporaryFolderName)) {
            $this->createTemporaryFolderName();
        }
        return $this->temporaryFolderName;
    }

    /**
     * @return void
     */
    protected function createTemporaryFolderName(): void
    {
        $temporaryPath = Environment::getVarPath() . '/transient/';
        do {
            $temporaryFolderName = $temporaryPath . 'export_temp_files_' . random_int(1, PHP_INT_MAX);
        } while (is_dir($temporaryFolderName));
        GeneralUtility::mkdir($temporaryFolderName);
        $this->temporaryFolderName = $temporaryFolderName;
    }

    /**
     * @return void
     */
    public function removeTemporaryFolderName(): void
    {
        if (!empty($this->temporaryFolderName)) {
            GeneralUtility::rmdir($this->temporaryFolderName, true);
        }
    }

    /**
     * Returns a \TYPO3\CMS\Core\Resource\Folder object for saving export files
     * to the server and is also used for uploading import files.
     *
     * @return Folder|null
     */
    public function getOrCreateDefaultImportExportFolder(): ?Folder
    {
        if (empty($this->defaultImportExportFolder)) {
            $this->createDefaultImportExportFolder();
        }
        return $this->defaultImportExportFolder;
    }

    /**
     * Creates a \TYPO3\CMS\Core\Resource\Folder object for saving export files
     * to the server and is also used for uploading import files.
     *
     * @return void
     */
    protected function createDefaultImportExportFolder(): void
    {
        $defaultTemporaryFolder = $this->getBackendUser()->getDefaultUploadTemporaryFolder();
        $defaultImportExportFolder = null;
        $importExportFolderName = 'importexport';

        if ($defaultTemporaryFolder !== null) {
            if ($defaultTemporaryFolder->hasFolder($importExportFolderName) === false) {
                $defaultImportExportFolder = $defaultTemporaryFolder->createFolder($importExportFolderName);
            } else {
                $defaultImportExportFolder = $defaultTemporaryFolder->getSubfolder($importExportFolderName);
            }
        }

        $this->defaultImportExportFolder = $defaultImportExportFolder;
    }

    /**
     * @return void
     */
    public function removeDefaultImportExportFolder(): void
    {
        if (!empty($this->defaultImportExportFolder)) {
            $this->defaultImportExportFolder->delete(true);
        }
    }

    /**
     * Recursively flattening the page tree array to a one-dimensional array.
     *
     * @param array $idH Page tree array
     * @param array $a Flat array of pages (internal, don't set from outside)
     * @return array Flat array with uid-uid pairs for all pages in the page tree.
     * @see Import::flatInversePageTreePid()
     */
    protected function flatInversePageTree(array $idH, array $a = []): array
    {
        $idH = array_reverse($idH);
        foreach ($idH as $k => $v) {
            $a[$v['uid']] = $v['uid'];
            if (is_array($v['subrow'])) {
                $a = $this->flatInversePageTree($v['subrow'], $a);
            }
        }
        return $a;
    }

    /**
     * Returns TRUE if the input table name is to be regarded as a static relation (that is, not exported etc).
     *
     * @param string $table Table name
     * @return bool TRUE, if table is marked static
     */
    protected function isTableStatic(string $table): bool
    {
        if (is_array($GLOBALS['TCA'][$table])) {
            return ($GLOBALS['TCA'][$table]['ctrl']['is_static'] ?? false) || in_array($table, $this->relStaticTables) || in_array('_ALL', $this->relStaticTables);
        }
        return false;
    }

    /**
     * Returns TRUE if the element should be excluded as static record.
     *
     * @param string $table Table name
     * @param int $uid UID value
     * @return bool TRUE, if table is marked static
     */
    protected function isExcluded(string $table, int $uid): bool
    {
        return (bool)($this->excludeMap[$table . ':' . $uid] ?? false);
    }

    /**
     * Returns TRUE if soft reference should be included in exported file.
     *
     * @param string $tokenID Token ID for soft reference
     * @return bool TRUE if softreference media should be included
     */
    protected function includeSoftref(string $tokenID): bool
    {
        $mode = $this->softrefCfg[$tokenID]['mode'];
        return $tokenID && $mode !== 'exclude' && $mode !== 'editable';
    }

    /**
     * Checks if the record exists
     *
     * @param string $table Table name
     * @param int $uid UID of record
     * @param string $fields Field list to select. Default is "uid,pid"
     * @return array|null Result of \TYPO3\CMS\Backend\Utility\BackendUtility::getRecord() which means the record if found, otherwise NULL
     */
    protected function doesRecordExist(string $table, int $uid, string $fields = ''): ?array
    {
        return BackendUtility::getRecord($table, $uid, $fields ?: 'uid,pid');
    }

    /**
     * Returns the page title path of a PID value. Results are cached internally
     *
     * @param int $pid Record PID to check
     * @return string The path for the input PID
     */
    protected function getRecordPath(int $pid): string
    {
        if (!isset($this->cacheGetRecordPath[$pid])) {
            $clause = $this->getBackendUser()->getPagePermsClause(Permission::PAGE_SHOW);
            $this->cacheGetRecordPath[$pid] = (string)BackendUtility::getRecordPath($pid, $clause, 20);
        }
        return $this->cacheGetRecordPath[$pid];
    }

    /**
     * Makes a selector-box from optValues
     *
     * @param string $prefix Form element name
     * @param string $value Current value
     * @param array $optValues Options to display (key/value pairs)
     * @return string HTML select element
     */
    protected function renderSelectBox(string $prefix, string $value, array $optValues): string
    {
        $opt = [];
        $isSelFlag = 0;
        foreach ($optValues as $k => $v) {
            $sel = (string)$k === (string)$value ? ' selected="selected"' : '';
            if ($sel) {
                $isSelFlag++;
            }
            $opt[] = '<option value="' . htmlspecialchars((string)$k) . '"' . $sel . '>' . htmlspecialchars((string)$v) . '</option>';
        }
        if (!$isSelFlag && (string)$value !== '') {
            $opt[] = '<option value="' . htmlspecialchars((string)$value) . '" selected="selected">' . htmlspecialchars('[\'' . (string)$value . '\']') . '</option>';
        }
        return '<select name="' . $prefix . '">' . implode('', $opt) . '</select>';
    }

    /**
     * Compares two records, the current database record and the one from the import memory.
     * Will return HTML code to show any differences between them!
     *
     * @param array $databaseRecord Database record, all fields (new values)
     * @param array $importRecord Import memory records for the same table/uid, all fields (old values)
     * @param string $table The table name of the record
     * @param bool $inverseDiff Inverse the diff view (switch red/green, needed for pre-update difference view)
     * @return string HTML
     */
    protected function compareRecords(array $databaseRecord, array $importRecord, string $table, bool $inverseDiff = false): string
    {
        // Initialize:
        $output = [];
        $diffUtility = GeneralUtility::makeInstance(DiffUtility::class);
        // Traverse based on database record
        foreach ($databaseRecord as $fN => $value) {
            if (is_array($GLOBALS['TCA'][$table]['columns'][$fN]) && $GLOBALS['TCA'][$table]['columns'][$fN]['config']['type'] !== 'passthrough') {
                if (isset($importRecord[$fN])) {
                    if (trim((string)$databaseRecord[$fN]) !== trim((string)$importRecord[$fN])) {
                        // Create diff-result:
                        $output[$fN] = $diffUtility->makeDiffDisplay(BackendUtility::getProcessedValue($table, $fN, !$inverseDiff ? $importRecord[$fN] : $databaseRecord[$fN], 0, true, true), BackendUtility::getProcessedValue($table, $fN, !$inverseDiff ? $databaseRecord[$fN] : $importRecord[$fN], 0, true, true));
                    }
                    unset($importRecord[$fN]);
                }
            }
        }
        // Traverse remaining in import record:
        foreach ($importRecord as $fN => $value) {
            if (is_array($GLOBALS['TCA'][$table]['columns'][$fN]) && $GLOBALS['TCA'][$table]['columns'][$fN]['config']['type'] !== 'passthrough') {
                $output[$fN] = '<strong>Field missing</strong> in database';
            }
        }
        // Create output:
        if (!empty($output)) {
            $tRows = [];
            foreach ($output as $fN => $state) {
                $tRows[] = '
                    <tr>
                        <td>' . htmlspecialchars($this->lang->sL($GLOBALS['TCA'][$table]['columns'][$fN]['label'])) . ' (' . htmlspecialchars((string)$fN) . ')</td>
                        <td>' . $state . '</td>
                    </tr>
                ';
            }
            $output = '<table class="table table-striped table-hover">' . implode('', $tRows) . '</table>';
        } else {
            $output = 'Match';
        }
        return '<strong class="text-nowrap">[' . htmlspecialchars($table . ':' . $importRecord['uid'] . ' => ' . $databaseRecord['uid']) . ']:</strong> ' . $output;
    }

    /**
     * Returns file processing object, initialized only once.
     *
     * @return ExtendedFileUtility File processor object
     */
    protected function getFileProcObj(): ExtendedFileUtility
    {
        if ($this->fileProcObj === null) {
            $this->fileProcObj = GeneralUtility::makeInstance(ExtendedFileUtility::class);
            $this->fileProcObj->setActionPermissions();
        }
        return $this->fileProcObj;
    }

    /*****************************
     * Error handling
     *****************************/

    /**
     * Sets error message in the internal error log
     *
     * @param string $message Error message
     */
    protected function addError(string $message): void
    {
        $this->errorLog[] = $message;
    }

    public function hasErrors(): bool
    {
        return empty($this->errorLog) === false;
    }

    /**
     * @return BackendUserAuthentication
     */
    protected function getBackendUser(): BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'];
    }

    /**
     * @return LanguageService
     */
    protected function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }

    /**************************
     * Getters and Setters
     *************************/

    /**
     * @return int
     */
    public function getPid(): int
    {
        return $this->pid;
    }

    /**
     * @param int $pid
     */
    public function setPid(int $pid): void
    {
        $this->pid = $pid;
        $this->pidRecord = null;
    }

    /**
     * Return record of root page of import or of export page tree
     * - or null if access denied to that page.
     *
     * If the page is the root of the page tree,
     * add some basic but missing information.
     *
     * @return array|null
     */
    protected function getPidRecord(): ?array
    {
        if ($this->pidRecord === null && $this->pid >= 0) {
            $pidRecord = BackendUtility::readPageAccess($this->pid, $this->permsClause);

            if (is_array($pidRecord)) {
                if ($this->pid === 0) {
                    $pidRecord += ['title' => '[root-level]', 'uid' => 0, 'pid' => 0];
                }
                $this->pidRecord = $pidRecord;
            }
        }

        return $this->pidRecord;
    }

    /**
     * Set flag to control whether disabled records and their children are excluded (true) or included (false). Defaults
     * to the old behaviour of including everything.
     *
     * @param bool $excludeDisabledRecords Set to true if if all disabled records should be excluded, false otherwise
     */
    public function setExcludeDisabledRecords(bool $excludeDisabledRecords): void
    {
        $this->excludeDisabledRecords = $excludeDisabledRecords;
    }

    /**
     * @return bool
     */
    public function isExcludeDisabledRecords(): bool
    {
        return $this->excludeDisabledRecords;
    }

    /**
     * @return array
     */
    public function getExcludeMap(): array
    {
        return $this->excludeMap;
    }

    /**
     * @param array $excludeMap
     */
    public function setExcludeMap(array $excludeMap): void
    {
        $this->excludeMap = $excludeMap;
    }

    /**
     * @return array
     */
    public function getSoftrefCfg(): array
    {
        return $this->softrefCfg;
    }

    /**
     * @param array $softrefCfg
     */
    public function setSoftrefCfg(array $softrefCfg): void
    {
        $this->softrefCfg = $softrefCfg;
    }

    /**
     * @return array
     */
    public function getExtensionDependencies(): array
    {
        return $this->extensionDependencies;
    }

    /**
     * @param array $extensionDependencies
     */
    public function setExtensionDependencies(array $extensionDependencies): void
    {
        $this->extensionDependencies = $extensionDependencies;
    }

    /**
     * @return bool
     */
    public function isShowStaticRelations(): bool
    {
        return $this->showStaticRelations;
    }

    /**
     * @param bool $showStaticRelations
     */
    public function setShowStaticRelations(bool $showStaticRelations): void
    {
        $this->showStaticRelations = $showStaticRelations;
    }

    /**
     * @return array
     */
    public function getRelStaticTables(): array
    {
        return $this->relStaticTables;
    }

    /**
     * @param array $relStaticTables
     */
    public function setRelStaticTables(array $relStaticTables): void
    {
        $this->relStaticTables = $relStaticTables;
    }

    /**
     * @return array
     */
    public function getErrorLog(): array
    {
        return $this->errorLog;
    }

    /**
     * @param array $errorLog
     */
    public function setErrorLog(array $errorLog): void
    {
        $this->errorLog = $errorLog;
    }

    /**
     * @return bool
     */
    public function isUpdate(): bool
    {
        return $this->update;
    }

    /**
     * @param bool $update
     */
    public function setUpdate(bool $update): void
    {
        $this->update = $update;
    }

    /**
     * @return array
     */
    public function getImportMode(): array
    {
        return $this->importMode;
    }

    /**
     * @param array $importMode
     */
    public function setImportMode(array $importMode): void
    {
        $this->importMode = $importMode;
    }

    /**
     * @return bool
     */
    public function isGlobalIgnorePid(): bool
    {
        return $this->globalIgnorePid;
    }

    /**
     * @param bool $globalIgnorePid
     */
    public function setGlobalIgnorePid(bool $globalIgnorePid): void
    {
        $this->globalIgnorePid = $globalIgnorePid;
    }

    /**
     * @return bool
     */
    public function isForceAllUids(): bool
    {
        return $this->forceAllUids;
    }

    /**
     * @param bool $forceAllUids
     */
    public function setForceAllUids(bool $forceAllUids): void
    {
        $this->forceAllUids = $forceAllUids;
    }

    /**
     * @return bool
     */
    public function isShowDiff(): bool
    {
        return $this->showDiff;
    }

    /**
     * @param bool $showDiff
     */
    public function setShowDiff(bool $showDiff): void
    {
        $this->showDiff = $showDiff;
    }

    /**
     * @return array
     */
    public function getSoftrefInputValues(): array
    {
        return $this->softrefInputValues;
    }

    /**
     * @param array $softrefInputValues
     */
    public function setSoftrefInputValues(array $softrefInputValues): void
    {
        $this->softrefInputValues = $softrefInputValues;
    }

    /**
     * @return string
     */
    public function getMode(): string
    {
        return $this->mode;
    }

    /**
     * @param string $mode
     */
    public function setMode(string $mode): void
    {
        $this->mode = $mode;
    }

    /**
     * @return array
     */
    public function getImportMapId(): array
    {
        return $this->importMapId;
    }

    /**
     * @param array $importMapId
     */
    public function setImportMapId(array $importMapId): void
    {
        $this->importMapId = $importMapId;
    }

    /**
     * @return array
     */
    public function getDat(): array
    {
        return $this->dat;
    }
}
