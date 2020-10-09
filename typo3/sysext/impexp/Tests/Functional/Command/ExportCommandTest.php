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

use Symfony\Component\Console\Tester\CommandTester;
use TYPO3\CMS\Impexp\Command\ExportCommand;
use TYPO3\CMS\Impexp\Tests\Functional\AbstractImportExportTestCase;

/**
 * Test case
 */
class ExportCommandTest extends AbstractImportExportTestCase
{
    /**
     * @test
     */
    public function exportCommandSucceeds(): void
    {
        /** @var ExportCommand */
        $command = new ExportCommand();
        $tester = new CommandTester($command);

        self::assertEquals($tester->execute(['file' => 'filepath'], []), 0);
    }
}
