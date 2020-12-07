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

namespace TYPO3\CMS\Impexp\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Core\Bootstrap;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Impexp\Import;

/**
 * Command for importing T3D/XML data files
 */
class ImportCommand extends Command
{
    /**
     * @var Import
     */
    protected $import;

    /**
     * Configure the command by defining the name, options and arguments
     */
    protected function configure(): void
    {
        $this
            ->setDescription('Imports a T3D / XML file with content into a page tree')
            ->addArgument(
                'file',
                InputArgument::REQUIRED,
                'The path and filename to import (.t3d or .xml)'
            )
            ->addArgument(
                'pageId',
                InputArgument::OPTIONAL,
                'The page ID to start from.',
                0
            )
            ->addOption(
                'updateRecords',
                null,
                InputOption::VALUE_NONE,
                'If set, existing records with the same UID will be updated instead of inserted'
            )
            ->addOption(
                'ignorePid',
                null,
                InputOption::VALUE_NONE,
                'If set, page IDs of updated records are not corrected (only works in conjunction with the updateRecords option)'
            )
            ->addOption(
                'forceUid',
                null,
                InputOption::VALUE_NONE,
                'If set, UIDs from file will be forced.'
            )
            ->addOption(
                'enableLog',
                null,
                InputOption::VALUE_NONE,
                'If set, all database actions are logged'
            );
    }

    /**
     * Executes the command for importing a t3d/xml file into the TYPO3 system
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Ensure the _cli_ user is authenticated
        Bootstrap::initializeBackendAuthentication();

        $io = new SymfonyStyle($input, $output);

        $import = $this->getImport();
        try {
            $import->setPid((int)$input->getArgument('pageId'));
            $import->setUpdate((bool)$input->getOption('updateRecords'));
            $import->setGlobalIgnorePid((bool)$input->getOption('ignorePid'));
            $import->setForceAllUids((bool)$input->getOption('forceUid'));
            $import->setEnableLogging((bool)$input->getOption('enableLog'));
            $import->loadFile((string)$input->getArgument('file'), true);
            $import->checkImportPrerequisites();
            $import->importData();
            $io->success('Importing ' . $input->getArgument('file') . ' to page ' . $input->getArgument('pageId') . ' succeeded.');
            return 0;
        } catch (\Exception $e) {
            $io->error('Importing ' . $input->getArgument('file') . ' to page ' . $input->getArgument('pageId') . ' failed.');
            if ($io->isVerbose()) {
                $io->writeln($e->getMessage());
                $io->writeln($import->getErrorLog());
            }
            return 1;
        }
    }

    /**
     * @return Import
     */
    protected function getImport(): Import
    {
        if (empty($this->import)) {
            $this->import = GeneralUtility::makeInstance(Import::class);
        }
        return $this->import;
    }
}
