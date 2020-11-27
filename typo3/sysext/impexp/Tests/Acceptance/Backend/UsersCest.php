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

use TYPO3\CMS\Impexp\Tests\Acceptance\Support\BackendTester;
use TYPO3\CMS\Impexp\Tests\Acceptance\Support\Helper\SiteConfiguration;

/**
 * Various context menu related tests
 */
class UsersCest extends AbstractCest
{
    protected $inPageTree = '#typo3-pagetree-treeContainer .nodes';
    protected $inModuleHeader = '.module-docheader';
    protected $inModuleTabs = '#ImportExportController .nav-tabs';
    protected $inModuleTabsBody = '#ImportExportController .tab-content';

    protected $buttonUser = '#typo3-cms-backend-backend-toolbaritems-usertoolbaritem';
    protected $buttonLogout = '#typo3-cms-backend-backend-toolbaritems-usertoolbaritem a.btn.btn-danger';
    protected $contextMenuMore = '#contentMenu0 a.list-group-item-submenu';
    protected $contextMenuExport = '#contentMenu1 .list-group-item[data-callback-action=exportT3d]';
    protected $contextMenuImport = '#contentMenu1 .list-group-item[data-callback-action=importT3d]';
    protected $buttonViewPage = 'span[data-identifier="actions-view-page"]';
    protected $tabUpload = 'a[href="#import-upload"]';
    protected $checkboxForceAllUids = 'input#checkForce_all_UIDS';

    /**
     * @param BackendTester $I
     * @param SiteConfiguration $siteConfiguration
     * @throws \Exception
     */
    public function _before(BackendTester $I, SiteConfiguration $siteConfiguration)
    {
        $siteConfiguration->adjustSiteConfiguration();
        $I->useExistingSession('admin');
    }

    /**
     * @param BackendTester $I
     *
     * @throws \Exception
     */
    public function doNotShowImportInContextMenuForNonAdminUser(BackendTester $I): void
    {
        $selectedPageIcon = $this->inPageTree . ' .node.identifier-1_1 .node-icon-container';

        $this->setPageAccess($I, 1, 1);
        $this->setModAccess($I, 1, ['web_list' => true]);
        $this->setUserTsConfig($I, 2, '');
        $this->switchToUser($I, 2);

        $I->click($selectedPageIcon);
        $I->waitForElementVisible($this->contextMenuMore, 5);
        $I->click($this->contextMenuMore);
        $I->waitForElementVisible($this->contextMenuExport, 5);
        $I->seeElement($this->contextMenuExport);
        $I->dontSeeElement($this->contextMenuImport);

        $this->logoutUser($I);
    }

    /**
     * @param BackendTester $I
     *
     * @throws \Exception
     */
    public function showImportInContextMenuForNonAdminUserIfFlagSet(BackendTester $I): void
    {
        $selectedPageIcon = $this->inPageTree . ' .node.identifier-1_1 .node-icon-container';

        $this->setUserTsConfig($I, 2, 'options.impexp.enableImportForNonAdminUser = 1');
        $this->switchToUser($I, 2);

        $I->click($selectedPageIcon);
        $I->waitForElementVisible($this->contextMenuMore, 5);
        $I->click($this->contextMenuMore);
        $I->waitForElementVisible($this->contextMenuExport, 5);
        $I->seeElement($this->contextMenuExport);
        $I->seeElement($this->contextMenuImport);

        $this->logoutUser($I);
    }

    /**
     * @param BackendTester $I
     *
     * @throws \Exception
     */
    public function hideImportCheckboxForceAllUidsForNonAdmin(BackendTester $I): void
    {
        $I->click($this->inPageTree . ' .node.identifier-0_1 .node-icon-container');
        $I->waitForElementVisible($this->contextMenuMore, 5);
        $I->click($this->contextMenuMore);
        $I->waitForElementVisible($this->contextMenuImport, 5);
        $I->click($this->contextMenuImport);
        $I->switchToContentFrame();
        $I->seeElement($this->checkboxForceAllUids);

        $this->switchToUser($I, 2);

        $I->click($this->inPageTree . ' .node.identifier-1_1 .node-icon-container');
        $I->waitForElementVisible($this->contextMenuMore, 5);
        $I->click($this->contextMenuMore);
        $I->waitForElementVisible($this->contextMenuImport, 5);
        $I->click($this->contextMenuImport);
        $I->switchToContentFrame();
        $I->dontSeeElement($this->checkboxForceAllUids);

        $this->logoutUser($I);
    }

    /**
     * @param BackendTester $I
     *
     * @throws \Exception
     */
    public function hideUploadTabAndImportPathIfNoImportFolderAvailable(BackendTester $I): void
    {
        $I->click($this->inPageTree . ' .node.identifier-0_1 .node-icon-container');
        $I->waitForElementVisible($this->contextMenuMore, 5);
        $I->click($this->contextMenuMore);
        $I->waitForElementVisible($this->contextMenuImport, 5);
        $I->click($this->contextMenuImport);
        $I->switchToContentFrame();
        $I->see('From path:', $this->inModuleTabsBody);
        $I->seeElement($this->inModuleTabs . ' ' . $this->tabUpload);

        $this->switchToUser($I, 2);

        $I->click($this->inPageTree . ' .node.identifier-1_1 .node-icon-container');
        $I->waitForElementVisible($this->contextMenuMore, 5);
        $I->click($this->contextMenuMore);
        $I->waitForElementVisible($this->contextMenuImport, 5);
        $I->click($this->contextMenuImport);
        $I->switchToContentFrame();
        $I->dontSee('From path:', $this->inModuleTabsBody);
        $I->dontSeeElement($this->inModuleTabs . ' ' . $this->tabUpload);

        $this->logoutUser($I);

        $this->setPageAccess($I, 1, 0);
        $this->setModAccess($I, 1, ['web_list' => false]);
        $this->setUserTsConfig($I, 2, '');
    }

    /**
     * @param BackendTester $I
     *
     * @throws \Exception
     */
    public function checkVisualElements(BackendTester $I): void
    {
        $I->click($this->inPageTree . ' .node.identifier-0_0 .node-icon-container');
        $I->waitForElementVisible($this->contextMenuMore, 5);
        $I->click($this->contextMenuMore);
        $I->waitForElementVisible($this->contextMenuExport, 5);
        $I->click($this->contextMenuImport);
        $I->switchToContentFrame();
        $I->dontSeeElement($this->inModuleHeader . ' ' . $this->buttonViewPage);

        $I->switchToMainFrame();

        $I->click("List");
        $I->click($this->inPageTree . ' .node.identifier-0_1 .node-icon-container');
        $I->waitForElementVisible($this->contextMenuMore, 5);
        $I->click($this->contextMenuMore);
        $I->waitForElementVisible($this->contextMenuExport, 5);
        $I->click($this->contextMenuImport);
        $I->switchToContentFrame();
        $I->seeElement($this->inModuleHeader . ' ' . $this->buttonViewPage);

        $this->setPageAccess($I, 1, 1);
        $this->setModAccess($I, 1, ['web_list' => true]);
        $this->setUserTsConfig($I, 2, 'options.impexp.enableImportForNonAdminUser = 1');
        $this->switchToUser($I, 2);

        $I->click($this->inPageTree . ' .node.identifier-1_1 .node-icon-container');
        $I->waitForElementVisible($this->contextMenuMore, 5);
        $I->click($this->contextMenuMore);
        $I->waitForElementVisible($this->contextMenuExport, 5);
        $I->click($this->contextMenuImport);
        $I->switchToContentFrame();
        $I->seeElement($this->inModuleHeader . ' ' . $this->buttonViewPage);

        $this->logoutUser($I);

        $this->setPageAccess($I, 1, 0);
        $this->setModAccess($I, 1, ['web_list' => false]);
        $this->setUserTsConfig($I, 2, '');
    }
}
