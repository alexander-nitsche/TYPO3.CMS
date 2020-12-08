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
use TYPO3\CMS\Core\Utility\GeneralUtility;
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

        $tester = new CommandTester(new ImportCommand());
        $tester->execute([], []);
    }

    /**
     * @test
     */
    public function importCommandRequiresFileArgumentOnly(): void
    {
        $filePath = 'EXT:impexp/Tests/Functional/Fixtures/XmlImports/sys_language.xml';

        $tester = new CommandTester(new ImportCommand());
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
        GeneralUtility::addInstance(Import::class, $importMock);

        $importMock->expects(self::once())->method('setPid')->with(self::equalTo($input['pageId']));
        $importMock->expects(self::once())->method('setUpdate')->with(self::equalTo($input['--updateRecords']));
        $importMock->expects(self::once())->method('setGlobalIgnorePid')->with(self::equalTo($input['--ignorePid']));
        $importMock->expects(self::once())->method('setForceAllUids')->with(self::equalTo($input['--forceUid']));
        $importMock->expects(self::once())->method('setEnableLogging')->with(self::equalTo($input['--enableLog']));
        $importMock->expects(self::once())->method('loadFile')->with(self::equalTo($input['file']));

        $tester = new CommandTester(new ImportCommand());
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
        $tester = new CommandTester(new ImportCommand());
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
                'expected' => 'File not found: '
            ],
            'unsupported file extension' => [
                'filePath' => 'EXT:impexp/Tests/Functional/Fixtures/XmlImports/unsupported.json',
                'expected' => 'File extension "json" is not valid. Supported file extensions are "xml", "t3d".'
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
