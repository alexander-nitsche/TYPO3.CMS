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

use TYPO3\CMS\Backend\Configuration\BackendUserConfiguration;
use TYPO3\CMS\Backend\Tree\View\BrowseTreeView;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Localization\LanguageService;
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
     * Construction of the tree structure with predefined depth.
     *
     * @param int $pid Page ID
     * @param int $levels Page tree levels
     */
    public function buildTreeByLevels(int $pid, int $levels): void
    {
        $this->expandAll = true;
        $checkSub = $levels > 0;

        $this->buildTree($pid, $levels, $checkSub);
    }

    /**
     * Construction of the tree structure according to the state of folding of the page tree module.
     *
     * @param int $pid Page ID
     */
    public function buildTreeByExpandedState(int $pid): void
    {
        $this->syncPageTreeState();

        $this->expandAll = false;
        if ($pid > 0) {
            $checkSub = (bool)($this->stored[$this->bank][$pid] ?? false);
        } else {
            $checkSub = true;
        }

        $this->buildTree($pid, Export::LEVELS_INFINITE, $checkSub);
    }

    /**
     * Creation of a tree structure with predefined depth to prepare the export.
     *
     * @param int $pid Page ID
     * @param int $levels Page tree levels
     * @param bool $checkSub Should root page be checked for sub pages?
     */
    protected function buildTree(int $pid, int $levels, bool $checkSub): void
    {
        $this->reset();

        // Root page
        if ($pid > 0) {
            $rootRecord = BackendUtility::getRecordWSOL('pages', $pid);
            $rootHtml = $this->getPageIcon($rootRecord);
        } else {
            $rootRecord = [
                'title' => $GLOBALS['TYPO3_CONF_VARS']['SYS']['sitename'],
                'uid' => 0,
            ];
            $rootHtml = $this->getRootIcon($rootRecord);
        }

        $this->tree[] = [
            'HTML' => $rootHtml,
            'row' => $rootRecord,
            'hasSub' => $checkSub,
            'bank' => $this->bank,
        ];

        // Subtree
        if ($checkSub) {
            $this->getTree($pid, $levels);
        }

        $idH = [];
        $idH[$pid]['uid'] = $pid;
        if (!empty($this->buffer_idH)) {
            $idH[$pid]['subrow'] = $this->buffer_idH;
        }
        $this->buffer_idH = $idH;

        // Check if root page has subtree
        if (empty($this->buffer_idH)) {
            $this->tree[0]['hasSub'] = false;
        }
    }

    /**
     * Sync folding state of EXT:impexp page tree with the official page tree module
     */
    protected function syncPageTreeState(): void
    {
        $backendUserConfiguration = GeneralUtility::makeInstance(BackendUserConfiguration::class);
        $pageTreeState = $backendUserConfiguration->get('BackendComponents.States.Pagetree');
        if (is_object($pageTreeState) && is_object($pageTreeState->stateHash)) {
            $pageTreeState = (array)$pageTreeState->stateHash;
        } else {
            $pageTreeState = $pageTreeState['stateHash'] ?: [];
        }

        $this->stored = [];
        foreach ($pageTreeState as $identifier => $isExpanded) {
            list($bank, $pageId) = explode('_', $identifier);
            $this->stored[$bank][$pageId] = $isExpanded;
        }
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
