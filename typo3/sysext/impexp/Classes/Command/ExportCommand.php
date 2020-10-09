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

namespace TYPO3\CMS\Impexp\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Core\Bootstrap;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Impexp\Command\Exception\InvalidFileException;

/**
 * Command for exporting T3D/XML data files
 */
class ExportCommand extends Command
{
    /**
     * Configure the command by defining the name, options and arguments
     */
    protected function configure(): void
    {
        $this
            ->setDescription('Exports a T3D / XML file with content of a page tree')
            ->addArgument(
                'file',
                InputArgument::REQUIRED,
                'The path and filename to export to (.t3d or .xml)'
            );
    }

    /**
     * Executes the command for exporting a t3d/xml file from the TYPO3 system
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Make sure the _cli_ user is loaded
        Bootstrap::initializeBackendAuthentication();

        $fileName = (string)$input->getArgument('file');
        $fileName = GeneralUtility::getFileAbsFileName($fileName);
        if ($fileName === '') {
            throw new InvalidFileException('The given filename "' . $fileName . '" is not valid', 1602257683);
        } elseif (file_exists($fileName)) {
            throw new InvalidFileException('The given filename "' . $fileName . '" already exists', 1602257702);
        }

        $io = new SymfonyStyle($input, $output);
        $io->success('Exported ' . $input->getArgument('file') . ' successfully');
        return 0;
    }
}
