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
use TYPO3\CMS\Impexp\Export;

/**
 * Test case
 */
class ExportTest extends AbstractImportExportTestCase
{
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
    public function creationAndDeletionOfTemporaryFolderSucceeds(): void
    {
        $this->exportMock->init();

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
        $this->exportMock->init();

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
    public function renderSucceedsWithoutArguments(): void
    {
        $this->exportMock->init();
        $this->exportMock->process();
        $actual = $this->exportMock->render();

        self::assertXmlStringEqualsXmlFile(__DIR__ . '/Fixtures/XmlExports/empty.xml', $actual);
    }

    /**
     * @test
     */
    public function saveXmlToFileIsDefaultAndSucceeds(): void
    {
        $this->exportMock->init();
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
        $this->exportMock->init();
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

        $this->exportMock->init();
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
