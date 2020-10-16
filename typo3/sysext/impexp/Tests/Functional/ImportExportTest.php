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

namespace TYPO3\CMS\Impexp\Tests\Functional;

use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Impexp\Export;
use TYPO3\CMS\Impexp\Import;

/**
 * Test case
 */
class ImportExportTest extends AbstractImportExportTestCase
{
    /**
     * @var array
     */
    protected $testExtensionsToLoad = [
        'typo3/sysext/core/Tests/Functional/Fixtures/Extensions/irre_tutorial',
    ];

    /**
     * @var Export|MockObject|AccessibleObjectInterface
     */
    protected $exportMock;

    protected function setUp(): void
    {
        parent::setUp();
        $this->exportMock = $this->getAccessibleMock(Export::class, ['setMetaData']);
    }

    /**
     * @test
     */
    public function importExportPingPongSucceeds(): void
    {
        $recordTypesIncludeFields = include __DIR__ . '/Fixtures/IrreRecordsIncludeFields.php';

        $import = GeneralUtility::makeInstance(Import::class);
        $import->init();
        $import->loadFile(
            __DIR__ . '/Fixtures/XmlImports/irre-records.xml',
            1
        );
        $import->force_all_UIDS = true;
        $import->importData(0);

        $this->exportMock->init();
        $this->exportMock->setPid(1);
        $this->exportMock->setLevels(Export::LEVELS_INFINITE);
        $this->exportMock->setTables(['_ALL']);
        $this->exportMock->setRelOnlyTables(['_ALL']);
        $this->exportMock->setRecordTypesIncludeFields($recordTypesIncludeFields);
        $this->exportMock->process();
        $actual = $this->exportMock->render();

        self::assertXmlStringEqualsXmlFile(__DIR__ . '/Fixtures/XmlImports/irre-records.xml', $actual);
    }
}
