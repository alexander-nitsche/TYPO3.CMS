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
     * @dataProvider generateExportFileNameSanitizesSuggestionProvider
     * @param string $suggestion
     * @param string $expected
     */
    public function generateExportFileNameSanitizesSuggestion(string $suggestion, string $expected): void
    {
        $this->exportMock->init();
        $actual = $this->exportMock->generateExportFileName($suggestion);

        self::assertEquals($expected, $actual);
    }

    public function generateExportFileNameSanitizesSuggestionProvider(): array
    {
        return [
            [
                'suggestion' => 'my-pagetree-of-pid-1_20181012 äöüß!"§$%&/()²³¼½¬{[]};,:µ<>|.2',
                'expected' => 'T3D_my-pagetree-of-pid-1_' . date('Y-m-d_H-i')
            ],
            [
                'suggestion' => 'superlong-suggestion-for-my-pagetree-of-pid-1_20181012 äöüß!"§$%&/()²³¼½¬{[]};,:µ<>|.2',
                'expected' => 'T3D_superlong-suggestion_' . date('Y-m-d_H-i')
            ],
        ];
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
