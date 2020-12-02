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

use PHPUnit\Framework\MockObject\MockObject;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Impexp\Export;
use TYPO3\TestingFramework\Core\AccessibleObjectInterface;

/**
 * Test case
 */
class ExportTest extends AbstractImportExportTestCase
{
    /**
     * @var array
     */
    protected $pathsToLinkInTestInstance = [
        'typo3/sysext/impexp/Tests/Functional/Fixtures/Folders/fileadmin/user_upload' => 'fileadmin/user_upload'
    ];

    /**
     * @var array
     */
    protected $testExtensionsToLoad = [
        'typo3/sysext/impexp/Tests/Functional/Fixtures/Extensions/template_extension'
    ];

    /**
     * @var Export|MockObject|AccessibleObjectInterface
     */
    protected $exportMock;

    /**
     * @var array
     */
    protected $recordTypesIncludeFields =
        [
            'pages' => [
                'title',
                'deleted',
                'doktype',
                'hidden',
                'perms_everybody'
            ],
            'tt_content' => [
                'CType',
                'header',
                'header_link',
                'deleted',
                'hidden',
                't3ver_oid'
            ],
            'sys_file' => [
                'storage',
                'type',
                'metadata',
                'identifier',
                'identifier_hash',
                'folder_hash',
                'mime_type',
                'name',
                'sha1',
                'size',
                'creation_date',
                'modification_date',
            ],
        ]
    ;

    protected function setUp(): void
    {
        parent::setUp();
        $this->exportMock = $this->getAccessibleMock(Export::class, ['setMetaData']);
    }

    /**
     * @test
     */
    public function creationAndDeletionOfTemporaryFolderSucceeds(): void
    {
        $temporaryFolderName = $this->exportMock->getOrCreateTemporaryFolderName();
        $temporaryFileName = $temporaryFolderName . '/export_file.txt';
        file_put_contents($temporaryFileName, 'Hello TYPO3 World.');
        self::assertTrue(is_dir($temporaryFolderName));
        self::assertTrue(is_file($temporaryFileName));

        $this->exportMock->removeTemporaryFolderName();
        self::assertFalse(is_dir($temporaryFolderName));
        self::assertFalse(is_file($temporaryFileName));
    }

    /**
     * @test
     */
    public function creationAndDeletionOfDefaultImportExportFolderSucceeds(): void
    {
        $exportFolder = $this->exportMock->getOrCreateDefaultImportExportFolder();
        $exportFileName = 'export_file.txt';
        $exportFolder->createFile($exportFileName);
        self::assertTrue(is_dir(Environment::getPublicPath() . '/' . $exportFolder->getPublicUrl()));
        self::assertTrue(is_file(Environment::getPublicPath() . '/' .$exportFolder->getPublicUrl() . $exportFileName));

        $this->exportMock->removeDefaultImportExportFolder();
        self::assertFalse(is_dir(Environment::getPublicPath() . '/' .$exportFolder->getPublicUrl()));
        self::assertFalse(is_file(Environment::getPublicPath() . '/' .$exportFolder->getPublicUrl() . $exportFileName));
    }

    /**
     * @test
     */
    public function renderPreviewWithoutArgumentsReturnsBasicArray(): void
    {
        $this->exportMock->process();
        $previewData = $this->exportMock->renderPreview();
        self::assertEquals([
            'update' => false,
            'showDiff' => false,
            'insidePageTree' => [],
            'outsidePageTree' => []
        ], $previewData);
    }

    /**
     * @test
     */
    public function renderPreviewForExportOfPageAndRecords(): void
    {
        $this->importDataSet(__DIR__ . '/Fixtures/DatabaseImports/pages.xml');
        $this->importDataSet(__DIR__ . '/Fixtures/DatabaseImports/tt_content.xml');
        $this->importDataSet(__DIR__ . '/Fixtures/DatabaseImports/sys_file.xml');
        $this->importDataSet(__DIR__ . '/Fixtures/DatabaseImports/sys_file-export-pages-and-tt-content.xml');

        $renderPreviewExport = include __DIR__ . '/Fixtures/ArrayAssertions/RenderPreviewExportPageAndRecords.php';

        $this->exportMock->setPid(0);
        $this->exportMock->setLevels(Export::LEVELS_INFINITE);
        $this->exportMock->setTables(['_ALL']);
        $this->exportMock->setRecordTypesIncludeFields($this->recordTypesIncludeFields);
        $this->exportMock->process();
        $previewData = $this->exportMock->renderPreview();
        self::assertEquals($renderPreviewExport, $previewData);
    }

    /**
     * @test
     */
    public function renderPreviewForExportOfTable(): void
    {
        $this->importDataSet(__DIR__ . '/Fixtures/DatabaseImports/pages.xml');
        $this->importDataSet(__DIR__ . '/Fixtures/DatabaseImports/tt_content.xml');
        $this->importDataSet(__DIR__ . '/Fixtures/DatabaseImports/sys_file.xml');
        $this->importDataSet(__DIR__ . '/Fixtures/DatabaseImports/sys_file-export-pages-and-tt-content.xml');

        $renderPreviewExport = include __DIR__ . '/Fixtures/ArrayAssertions/RenderPreviewExportTable.php';

        $this->exportMock->setList(['tt_content:1']);
        $this->exportMock->setRecordTypesIncludeFields($this->recordTypesIncludeFields);
        $this->exportMock->process();
        $previewData = $this->exportMock->renderPreview();
        self::assertEquals($renderPreviewExport, $previewData);
    }

    /**
     * @test
     */
    public function renderPreviewForExportOfRecords(): void
    {
        $this->importDataSet(__DIR__ . '/Fixtures/DatabaseImports/pages.xml');
        $this->importDataSet(__DIR__ . '/Fixtures/DatabaseImports/tt_content.xml');
        $this->importDataSet(__DIR__ . '/Fixtures/DatabaseImports/sys_file.xml');
        $this->importDataSet(__DIR__ . '/Fixtures/DatabaseImports/sys_file-export-pages-and-tt-content.xml');

        $renderPreviewExport = include __DIR__ . '/Fixtures/ArrayAssertions/RenderPreviewExportRecords.php';

        $this->exportMock->setRecord(['tt_content:1', 'tt_content:2']);
        $this->exportMock->setRecordTypesIncludeFields($this->recordTypesIncludeFields);
        $this->exportMock->process();
        $previewData = $this->exportMock->renderPreview();
        self::assertEquals($renderPreviewExport, $previewData);
    }

    /**
     * @test
     */
    public function renderSucceedsWithoutArguments(): void
    {
        $this->exportMock->process();
        $actual = $this->exportMock->render();

        self::assertXmlStringEqualsXmlFile(__DIR__ . '/Fixtures/XmlExports/empty.xml', $actual);
    }

    /**
     * @test
     */
    public function saveXmlToFileIsDefaultAndSucceeds(): void
    {
        $this->exportMock->setExportFileName('export');
        $this->exportMock->process();

        $file = $this->exportMock->saveToFile();
        $filePath = Environment::getPublicPath() . '/' . $file->getPublicUrl();

        self::assertStringEndsWith('export.xml', $filePath);
        self::assertXmlFileEqualsXmlFile(__DIR__ . '/Fixtures/XmlExports/empty.xml', $filePath);
    }

    /**
     * @test
     */
    public function saveT3dToFileSucceeds(): void
    {
        $this->exportMock->setExportFileName('export');
        $this->exportMock->setExportFileType(Export::FILETYPE_T3D);
        $this->exportMock->process();

        $file = $this->exportMock->saveToFile();
        $filePath = Environment::getPublicPath() . '/' . $file->getPublicUrl();

        // remove final newlines
        $expected = trim(file_get_contents(__DIR__ . '/Fixtures/T3dExports/empty.t3d'));
        $actual = trim(file_get_contents($filePath));

        self::assertStringEndsWith('export.t3d', $filePath);
        self::assertEquals($expected, $actual);
    }

    /**
     * @test
     */
    public function saveT3dCompressedToFileSucceeds(): void
    {
        if (!function_exists('gzcompress')) {
            self::markTestSkipped('The function gzcompress() is not available for compression.');
        }

        $this->exportMock->setExportFileName('export');
        $this->exportMock->setExportFileType(Export::FILETYPE_T3DZ);
        $this->exportMock->process();

        $file = $this->exportMock->saveToFile();
        $filePath = Environment::getPublicPath() . '/' . $file->getPublicUrl();

        // remove final newlines
        $expected = trim(file_get_contents(__DIR__ . '/Fixtures/T3dExports/empty-z.t3d'));
        $actual = trim(file_get_contents($filePath));

        self::assertStringEndsWith('export-z.t3d', $filePath);
        self::assertEquals($expected, $actual);
    }
}
