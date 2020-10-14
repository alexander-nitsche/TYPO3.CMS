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
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Impexp\Command\ExportCommand;
use TYPO3\CMS\Impexp\Export;
use TYPO3\CMS\Impexp\Tests\Functional\AbstractImportExportTestCase;
use TYPO3\TestingFramework\Core\AccessibleObjectInterface;

/**
 * Test case
 */
class ExportCommandTest extends AbstractImportExportTestCase
{
    /**
     * @var Export|MockObject|AccessibleObjectInterface
     */
    protected $exportMock;

    /**
     * @var ExportCommand|MockObject|AccessibleObjectInterface
     */
    protected $commandMock;

    protected function setUp(): void
    {
        parent::setUp();
        $this->exportMock = $this->getAccessibleMock(Export::class, ['setMetaData']);
        $this->commandMock = $this->getAccessibleMock(ExportCommand::class, ['getExport']);
        $this->commandMock->expects(self::any())->method('getExport')->willReturn($this->exportMock);
    }

    /**
     * @test
     */
    public function exportCommandRequiresNoArguments(): void
    {
        $tester = new CommandTester($this->commandMock);
        $tester->execute([], []);

        self::assertEquals(0, $tester->getStatusCode());
    }

    /**
     * @test
     */
    public function exportCommandSavesExportWithGivenFileName(): void
    {
        $fileName = 'empty_export';

        $tester = new CommandTester($this->commandMock);
        $tester->execute(['file' => $fileName], []);

        preg_match('/([^\s]*importexport[^\s]*)/', $tester->getDisplay(), $display);
        $filePath = Environment::getPublicPath() . '/' . $display[1];

        self::assertEquals(0, $tester->getStatusCode());
        self::assertStringEndsWith('empty_export.xml', $filePath);
        self::assertXmlFileEqualsXmlFile(__DIR__ . '/../Fixtures/XmlExports/empty.xml', $filePath);
    }
}
