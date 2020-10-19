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

use TYPO3\CMS\Core\Core\Bootstrap;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Abstract used by ext:impexp functional tests
 */
abstract class AbstractImportExportTestCase extends FunctionalTestCase
{
    /**
     * @var array
     */
    protected $coreExtensionsToLoad = [
        'impexp',
    ];

    /**
     * Absolute path to files that must be removed
     * after a test - handled in tearDown
     *
     * @var array
     */
    protected $testFilesToDelete = [];

    /**
     * Set up for set up the backend user, initialize the language object
     * and creating the Export instance
     */
    protected function setUp(): void
    {
        parent::setUp();

        $backendUser = $this->setUpBackendUserFromFixture(1);
        $backendUser->workspace = 0;
        Bootstrap::initializeLanguageObject();
    }

    /**
     * Tear down for remove of the test files
     */
    protected function tearDown(): void
    {
        foreach ($this->testFilesToDelete as $absoluteFileName) {
            if (@is_file($absoluteFileName)) {
                unlink($absoluteFileName);
            }
        }
        parent::tearDown();
    }

    /**
     * Test if the local filesystem is case sensitive.
     * Needed for some export related tests
     *
     * @return bool
     */
    protected function isCaseSensitiveFilesystem()
    {
        $caseSensitive = true;
        $path = GeneralUtility::tempnam('aAbB');

        // do the actual sensitivity check
        if (@file_exists(strtoupper($path)) && @file_exists(strtolower($path))) {
            $caseSensitive = false;
        }

        // clean filesystem
        unlink($path);
        return $caseSensitive;
    }
}
