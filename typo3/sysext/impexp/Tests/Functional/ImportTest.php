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

namespace TYPO3\CMS\Impexp\Tests\Functional;

use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Impexp\Exception\LoadingFileFailedException;
use TYPO3\CMS\Impexp\Import;

/**
 * Test case
 */
class ImportTest extends AbstractImportExportTestCase
{
    /**
     * @var array
     */
    protected $pathsToLinkInTestInstance = [
        'typo3/sysext/impexp/Tests/Functional/Fixtures/XmlImports' => 'fileadmin/xml_imports',
    ];

    /**
     * @var Import|MockObject|AccessibleObjectInterface
     */
    protected $importMock;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importMock = $this->getAccessibleMock(Import::class, ['dummy']);
    }

    /**
     * @test
     * @dataProvider loadingFileFromWithinTypo3BaseFolderSucceedsProvider
     * @param string $filePath
     */
    public function loadingFileFromWithinTypo3BaseFolderSucceeds(string $filePath): void
    {
        $filePath = str_replace('%EnvironmentPublicPath%', Environment::getPublicPath(), $filePath);

        $this->importMock->init();
        $this->importMock->loadFile($filePath);

        self::assertTrue(true);
    }

    public function loadingFileFromWithinTypo3BaseFolderSucceedsProvider(): array
    {
        return [
            'relative path to fileadmin' => ['fileadmin/xml_imports/sys_language.xml'],
            'relative path to system extensions' => ['typo3/sysext/impexp/Tests/Functional/Fixtures/XmlImports/sys_language.xml'],
            'absolute path to system extensions' => ['%EnvironmentPublicPath%/typo3/sysext/impexp/Tests/Functional/Fixtures/XmlImports/sys_language.xml'],
            'extension path' => ['EXT:impexp/Tests/Functional/Fixtures/XmlImports/sys_language.xml'],
        ];
    }

    /**
     * @test
     * @dataProvider loadingFileFailsProvider
     * @param string $filePath
     */
    public function loadingFileFails(string $filePath): void
    {
        $this->expectException(LoadingFileFailedException::class);

        $this->importMock->init();
        $this->importMock->loadFile($filePath);
    }

    public function loadingFileFailsProvider(): array
    {
        return [
            'storage path' => ['1:/xml_imports/sys_language.xml'],
            'absolute path outside typo3 base folder' => ['/fileadmin/xml_imports/sys_language.xml'],
            'path to not existing file' => ['fileadmin/xml_imports/me_does_not_exist.xml'],
            'empty path' => [''],
        ];
    }
}
