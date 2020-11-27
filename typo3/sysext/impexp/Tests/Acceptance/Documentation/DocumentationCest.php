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

namespace TYPO3\CMS\Impexp\Tests\Acceptance\Documentation;

use TYPO3\CMS\Impexp\Tests\Acceptance\Support\BackendTester;
use TYPO3\CMS\Impexp\Tests\Acceptance\Support\Helper\PageTree;
use TYPO3\CMS\Impexp\Tests\Acceptance\Support\Helper\SiteConfiguration;

/**
 * Taking screenshots on a tour of the import and export modules.
 */
class DocumentationCest
{
    /**
     * Absolute path to files that must be removed
     * after a test - handled in _after
     *
     * @var array
     */
    protected $testFilesToDelete = [];

    protected $buttonTogglePageTree = '.t3js-topbar-button-navigationcomponent';
    protected $buttonToggleModuleMenu = '.t3js-topbar-button-modulemenu';

    protected $inPageTree = '#typo3-pagetree-treeContainer .nodes';
    protected $inModuleHeader = '.module-docheader';
    protected $inModuleTabs = '#ImportExportController .nav-tabs';
    protected $inModuleTabsBody = '#ImportExportController .tab-content';
    protected $inTabConfiguration = '#export-configuration';
    protected $inFlashMessages = '.typo3-messages';

    protected $contextMenuMore = '#contentMenu0 a.list-group-item-submenu';
    protected $contextMenuExport = '#contentMenu1 .list-group-item[data-callback-action=exportT3d]';

    /**
     * @param BackendTester $I
     * @param PageTree $pageTree
     * @param SiteConfiguration $siteConfiguration
     * @throws \Exception
     */
    public function _before(BackendTester $I, PageTree $pageTree, SiteConfiguration $siteConfiguration)
    {
        $siteConfiguration->adjustSiteConfiguration();

        $I->resizeWindow(640, 640);
//        $I->resizeWindow(640, 1800);

        $I->useExistingSession('admin');
        $I->click('List');
        $I->click($this->buttonToggleModuleMenu);

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
        $this->testFilesToDelete = [];
    }

    /**
     * @param BackendTester $I
     */
    public function walkExportModule(BackendTester $I): void
    {
        $selectedPageIcon = $this->inPageTree . ' .node.identifier-0_32 .node-icon-container';

        // [hide page tree]
        $I->click($this->buttonTogglePageTree);
        $I->switchToContentFrame();

        // List module - Pages
        $I->scrollModuleBodyTo(['id' => 'recordlist-pages'], 0, -120);
        $I->click('(//a[@data-table="pages"])[1]');
        $I->waitForElementVisible($this->contextMenuMore, 5);
        $I->click($this->contextMenuMore);
        $I->moveMouseOver($this->contextMenuExport);
        $I->makeScreenshot('export1-2');

        // List module - Pages only
        $I->moveMouseOver('a[title="Create new record"]');
        $I->waitForElementNotVisible($this->contextMenuExport);
        $I->click('//div[contains(@class, "panel-heading")]/a[contains(text(), "Page")]');
        $I->waitForElementVisible('a[title="Export"]', 3);
        $I->moveMouseOver('a[title="Export"]');
        $I->makeScreenshot('export1');

        // List module - Clipboard
        $I->scrollModuleBodyTo(["id" => "clipboard_form"], 0, -80);
        $I->click('//div[@id="clipboard_form"]//tr[3]//a');
        $I->waitForElement('//div[@id="clipboard_form"]//tr[3]//span[contains(@class, "fa-check-circle")]');

        // List module - Pages
        $I->scrollModuleBodyTo(['id' => 'recordlist-pages'], 0, -120);
        $I->click('//div[@id="recordlist-pages"]//tbody/tr[1]/td[4]//label');
        $I->moveMouseOver('//div[@id="recordlist-pages"]//thead/tr[1]/th[4]/div[2]/a[1]');
        $I->makeScreenshot('export2-1');
        $I->click('//div[@id="recordlist-pages"]//thead/tr[1]/th[4]/div[2]/a[1]');
        $I->wait(1);

        // List module - Clipboard
        $I->scrollModuleBodyTo(["id" => "clipboard_form"], 0, -80);
        $I->click('//button[@id="menuSelector"]');
        $I->waitForElementVisible('//button[@id="menuSelector"]/following-sibling::ul');
        $I->moveMouseOver('//button[@id="menuSelector"]/following-sibling::ul/li[1]/a');
        $I->makeScreenshot('export2-2');
        $I->scrollModuleBodyTo(['id' => 'recordlist-pages'], 0, -120);

        // [Show page tree]
        $I->switchToMainFrame();
        $I->click($this->buttonTogglePageTree);

        // Page tree
        $I->click($selectedPageIcon);
        $I->waitForElementVisible($this->contextMenuMore, 5);
        $I->click($this->contextMenuMore);
        $I->moveMouseOver($this->contextMenuExport);
        $I->makeScreenshot('export');
        $I->click($this->contextMenuExport);

        $I->switchToContentFrame();

        // Export module - Tab "Configuration"
        $I->click('.btn[value=Update]', $this->inTabConfiguration);

        // [Hide page tree]
        $I->switchToMainFrame();
        $I->click($this->buttonTogglePageTree);
        $I->switchToContentFrame();

        // Export module - Tab "Configuration"
        $I->waitForElement('.t3js-impexp-preview', 5);
        $I->makeScreenshot('pagetreecfg');
        $I->scrollModuleBodyTo(['name' => 'tx_impexp[pagetree][levels]'], 0, -80);
        //TODO: Opening basic selectbox via click does not work :(
//        $I->click(['name' => 'tx_impexp[pagetree][levels]']);
//        $I->makeScreenshot('impexp_misc');
        //TODO: End
        $I->scrollModuleBodyTo(['css' => '.t3js-impexp-preview'], 0, -80);
        $I->wait(1);
        $I->makeScreenshot('impexp');

        //TODO: Continue here and generate all required images for documentation: TYPO3 Manual > Other > Import / Export
    }
}
