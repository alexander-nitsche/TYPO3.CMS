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

namespace TYPO3\CMS\Impexp;

use Doctrine\DBAL\Driver\Statement;
use TYPO3\CMS\Backend\Tree\View\PageTreeView;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryHelper;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Database\Query\Restriction\WorkspaceRestriction;
use TYPO3\CMS\Core\Database\ReferenceIndex;
use TYPO3\CMS\Core\Exception;
use TYPO3\CMS\Core\Html\HtmlParser;
use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Resource\Exception\InsufficientFolderWritePermissionsException;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\Folder;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Type\Bitmask\Permission;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\PathUtility;
use TYPO3\CMS\Impexp\View\ExportPageTreeView;

/**
 * EXAMPLE for using the impexp-class for exporting stuff:
 *
 * Create and initialize:
 * $this->export = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Impexp\ImportExport::class);
 * $this->export->init();
 * Set which tables relations we will allow:
 * $this->export->relOnlyTables[]="tt_news";	// exclusively includes. See comment in the class
 *
 * Adding records:
 * $this->export->export_addRecord("pages", $this->pageinfo);
 * $this->export->export_addRecord("pages", \TYPO3\CMS\Backend\Utility\BackendUtility::getRecord("pages", 38));
 * $this->export->export_addRecord("pages", \TYPO3\CMS\Backend\Utility\BackendUtility::getRecord("pages", 39));
 * $this->export->export_addRecord("tt_content", \TYPO3\CMS\Backend\Utility\BackendUtility::getRecord("tt_content", 12));
 * $this->export->export_addRecord("tt_content", \TYPO3\CMS\Backend\Utility\BackendUtility::getRecord("tt_content", 74));
 * $this->export->export_addRecord("sys_template", \TYPO3\CMS\Backend\Utility\BackendUtility::getRecord("sys_template", 20));
 *
 * Adding all the relations (recursively in 5 levels so relations has THEIR relations registered as well)
 * for($a=0;$a<5;$a++) {
 * $addR = $this->export->export_addDBRelations($a);
 * if (empty($addR)) break;
 * }
 *
 * Finally load all the files.
 * $this->export->export_addFilesFromRelations();	// MUST be after the DBrelations are set so that file from ALL added records are included!
 *
 * Write export
 * $out = $this->export->render();
 * @internal this is not part of TYPO3's Core API.
 */

/**
 * T3D file Export library (TYPO3 Record Document)
 */
class Export extends ImportExport
{
    const LEVELS_RECORDS_ON_THIS_PAGE = -2;
    const LEVELS_EXPANDED_TREE = -1;
    const LEVELS_INFINITE = 999;

    const FILETYPE_XML = 'xml';
    const FILETYPE_T3D = 't3d';
    const FILETYPE_T3DZ = 't3d_compressed';

    /**
     * A WHERE clause for selection records from the pages table based on read-permissions of the current backend user.
     *
     * @var string
     */
    protected $perms_clause;

    /**
     * @var string
     */
    protected $title = '';

    /**
     * @var string
     */
    protected $description = '';

    /**
     * @var string
     */
    protected $notes = '';

    /**
     * @var array
     */
    protected $record = [];

    /**
     * @var array
     */
    protected $list = [];

    /**
     * @var int
     */
    protected $pid = -1;

    /**
     * @var int
     */
    protected $levels = 0;

    /**
     * @var array
     */
    protected $tables = [];

    /**
     * Add table names here which are THE ONLY ones which will be included
     * into export if found as relations. '_ALL' will allow all tables.
     *
     * @var array
     */
    protected $relOnlyTables = [];

    /**
     * @var string
     */
    protected $treeHTML = '';

    /**
     * If set, HTML file resources are included.
     *
     * @var bool
     */
    protected $includeExtFileResources = true;

    /**
     * Files with external media (HTML/css style references inside)
     *
     * @var string
     */
    protected $extFileResourceExtensions = 'html,htm,css';

    /**
     * Keys are [recordname], values are an array of fields to be included
     * in the export
     *
     * @var array
     */
    protected $recordTypesIncludeFields = [];

    /**
     * Default array of fields to be included in the export
     *
     * @var array
     */
    protected $defaultRecordIncludeFields = ['uid', 'pid'];

    /**
     * @var bool
     */
    protected $saveFilesOutsideExportFile = false;

    /**
     * @var string
     */
    protected $exportFileName = '';

    /**
     * @var string
     */
    protected $exportFileType = self::FILETYPE_XML;

    /**
     * @var array
     */
    protected $supportedFileTypes = [];

    /**
     * Cache of checkPID values.
     *
     * @var array
     */
    protected $checkPID_cache = [];

    /**************************
     * Initialize
     *************************/

    /**
     * Init the object
     */
    public function init()
    {
        parent::init();
        $this->mode = 'export';
        $this->perms_clause = $this->getBackendUser()->getPagePermsClause(Permission::PAGE_SHOW);
    }

    /**************************
     * Export / Init + Meta Data
     *************************/

    /**
     * Process configuration
     */
    public function process(): void
    {
        $this->setHeaderBasics();

        // Meta data setting:
        $beUser = $this->getBackendUser();
        $this->setMetaData();

        // Configure which records to export
        if (is_array($this->record)) {
            foreach ($this->record as $ref) {
                $rParts = explode(':', $ref);
                $this->export_addRecord($rParts[0], BackendUtility::getRecord($rParts[0], (int)$rParts[1]));
            }
        }
        // Configure which tables to export
        if (is_array($this->list)) {
            foreach ($this->list as $ref) {
                $rParts = explode(':', $ref);
                if ($beUser->check('tables_select', $rParts[0])) {
                    $statement = $this->exec_listQueryPid($rParts[0], (int)$rParts[1]);
                    while ($subTrow = $statement->fetch()) {
                        $this->export_addRecord($rParts[0], $subTrow);
                    }
                }
            }
        }
        // Pagetree
        if ($this->pid !== -1) {
            // Based on click-expandable tree
            $idH = null;
            $pid = $this->pid;
            $levels = $this->levels;
            if ($levels === self::LEVELS_EXPANDED_TREE) {
                $pagetree = GeneralUtility::makeInstance(ExportPageTreeView::class);
                if ($this->excludeDisabledRecords) {
                    $pagetree->init(BackendUtility::BEenableFields('pages'));
                }
                $tree = $pagetree->ext_tree($pid, $this->filterPageIds($this->excludeMap));
                $this->treeHTML = $pagetree->printTree($tree);
                $idH = $pagetree->buffer_idH;
            } elseif ($levels === self::LEVELS_RECORDS_ON_THIS_PAGE) {
                $this->addRecordsForPid($pid, $this->tables);
            } else {
                // Based on depth
                // Drawing tree:
                // If the ID is zero, export root
                if (!$this->pid && $beUser->isAdmin()) {
                    $sPage = [
                        'uid' => 0,
                        'title' => 'ROOT'
                    ];
                } else {
                    $sPage = BackendUtility::getRecordWSOL('pages', $pid, '*', ' AND ' . $this->perms_clause);
                }
                if (is_array($sPage)) {
                    $tree = GeneralUtility::makeInstance(PageTreeView::class);
                    $initClause = 'AND ' . $this->perms_clause . $this->filterPageIds($this->excludeMap);
                    if ($this->excludeDisabledRecords) {
                        $initClause .= BackendUtility::BEenableFields('pages');
                    }
                    $tree->init($initClause);
                    $HTML = $this->iconFactory->getIconForRecord('pages', $sPage, Icon::SIZE_SMALL)->render();
                    $tree->tree[] = ['row' => $sPage, 'HTML' => $HTML];
                    $tree->buffer_idH = [];
                    if ($levels > 0) {
                        $tree->getTree($pid, $levels);
                    }
                    $idH = [];
                    $idH[$pid]['uid'] = $pid;
                    if (!empty($tree->buffer_idH)) {
                        $idH[$pid]['subrow'] = $tree->buffer_idH;
                    }
                    $pagetree = GeneralUtility::makeInstance(ExportPageTreeView::class);
                    $this->treeHTML = $pagetree->printTree($tree->tree);
                }
            }
            // In any case we should have a multi-level array, $idH, with the page structure
            // here (and the HTML-code loaded into memory for nice display...)
            if (is_array($idH)) {
                // Sets the pagetree and gets a 1-dim array in return with the pages (in correct submission order BTW...)
                $flatList = $this->setPageTree($idH);
                foreach ($flatList as $k => $value) {
                    $this->export_addRecord('pages', BackendUtility::getRecord('pages', $k));
                    $this->addRecordsForPid((int)$k, $this->tables);
                }
            }
        }
        // After adding ALL records we set relations:
        for ($a = 0; $a < 10; $a++) {
            $addR = $this->export_addDBRelations($a);
            if (empty($addR)) {
                break;
            }
        }
        // Finally files are added:
        // MUST be after the DBrelations are set so that files from ALL added records are included!
        $this->export_addFilesFromRelations();

        $this->export_addFilesFromSysFilesRecords();
    }

    /**
     * Set header basics
     */
    public function setHeaderBasics()
    {
        // Initializing:
        if (is_array($this->softrefCfg)) {
            foreach ($this->softrefCfg as $key => $value) {
                if (!strlen($value['mode'])) {
                    unset($this->softrefCfg[$key]);
                }
            }
        }
        // Setting in header memory:
        // Version of file format
        $this->dat['header']['XMLversion'] = '1.0';
        // Initialize meta data array (to put it in top of file)
        $this->dat['header']['meta'] = [];
        // Add list of tables to consider static
        $this->dat['header']['relStaticTables'] = $this->relStaticTables;
        // The list of excluded records
        $this->dat['header']['excludeMap'] = $this->excludeMap;
        // Soft Reference mode for elements
        $this->dat['header']['softrefCfg'] = $this->softrefCfg;
        // List of extensions the import depends on.
        $this->dat['header']['extensionDependencies'] = $this->extensionDependencies;
        $this->dat['header']['charset'] = 'utf-8';
    }

    /**
     * Set charset
     *
     * @param string $charset Charset for the content in the export. During import the character set will be converted if the target system uses another charset.
     */
    public function setCharset($charset)
    {
        $this->dat['header']['charset'] = $charset;
    }

    /**
     * Sets meta data
     */
    protected function setMetaData()
    {
        $this->dat['header']['meta'] = [
            'title' => $this->title,
            'description' => $this->description,
            'notes' => $this->notes,
            'packager_username' => $this->getBackendUser()->user['username'],
            'packager_name' => $this->getBackendUser()->user['realName'],
            'packager_email' => $this->getBackendUser()->user['email'],
            'TYPO3_version' => (string)GeneralUtility::makeInstance(Typo3Version::class),
            'created' => strftime('%A %e. %B %Y', $GLOBALS['EXEC_TIME'])
        ];
    }

    /**************************
     * Export / Init Page tree
     *************************/

    /**
     * Sets the page-tree array in the export header and returns the array in a flattened version
     *
     * @param array $idH Hierarchy of ids, the page tree: array([uid] => array("uid" => [uid], "subrow" => array(.....)), [uid] => ....)
     * @return array The hierarchical page tree converted to a one-dimensional list of pages
     */
    public function setPageTree($idH)
    {
        $this->dat['header']['pagetree'] = $this->unsetExcludedSections($idH);
        return $this->flatInversePageTree($this->dat['header']['pagetree']);
    }

    /**
     * Removes entries in the page tree which are found in ->excludeMap[]
     *
     * @param array $idH Page uid hierarchy
     * @return array Modified input array
     * @internal
     * @see setPageTree()
     */
    public function unsetExcludedSections($idH)
    {
        if (is_array($idH)) {
            foreach ($idH as $k => $v) {
                if ($this->excludeMap['pages:' . $idH[$k]['uid']]) {
                    unset($idH[$k]);
                } elseif (is_array($idH[$k]['subrow'])) {
                    $idH[$k]['subrow'] = $this->unsetExcludedSections($idH[$k]['subrow']);
                }
            }
        }
        return $idH;
    }

    /**************************
     * Export
     *************************/

    /**
     * Sets the fields of record types to be included in the export
     *
     * @param array $recordTypesIncludeFields Keys are [recordname], values are an array of fields to be included in the export
     * @throws Exception if an array value is not type of array
     */
    public function setRecordTypesIncludeFields(array $recordTypesIncludeFields)
    {
        foreach ($recordTypesIncludeFields as $table => $fields) {
            if (!is_array($fields)) {
                throw new Exception('The include fields for record type ' . htmlspecialchars($table) . ' are not defined by an array.', 1391440658);
            }
            $this->setRecordTypeIncludeFields($table, $fields);
        }
    }

    /**
     * Sets the fields of a record type to be included in the export
     *
     * @param string $table The record type
     * @param array $fields The fields to be included
     */
    public function setRecordTypeIncludeFields($table, array $fields)
    {
        $this->recordTypesIncludeFields[$table] = $fields;
    }

    /**
     * Filter page IDs by traversing exclude array, finding all
     * excluded pages (if any) and making an AND NOT IN statement for the select clause.
     *
     * @param array $exclude Exclude array from import/export object.
     * @return string AND where clause part to filter out page uids.
     */
    protected function filterPageIds(array $exclude): string
    {
        // Get keys:
        $exclude = array_keys($exclude);
        // Traverse
        $pageIds = [];
        foreach ($exclude as $element) {
            [$table, $uid] = explode(':', $element);
            if ($table === 'pages') {
                $pageIds[] = (int)$uid;
            }
        }
        // Add to clause:
        if (!empty($pageIds)) {
            return ' AND uid NOT IN (' . implode(',', $pageIds) . ')';
        }
        return '';
    }

    /**
     * Adds records to the export object for a specific page id.
     *
     * @param int $k Page id for which to select records to add
     * @param array $tables Array of table names to select from
     */
    protected function addRecordsForPid(int $k, array $tables): void
    {
        foreach ($GLOBALS['TCA'] as $table => $value) {
            if ($table !== 'pages'
                && (in_array($table, $tables, true) || in_array('_ALL', $tables, true))
                && $this->getBackendUser()->check('tables_select', $table)
                && !$GLOBALS['TCA'][$table]['ctrl']['is_static']
            ) {
                $statement = $this->exec_listQueryPid($table, $k);
                while ($subTrow = $statement->fetch()) {
                    $this->export_addRecord($table, $subTrow);
                }
            }
        }
    }

    /**
     * Selects records from table / pid
     *
     * @param string $table Table to select from
     * @param int $pid Page ID to select from
     * @return Statement Query statement
     */
    protected function exec_listQueryPid(string $table, int $pid): Statement
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($table);

        $orderBy = $GLOBALS['TCA'][$table]['ctrl']['sortby'] ?: $GLOBALS['TCA'][$table]['ctrl']['default_sortby'];
        $queryBuilder->getRestrictions()->add(GeneralUtility::makeInstance(WorkspaceRestriction::class, 0));

        if ($this->excludeDisabledRecords === false) {
            $queryBuilder->getRestrictions()
                ->removeAll()
                ->add(GeneralUtility::makeInstance(DeletedRestriction::class))
                ->add(GeneralUtility::makeInstance(WorkspaceRestriction::class, 0));
        }

        $queryBuilder->select('*')
            ->from($table)
            ->where(
                $queryBuilder->expr()->eq(
                    'pid',
                    $queryBuilder->createNamedParameter($pid, \PDO::PARAM_INT)
                )
            );

        foreach (QueryHelper::parseOrderBy((string)$orderBy) as $orderPair) {
            [$fieldName, $order] = $orderPair;
            $queryBuilder->addOrderBy($fieldName, $order);
        }

        return $queryBuilder->execute();
    }

    /**
     * Adds the record $row from $table.
     * No checking for relations done here. Pure data.
     *
     * @param string $table Table name
     * @param array $row Record row.
     * @param int $relationLevel (Internal) if the record is added as a relation, this is set to the "level" it was on.
     */
    public function export_addRecord($table, $row, $relationLevel = 0)
    {
        BackendUtility::workspaceOL($table, $row);
        if ($this->excludeDisabledRecords && !$this->isActive($table, $row['uid'])) {
            return;
        }
        if ((string)$table !== '' && is_array($row) && $row['uid'] > 0 && !$this->excludeMap[$table . ':' . $row['uid']]) {
            if ($this->checkPID($table === 'pages' ? $row['uid'] : $row['pid'])) {
                if (!isset($this->dat['records'][$table . ':' . $row['uid']])) {
                    // Prepare header info:
                    $row = $this->filterRecordFields($table, $row);
                    $headerInfo = [];
                    $headerInfo['uid'] = $row['uid'];
                    $headerInfo['pid'] = $row['pid'];
                    $headerInfo['title'] = GeneralUtility::fixed_lgd_cs(BackendUtility::getRecordTitle($table, $row), 40);
                    if ($relationLevel) {
                        $headerInfo['relationLevel'] = $relationLevel;
                    }
                    // Set the header summary:
                    $this->dat['header']['records'][$table][$row['uid']] = $headerInfo;
                    // Create entry in the PID lookup:
                    $this->dat['header']['pid_lookup'][$row['pid']][$table][$row['uid']] = 1;
                    // Initialize reference index object:
                    $refIndexObj = GeneralUtility::makeInstance(ReferenceIndex::class);
                    $refIndexObj->enableRuntimeCache();
                    $relations = $refIndexObj->getRelations($table, $row);
                    $relations = $this->fixFileIDsInRelations($relations);
                    $relations = $this->removeSoftrefsHavingTheSameDatabaseRelation($relations);
                    // Data:
                    $this->dat['records'][$table . ':' . $row['uid']] = [];
                    $this->dat['records'][$table . ':' . $row['uid']]['data'] = $row;
                    $this->dat['records'][$table . ':' . $row['uid']]['rels'] = $relations;
                    // Add information about the relations in the record in the header:
                    $this->dat['header']['records'][$table][$row['uid']]['rels'] = $this->flatDBrels($this->dat['records'][$table . ':' . $row['uid']]['rels']);
                    // Add information about the softrefs to header:
                    $this->dat['header']['records'][$table][$row['uid']]['softrefs'] = $this->flatSoftRefs($this->dat['records'][$table . ':' . $row['uid']]['rels']);
                } else {
                    $this->addError('Record ' . $table . ':' . $row['uid'] . ' already added.');
                }
            } else {
                $this->addError('Record ' . $table . ':' . $row['uid'] . ' was outside your DB mounts!');
            }
        }
    }

    /**
     * Checking if a PID is in the webmounts of the user
     *
     * @param int $pid Page ID to check
     * @return bool TRUE if OK
     */
    public function checkPID(int $pid): bool
    {
        if (!isset($this->checkPID_cache[$pid])) {
            $this->checkPID_cache[$pid] = (bool)$this->getBackendUser()->isInWebMount($pid);
        }
        return $this->checkPID_cache[$pid];
    }

    /**
     * This changes the file reference ID from a hash based on the absolute file path
     * (coming from ReferenceIndex) to a hash based on the relative file path.
     *
     * @param array $relations
     * @return array
     */
    protected function fixFileIDsInRelations(array $relations)
    {
        foreach ($relations as $field => $relation) {
            if (isset($relation['type']) && $relation['type'] === 'file') {
                foreach ($relation['newValueFiles'] as $key => $fileRelationData) {
                    $absoluteFilePath = $fileRelationData['ID_absFile'];
                    if (GeneralUtility::isFirstPartOfStr($absoluteFilePath, Environment::getPublicPath())) {
                        $relatedFilePath = PathUtility::stripPathSitePrefix($absoluteFilePath);
                        $relations[$field]['newValueFiles'][$key]['ID'] = md5($relatedFilePath);
                    }
                }
            }
            if ($relation['type'] === 'flex') {
                if (is_array($relation['flexFormRels']['file'])) {
                    foreach ($relation['flexFormRels']['file'] as $key => $subList) {
                        foreach ($subList as $subKey => $fileRelationData) {
                            $absoluteFilePath = $fileRelationData['ID_absFile'];
                            if (GeneralUtility::isFirstPartOfStr($absoluteFilePath, Environment::getPublicPath())) {
                                $relatedFilePath = PathUtility::stripPathSitePrefix($absoluteFilePath);
                                $relations[$field]['flexFormRels']['file'][$key][$subKey]['ID'] = md5($relatedFilePath);
                            }
                        }
                    }
                }
            }
        }
        return $relations;
    }

    /**
     * Relations could contain db relations to sys_file records. Some configuration combinations of TCA and
     * SoftReferenceIndex create also softref relation entries for the identical file. This results
     * in double included files, one in array "files" and one in array "file_fal".
     * This function checks the relations for this double inclusions and removes the redundant softref relation.
     *
     * @param array $relations
     * @return array
     */
    protected function removeSoftrefsHavingTheSameDatabaseRelation($relations)
    {
        $fixedRelations = [];
        foreach ($relations as $field => $relation) {
            $newRelation = $relation;
            if (isset($newRelation['type']) && $newRelation['type'] === 'db') {
                foreach ($newRelation['itemArray'] as $key => $dbRelationData) {
                    if ($dbRelationData['table'] === 'sys_file') {
                        if (isset($newRelation['softrefs']['keys']['typolink'])) {
                            foreach ($newRelation['softrefs']['keys']['typolink'] as $softrefKey => $softRefData) {
                                if ($softRefData['subst']['type'] === 'file') {
                                    $file = GeneralUtility::makeInstance(ResourceFactory::class)->retrieveFileOrFolderObject($softRefData['subst']['relFileName']);
                                    if ($file instanceof File) {
                                        if ($file->getUid() == $dbRelationData['id']) {
                                            unset($newRelation['softrefs']['keys']['typolink'][$softrefKey]);
                                        }
                                    }
                                }
                            }
                            if (empty($newRelation['softrefs']['keys']['typolink'])) {
                                unset($newRelation['softrefs']);
                            }
                        }
                    }
                }
            }
            $fixedRelations[$field] = $newRelation;
        }
        return $fixedRelations;
    }

    /**
     * This analyzes the existing added records, finds all database relations to records and adds these records to the export file.
     * This function can be called repeatedly until it returns an empty array.
     * In principle it should not allow to infinite recursivity, but you better set a limit...
     * Call this BEFORE the ext_addFilesFromRelations (so files from added relations are also included of course)
     *
     * @param int $relationLevel Recursion level
     * @return array overview of relations found and added: Keys [table]:[uid], values array with table and id
     * @see export_addFilesFromRelations()
     */
    public function export_addDBRelations($relationLevel = 0)
    {
        // Traverse all "rels" registered for "records"
        if (!is_array($this->dat['records'])) {
            $this->addError('There were no records available.');
            return [];
        }
        $addR = [];
        foreach ($this->dat['records'] as $k => $value) {
            if (!is_array($this->dat['records'][$k])) {
                continue;
            }
            foreach ($this->dat['records'][$k]['rels'] as $fieldname => $vR) {
                // For all DB types of relations:
                if ($vR['type'] === 'db') {
                    foreach ($vR['itemArray'] as $fI) {
                        $this->export_addDBRelations_registerRelation($fI, $addR);
                    }
                }
                // For all flex/db types of relations:
                if ($vR['type'] === 'flex') {
                    // DB relations in flex form fields:
                    if (is_array($vR['flexFormRels']['db'])) {
                        foreach ($vR['flexFormRels']['db'] as $subList) {
                            foreach ($subList as $fI) {
                                $this->export_addDBRelations_registerRelation($fI, $addR);
                            }
                        }
                    }
                    // DB oriented soft references in flex form fields:
                    if (is_array($vR['flexFormRels']['softrefs'])) {
                        foreach ($vR['flexFormRels']['softrefs'] as $subList) {
                            foreach ($subList['keys'] as $spKey => $elements) {
                                foreach ($elements as $el) {
                                    if ($el['subst']['type'] === 'db' && $this->includeSoftref($el['subst']['tokenID'])) {
                                        [$tempTable, $tempUid] = explode(':', $el['subst']['recordRef']);
                                        $fI = [
                                            'table' => $tempTable,
                                            'id' => $tempUid
                                        ];
                                        $this->export_addDBRelations_registerRelation($fI, $addR, $el['subst']['tokenID']);
                                    }
                                }
                            }
                        }
                    }
                }
                // In any case, if there are soft refs:
                if (is_array($vR['softrefs']['keys'])) {
                    foreach ($vR['softrefs']['keys'] as $spKey => $elements) {
                        foreach ($elements as $el) {
                            if ($el['subst']['type'] === 'db' && $this->includeSoftref($el['subst']['tokenID'])) {
                                [$tempTable, $tempUid] = explode(':', $el['subst']['recordRef']);
                                $fI = [
                                    'table' => $tempTable,
                                    'id' => $tempUid
                                ];
                                $this->export_addDBRelations_registerRelation($fI, $addR, $el['subst']['tokenID']);
                            }
                        }
                    }
                }
            }
        }

        // Now, if there were new records to add, do so:
        if (!empty($addR)) {
            foreach ($addR as $fI) {
                // Get and set record:
                $row = BackendUtility::getRecord($fI['table'], $fI['id']);

                if (is_array($row)) {
                    // Depending on db driver, int fields may or may not be returned as integer or as string. The
                    // loop aligns that detail and forces strings for everything to have exports more db agnostic.
                    foreach ($row as $fieldName => $value) {
                        // Keep null but force everything else to string
                        $row[$fieldName] = $value === null ? $value : (string)$value;
                    }
                    $this->export_addRecord($fI['table'], $row, $relationLevel + 1);
                }
                // Set status message
                // Relation pointers always larger than zero except certain "select" types with
                // negative values pointing to uids - but that is not supported here.
                if ($fI['id'] > 0) {
                    $rId = $fI['table'] . ':' . $fI['id'];
                    if (!isset($this->dat['records'][$rId])) {
                        $this->dat['records'][$rId] = 'NOT_FOUND';
                        $this->addError('Relation record ' . $rId . ' was not found!');
                    }
                }
            }
        }
        // Return overview of relations found and added
        return $addR;
    }

    /**
     * Helper function for export_addDBRelations()
     *
     * @param array $fI Array with table/id keys to add
     * @param array $addR Add array, passed by reference to be modified
     * @param string $tokenID Softref Token ID, if applicable.
     * @see export_addDBRelations()
     */
    public function export_addDBRelations_registerRelation($fI, &$addR, $tokenID = '')
    {
        $rId = $fI['table'] . ':' . $fI['id'];
        if (
            isset($GLOBALS['TCA'][$fI['table']]) && !$this->isTableStatic($fI['table']) && !$this->isExcluded($fI['table'], $fI['id'])
            && (!$tokenID || $this->includeSoftref($tokenID)) && $this->inclRelation($fI['table'])
        ) {
            if (!isset($this->dat['records'][$rId])) {
                // Set this record to be included since it is not already.
                $addR[$rId] = $fI;
            }
        }
    }

    /**
     * Returns TRUE if the input table name is to be included as relation
     *
     * @param string $table Table name
     * @return bool TRUE, if table is marked static
     */
    public function inclRelation(string $table): bool
    {
        return is_array($GLOBALS['TCA'][$table])
            && (in_array($table, $this->relOnlyTables) || in_array('_ALL', $this->relOnlyTables))
            && $this->getBackendUser()->check('tables_select', $table);
    }

    /**
     * This adds all files in relations.
     * Call this method AFTER adding all records including relations.
     *
     * @see export_addDBRelations()
     */
    public function export_addFilesFromRelations()
    {
        // Traverse all "rels" registered for "records"
        if (!is_array($this->dat['records'])) {
            $this->addError('There were no records available.');
            return;
        }
        foreach ($this->dat['records'] as $k => $value) {
            if (!isset($this->dat['records'][$k]['rels']) || !is_array($this->dat['records'][$k]['rels'])) {
                continue;
            }
            foreach ($this->dat['records'][$k]['rels'] as $fieldname => $vR) {
                // For all file type relations:
                if ($vR['type'] === 'file') {
                    foreach ($vR['newValueFiles'] as $key => $fI) {
                        $this->export_addFile($fI, $k, $fieldname);
                        // Remove the absolute reference to the file so it doesn't expose absolute paths from source server:
                        unset($this->dat['records'][$k]['rels'][$fieldname]['newValueFiles'][$key]['ID_absFile']);
                    }
                }
                // For all flex type relations:
                if ($vR['type'] === 'flex') {
                    if (is_array($vR['flexFormRels']['file'])) {
                        foreach ($vR['flexFormRels']['file'] as $key => $subList) {
                            foreach ($subList as $subKey => $fI) {
                                $this->export_addFile($fI, $k, $fieldname);
                                // Remove the absolute reference to the file so it doesn't expose absolute paths from source server:
                                unset($this->dat['records'][$k]['rels'][$fieldname]['flexFormRels']['file'][$key][$subKey]['ID_absFile']);
                            }
                        }
                    }
                    // DB oriented soft references in flex form fields:
                    if (is_array($vR['flexFormRels']['softrefs'])) {
                        foreach ($vR['flexFormRels']['softrefs'] as $key => $subList) {
                            foreach ($subList['keys'] as $spKey => $elements) {
                                foreach ($elements as $subKey => $el) {
                                    if ($el['subst']['type'] === 'file' && $this->includeSoftref($el['subst']['tokenID'])) {
                                        // Create abs path and ID for file:
                                        $ID_absFile = GeneralUtility::getFileAbsFileName(Environment::getPublicPath() . '/' . $el['subst']['relFileName']);
                                        $ID = md5($el['subst']['relFileName']);
                                        if ($ID_absFile) {
                                            if (!$this->dat['files'][$ID]) {
                                                $fI = [
                                                    'filename' => PathUtility::basename($ID_absFile),
                                                    'ID_absFile' => $ID_absFile,
                                                    'ID' => $ID,
                                                    'relFileName' => $el['subst']['relFileName']
                                                ];
                                                $this->export_addFile($fI, '_SOFTREF_');
                                            }
                                            $this->dat['records'][$k]['rels'][$fieldname]['flexFormRels']['softrefs'][$key]['keys'][$spKey][$subKey]['file_ID'] = $ID;
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
                // In any case, if there are soft refs:
                if (is_array($vR['softrefs']['keys'])) {
                    foreach ($vR['softrefs']['keys'] as $spKey => $elements) {
                        foreach ($elements as $subKey => $el) {
                            if ($el['subst']['type'] === 'file' && $this->includeSoftref($el['subst']['tokenID'])) {
                                // Create abs path and ID for file:
                                $ID_absFile = GeneralUtility::getFileAbsFileName(Environment::getPublicPath() . '/' . $el['subst']['relFileName']);
                                $ID = md5($el['subst']['relFileName']);
                                if ($ID_absFile) {
                                    if (!$this->dat['files'][$ID]) {
                                        $fI = [
                                            'filename' => PathUtility::basename($ID_absFile),
                                            'ID_absFile' => $ID_absFile,
                                            'ID' => $ID,
                                            'relFileName' => $el['subst']['relFileName']
                                        ];
                                        $this->export_addFile($fI, '_SOFTREF_');
                                    }
                                    $this->dat['records'][$k]['rels'][$fieldname]['softrefs']['keys'][$spKey][$subKey]['file_ID'] = $ID;
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    /**
     * This adds all files from sys_file records
     */
    public function export_addFilesFromSysFilesRecords()
    {
        if (!isset($this->dat['header']['records']['sys_file']) || !is_array($this->dat['header']['records']['sys_file'])) {
            return;
        }
        foreach ($this->dat['header']['records']['sys_file'] as $sysFileUid => $_) {
            $recordData = $this->dat['records']['sys_file:' . $sysFileUid]['data'];
            $file = GeneralUtility::makeInstance(ResourceFactory::class)->createFileObject($recordData);
            $this->export_addSysFile($file);
        }
    }

    /**
     * Adds a files content from a sys file record to the export memory
     *
     * @param File $file
     */
    public function export_addSysFile(File $file)
    {
        $fileContent = '';
        try {
            if (!$this->saveFilesOutsideExportFile) {
                $fileContent = $file->getContents();
            } else {
                $file->checkActionPermission('read');
            }
        } catch (\Exception $e) {
            $this->addError('Error when trying to add file ' . $file->getCombinedIdentifier() . ': ' . $e->getMessage());
            return;
        }
        $fileUid = $file->getUid();
        $fileSha1 = $file->getStorage()->hashFile($file, 'sha1');
        if ($fileSha1 !== $file->getProperty('sha1')) {
            $this->addError('File sha1 hash of ' . $file->getCombinedIdentifier() . ' is not up-to-date in index! File added on current sha1.');
            $this->dat['records']['sys_file:' . $fileUid]['data']['sha1'] = $fileSha1;
        }

        $fileRec = [];
        $fileRec['filename'] = $file->getProperty('name');
        $fileRec['filemtime'] = $file->getProperty('modification_date');

        // build unique id based on the storage and the file identifier
        $fileId = md5($file->getStorage()->getUid() . ':' . $file->getProperty('identifier_hash'));

        // Setting this data in the header
        $this->dat['header']['files_fal'][$fileId] = $fileRec;

        if (!$this->saveFilesOutsideExportFile) {
            // ... and finally add the heavy stuff:
            $fileRec['content'] = $fileContent;
        } else {
            GeneralUtility::upload_copy_move($file->getForLocalProcessing(false), $this->getOrCreateTemporaryFolderName() . '/' . $file->getProperty('sha1'));
        }
        $fileRec['content_sha1'] = $fileSha1;

        $this->dat['files_fal'][$fileId] = $fileRec;
    }

    /**
     * Adds a files content to the export memory
     *
     * @param array $fI File information with three keys: "filename" = filename without path, "ID_absFile" = absolute filepath to the file (including the filename), "ID" = md5 hash of "ID_absFile". "relFileName" is optional for files attached to records, but mandatory for soft referenced files (since the relFileName determines where such a file should be stored!)
     * @param string $recordRef If the file is related to a record, this is the id on the form [table]:[id]. Information purposes only.
     * @param string $fieldname If the file is related to a record, this is the field name it was related to. Information purposes only.
     */
    public function export_addFile($fI, $recordRef = '', $fieldname = '')
    {
        if (!@is_file($fI['ID_absFile'])) {
            $this->addError($fI['ID_absFile'] . ' was not a file! Skipping.');
            return;
        }
        $fileInfo = stat($fI['ID_absFile']);
        $fileRec = [];
        $fileRec['filename'] = PathUtility::basename($fI['ID_absFile']);
        $fileRec['filemtime'] = $fileInfo['mtime'];
        //for internal type file_reference
        $fileRec['relFileRef'] = PathUtility::stripPathSitePrefix($fI['ID_absFile']);
        if ($recordRef) {
            $fileRec['record_ref'] = $recordRef . '/' . $fieldname;
        }
        if ($fI['relFileName']) {
            $fileRec['relFileName'] = $fI['relFileName'];
        }
        // Setting this data in the header
        $this->dat['header']['files'][$fI['ID']] = $fileRec;
        // ... and for the recordlisting, why not let us know WHICH relations there was...
        if ($recordRef && $recordRef !== '_SOFTREF_') {
            $refParts = explode(':', $recordRef, 2);
            if (!is_array($this->dat['header']['records'][$refParts[0]][$refParts[1]]['filerefs'])) {
                $this->dat['header']['records'][$refParts[0]][$refParts[1]]['filerefs'] = [];
            }
            $this->dat['header']['records'][$refParts[0]][$refParts[1]]['filerefs'][] = $fI['ID'];
        }
        $fileMd5 = md5_file($fI['ID_absFile']);
        if (!$this->saveFilesOutsideExportFile) {
            // ... and finally add the heavy stuff:
            $fileRec['content'] = (string)file_get_contents($fI['ID_absFile']);
        } else {
            GeneralUtility::upload_copy_move($fI['ID_absFile'], $this->getOrCreateTemporaryFolderName() . '/' . $fileMd5);
        }
        $fileRec['content_md5'] = $fileMd5;
        $this->dat['files'][$fI['ID']] = $fileRec;
        // For soft references, do further processing:
        if ($recordRef === '_SOFTREF_') {
            // Files with external media?
            // This is only done with files grabbed by a softreference parser since it is deemed improbable that hard-referenced files should undergo this treatment.
            $html_fI = pathinfo(PathUtility::basename($fI['ID_absFile']));
            if ($this->includeExtFileResources && GeneralUtility::inList($this->extFileResourceExtensions, strtolower($html_fI['extension']))) {
                $uniquePrefix = '###' . md5($GLOBALS['EXEC_TIME']) . '###';
                if (strtolower($html_fI['extension']) === 'css') {
                    $prefixedMedias = explode($uniquePrefix, (string)preg_replace('/(url[[:space:]]*\\([[:space:]]*["\']?)([^"\')]*)(["\']?[[:space:]]*\\))/i', '\\1' . $uniquePrefix . '\\2' . $uniquePrefix . '\\3', $fileRec['content']));
                } else {
                    // html, htm:
                    $htmlParser = GeneralUtility::makeInstance(HtmlParser::class);
                    $prefixedMedias = explode($uniquePrefix, $htmlParser->prefixResourcePath($uniquePrefix, $fileRec['content'], [], $uniquePrefix));
                }
                $htmlResourceCaptured = false;
                foreach ($prefixedMedias as $k => $v) {
                    if ($k % 2) {
                        $EXTres_absPath = GeneralUtility::resolveBackPath(PathUtility::dirname($fI['ID_absFile']) . '/' . $v);
                        $EXTres_absPath = GeneralUtility::getFileAbsFileName($EXTres_absPath);
                        if ($EXTres_absPath && GeneralUtility::isFirstPartOfStr($EXTres_absPath, Environment::getPublicPath() . '/' . $this->fileadminFolderName . '/') && @is_file($EXTres_absPath)) {
                            $htmlResourceCaptured = true;
                            $EXTres_ID = md5($EXTres_absPath);
                            $this->dat['header']['files'][$fI['ID']]['EXT_RES_ID'][] = $EXTres_ID;
                            $prefixedMedias[$k] = '{EXT_RES_ID:' . $EXTres_ID . '}';
                            // Add file to memory if it is not set already:
                            if (!isset($this->dat['header']['files'][$EXTres_ID])) {
                                $fileInfo = stat($EXTres_absPath);
                                $fileRec = [];
                                $fileRec['filename'] = PathUtility::basename($EXTres_absPath);
                                $fileRec['filemtime'] = $fileInfo['mtime'];
                                $fileRec['record_ref'] = '_EXT_PARENT_:' . $fI['ID'];
                                // Media relative to the HTML file.
                                $fileRec['parentRelFileName'] = $v;
                                // Setting this data in the header
                                $this->dat['header']['files'][$EXTres_ID] = $fileRec;
                                // ... and finally add the heavy stuff:
                                $fileRec['content'] = (string)file_get_contents($EXTres_absPath);
                                $fileRec['content_md5'] = md5($fileRec['content']);
                                $this->dat['files'][$EXTres_ID] = $fileRec;
                            }
                        }
                    }
                }
                if ($htmlResourceCaptured) {
                    $this->dat['files'][$fI['ID']]['tokenizedContent'] = implode('', $prefixedMedias);
                }
            }
        }
    }

    /**
     * DB relations flattened to 1-dim array.
     * The list will be unique, no table/uid combination will appear twice.
     *
     * @param array $dbrels 2-dim Array of database relations organized by table key
     * @return array 1-dim array where entries are table:uid and keys are array with table/id
     */
    public function flatDBrels($dbrels)
    {
        $list = [];
        foreach ($dbrels as $dat) {
            if ($dat['type'] === 'db') {
                foreach ($dat['itemArray'] as $i) {
                    $list[$i['table'] . ':' . $i['id']] = $i;
                }
            }
            if ($dat['type'] === 'flex' && is_array($dat['flexFormRels']['db'])) {
                foreach ($dat['flexFormRels']['db'] as $subList) {
                    foreach ($subList as $i) {
                        $list[$i['table'] . ':' . $i['id']] = $i;
                    }
                }
            }
        }
        return $list;
    }

    /**
     * Soft References flattened to 1-dim array.
     *
     * @param array $dbrels 2-dim Array of database relations organized by table key
     * @return array 1-dim array where entries are arrays with properties of the soft link found and keys are a unique combination of field, spKey, structure path if applicable and token ID
     */
    public function flatSoftRefs($dbrels)
    {
        $list = [];
        foreach ($dbrels as $field => $dat) {
            if (is_array($dat['softrefs']['keys'])) {
                foreach ($dat['softrefs']['keys'] as $spKey => $elements) {
                    if (is_array($elements)) {
                        foreach ($elements as $subKey => $el) {
                            $lKey = $field . ':' . $spKey . ':' . $subKey;
                            $list[$lKey] = array_merge(['field' => $field, 'spKey' => $spKey], $el);
                            // Add file_ID key to header - slightly "risky" way of doing this because if the calculation
                            // changes for the same value in $this->records[...] this will not work anymore!
                            if ($el['subst'] && $el['subst']['relFileName']) {
                                $list[$lKey]['file_ID'] = md5(Environment::getPublicPath() . '/' . $el['subst']['relFileName']);
                            }
                        }
                    }
                }
            }
            if ($dat['type'] === 'flex' && is_array($dat['flexFormRels']['softrefs'])) {
                foreach ($dat['flexFormRels']['softrefs'] as $structurePath => $subSoftrefs) {
                    if (is_array($subSoftrefs['keys'])) {
                        foreach ($subSoftrefs['keys'] as $spKey => $elements) {
                            foreach ($elements as $subKey => $el) {
                                $lKey = $field . ':' . $structurePath . ':' . $spKey . ':' . $subKey;
                                $list[$lKey] = array_merge(['field' => $field, 'spKey' => $spKey, 'structurePath' => $structurePath], $el);
                                // Add file_ID key to header - slightly "risky" way of doing this because if the calculation
                                // changes for the same value in $this->records[...] this will not work anymore!
                                if ($el['subst'] && $el['subst']['relFileName']) {
                                    $list[$lKey]['file_ID'] = md5(Environment::getPublicPath() . '/' . $el['subst']['relFileName']);
                                }
                            }
                        }
                    }
                }
            }
        }
        return $list;
    }

    /**
     * If include fields for a specific record type are set, the data
     * are filtered out with fields are not included in the fields.
     *
     * @param string $table The record type to be filtered
     * @param array $row The data to be filtered
     * @return array The filtered record row
     */
    protected function filterRecordFields($table, array $row)
    {
        if (isset($this->recordTypesIncludeFields[$table])) {
            $includeFields = array_unique(array_merge(
                $this->recordTypesIncludeFields[$table],
                $this->defaultRecordIncludeFields
            ));
            $newRow = [];
            foreach ($row as $key => $value) {
                if (in_array($key, $includeFields)) {
                    $newRow[$key] = $value;
                }
            }
        } else {
            $newRow = $row;
        }
        return $newRow;
    }

    /**************************
     * File Output
     *************************/

    /**
     * This compiles and returns the data content for an exported file
     * - "xml" gives xml
     * - "t3d" and "t3d_compressed" gives serialized array, possibly compressed
     *
     * @return string The output file stream
     */
    public function render()
    {
        if ($this->exportFileType === self::FILETYPE_XML) {
            $out = $this->createXML();
        } else {
            $out = '';
            // adding header:
            $out .= $this->addFilePart(serialize($this->dat['header']));
            // adding records:
            $out .= $this->addFilePart(serialize($this->dat['records']));
            // adding files:
            $out .= $this->addFilePart(serialize($this->dat['files']));
            // adding files_fal:
            $out .= $this->addFilePart(serialize($this->dat['files_fal']));
        }
        return $out;
    }

    /**
     * Creates XML string from input array
     *
     * @return string XML content
     */
    public function createXML()
    {
        // Options:
        $options = [
            'alt_options' => [
                '/header' => [
                    'disableTypeAttrib' => true,
                    'clearStackPath' => true,
                    'parentTagMap' => [
                        'files' => 'file',
                        'files_fal' => 'file',
                        'records' => 'table',
                        'table' => 'rec',
                        'rec:rels' => 'relations',
                        'relations' => 'element',
                        'filerefs' => 'file',
                        'pid_lookup' => 'page_contents',
                        'header:relStaticTables' => 'static_tables',
                        'static_tables' => 'tablename',
                        'excludeMap' => 'item',
                        'softrefCfg' => 'softrefExportMode',
                        'extensionDependencies' => 'extkey',
                        'softrefs' => 'softref_element'
                    ],
                    'alt_options' => [
                        '/pagetree' => [
                            'disableTypeAttrib' => true,
                            'useIndexTagForNum' => 'node',
                            'parentTagMap' => [
                                'node:subrow' => 'node'
                            ]
                        ],
                        '/pid_lookup/page_contents' => [
                            'disableTypeAttrib' => true,
                            'parentTagMap' => [
                                'page_contents' => 'table'
                            ],
                            'grandParentTagMap' => [
                                'page_contents/table' => 'item'
                            ]
                        ]
                    ]
                ],
                '/records' => [
                    'disableTypeAttrib' => true,
                    'parentTagMap' => [
                        'records' => 'tablerow',
                        'tablerow:data' => 'fieldlist',
                        'tablerow:rels' => 'related',
                        'related' => 'field',
                        'field:itemArray' => 'relations',
                        'field:newValueFiles' => 'filerefs',
                        'field:flexFormRels' => 'flexform',
                        'relations' => 'element',
                        'filerefs' => 'file',
                        'flexform:db' => 'db_relations',
                        'flexform:softrefs' => 'softref_relations',
                        'softref_relations' => 'structurePath',
                        'db_relations' => 'path',
                        'path' => 'element',
                        'keys' => 'softref_key',
                        'softref_key' => 'softref_element'
                    ],
                    'alt_options' => [
                        '/records/tablerow/fieldlist' => [
                            'useIndexTagForAssoc' => 'field'
                        ]
                    ]
                ],
                '/files' => [
                    'disableTypeAttrib' => true,
                    'parentTagMap' => [
                        'files' => 'file'
                    ]
                ],
                '/files_fal' => [
                    'disableTypeAttrib' => true,
                    'parentTagMap' => [
                        'files_fal' => 'file'
                    ]
                ]
            ]
        ];
        // Creating XML file from $outputArray:
        $charset = $this->dat['header']['charset'] ?: 'utf-8';
        $XML = '<?xml version="1.0" encoding="' . $charset . '" standalone="yes" ?>' . LF;
        $XML .= GeneralUtility::array2xml($this->dat, '', 0, 'T3RecordDocument', 0, $options);
        return $XML;
    }

    /**
     * Returns a content part for a filename being build.
     *
     * @param string $data Data to store in part
     * @return string Content stream.
     */
    public function addFilePart($data)
    {
        $compress = $this->exportFileType === self::FILETYPE_T3DZ;
        if ($compress) {
            $data = (string)gzcompress($data);
        }
        return md5($data) . ':' . ($compress ? '1' : '0') . ':' . str_pad((string)strlen($data), 10, '0', STR_PAD_LEFT) . ':' . $data . ':';
    }

    /**
     * @return File
     * @throws InsufficientFolderWritePermissionsException
     */
    public function saveToFile(): File
    {
        $saveFolder = $this->getOrCreateDefaultImportExportFolder();
        $fileName = $this->getOrGenerateExportFileNameWithFileExtension();
        $fileContent = $this->render();

        if (!($saveFolder instanceof Folder && $saveFolder->checkActionPermission('write'))) {
            throw new InsufficientFolderWritePermissionsException(
                'You are not allowed to write to the target folder "' . $saveFolder->getPublicUrl() .'"',
                1602432207
            );
        }

        $temporaryFileName = GeneralUtility::tempnam('export');
        GeneralUtility::writeFile($temporaryFileName, $fileContent);
        $file = $saveFolder->addFile($temporaryFileName, $fileName, 'replace');

        if ($this->saveFilesOutsideExportFile) {
            $filesFolderName = $fileName . '.files';
            $filesFolder = $saveFolder->createFolder($filesFolderName);
            $temporaryFilesForExport = GeneralUtility::getFilesInDir($this->getOrCreateTemporaryFolderName(), '', true);
            foreach ($temporaryFilesForExport as $temporaryFileForExport) {
                $filesFolder->addFile($temporaryFileForExport);
            }
            $this->removeTemporaryFolderName();
        }

        return $file;
    }

    /**
     * @return string
     */
    public function getExportFileName(): string
    {
        return $this->exportFileName;
    }

    /**
     * @param string $exportFileName
     */
    public function setExportFileName(string $exportFileName): void
    {
        $exportFileName = trim(preg_replace('/[^[:alnum:]._-]*/', '', $exportFileName));
        $this->exportFileName = $exportFileName;
    }

    /**
     * @return string
     */
    public function getOrGenerateExportFileNameWithFileExtension(): string
    {
        if (!empty($this->exportFileName)) {
            $exportFileName = $this->exportFileName;
        } else {
            $exportFileName = $this->generateExportFileName();
        }
        $exportFileName .= $this->getFileExtensionByFileType();

        return $exportFileName;
    }

    /**
     * @return string
     */
    protected function generateExportFileName(): string
    {
        if ($this->pid !== -1) {
            $exportFileName = 'tree_PID' . $this->pid . '_L' . $this->levels;
        } elseif (!empty($this->getRecord())) {
            $exportFileName = 'recs_' . implode('-', $this->getRecord());
            $exportFileName = str_replace(':', '_', $exportFileName);
        } elseif (!empty($this->getList())) {
            $exportFileName = 'list_' . implode('-', $this->getList());
            $exportFileName = str_replace(':', '_', $exportFileName);
        } else {
            $exportFileName = 'export';
        }

        $exportFileName = substr(trim(preg_replace('/[^[:alnum:]_-]/', '-', $exportFileName)), 0, 20);

        return 'T3D_' . $exportFileName . '_' . date('Y-m-d_H-i');
    }

    /**
     * @return string
     */
    public function getExportFileType(): string
    {
        return $this->exportFileType;
    }

    /**
     * @param string $exportFileType
     */
    public function setExportFileType(string $exportFileType): void
    {
        $supportedFileTypes = $this->getSupportedFileTypes();
        if (!in_array($exportFileType, $supportedFileTypes)) {
            throw new \InvalidArgumentException(
                sprintf(
                    'File type "%s" is not valid. Supported file types are %s.',
                    $exportFileType,
                    implode(', ', array_map(function($fileType){
                        return '"' . $fileType . '"';
                    }, $supportedFileTypes))
                ),
                1602505264
            );
        }
        $this->exportFileType = $exportFileType;
    }

    /**
     * @return array
     */
    public function getSupportedFileTypes(): array
    {
        if (empty($this->supportedFileTypes)) {
            $supportedFileTypes = [];
            $supportedFileTypes[] = self::FILETYPE_XML;
            $supportedFileTypes[] = self::FILETYPE_T3D;
            if ($this->isCompressionAvailable()) {
                $supportedFileTypes[] = self::FILETYPE_T3DZ;
            }
            $this->supportedFileTypes = $supportedFileTypes;
        }
        return $this->supportedFileTypes;
    }

    /**
     * @return bool
     */
    protected function isCompressionAvailable(): bool
    {
        return function_exists('gzcompress');
    }

    /**
     * @return string
     */
    public function getFileExtensionByFileType(): string
    {
        switch ($this->exportFileType) {
            case self::FILETYPE_XML:
                return '.xml';
            case self::FILETYPE_T3D:
                return '.t3d';
            case self::FILETYPE_T3DZ:
            default:
                return '-z.t3d';
        }
    }

    /**************************
     * Getters and Setters
     *************************/

    /**
     * @return string
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * @param string $title
     */
    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    /**
     * @return string
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * @param string $description
     */
    public function setDescription(string $description): void
    {
        $this->description = $description;
    }

    /**
     * @return string
     */
    public function getNotes(): string
    {
        return $this->notes;
    }

    /**
     * @param string $notes
     */
    public function setNotes(string $notes): void
    {
        $this->notes = $notes;
    }

    /**
     * @return array
     */
    public function getRecord(): array
    {
        return $this->record;
    }

    /**
     * @param array $record
     */
    public function setRecord(array $record): void
    {
        $this->record = $record;
    }

    /**
     * @return array
     */
    public function getList(): array
    {
        return $this->list;
    }

    /**
     * @param array $list
     */
    public function setList(array $list): void
    {
        $this->list = $list;
    }

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
    }

    /**
     * @return int
     */
    public function getLevels(): int
    {
        return $this->levels;
    }

    /**
     * @param int $levels
     */
    public function setLevels(int $levels): void
    {
        $this->levels = $levels;
    }

    /**
     * @return array
     */
    public function getTables(): array
    {
        return $this->tables;
    }

    /**
     * @param array $tables
     */
    public function setTables(array $tables): void
    {
        $this->tables = $tables;
    }

    /**
     * @return array
     */
    public function getRelOnlyTables(): array
    {
        return $this->relOnlyTables;
    }

    /**
     * @param array $relOnlyTables
     */
    public function setRelOnlyTables(array $relOnlyTables): void
    {
        $this->relOnlyTables = $relOnlyTables;
    }

    /**
     * @return string
     */
    public function getTreeHTML(): string
    {
        return $this->treeHTML;
    }

    /**
     * @return bool
     */
    public function isIncludeExtFileResources(): bool
    {
        return $this->includeExtFileResources;
    }

    /**
     * @param bool $includeExtFileResources
     */
    public function setIncludeExtFileResources(bool $includeExtFileResources): void
    {
        $this->includeExtFileResources = $includeExtFileResources;
    }

    /**
     * Option to enable having the files not included in the export file.
     * The files are saved to a temporary folder instead.
     *
     * @param bool $saveFilesOutsideExportFile
     * @see ImportExport::getOrCreateTemporaryFolderName()
     */
    public function setSaveFilesOutsideExportFile(bool $saveFilesOutsideExportFile)
    {
        $this->saveFilesOutsideExportFile = $saveFilesOutsideExportFile;
    }

    /**
     * @return bool
     */
    public function isSaveFilesOutsideExportFile(): bool
    {
        return $this->saveFilesOutsideExportFile;
    }
}
