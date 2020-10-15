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

namespace TYPO3\CMS\Impexp\Tests\Unit;

use TYPO3\CMS\Impexp\Export;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Test case
 */
class ExportTest extends UnitTestCase
{
    /**
     * @var Export|MockObject|AccessibleObjectInterface
     */
    protected $exportMock;

    protected function setUp(): void
    {
        parent::setUp();
        $this->exportMock = $this->getAccessibleMock(Export::class, ['isCompressionAvailable'], [], '', false);
        $this->exportMock->expects(self::any())->method('isCompressionAvailable')->willReturn(true);
    }

    /**
     * @test
     * @dataProvider setExportFileNameSanitizesFileNameProvider
     * @param string $fileName
     * @param string $expected
     */
    public function setExportFileNameSanitizesFileName(string $fileName, string $expected): void
    {
        $this->exportMock->init();
        $this->exportMock->setExportFileName($fileName);
        $actual = $this->exportMock->getExportFileName();

        self::assertEquals($expected, $actual);
    }

    public function setExportFileNameSanitizesFileNameProvider(): array
    {
        return [
            [
                'fileName' => 'my-export-file_20201012 äöüß!"§$%&/()²³¼½¬{[]};,:µ<>|.1',
                'expected' => 'my-export-file_20201012.1'
            ],
        ];
    }

    /**
     * @test
     */
    public function generateExportFileNameConsidersPidAndLevels(): void
    {
        $this->exportMock->init();
        $this->exportMock->setPid(1);
        $this->exportMock->setLevels(2);
        self::assertEquals('T3D_tree_PID1_L2_', substr($this->exportMock->generateExportFileName(), 0, -16));
    }

    /**
     * @test
     */
    public function generateExportFileNameConsidersRecords(): void
    {
        $this->exportMock->init();
        $this->exportMock->setRecord(['page:1', 'tt_content:1']);
        self::assertEquals('T3D_recs_page_1-tt_conte_', substr($this->exportMock->generateExportFileName(), 0, -16));
    }

    /**
     * @test
     */
    public function generateExportFileNameConsidersLists(): void
    {
        $this->exportMock->init();
        $this->exportMock->setList(['sys_language:0', 'news:12']);
        self::assertEquals('T3D_list_sys_language_0-_', substr($this->exportMock->generateExportFileName(), 0, -16));
    }

    /**
     * @test
     * @dataProvider setExportFileTypeSucceedsWithSupportedFileTypeProvider
     * @param string $fileType
     */
    public function setExportFileTypeSucceedsWithSupportedFileType(string $fileType): void
    {
        $this->exportMock->setExportFileType($fileType);
        self::assertEquals($fileType, $this->exportMock->getExportFileType());
    }

    public function setExportFileTypeSucceedsWithSupportedFileTypeProvider(): array
    {
        return [
            ['fileType' => Export::FILETYPE_XML],
            ['fileType' => Export::FILETYPE_T3D],
            ['fileType' => Export::FILETYPE_T3DZ],
        ];
    }

    /**
     * @test
     */
    public function setExportFileTypeFailsWithUnsupportedFileType(): void
    {
        $this->expectException(\Exception::class);
        $this->exportMock->setExportFileType('json');
    }
}
