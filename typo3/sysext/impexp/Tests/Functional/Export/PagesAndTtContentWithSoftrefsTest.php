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

namespace TYPO3\CMS\Impexp\Tests\Functional\Export;

use PHPUnit\Framework\MockObject\MockObject;
use TYPO3\CMS\Impexp\Export;
use TYPO3\CMS\Impexp\Tests\Functional\AbstractImportExportTestCase;
use TYPO3\TestingFramework\Core\AccessibleObjectInterface;

/**
 * Test case
 */
class PagesAndTtContentWithSoftrefsTest extends AbstractImportExportTestCase
{
    /**
     * @var array
     */
    protected $pathsToLinkInTestInstance = [
        'typo3/sysext/impexp/Tests/Functional/Fixtures/Folders/fileadmin/user_upload' => 'fileadmin/user_upload'
    ];

    /**
     * @var array
     */
    protected $recordTypesIncludeFields =
        [
            'pages' => [
                'title',
                'deleted',
                'doktype',
                'hidden',
                'perms_everybody'
            ],
            'tt_content' => [
                'CType',
                'header',
                'header_link',
                'list_type',
                'pi_flexform',
                'deleted',
                'hidden',
                't3ver_oid'
            ],
            'sys_file' => [
                'storage',
                'type',
                'metadata',
                'identifier',
                'identifier_hash',
                'folder_hash',
                'mime_type',
                'name',
                'sha1',
                'size',
                'creation_date',
                'modification_date',
            ],
        ]
    ;

    protected function setUp(): void
    {
        parent::setUp();

        $this->importDataSet(__DIR__ . '/../Fixtures/DatabaseImports/pages.xml');
        $this->importDataSet(__DIR__ . '/../Fixtures/DatabaseImports/tt_content-with-softrefs.xml');
        $this->importDataSet(__DIR__ . '/../Fixtures/DatabaseImports/sys_file.xml');
    }

    /**
     * @test
     */
    public function exportPagesAndRelatedTtContentWithSoftrefs(): void
    {
        $GLOBALS['TCA']['tt_content']['columns']['pi_flexform']['config']['ds']['default'] = '
<T3DataStructure>
    <ROOT>
        <type>array</type>
        <el>
            <softrefLink>
                <TCEforms>
                    <label>Soft reference link</label>
                    <config>
                        <type>input</type>
                        <renderType>inputLink</renderType>
                        <softref>typolink</softref>
                        <fieldControl>
                            <linkPopup>
                                <options>
                                    <title>Link</title>
                                    <blindLinkOptions>mail,folder,spec</blindLinkOptions>
                                    <windowOpenParameters>height=300,width=500,status=0,menubar=0,scrollbars=1</windowOpenParameters>
                                </options>
                            </linkPopup>
                        </fieldControl>
                    </config>
                </TCEforms>
            </softrefLink>
        </el>
    </ROOT>
</T3DataStructure>';

        /** @var Export|MockObject|AccessibleObjectInterface $subject */
        $subject = $this->getAccessibleMock(Export::class, ['setMetaData']);
        $subject->init();
        $subject->setPid(1);
        $subject->setLevels(1);
        $subject->setTables(['_ALL']);
        $subject->setRelOnlyTables(['sys_file']);
        $subject->setRecordTypesIncludeFields($this->recordTypesIncludeFields);
        $subject->process();

        $out = $subject->render();

        self::assertXmlStringEqualsXmlFile(
            __DIR__ . '/../Fixtures/XmlExports/pages-and-ttcontent-with-softrefs.xml',
            $out
        );
    }
}
