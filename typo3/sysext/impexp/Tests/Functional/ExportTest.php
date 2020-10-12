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
     * @test
     */
    public function creationAndDeletionOfTemporaryFolderSucceeds(): void
    {
        $export = new Export();
        $export->init();

        $temporaryFolderName = $export->getOrCreateTemporaryFolderName();
        $temporaryFileName = $temporaryFolderName . '/export_file.txt';
        file_put_contents($temporaryFileName, 'Hello TYPO3 World.');
        self::assertTrue(is_dir($temporaryFolderName));
        self::assertTrue(is_file($temporaryFileName));

        $export->removeTemporaryFolderName();
        self::assertFalse(is_dir($temporaryFolderName));
        self::assertFalse(is_file($temporaryFileName));
    }

    /**
     * @test
     */
    public function creationAndDeletionOfDefaultImportExportFolderSucceeds(): void
    {
        $export = new Export();
        $export->init();

        $exportFolder = $export->getOrCreateDefaultImportExportFolder();
        $exportFileName = 'export_file.txt';
        $exportFolder->createFile($exportFileName);
        self::assertTrue(is_dir(Environment::getPublicPath() . '/' . $exportFolder->getPublicUrl()));
        self::assertTrue(is_file(Environment::getPublicPath() . '/' .$exportFolder->getPublicUrl() . $exportFileName));

        $export->removeDefaultImportExportFolder();
        self::assertFalse(is_dir(Environment::getPublicPath() . '/' .$exportFolder->getPublicUrl()));
        self::assertFalse(is_file(Environment::getPublicPath() . '/' .$exportFolder->getPublicUrl() . $exportFileName));
    }

    /**
     * @test
     */
    public function compileMemoryToFileContentSucceedsWithoutArguments(): void
    {
        $export = new Export();
        $export->init();
        $actual = $export->compileMemoryToFileContent();

        self::assertXmlStringEqualsXmlFile(__DIR__ . '/Fixtures/XmlExports/empty.xml', $actual);
    }

    /**
     * @test
     */
    public function saveXmlToFileIsDefaultAndSucceeds(): void
    {
        $export = new Export();
        $export->init();

        $fileName = 'export.xml';
        $fileContent = $export->compileMemoryToFileContent();
        $file = $export->saveToFile($fileName, $fileContent);
        $filePath = Environment::getPublicPath() . '/' . $file->getPublicUrl();

        self::assertStringEndsWith('export.xml', $filePath);
        self::assertXmlFileEqualsXmlFile(__DIR__ . '/Fixtures/XmlExports/empty.xml', $filePath);
    }

    /**
     * @test
     */
    public function saveT3dToFileSucceeds(): void
    {
        $export = new Export();
        $export->init();
        $export->setExportFileType(Export::FILETYPE_T3D);

        $fileName = 'export.t3d';
        $fileContent = $export->compileMemoryToFileContent();
        $file = $export->saveToFile($fileName, $fileContent);
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

        $export = new Export();
        $export->init();
        $export->setExportFileType(Export::FILETYPE_T3DZ);

        $fileName = 'export-z.t3d';
        $fileContent = $export->compileMemoryToFileContent();
        $file = $export->saveToFile($fileName, $fileContent);
        $filePath = Environment::getPublicPath() . '/' . $file->getPublicUrl();

        // remove final newlines
        $expected = trim(file_get_contents(__DIR__ . '/Fixtures/T3dExports/empty-z.t3d'));
        $actual = trim(file_get_contents($filePath));

        self::assertStringEndsWith('export-z.t3d', $filePath);
        self::assertEquals($expected, $actual);
    }
}
