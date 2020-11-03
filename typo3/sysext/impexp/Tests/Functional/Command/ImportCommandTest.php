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

namespace TYPO3\CMS\Impexp\Tests\Functional\Command;

use Symfony\Component\Console\Output\Output;
use Symfony\Component\Console\Tester\CommandTester;
use TYPO3\CMS\Impexp\Command\ImportCommand;
use TYPO3\CMS\Impexp\Import;
use TYPO3\CMS\Impexp\Tests\Functional\AbstractImportExportTestCase;

/**
 * Test case
 */
class ImportCommandTest extends AbstractImportExportTestCase
{
    /**
     * @test
     */
    public function importCommandRequiresFileArgument(): void
    {
        $this->expectException(\Symfony\Component\Console\Exception\RuntimeException::class);
        $this->expectExceptionMessage('Not enough arguments (missing: "file")');

        $importMock = $this->getAccessibleMock(Import::class, ['dummy']);
        $commandMock = $this->getAccessibleMock(ImportCommand::class, ['getImport']);
        $commandMock->expects(self::any())->method('getImport')->willReturn($importMock);

        $tester = new CommandTester($commandMock);
        $tester->execute([], []);

        self::assertEquals(0, $tester->getStatusCode());
    }

    /**
     * @test
     */
    public function importCommandRequiresFileArgumentOnly(): void
    {
        $filePath = 'EXT:impexp/Tests/Functional/Fixtures/XmlImports/sys_language.xml';

        $importMock = $this->getAccessibleMock(Import::class, ['dummy']);
        $commandMock = $this->getAccessibleMock(ImportCommand::class, ['getImport']);
        $commandMock->expects(self::any())->method('getImport')->willReturn($importMock);

        $tester = new CommandTester($commandMock);
        $tester->execute(['file' => $filePath], []);

        self::assertEquals(0, $tester->getStatusCode());
    }

    /**
     * @test
     */
    public function importCommandPassesArgumentsToImportObject(): void
    {
        $input = [
            'file' => 'EXT:impexp/Tests/Functional/Fixtures/XmlImports/sys_language.xml',
            'pageId' => 3,
            '--updateRecords' => true,
            '--ignorePid' => true,
            '--forceUid' => true,
            '--enableLog' => true,
        ];

        $importMock = $this->getAccessibleMock(Import::class, [
            'setPid', 'setUpdate', 'setGlobalIgnorePid', 'setForceAllUids', 'setEnableLogging', 'loadFile'
        ]);
        $commandMock = $this->getAccessibleMock(ImportCommand::class, ['getImport']);
        $commandMock->expects(self::any())->method('getImport')->willReturn($importMock);

        $importMock->expects(self::once())->method('setPid')->with(self::equalTo($input['pageId']));
        $importMock->expects(self::once())->method('setUpdate')->with(self::equalTo($input['--updateRecords']));
        $importMock->expects(self::once())->method('setGlobalIgnorePid')->with(self::equalTo($input['--ignorePid']));
        $importMock->expects(self::once())->method('setForceAllUids')->with(self::equalTo($input['--forceUid']));
        $importMock->expects(self::once())->method('setEnableLogging')->with(self::equalTo($input['--enableLog']));
        $importMock->expects(self::once())->method('loadFile')->with(self::equalTo($input['file']));

        $tester = new CommandTester($commandMock);
        $tester->execute($input);
    }

    /**
     * @test
     * @dataProvider importCommandFailsDataProvider
     * @param string $filePath
     * @param string $expected
     */
    public function importCommandFails(string $filePath, string $expected): void
    {
        $importMock = $this->getAccessibleMock(Import::class, ['dummy']);
        $commandMock = $this->getAccessibleMock(ImportCommand::class, ['getImport']);
        $commandMock->expects(self::any())->method('getImport')->willReturn($importMock);

        $tester = new CommandTester($commandMock);
        $tester->execute(
            ['file' => $filePath, '--forceUid' => true],
            ['verbosity' => Output::VERBOSITY_VERBOSE]
        );

        self::assertEquals(1, $tester->getStatusCode());
        self::assertStringContainsString($expected, $tester->getDisplay(true));
    }

    public function importCommandFailsDataProvider(): array
    {
        return [
            'path to not existing file' => [
                'filePath' => 'EXT:impexp/Tests/Functional/Fixtures/XmlImports/me_does_not_exist.xml',
                'expected' => 'Loading of the import file "EXT:impexp/Tests/Functional/Fixtures/XmlImports/me_does_not_exist.xml" failed.'
            ],
            'missing required extension' => [
                'filePath' => 'EXT:impexp/Tests/Functional/Fixtures/XmlImports/sys_category_table_with_news.xml',
                'expected' => 'Prerequisites for file import are not met.'
            ],
            'forcing uids of sys_file records not supported' => [
                'filePath' => 'EXT:impexp/Tests/Functional/Fixtures/XmlImports/pages-and-ttcontent-with-image-with-forced-uids.xml',
                'expected' => 'The import has failed.',
            ],
        ];
    }
}
