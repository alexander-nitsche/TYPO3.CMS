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
class ContextMenuCest
{
    protected $inPageTree = '#typo3-pagetree-treeContainer .nodes';
    protected $inModuleHeader = '.module-docheader';

    protected $buttonUser = '#typo3-cms-backend-backend-toolbaritems-usertoolbaritem';
    protected $buttonLogout = '#typo3-cms-backend-backend-toolbaritems-usertoolbaritem a.btn.btn-danger';
    protected $contextMenuMore = '#contentMenu0 a.list-group-item-submenu';
    protected $contextMenuExport = '#contentMenu1 .list-group-item[data-callback-action=exportT3d]';
    protected $contextMenuImport = '#contentMenu1 .list-group-item[data-callback-action=importT3d]';

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

        $this->setPageAccess($I, 1, 0);
        $this->setModAccess($I, 1, ['web_list' => false]);
        $this->setUserTsConfig($I, 2, '');
    }

    protected function setPageAccess(BackendTester $I, int $pageId, int $userGroupId, int $recursionLevel = 1): void
    {
        $selectedPageNode = $this->inPageTree . ' [id="identifier-0_' . $pageId . '"]';

        $I->switchToMainFrame();
        $I->click('Access');
        $I->waitForElement($this->inPageTree . ' .node', 5);
        $I->click($selectedPageNode);
        $I->switchToContentFrame();
        $I->click('//table[@id="typo3-permissionList"]/tbody/tr[1]/td[1]/a[@title="Change permissions"]');
        $I->waitForElementVisible('#PermissionControllerEdit');
        $I->selectOption('//form[@id="PermissionControllerEdit"]/div[3]/select', ['value' => $userGroupId]);
        $recursionLevelOption = $I->grabTextFrom('//select[@id="recursionLevel"]/option[' . $recursionLevel . ']');
        $I->selectOption('//select[@id="recursionLevel"]', ['value' => $recursionLevelOption]);
        $I->click($this->inModuleHeader . ' .btn[title="Save and close"]');
    }

    protected function setModAccess(BackendTester $I, int $userGroupId, array $modAccessByName): void
    {
        try {
            $I->seeElement($this->inModuleHeader . " [name=BackendUserModuleMenu]");
        } catch (\Exception $e) {
            $I->switchToMainFrame();
            $I->click('Backend Users');
            $I->switchToContentFrame();
        }

        $I->selectOption($this->inModuleHeader . " [name=BackendUserModuleMenu]", ['text'=>'Backend user groups']);
        $I->waitForElement('#beuser-main-content');
        $I->click('//div[@id="beuser-main-content"]//table/tbody/tr[descendant::a[@data-uid="' . $userGroupId . '"]]/td[2]/a');
        $I->waitForElementVisible('#EditDocumentController');
        $I->click('//form[@id="EditDocumentController"]//ul/li[2]/a');

        foreach ($modAccessByName as $modName => $modAccess) {
            if ((bool)$modAccess) {
                $I->checkOption('//input[@value="' . $modName . '"]');
            } else {
                $I->uncheckOption('//input[@value="' . $modName . '"]');
            }
        }

        $I->click($this->inModuleHeader . ' .btn[title="Save"]');
        $I->wait(0.5);
        $I->click($this->inModuleHeader . ' .btn[title="Close"]');
        $I->waitForElement('#beuser-main-content');
    }

    protected function setUserTsConfig(BackendTester $I, int $userId, string $userTsConfig): void
    {
        try {
            $I->seeElement($this->inModuleHeader . " [name=BackendUserModuleMenu]");
        } catch (\Exception $e) {
            $I->switchToMainFrame();
            $I->click('Backend Users');
            $I->switchToContentFrame();
        }

        $I->selectOption($this->inModuleHeader . " [name=BackendUserModuleMenu]", ['text'=>'Backend users']);
        $I->waitForElement('#typo3-backend-user-list');
        $I->click('//table[@id="typo3-backend-user-list"]/tbody/tr[descendant::a[@data-uid="' . $userId . '"]]//a[@title="Edit"]');
        $I->waitForElement('#EditDocumentController');
        $I->click('//form[@id="EditDocumentController"]//ul/li[4]/a');
        $I->fillField('//div[@class="tab-content"]/div[4]/fieldset[1]//textarea', $userTsConfig);
        $I->click($this->inModuleHeader . ' .btn[title="Save"]');
        $I->wait(0.5);
        $I->click($this->inModuleHeader . ' .btn[title="Close"]');
        $I->waitForElement('#typo3-backend-user-list');
    }

    protected function switchToUser(BackendTester $I, int $userId): void
    {
        try {
            $I->seeElement($this->inModuleHeader . " [name=BackendUserModuleMenu]");
        } catch (\Exception $e) {
            $I->switchToMainFrame();
            $I->click('Backend Users');
            $I->switchToContentFrame();
        }

        $I->selectOption($this->inModuleHeader . " [name=BackendUserModuleMenu]", ['text'=>'Backend users']);
        $I->waitForElement('#typo3-backend-user-list');
        $I->click('//table[@id="typo3-backend-user-list"]/tbody/tr[descendant::a[@data-uid="' . $userId . '"]]//a[@title="Switch to user"]');
        $I->waitForElement($this->inPageTree . ' .node', 5);
    }

    protected function logoutUser(BackendTester $I): void
    {
        try {
            $I->seeElement($this->buttonUser);
        } catch (\Exception $e) {
            $I->switchToMainFrame();
        }

        $I->click($this->buttonUser);
        $I->waitForElementVisible($this->buttonLogout, 5);
        $I->click($this->buttonLogout);
    }
}
