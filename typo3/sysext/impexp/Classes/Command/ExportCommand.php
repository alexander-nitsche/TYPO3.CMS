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
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Core\Bootstrap;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\PathUtility;
use TYPO3\CMS\Impexp\Export;

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
                InputArgument::OPTIONAL,
                'The filename to export to (without file extension)'
            )
            ->addOption(
                'fileType',
                'f',
                InputOption::VALUE_OPTIONAL,
                'The file type (xml, t3d, t3d_compressed).',
                Export::FILETYPE_XML
            )
            ->addOption(
                'pid',
                'p',
                InputOption::VALUE_OPTIONAL,
                'The root page of the exported page tree.',
                -1
            )
            ->addOption(
                'levels',
                'l',
                InputOption::VALUE_OPTIONAL,
                sprintf(
                    'The depth of the exported page tree. ' .
                    '"%d": "Records on this page", ' .
                    '"%d": "Expanded tree", ' .
                    '"0": "This page", ' .
                    '"1": "1 level down", ' .
                    '.. ' .
                    '"%d": "Infinite levels".',
                    Export::LEVELS_RECORDS_ON_THIS_PAGE,
                    Export::LEVELS_EXPANDED_TREE,
                    Export::LEVELS_INFINITE
                ),
                0
            )
            ->addOption(
                'table',
                't',
                InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY,
                'Include all records of this table. Examples: "_ALL", "tt_content", "sys_file_reference", etc.'
            )
            ->addOption(
                'record',
                null,
                InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY,
                'Include this specific record. Pattern is "{table}:{record}". Examples: "tt_content:12", etc.'
            )
            ->addOption(
                'list',
                null,
                InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY,
                'Include the records of this table and this page. Pattern is "{table}:{pid}". Examples: "sys_language:0", etc.'
            )
            ->addOption(
                'relative',
                'r',
                InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY,
                'Include the records of this table which are referenced by other records. Examples: "_ALL", "sys_category", etc.'
            )
            ->addOption(
                'static',
                's',
                InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY,
                'Do not include records of this table, but keep the foreign key in references of other records to this table. Examples: "_ALL", "sys_language", etc.'
            )
            ->addOption(
                'exclude',
                'e',
                InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY,
                'Exclude this specific record. Pattern is "{table}:{record}". Examples: "fe_users:3", etc.'
            )
            ->addOption(
                'excludeDisabledRecords',
                null,
                InputOption::VALUE_NONE,
                'Exclude records which are handled as disabled by their TCA configuration, e.g. by fields "disabled", "starttime" or "endtime".'
            )
            ->addOption(
                'excludeHtmlCss',
                null,
                InputOption::VALUE_NONE,
                'Exclude referenced HTML and CSS files.'
            )
            ->addOption(
                'title',
                null,
                InputOption::VALUE_OPTIONAL,
                'The meta title of the export.'
            )
            ->addOption(
                'description',
                null,
                InputOption::VALUE_OPTIONAL,
                'The meta description of the export.'
            )
            ->addOption(
                'notes',
                null,
                InputOption::VALUE_OPTIONAL,
                'The meta notes of the export.'
            )
            ->addOption(
                'dependency',
                'd',
                InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY,
                'This TYPO3 extension is required for the exported records. Examples: "news", "powermail", etc.'
            )
            ->addOption(
                'saveFilesOutsideExportFile',
                null,
                InputOption::VALUE_NONE,
                'Save files into separate folder instead of including them into the common export file. Folder name pattern is "{file}.files".'
            )
        ;
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

        $io = new SymfonyStyle($input, $output);

        try {
            $export = $this->getExport();
            $export->init();
            if ($input->getOption('fileType') != $export->getExportFileType()) {
                $export->setExportFileType((string)$input->getOption('fileType'));
            }
            if ($input->getArgument('file') != $export->getExportFileName()) {
                $export->setExportFileName(PathUtility::basename($input->getArgument('file')));
            }
            if ($input->getOption('pid') != $export->getPid()) {
                $export->setPid((int)$input->getOption('pid'));
            }
            if ($input->getOption('levels') != $export->getLevels()) {
                $export->setLevels((int)$input->getOption('levels'));
            }
            if ($input->getOption('table') != $export->getTables()) {
                $export->setTables($input->getOption('table'));
            }
            if ($input->getOption('record') != $export->getRecord()) {
                $export->setRecord($input->getOption('record'));
            }
            if ($input->getOption('list') != $export->getList()) {
                $export->setList($input->getOption('list'));
            }
            if ($input->getOption('relative') != $export->getRelOnlyTables()) {
                $export->setRelOnlyTables($input->getOption('relative'));
            }
            if ($input->getOption('static') != $export->getRelStaticTables()) {
                $export->setRelStaticTables($input->getOption('static'));
            }
            if ($input->getOption('exclude') != $export->getExcludeMap()) {
                $export->setExcludeMap($input->getOption('exclude'));
            }
            if ($input->getOption('excludeDisabledRecords') != $export->isExcludeDisabledRecords()) {
                $export->setExcludeDisabledRecords($input->getOption('excludeDisabledRecords'));
            }
            if (!$input->getOption('excludeHtmlCss') != $export->isIncludeExtFileResources()) {
                $export->setIncludeExtFileResources(!$input->getOption('excludeHtmlCss'));
            }
            if ($input->getOption('title') != $export->getTitle()) {
                $export->setTitle($input->getOption('title'));
            }
            if ($input->getOption('description') != $export->getDescription()) {
                $export->setDescription($input->getOption('description'));
            }
            if ($input->getOption('notes') != $export->getNotes()) {
                $export->setNotes($input->getOption('notes'));
            }
            if ($input->getOption('dependency') != $export->getExtensionDependencies()) {
                $export->setExtensionDependencies($input->getOption('dependency'));
            }
            if ($input->getOption('saveFilesOutsideExportFile') != $export->isSaveFilesOutsideExportFile()) {
                $export->setSaveFilesOutsideExportFile($input->getOption('saveFilesOutsideExportFile'));
            }
            $export->process();
            $saveFile = $export->saveToFile();
            $io->success('Exporting to ' . $saveFile->getPublicUrl() . ' succeeded.');
            return 0;
        } catch (\Exception $e) {
            $saveFolder = $export->getOrCreateDefaultImportExportFolder();
            $io->error('Exporting to ' . $saveFolder->getPublicUrl() . ' failed.');
            if ($io->isVerbose()) {
                $io->writeln($e->getMessage());
            }
            return 1;
        }
    }

    /**
     * @return Export
     */
    protected function getExport(): Export
    {
        return GeneralUtility::makeInstance(Export::class);
    }
}
