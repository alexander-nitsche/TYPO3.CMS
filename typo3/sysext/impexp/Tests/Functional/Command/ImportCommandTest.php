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
        // Catch exception which is thrown due to mocking of all setters of the Import object
        // - but this test is not about this exception but the expectations further down
        $this->expectException(\TYPO3\CMS\Impexp\Command\Exception\LoadingFileFailedException::class);

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
}
