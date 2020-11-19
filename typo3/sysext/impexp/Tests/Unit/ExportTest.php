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

use PHPUnit\Framework\MockObject\MockObject;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Impexp\Export;
use TYPO3\TestingFramework\Core\AccessibleObjectInterface;
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
    public function getOrGenerateExportFileNameWithFileExtensionConsidersPidAndLevels(): void
    {
        $this->exportMock->init();
        $this->exportMock->setPid(1);
        $this->exportMock->setLevels(2);
        $patternDateTime = '[0-9-_]{16}';
        self::assertRegExp("/T3D_tree_PID1_L2_$patternDateTime.xml/", $this->exportMock->getOrGenerateExportFileNameWithFileExtension());
    }

    /**
     * @test
     */
    public function getOrGenerateExportFileNameWithFileExtensionConsidersRecords(): void
    {
        $this->exportMock->init();
        $this->exportMock->setRecord(['page:1', 'tt_content:1']);
        $patternDateTime = '[0-9-_]{16}';
        self::assertRegExp("/T3D_recs_page_1-tt_conte_$patternDateTime.xml/", $this->exportMock->getOrGenerateExportFileNameWithFileExtension());
    }

    /**
     * @test
     */
    public function getOrGenerateExportFileNameWithFileExtensionConsidersLists(): void
    {
        $this->exportMock->init();
        $this->exportMock->setList(['sys_language:0', 'news:12']);
        $patternDateTime = '[0-9-_]{16}';
        self::assertRegExp("/T3D_list_sys_language_0-_$patternDateTime.xml/", $this->exportMock->getOrGenerateExportFileNameWithFileExtension());
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

    /**
     * @test
     */
    public function fixFileIdInRelationsProcessesOriginalRelationsArray(): void
    {
        $relations = [
            ['type' => 'file', 'newValueFiles' => [[
                'ID_absFile' => Environment::getPublicPath() . '/fileRelation.png'
            ]]],
            ['type' => 'flex', 'flexFormRels' => ['file' => [[[
                'ID_absFile' => Environment::getPublicPath() . '/fileRelationInFlexForm.png'
            ]]]]],
        ];

        $expected = [
            ['type' => 'file', 'newValueFiles' => [[
                'ID_absFile' => Environment::getPublicPath() . '/fileRelation.png',
                'ID' => '987eaa6ab0a50497101d373cfc983400',
            ]]],
            ['type' => 'flex', 'flexFormRels' => ['file' => [[[
                'ID_absFile' => Environment::getPublicPath() . '/fileRelationInFlexForm.png',
                'ID' => '4cd9d9637e042ebff3568ad4e0266e77',
            ]]]]],
        ];

        $this->exportMock->fixFileIdInRelations($relations);
        self::assertEquals($expected, $relations);
    }
}
