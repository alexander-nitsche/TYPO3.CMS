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

namespace TYPO3\CMS\Impexp\Tests\Functional\Export;

use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Impexp\Tests\Functional\AbstractImportExportTestCase;
use TYPO3\CMS\Impexp\View\ExportPageTreeView;

/**
 * Test case
 */
class ExportPageTreeViewTest extends AbstractImportExportTestCase
{
    /**
     * @var array
     */
    protected $testExtensionsToLoad = [
        'typo3/sysext/core/Tests/Functional/Fixtures/Extensions/irre_tutorial',
    ];

    /**
     * @test
     */
    public function renderTree(): void
    {
        $this->importDataSet(__DIR__ . '/../Fixtures/DatabaseImports/irre_tutorial.xml');

        $expectedTreeHTML = '<ul class="list-tree list-tree-root list-tree-root-clean">
            <li id="pages1_">
                <span class="list-tree-group">
                    <span class="list-tree-icon">
                        <span class="t3js-icon icon icon-size-small icon-state-default icon-apps-pagetree-page-default" data-identifier="apps-pagetree-page-default">
                            <span class="icon-markup">
                                <svg class="icon-color" role="img"><use xlink:href="typo3/sysext/core/Resources/Public/Icons/T3Icons/sprites/apps.svg#apps-pagetree-page-default" /></svg>
                            </span>
                        </span>
                    </span>
                    <span class="list-tree-title">IRRE</span>
                </span>
            </li>
        </ul>';

        $subject = GeneralUtility::makeInstance(ExportPageTreeView::class);
        $tree = $subject->ext_tree(1);
        $treeHTML = $subject->printTree($tree);

        self::assertXmlStringEqualsXmlString($expectedTreeHTML, $treeHTML);
    }
}
