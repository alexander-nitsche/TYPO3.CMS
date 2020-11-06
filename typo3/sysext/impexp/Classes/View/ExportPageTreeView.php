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

namespace TYPO3\CMS\Impexp\View;

use TYPO3\CMS\Backend\Tree\View\BrowseTreeView;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Type\Bitmask\Permission;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Impexp\Export;

/**
 * Extension of the page tree class. Used to get the tree of pages to export.
 * @internal
 */
class ExportPageTreeView extends BrowseTreeView
{
    /**
     * @var LanguageService
     */
    private $lang;

    /**
     * Initialization
     */
    public function __construct()
    {
        parent::__construct();
        $this->init();

        $this->lang = $this->getLanguageService();
    }

    /**
     * Wrapping title from page tree.
     *
     * @param string $title Title to wrap
     * @param string $row Item record
     * @param int $bank Bank pointer (which mount point number)
     * @return string Wrapped title
     * @internal
     */
    public function wrapTitle($title, $row, $bank = 0)
    {
        return trim($title) === '' ? '<em>[' . htmlspecialchars($this->lang->sL('LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:labels.no_title')) . ']</em>' : htmlspecialchars($title);
    }

    /**
     * Remove link from Plus/Minus icon
     *
     * @param string $icon Icon HTML
     * @param string $cmd
     * @param string $bMark
     * @param bool $isOpen
     * @return string Icon HTML
     */
    public function PM_ATagWrap($icon, $cmd, $bMark = '', $isOpen = false)
    {
        return $icon;
    }

    /**
     * Remove link from icon
     *
     * @param string $icon Icon HTML
     * @param array $row Record row (page)
     * @return string Icon HTML
     */
    public function wrapIcon($icon, $row)
    {
        return $icon;
    }

    /**
     * Tree rendering
     *
     * @param int $pid PID value
     * @param string $clause Additional where clause
     * @return array Array of tree elements
     */
    public function ext_tree(int $pid, string $clause = '')
    {
        // Initialize:
        $this->init(' AND ' . $this->BE_USER->getPagePermsClause(Permission::PAGE_SHOW) . $clause);
        // Get stored tree structure:
        $this->stored = json_decode($this->BE_USER->uc['browseTrees']['browsePages'], true);
        $treeArr = [];
        $idx = 0;
        // Set first:
        $this->bank = $idx;
        $isOpen = $this->stored[$idx][$pid] || $this->expandFirst;
        // save ids
        $curIds = $this->ids;
        $this->reset();
        $this->ids = $curIds;
        if ($pid > 0) {
            $rootRecord = BackendUtility::getRecordWSOL('pages', $pid);
            $rootHtml = $this->getPageIcon($rootRecord);
        } else {
            $rootRecord = [
                'title' => $GLOBALS['TYPO3_CONF_VARS']['SYS']['sitename'],
                'uid' => 0
            ];
            $rootHtml = $this->getRootIcon($rootRecord);
        }
        $this->tree[] = ['HTML' => $rootHtml, 'row' => $rootRecord, 'hasSub' => $isOpen];
        if ($isOpen) {
            // Set depth:
            if ($this->addSelfId) {
                $this->ids[] = $pid;
            }
            $this->getTree($pid, Export::LEVELS_INFINITE, '');
            $idH = [];
            $idH[$pid]['uid'] = $pid;
            if (!empty($this->buffer_idH)) {
                $idH[$pid]['subrow'] = $this->buffer_idH;
            }
            $this->buffer_idH = $idH;
        }
        // Add tree:
        return array_merge($treeArr, $this->tree);
    }

    /**
     * Get page icon for the row.
     *
     * @param array $row
     * @return string Icon image tag.
     */
    protected function getPageIcon(array $row): string
    {
        $iconFactory = GeneralUtility::makeInstance(IconFactory::class);
        return $iconFactory->getIconForRecord($this->table, $row, Icon::SIZE_SMALL)->render();
    }
}
