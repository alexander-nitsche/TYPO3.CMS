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

namespace TYPO3\CMS\Impexp\Tests\Functional\Import;

use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Impexp\Import;
use TYPO3\CMS\Impexp\Tests\Functional\AbstractImportExportTestCase;

/**
 * Test case
 */
class PagesAndTtContentWithImagesInFilledDatabaseTest extends AbstractImportExportTestCase
{
    /**
     * @var array
     */
    protected $additionalFoldersToCreate = [
        '/fileadmin/user_upload'
    ];

    /**
     * @var array
     */
    protected $pathsToProvideInTestInstance = [
        'typo3/sysext/impexp/Tests/Functional/Fixtures/Folders/fileadmin/user_upload/typo3_image2.jpg' => 'fileadmin/user_upload/typo3_image2.jpg',
    ];

    /**
     * @test
     */
    public function importPagesAndRelatedTtContentWithDifferentImageToExistingData()
    {
        $this->importDataSet(__DIR__ . '/../Fixtures/DatabaseImports/pages.xml');
        $this->importDataSet(__DIR__ . '/../Fixtures/DatabaseImports/tt_content-with-image.xml');
        $this->importDataSet(__DIR__ . '/../Fixtures/DatabaseImports/sys_language.xml');
        $this->importDataSet(__DIR__ . '/../Fixtures/DatabaseImports/sys_file.xml');
        $this->importDataSet(__DIR__ . '/../Fixtures/DatabaseImports/sys_file_metadata.xml');
        $this->importDataSet(__DIR__ . '/../Fixtures/DatabaseImports/sys_file_reference.xml');
        $this->importDataSet(__DIR__ . '/../Fixtures/DatabaseImports/sys_file_storage.xml');

        $subject = GeneralUtility::makeInstance(Import::class);
        $subject->setPid(0);
        $subject->loadFile(
            'EXT:impexp/Tests/Functional/Fixtures/XmlImports/pages-and-ttcontent-with-existing-different-image.xml',
            true
        );
        $subject->importData();

        $this->testFilesToDelete[] = Environment::getPublicPath() . '/fileadmin/user_upload/typo3_image2_01.jpg';

        $this->assertCSVDataSet('EXT:impexp/Tests/Functional/Fixtures/DatabaseAssertions/importPagesAndRelatedTtContentWithDifferentImageToExistingData.csv');

        self::assertFileEquals(__DIR__ . '/../Fixtures/Folders/fileadmin/user_upload/typo3_image2.jpg', Environment::getPublicPath() . '/fileadmin/user_upload/typo3_image2.jpg');
        self::assertFileEquals(__DIR__ . '/../Fixtures/FileAssertions/typo3_image2_01.jpg', Environment::getPublicPath() . '/fileadmin/user_upload/typo3_image2_01.jpg');
    }

    /**
     * @test
     */
    public function updatePagesAndRelatedTtContentWithDifferentImageToExistingData()
    {
        $this->importDataSet(__DIR__ . '/../Fixtures/DatabaseImports/pages.xml');
        $this->importDataSet(__DIR__ . '/../Fixtures/DatabaseImports/tt_content-with-image.xml');
        $this->importDataSet(__DIR__ . '/../Fixtures/DatabaseImports/sys_language.xml');
        $this->importDataSet(__DIR__ . '/../Fixtures/DatabaseImports/sys_file.xml');
        $this->importDataSet(__DIR__ . '/../Fixtures/DatabaseImports/sys_file_metadata.xml');
        $this->importDataSet(__DIR__ . '/../Fixtures/DatabaseImports/sys_file_reference.xml');
        $this->importDataSet(__DIR__ . '/../Fixtures/DatabaseImports/sys_file_storage.xml');

        $subject = GeneralUtility::makeInstance(Import::class);
        try {
            $subject->setPid(0);
            $subject->setUpdate(true);
            $subject->loadFile(
                'EXT:impexp/Tests/Functional/Fixtures/XmlImports/pages-and-ttcontent-with-existing-different-image.xml',
                true
            );
            $subject->importData();
        } catch (\Exception $e) {
            // This warning is expected, but the import is completed anyway
            self::assertEquals(
                ['Updating sys_file records is not supported! They will be imported as new records!'],
                $subject->getErrorLog()
            );
        }

        $this->testFilesToDelete[] = Environment::getPublicPath() . '/fileadmin/user_upload/typo3_image2_01.jpg';

        $this->assertCSVDataSet('EXT:impexp/Tests/Functional/Fixtures/DatabaseAssertions/updatePagesAndRelatedTtContentWithDifferentImageToExistingData.csv');

        self::assertFileEquals(__DIR__ . '/../Fixtures/Folders/fileadmin/user_upload/typo3_image2.jpg', Environment::getPublicPath() . '/fileadmin/user_upload/typo3_image2.jpg');
        self::assertFileEquals(__DIR__ . '/../Fixtures/FileAssertions/typo3_image2_01.jpg', Environment::getPublicPath() . '/fileadmin/user_upload/typo3_image2_01.jpg');
    }

    /**
     * @test
     */
    public function updatePagesAndRelatedTtContentWithDifferentImageToExistingDataAndPagesAsNew()
    {
        $this->importDataSet(__DIR__ . '/../Fixtures/DatabaseImports/pages.xml');
        $this->importDataSet(__DIR__ . '/../Fixtures/DatabaseImports/tt_content-with-image.xml');
        $this->importDataSet(__DIR__ . '/../Fixtures/DatabaseImports/sys_language.xml');
        $this->importDataSet(__DIR__ . '/../Fixtures/DatabaseImports/sys_file.xml');
        $this->importDataSet(__DIR__ . '/../Fixtures/DatabaseImports/sys_file_metadata.xml');
        $this->importDataSet(__DIR__ . '/../Fixtures/DatabaseImports/sys_file_reference.xml');
        $this->importDataSet(__DIR__ . '/../Fixtures/DatabaseImports/sys_file_storage.xml');

        $subject = GeneralUtility::makeInstance(Import::class);
        try {
            $subject->setPid(0);
            $subject->setUpdate(true);
            $subject->setImportMode([
                'pages:1' => Import::IMPORT_MODE_AS_NEW,
                'pages:2' => Import::IMPORT_MODE_AS_NEW,
            ]);
            $subject->loadFile(
                'EXT:impexp/Tests/Functional/Fixtures/XmlImports/pages-and-ttcontent-with-existing-different-image.xml',
                true
            );
            $subject->importData();
        } catch (\Exception $e) {
            // This warning is expected, but the import is completed anyway
            self::assertEquals(
                ['Updating sys_file records is not supported! They will be imported as new records!'],
                $subject->getErrorLog()
            );
        }

        $this->testFilesToDelete[] = Environment::getPublicPath() . '/fileadmin/user_upload/typo3_image2_01.jpg';

        $this->assertCSVDataSet('EXT:impexp/Tests/Functional/Fixtures/DatabaseAssertions/updatePagesAndRelatedTtContentWithDifferentImageToExistingDataAndPagesAsNew.csv');

        self::assertFileEquals(__DIR__ . '/../Fixtures/Folders/fileadmin/user_upload/typo3_image2.jpg', Environment::getPublicPath() . '/fileadmin/user_upload/typo3_image2.jpg');
        self::assertFileEquals(__DIR__ . '/../Fixtures/FileAssertions/typo3_image2_01.jpg', Environment::getPublicPath() . '/fileadmin/user_upload/typo3_image2_01.jpg');
    }

    /**
     * @test
     */
    public function updatePagesAndRelatedTtContentKeepsRelationsBetweenImportedPagesAndRecords(): void
    {
        $this->importDataSet(__DIR__ . '/../Fixtures/DatabaseImports/pages.xml');
        $this->importDataSet(__DIR__ . '/../Fixtures/DatabaseImports/tt_content-with-image.xml');
        $this->importDataSet(__DIR__ . '/../Fixtures/DatabaseImports/sys_language.xml');
        $this->importDataSet(__DIR__ . '/../Fixtures/DatabaseImports/sys_file.xml');
        $this->importDataSet(__DIR__ . '/../Fixtures/DatabaseImports/sys_file_metadata.xml');
        $this->importDataSet(__DIR__ . '/../Fixtures/DatabaseImports/sys_file_reference.xml');
        $this->importDataSet(__DIR__ . '/../Fixtures/DatabaseImports/sys_file_storage.xml');

        $subject = GeneralUtility::makeInstance(Import::class);
        try {
            $subject->setPid(0);
            $subject->setUpdate(true);
            $subject->loadFile(
                'EXT:impexp/Tests/Functional/Fixtures/XmlImports/pages-and-ttcontent-with-image-with-forced-uids.xml',
                true
            );
            $subject->importData();
        } catch (\Exception $e) {
            // This warning is expected, but the import is completed anyway
            self::assertEquals(
                ['Updating sys_file records is not supported! They will be imported as new records!'],
                $subject->getErrorLog()
            );
        }

        $this->testFilesToDelete[] = Environment::getPublicPath() . '/fileadmin/user_upload/typo3_image2_01.jpg';

        $this->assertCSVDataSet('EXT:impexp/Tests/Functional/Fixtures/DatabaseAssertions/updatePagesAndRelatedTtContentKeepsRelationsBetweenImportedPagesAndRecords.csv');

        self::assertFileEquals(__DIR__ . '/../Fixtures/Folders/fileadmin/user_upload/typo3_image2.jpg', Environment::getPublicPath() . '/fileadmin/user_upload/typo3_image2.jpg');
    }

    /**
     * @test
     */
    public function importPagesAndRelatedTtContentWithSameImageToExistingData()
    {
        $this->importDataSet(__DIR__ . '/../Fixtures/DatabaseImports/pages.xml');
        $this->importDataSet(__DIR__ . '/../Fixtures/DatabaseImports/tt_content-with-image.xml');
        $this->importDataSet(__DIR__ . '/../Fixtures/DatabaseImports/sys_language.xml');
        $this->importDataSet(__DIR__ . '/../Fixtures/DatabaseImports/sys_file.xml');
        $this->importDataSet(__DIR__ . '/../Fixtures/DatabaseImports/sys_file_metadata.xml');
        $this->importDataSet(__DIR__ . '/../Fixtures/DatabaseImports/sys_file_reference.xml');
        $this->importDataSet(__DIR__ . '/../Fixtures/DatabaseImports/sys_file_storage.xml');

        $subject = GeneralUtility::makeInstance(Import::class);
        $subject->setPid(0);
        $subject->loadFile(
            'EXT:impexp/Tests/Functional/Fixtures/XmlImports/pages-and-ttcontent-with-existing-same-image.xml',
            true
        );
        $subject->importData();

        $this->assertCSVDataSet('EXT:impexp/Tests/Functional/Fixtures/DatabaseAssertions/importPagesAndRelatedTtContentWithSameImageToExistingData.csv');

        self::assertFileEquals(__DIR__ . '/../Fixtures/Folders/fileadmin/user_upload/typo3_image2.jpg', Environment::getPublicPath() . '/fileadmin/user_upload/typo3_image2.jpg');
    }

    /**
     * This test checks multiple remapping does not occur - issue #67188
     * Scenario:
     * - Have a local sys_file:1 entry to some image
     * - Have an import file with 3 tt_content, first element pointing to sys_file:1
     *   image "used-1.jpg" (different from locally existing one), and the other two pointing to
     *   sys_file:2 "used-2.jpg" (also not existing locally)
     * Upon import, the following is expected:
     * - sys_file:1 from import file becomes sys_file:2 locally
     * - sys_file:2 from import file becomes sys_file:3 locally
     * - content element:1 should reference sys_file:2
     * - content element:2 & 3 should reference sys_file:3
     * The issue from #67188 is that tt_content:1 was first mapped to sys_file:2
     * and when tt_content:2 and tt_content:3 were processed, tt_content:1 image
     * reference was mapped a second time to the not correct relation sys_file:3,
     * ending up in mixed sys_file_reference entries.
     * This test verifies first content element still points to the image used-1.jpg
     * while the other two point to point to image used-2.jpg
     * Note the internal handler mixes up insert orders resulting in former tt_content:1
     * ending up as tt_content:3 and 2/3 ending up as 2/1 uid-wise ... making this issue
     * even harder to grasp.
     *
     * @test
     */
    public function importPagesAndTtContentWithRemappingNewSysFileEntries()
    {
        // Have a single sys_file entry with uid 1
        $this->importDataSet(__DIR__ . '/../Fixtures/DatabaseImports/sys_file_single_image.xml');
        $this->importDataSet(__DIR__ . '/../Fixtures/DatabaseImports/sys_file_storage.xml');

        $subject = GeneralUtility::makeInstance(Import::class);
        $subject->setPid(0);
        // Import file with sys_file:1 and sys_file:2, where sys_file:1 has one connected
        // content element, and sys_file:2 has two connected content elements.
        $subject->loadFile(
            'EXT:impexp/Tests/Functional/Fixtures/XmlImports/pages-and-ttcontent-with-two-images.xml',
            true
        );
        $subject->importData();

        $this->testFilesToDelete[] = Environment::getPublicPath() . '/fileadmin/user_upload/used-1.jpg';
        $this->testFilesToDelete[] = Environment::getPublicPath() . '/fileadmin/user_upload/used-2.jpg';

        // Expect mapping is updated: one content element should still reference new sys_file:2,
        // two others should reference new sys_file:3
        $this->assertCSVDataSet('EXT:impexp/Tests/Functional/Fixtures/DatabaseAssertions/importPagesAndTtContentWithRemappingNewSysFileEntries.csv');
    }

    /**
     * @test
     */
    public function importImageIntoSystemAndMatchingThePathOfTheSecondStorage(): void
    {
        $this->importDataSet(__DIR__ . '/../Fixtures/DatabaseImports/sys_file_single_image.xml');
        $this->importDataSet(__DIR__ . '/../Fixtures/DatabaseImports/sys_file_storages.xml');

        $subject = GeneralUtility::makeInstance(Import::class);
        $subject->setPid(0);
        $subject->loadFile(
            'EXT:impexp/Tests/Functional/Fixtures/XmlImports/pages-and-ttcontent-with-two-images.xml',
            true
        );
        $subject->checkImportPrerequisites();
        self::assertTrue(true);
    }
}
