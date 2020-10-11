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
        $export->init(0);

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
        $export->init(0);

        $exportFolder = $export->getOrCreateDefaultImportExportFolder();
        $exportFileName = 'export_file.txt';
        $exportFolder->createFile($exportFileName);
        self::assertTrue(is_dir(Environment::getPublicPath() . '/' . $exportFolder->getPublicUrl()));
        self::assertTrue(is_file(Environment::getPublicPath() . '/' .$exportFolder->getPublicUrl() . $exportFileName));

        $export->removeDefaultImportExportFolder();
        self::assertFalse(is_dir(Environment::getPublicPath() . '/' .$exportFolder->getPublicUrl()));
        self::assertFalse(is_file(Environment::getPublicPath() . '/' .$exportFolder->getPublicUrl() . $exportFileName));
    }
}
