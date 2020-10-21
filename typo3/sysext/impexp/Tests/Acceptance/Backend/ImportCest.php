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
use TYPO3\CMS\Impexp\Tests\Acceptance\Support\BackendTester;
use TYPO3\CMS\Impexp\Tests\Acceptance\Support\Helper\ModalDialog;
use TYPO3\CMS\Impexp\Tests\Acceptance\Support\Helper\PageTree;
use TYPO3\CMS\Impexp\Tests\Acceptance\Support\Helper\SiteConfiguration;

/**
 * Various import related tests
 */
class ImportCest
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
        $this->testFilesToDelete = [];
    }

    /**
     * @param BackendTester $I
     * @param ModalDialog $modalDialog
     *
     * @throws \Exception
     */
    public function importPageAndRecords(BackendTester $I, ModalDialog $modalDialog)
    {
        $I->wantToTest('importing a page with records.');

        $page1Icon = '.node.identifier-0_1 .node-icon-container';
        $contextMenuMore = '#contentMenu0 a.list-group-item-submenu';
        $contextMenuImport = '#contentMenu1 .list-group-item[data-callback-action=importT3d]';

        $I->click($page1Icon);
        $I->waitForElementVisible($contextMenuMore, 5);
        $I->click($contextMenuMore);
        $I->waitForElementVisible($contextMenuImport, 5);
        $I->click($contextMenuImport);

        $fixtureFilePath = 'Acceptance/Fixtures/404_page_and_records.xml';
        $tabUpload = 'a[href="#import-upload"]';
        $inputUploadFile = 'input[type=file]';
        $buttonUploadFile = '_upload';
        $buttonImport = 'button[name="tx_impexp[import_file]"]';
        $buttonNewImport = 'input[name="tx_impexp[new_import]"]';

        $I->switchToContentFrame();
        $I->click($tabUpload, $this->inModuleTabs);
        $I->waitForElementVisible($inputUploadFile, 5);
        $I->attachFile($inputUploadFile, $fixtureFilePath);
        $I->click($buttonUploadFile, $this->inModuleBody);
        $I->wait(1);
        $I->canSeeElement($this->inFlashMessages . ' .alert.alert-success');
        $I->canSee('Uploading file', $this->inFlashMessages . ' .alert.alert-success .alert-message');
        $flashMessage = $I->grabTextFrom($this->inFlashMessages . ' .alert.alert-success .alert-message');
        preg_match('/[^"]+"([^"]+)"[^"]+"([^"]+)"[^"]+/', $flashMessage, $flashMessageParts);
        $loadFilePath = Environment::getProjectPath() . '/fileadmin' . $flashMessageParts[2] . $flashMessageParts[1];
        $I->assertFileExists($loadFilePath);
        $this->testFilesToDelete[] = $loadFilePath;

        $I->click($buttonImport);
        $modalDialog->clickButtonInDialog('button[name="ok"]');

        $I->switchToMainFrame();
        $I->see('404', $this->inPageTree . ' .node-name');
        $I->switchToContentFrame();
        $I->seeElement($buttonNewImport);
    }

    /**
     * @param BackendTester $I
     * @param ModalDialog $modalDialog
     * @param PageTree $pageTree
     *
     * @throws \Exception
     */
    public function importTable(BackendTester $I, ModalDialog $modalDialog, PageTree $pageTree)
    {
        $I->wantToTest('importing a table of records.');

        $sysCategoryTable = '#recordlist-sys_category';

        $I->switchToContentFrame();
        $sysCategoryRecordsBefore = $I->grabMultiple($sysCategoryTable . ' .t3js-entity', 'data-uid');
        $I->switchToMainFrame();

        $page1Icon = '.node.identifier-0_1 .node-icon-container';
        $contextMenuMore = '#contentMenu0 a.list-group-item-submenu';
        $contextMenuImport = '#contentMenu1 .list-group-item[data-callback-action=importT3d]';

        $I->click($page1Icon);
        $I->waitForElementVisible($contextMenuMore, 5);
        $I->click($contextMenuMore);
        $I->waitForElementVisible($contextMenuImport, 5);
        $I->click($contextMenuImport);

        $fixtureFilePath = 'Acceptance/Fixtures/sys_category_table.xml';
        $tabUpload = 'a[href="#import-upload"]';
        $inputUploadFile = 'input[type=file]';
        $buttonUploadFile = '_upload';
        $buttonImport = 'button[name="tx_impexp[import_file]"]';

        $I->switchToContentFrame();
        $I->click($tabUpload, $this->inModuleTabs);
        $I->waitForElementVisible($inputUploadFile, 5);
        $I->attachFile($inputUploadFile, $fixtureFilePath);
        $I->click($buttonUploadFile, $this->inModuleBody);
        $I->wait(1);
        $I->canSeeElement($this->inFlashMessages . ' .alert.alert-success');
        $I->canSee('Uploading file', $this->inFlashMessages . ' .alert.alert-success .alert-message');
        $flashMessage = $I->grabTextFrom($this->inFlashMessages . ' .alert.alert-success .alert-message');
        preg_match('/[^"]+"([^"]+)"[^"]+"([^"]+)"[^"]+/', $flashMessage, $flashMessageParts);
        $loadFilePath = Environment::getProjectPath() . '/fileadmin' . $flashMessageParts[2] . $flashMessageParts[1];
        $I->assertFileExists($loadFilePath);
        $this->testFilesToDelete[] = $loadFilePath;

        $I->click($buttonImport);
        $modalDialog->clickButtonInDialog('button[name="ok"]');

        $I->switchToMainFrame();
        $pageTree->openPath(['styleguide TCA demo']);
        $I->switchToContentFrame();
        $sysCategoryRecords = $I->grabMultiple($sysCategoryTable . ' .t3js-entity', 'data-uid');
        $sysCategoryRecordsNew = array_diff($sysCategoryRecords, $sysCategoryRecordsBefore);
        $I->assertCount(5, $sysCategoryRecordsNew);
    }

    /**
     * @param BackendTester $I
     * @param ModalDialog $modalDialog
     * @param PageTree $pageTree
     *
     * @throws \Exception
     */
    public function importRecord(BackendTester $I, ModalDialog $modalDialog, PageTree $pageTree)
    {
        $I->wantToTest('importing a single record.');

        $sysCategoryTable = '#recordlist-sys_category';

        $I->switchToContentFrame();
        $sysCategoryRecordsBefore = $I->grabMultiple($sysCategoryTable . ' .t3js-entity', 'data-uid');
        $I->switchToMainFrame();

        $page1Icon = '.node.identifier-0_1 .node-icon-container';
        $contextMenuMore = '#contentMenu0 a.list-group-item-submenu';
        $contextMenuImport = '#contentMenu1 .list-group-item[data-callback-action=importT3d]';

        $I->click($page1Icon);
        $I->waitForElementVisible($contextMenuMore, 5);
        $I->click($contextMenuMore);
        $I->waitForElementVisible($contextMenuImport, 5);
        $I->click($contextMenuImport);

        $fixtureFilePath = 'Acceptance/Fixtures/sys_category_record.xml';
        $tabUpload = 'a[href="#import-upload"]';
        $inputUploadFile = 'input[type=file]';
        $buttonUploadFile = '_upload';
        $buttonImport = 'button[name="tx_impexp[import_file]"]';

        $I->switchToContentFrame();
        $I->click($tabUpload, $this->inModuleTabs);
        $I->waitForElementVisible($inputUploadFile, 5);
        $I->attachFile($inputUploadFile, $fixtureFilePath);
        $I->click($buttonUploadFile, $this->inModuleBody);
        $I->wait(1);
        $I->canSeeElement($this->inFlashMessages . ' .alert.alert-success');
        $I->canSee('Uploading file', $this->inFlashMessages . ' .alert.alert-success .alert-message');
        $flashMessage = $I->grabTextFrom($this->inFlashMessages . ' .alert.alert-success .alert-message');
        preg_match('/[^"]+"([^"]+)"[^"]+"([^"]+)"[^"]+/', $flashMessage, $flashMessageParts);
        $loadFilePath = Environment::getProjectPath() . '/fileadmin' . $flashMessageParts[2] . $flashMessageParts[1];
        $I->assertFileExists($loadFilePath);
        $this->testFilesToDelete[] = $loadFilePath;

        $I->click($buttonImport);
        $modalDialog->clickButtonInDialog('button[name="ok"]');

        $I->switchToMainFrame();
        $pageTree->openPath(['styleguide TCA demo']);
        $I->switchToContentFrame();
        $sysCategoryRecords = $I->grabMultiple($sysCategoryTable . ' .t3js-entity', 'data-uid');
        $sysCategoryRecordsNew = array_diff($sysCategoryRecords, $sysCategoryRecordsBefore);
        $I->assertCount(1, $sysCategoryRecordsNew);
    }
}
