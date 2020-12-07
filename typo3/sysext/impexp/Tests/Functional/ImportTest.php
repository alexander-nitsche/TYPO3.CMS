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

use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Impexp\Exception\LoadingFileFailedException;
use TYPO3\CMS\Impexp\Import;

/**
 * Test case
 */
class ImportTest extends AbstractImportExportTestCase
{
    /**
     * @var array
     */
    protected $pathsToLinkInTestInstance = [
        'typo3/sysext/impexp/Tests/Functional/Fixtures/XmlImports' => 'fileadmin/xml_imports',
    ];

    /**
     * @var Import|MockObject|AccessibleObjectInterface
     */
    protected $importMock;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importMock = $this->getAccessibleMock(Import::class, ['dummy']);
    }

    /**
     * @test
     * @dataProvider loadingFileFromWithinTypo3BaseFolderSucceedsProvider
     * @param string $filePath
     */
    public function loadingFileFromWithinTypo3BaseFolderSucceeds(string $filePath): void
    {
        $filePath = str_replace('%EnvironmentPublicPath%', Environment::getPublicPath(), $filePath);

        $this->importMock->init();
        $this->importMock->loadFile($filePath);

        self::assertTrue(true);
    }

    public function loadingFileFromWithinTypo3BaseFolderSucceedsProvider(): array
    {
        return [
            'relative path to fileadmin' => ['fileadmin/xml_imports/sys_language.xml'],
            'relative path to system extensions' => ['typo3/sysext/impexp/Tests/Functional/Fixtures/XmlImports/sys_language.xml'],
            'absolute path to system extensions' => ['%EnvironmentPublicPath%/typo3/sysext/impexp/Tests/Functional/Fixtures/XmlImports/sys_language.xml'],
            'extension path' => ['EXT:impexp/Tests/Functional/Fixtures/XmlImports/sys_language.xml'],
        ];
    }

    /**
     * @test
     * @dataProvider loadingFileFailsProvider
     * @param string $filePath
     */
    public function loadingFileFails(string $filePath): void
    {
        $this->expectException(LoadingFileFailedException::class);

        $this->importMock->init();
        $this->importMock->loadFile($filePath);
    }

    public function loadingFileFailsProvider(): array
    {
        return [
            'storage path' => ['1:/xml_imports/sys_language.xml'],
            'absolute path outside typo3 base folder' => ['/fileadmin/xml_imports/sys_language.xml'],
            'path to not existing file' => ['fileadmin/xml_imports/me_does_not_exist.xml'],
            'empty path' => [''],
        ];
    }

    /**
     * @test
     */
    public function renderPreviewForImportOfPageAndRecords(): void
    {
        $renderPreviewImport = include __DIR__ . '/Fixtures/ArrayAssertions/RenderPreviewImportPageAndRecords.php';

        $this->importMock->init();
        $this->importMock->setPid(0);
        $this->importMock->loadFile('EXT:impexp/Tests/Functional/Fixtures/XmlImports/pages-and-ttcontent.xml');
        $previewData = $this->importMock->renderPreview();
        self::assertEquals($renderPreviewImport, $previewData);
    }

    /**
     * @test
     */
    public function renderPreviewForImportOfPageAndRecordsByUpdate(): void
    {
        $renderPreviewImport = include __DIR__ . '/Fixtures/ArrayAssertions/RenderPreviewImportPageAndRecordsByUpdate.php';

        $this->importMock->init();
        $this->importMock->setPid(0);
        $this->importMock->loadFile('EXT:impexp/Tests/Functional/Fixtures/XmlImports/pages-and-ttcontent.xml');
        $this->importMock->importData();
        $this->importMock->setUpdate(true);
        $previewData = $this->importMock->renderPreview();
        self::assertEquals($renderPreviewImport, $previewData);
    }

    /**
     * @test
     */
    public function renderPreviewForImportOfPageAndRecordsWithDiffView(): void
    {
        $renderPreviewImport = include __DIR__ . '/Fixtures/ArrayAssertions/RenderPreviewImportPageAndRecordsWithDiff.php';

        $this->importMock->init();
        $this->importMock->setPid(0);
        $this->importMock->loadFile('EXT:impexp/Tests/Functional/Fixtures/XmlImports/pages-and-ttcontent.xml');
        $this->importMock->importData();
        $this->importMock->setShowDiff(true);
        $this->importMock->loadFile('EXT:impexp/Tests/Functional/Fixtures/XmlImports/pages-and-ttcontent-with-two-images.xml');
        $previewData = $this->importMock->renderPreview();
        self::assertEquals($renderPreviewImport, $previewData);
    }

    /**
     * @test
     */
    public function renderPreviewForImportOfPageAndRecordsByUpdateWithDiffView(): void
    {
        $renderPreviewImport = include __DIR__ . '/Fixtures/ArrayAssertions/RenderPreviewImportPageAndRecordsByUpdateWithDiff.php';

        $this->importMock->init();
        $this->importMock->setPid(0);
        $this->importMock->loadFile('EXT:impexp/Tests/Functional/Fixtures/XmlImports/pages-and-ttcontent.xml');
        $this->importMock->importData();
        $this->importMock->setShowDiff(true);
        $this->importMock->setUpdate(true);
        $this->importMock->loadFile('EXT:impexp/Tests/Functional/Fixtures/XmlImports/pages-and-ttcontent-with-two-images.xml');
        $previewData = $this->importMock->renderPreview();
        self::assertEquals($renderPreviewImport, $previewData);
    }

    /**
     * @test
     */
    public function renderPreviewForImportOfPageAndRecordsWithSoftRefs(): void
    {
        $renderPreviewImport = include __DIR__ . '/Fixtures/ArrayAssertions/RenderPreviewImportPageAndRecordsWithSoftRefs.php';

        $this->importMock->init();
        $this->importMock->setPid(0);
        $this->importMock->loadFile('EXT:impexp/Tests/Functional/Fixtures/XmlImports/pages-and-ttcontent-with-softrefs.xml');
        $previewData = $this->importMock->renderPreview();
        self::assertEquals($renderPreviewImport, $previewData);
    }

    /**
     * Temporary test until there is a complex functional test which tests addFiles() implicitly.
     *
     * @test
     * @dataProvider addFilesSucceedsDataProvider
     * @param array $dat
     * @param array $relations
     * @param string $tokenID
     * @param array $expected
     */
    public function addFilesSucceeds(array $dat, array $relations, string $tokenID, array $expected): void
    {
        $importMock = $this->getAccessibleMock(
            Import::class,
            ['addError'],
            [], '', true
        );
        $importMock->init();

        $lines = [];
        $importMock->_set('dat', $dat);
        $importMock->addFiles($relations, $lines, 0, $tokenID);
        self::assertEquals($expected, $lines);
    }

    public function addFilesSucceedsDataProvider(): array
    {
        return [
            ['dat' => [
                'header' => [
                    'files' => [
                        '123456789' => [
                            'filename' => 'filename.jpg',
                            'relFileName' => 'filename.jpg',
                        ]
                    ]
                ]
            ], 'relations' => [
                '123456789'
            ], 'tokenID' => '987654321'
            , 'expected' => [
                [
                    'ref' => 'FILE',
                    'type' => 'file',
                    'preCode' => '&nbsp;&nbsp;&nbsp;&nbsp;<span title="FILE"><span class="t3js-icon icon icon-size-small icon-state-default icon-status-reference-hard" data-identifier="status-reference-hard">
'."\t".'<span class="icon-markup">
<img src="typo3/sysext/impexp/Resources/Public/Icons/status-reference-hard.png" width="16" height="16" alt="" />
'."\t".'</span>'."\n\t\n".'</span></span>',
                    'title' => 'filename.jpg',
                    'showDiffContent' => false,
                ],
            ]]
        ];
    }

    /**
     * @test
     */
    public function loadXmlSucceeds(): void
    {
        $this->importMock->setPid(0);
        $this->importMock->loadFile(
            'EXT:impexp/Tests/Functional/Fixtures/XmlExports/empty.xml',
            true
        );
        self::assertTrue(true);
    }

    /**
     * @test
     */
    public function loadT3dSucceeds(): void
    {
        $this->importMock->setPid(0);
        $this->importMock->loadFile(
            'EXT:impexp/Tests/Functional/Fixtures/T3dExports/empty.t3d',
            true
        );
        self::assertTrue(true);
    }

    /**
     * @test
     */
    public function loadT3dCompressedSucceeds(): void
    {
        if (!function_exists('gzuncompress')) {
            self::markTestSkipped('The function gzuncompress() is not available for decompression.');
        }

        $this->importMock->setPid(0);
        $this->importMock->loadFile(
            'EXT:impexp/Tests/Functional/Fixtures/T3dExports/empty-z.t3d',
            true
        );
        self::assertTrue(true);
    }
}
