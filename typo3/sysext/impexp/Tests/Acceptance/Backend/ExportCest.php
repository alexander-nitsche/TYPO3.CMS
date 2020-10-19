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

namespace TYPO3\CMS\Impexp\Tests\Acceptance\Backend;

use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Impexp\Tests\Acceptance\Support\Helper\PageTree;
use TYPO3\CMS\Impexp\Tests\Acceptance\Support\Helper\SiteConfiguration;
use TYPO3\CMS\Impexp\Tests\Acceptance\Support\BackendTester;

/**
 * Various export related tests
 */
class ExportCest
{
    /**
     * Absolute path to files that must be removed
     * after a test - handled in _after
     *
     * @var array
     */
    protected $testFilesToDelete = [];

    protected $inPageTree = '#typo3-pagetree-treeContainer .nodes';
    protected $inModuleTabs = '#ImportExportController .nav-tabs';
    protected $inModuleBody = '#ImportExportController .tab-content';
    protected $inFlashMessages = '.typo3-messages';

    /**
     * @param BackendTester $I
     * @param PageTree $pageTree
     * @param SiteConfiguration $siteConfiguration
     * @throws \Exception
     */
    public function _before(BackendTester $I, PageTree $pageTree, SiteConfiguration $siteConfiguration)
    {
        $siteConfiguration->adjustSiteConfiguration();
        $I->useExistingSession('admin');
        $I->click('List');

        $pageTree->openPath(['styleguide TCA demo']);
        $I->waitForElement($this->inPageTree . ' .node', 5);
    }

    /**
     * @param BackendTester $I
     */
    public function _after(BackendTester $I)
    {
        $I->amGoingTo('clean up created files');

        foreach ($this->testFilesToDelete as $filePath) {
            unlink($filePath);
            $I->dontSeeFileFound($filePath);
        }
    }

    /**
     * @param BackendTester $I
     *
     * @throws \Exception
     */
    public function exportPageAndRecords(BackendTester $I)
    {
        $I->wantToTest('exporting a page with records.');

        $page1Icon = '.node.identifier-0_1 .node-icon-container';
        $contextMenuMore = '#contentMenu0 a.list-group-item-submenu';
        $contextMenuExport = '#contentMenu1 .list-group-item[data-callback-action=exportT3d]';

        $I->click($page1Icon);
        $I->waitForElementVisible($contextMenuMore, 5);
        $I->click($contextMenuMore);
        $I->waitForElementVisible($contextMenuExport, 5);
        $I->click($contextMenuExport);

        $tabExport = 'a[href="#export-filepreset"]';
        $contentExport = '#export-filepreset';
        $buttonSaveToFile = 'tx_impexp[save_export]';

        $I->switchToContentFrame();
        $I->click($tabExport, $this->inModuleTabs);
        $I->waitForElementVisible($contentExport, 5);
        $I->click($buttonSaveToFile, $this->inModuleBody);
        $I->wait(1);
        $I->canSeeElement($this->inFlashMessages . ' .alert.alert-success');
        $I->canSee('SAVED FILE', $this->inFlashMessages . ' .alert.alert-success .alert-title');
        $flashMessage = $I->grabTextFrom($this->inFlashMessages . ' .alert.alert-success .alert-message');
        preg_match('/[^"]+"([^"]+)"[^"]+/', $flashMessage, $flashMessageParts);
        $saveFilePath = Environment::getProjectPath() . '/' . $flashMessageParts[1];
        $I->assertFileExists($saveFilePath);

        $this->testFilesToDelete[] = $saveFilePath;
    }

    /**
     * @param BackendTester $I
     */
    protected function waitForAjaxRequestToFinish(BackendTester $I): void
    {
        $I->waitForJS('return $.active == 0;', 10);
        // sometimes rendering is still slower that ajax being finished.
        $I->wait(0.5);
    }
}
