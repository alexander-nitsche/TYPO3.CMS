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

namespace TYPO3\CMS\Impexp\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Routing\Exception\RouteNotFoundException;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Template\ModuleTemplate;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Type\Bitmask\Permission;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Fluid\View\StandaloneView;

/**
 * Main script class for the Import / Export facility.
 *
 * @internal this is a TYPO3 Backend controller implementation and not part of TYPO3's Core API.
 */
abstract class ImportExportController
{
    /**
     * The integer value of the GET/POST var, 'id'. Used for submodules to the 'Web' module (page id)
     *
     * @var int
     */
    protected $id;

    /**
     * Array containing the current page.
     *
     * @var array
     */
    protected $pageInfo;

    /**
     * A WHERE clause for selection records from the pages table based on read-permissions of the current backend user.
     *
     * @var string
     */
    protected $permsClause;

    /**
     * @var LanguageService
     */
    protected $lang;

    /**
     * @var IconFactory
     */
    protected $iconFactory;

    /**
     * The name of the module
     *
     * @var string
     */
    protected $moduleName = '';

    /**
     * ModuleTemplate Container
     *
     * @var ModuleTemplate
     */
    protected $moduleTemplate;

    /**
     * @var UriBuilder
     */
    protected $uriBuilder;

    /**
     * @var StandaloneView
     */
    protected $standaloneView;

    /**
     * Return URL
     *
     * @var string
     */
    protected $returnUrl;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->iconFactory = GeneralUtility::makeInstance(IconFactory::class);
        $this->moduleTemplate = GeneralUtility::makeInstance(ModuleTemplate::class);
        $this->uriBuilder = GeneralUtility::makeInstance(UriBuilder::class);

        $templatePath = ExtensionManagementUtility::extPath('impexp') . 'Resources/Private/';

        $this->standaloneView = GeneralUtility::makeInstance(StandaloneView::class);
        $this->standaloneView->setTemplateRootPaths([$templatePath . 'Templates/ImportExport/']);
        $this->standaloneView->setLayoutRootPaths([$templatePath . 'Layouts/']);
        $this->standaloneView->setPartialRootPaths([$templatePath . 'Partials/']);
        $this->standaloneView->getRequest()->setControllerExtensionName('impexp');

        $this->permsClause = $this->getBackendUser()->getPagePermsClause(Permission::PAGE_SHOW);
        $this->returnUrl = GeneralUtility::sanitizeLocalUrl(GeneralUtility::_GP('returnUrl'));
        $this->lang = $this->getLanguageService();
        $this->lang->includeLLFile('EXT:impexp/Resources/Private/Language/locallang.xlf');
    }

    /**
     * Injects the request object for the current request and gathers all data
     *
     * IMPORTING DATA:
     *
     * Incoming array has syntax:
     * GETvar 'id' = import page id (must be readable)
     *
     * file = pointing to filename relative to public web path
     *
     * [all relation fields are clear, but not files]
     * - page-tree is written first
     * - then remaining pages (to the root of import)
     * - then all other records are written either to related included pages or if not found to import-root (should be a sysFolder in most cases)
     * - then all internal relations are set and non-existing relations removed, relations to static tables preserved.
     *
     * EXPORTING DATA:
     *
     * Incoming array has syntax:
     *
     * file[] = file
     * dir[] = dir
     * list[] = table:pid
     * record[] = table:uid
     *
     * pagetree[id] = (single id)
     * pagetree[levels]=1,2,3, -1 = currently unpacked tree, -2 = only tables on page
     * pagetree[tables][]=table/_ALL
     *
     * external_ref[tables][]=table/_ALL
     *
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    abstract public function mainAction(ServerRequestInterface $request): ResponseInterface;

    /**
     * @param ServerRequestInterface $request
     * @throws RouteNotFoundException
     */
    protected function main(ServerRequestInterface $request): void
    {
        $queryParams = $request->getQueryParams();
        $parsedBody = $request->getParsedBody();

        $this->id = (int)($parsedBody['id'] ?? $queryParams['id'] ?? 0);
        $this->pageInfo = BackendUtility::readPageAccess($this->id, $this->permsClause);
        if (is_array($this->pageInfo)) {
            $this->moduleTemplate->getDocHeaderComponent()->setMetaInformation($this->pageInfo);
        }

        // Setting up the context sensitive menu:
        $this->moduleTemplate->getPageRenderer()->loadRequireJsModule('TYPO3/CMS/Backend/ContextMenu');
        $this->moduleTemplate->getPageRenderer()->loadRequireJsModule('TYPO3/CMS/Impexp/ImportExport');
        $this->moduleTemplate->addJavaScriptCode(
            'ImpexpInLineJS',
            'if (top.fsMod) top.fsMod.recentIds["web"] = ' . $this->id . ';'
        );

        $this->standaloneView->assign('moduleUrl', (string)$this->uriBuilder->buildUriFromRoute($this->moduleName));
        $this->standaloneView->assign('id', $this->id);
    }

    /**
     * Create the panel of buttons for submitting the form or otherwise perform operations.
     */
    protected function getButtons(): void
    {
        $buttonBar = $this->moduleTemplate->getDocHeaderComponent()->getButtonBar();
        // back button
        if ($this->returnUrl) {
            $backButton = $buttonBar->makeLinkButton()
                ->setHref($this->returnUrl)
                ->setTitle($this->lang->sL('LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:labels.goBack'))
                ->setIcon($this->moduleTemplate->getIconFactory()->getIcon('actions-view-go-back', Icon::SIZE_SMALL));
            $buttonBar->addButton($backButton);
        }
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
}
