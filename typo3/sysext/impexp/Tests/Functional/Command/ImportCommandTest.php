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

namespace TYPO3\CMS\Impexp\Tests\Functional\Command;

use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Console\Tester\CommandTester;
use TYPO3\CMS\Impexp\Command\ImportCommand;
use TYPO3\CMS\Impexp\Import;
use TYPO3\CMS\Impexp\Tests\Functional\AbstractImportExportTestCase;
use TYPO3\TestingFramework\Core\AccessibleObjectInterface;

/**
 * Test case
 */
class ImportCommandTest extends AbstractImportExportTestCase
{
    /**
     * @var Import|MockObject|AccessibleObjectInterface
     */
    protected $importMock;

    /**
     * @var ImportCommand|MockObject|AccessibleObjectInterface
     */
    protected $commandMock;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importMock = $this->getAccessibleMock(Import::class, ['dummy']);
        $this->commandMock = $this->getAccessibleMock(ImportCommand::class, ['getImport']);
        $this->commandMock->expects(self::any())->method('getImport')->willReturn($this->importMock);
    }

    /**
     * @test
     */
    public function importCommandRequiresFileArgument(): void
    {
        $this->expectException(\Symfony\Component\Console\Exception\RuntimeException::class);
        $this->expectExceptionMessage('Not enough arguments (missing: "file")');

        $tester = new CommandTester($this->commandMock);
        $tester->execute([], []);

        self::assertEquals(0, $tester->getStatusCode());
    }

    /**
     * @test
     */
    public function importCommandRequiresFileArgumentOnly(): void
    {
        $filePath = 'EXT:impexp/Tests/Functional/Fixtures/XmlImports/sys_language.xml';

        $tester = new CommandTester($this->commandMock);
        $tester->execute(['file' => $filePath], []);

        self::assertEquals(0, $tester->getStatusCode());
    }
}
