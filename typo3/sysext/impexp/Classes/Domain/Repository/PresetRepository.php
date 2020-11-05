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

namespace TYPO3\CMS\Impexp\Domain\Repository;

use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageQueue;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Impexp\Exception\InsufficientUserPermissionsException;
use TYPO3\CMS\Impexp\Exception\MalformedPresetException;
use TYPO3\CMS\Impexp\Exception\PresetNotFoundException;

/**
 * Export preset repository
 *
 * @internal This class is a specific repository implementation and is not considered part of the Public TYPO3 API.
 */
class PresetRepository
{
    /**
     * @var string
     */
    protected $table = 'tx_impexp_presets';

    /**
     * @param int $pageId
     * @return array
     */
    public function getPresets(int $pageId): array
    {
        $queryBuilder = $this->createQueryBuilder();

        $queryBuilder->select('*')
            ->from($this->table)
            ->where(
                $queryBuilder->expr()->orX(
                    $queryBuilder->expr()->gt(
                        'public',
                        $queryBuilder->createNamedParameter(0, \PDO::PARAM_INT)
                    ),
                    $queryBuilder->expr()->eq(
                        'user_uid',
                        $queryBuilder->createNamedParameter($this->getBackendUser()->user['uid'], \PDO::PARAM_INT)
                    )
                )
            );

        if ($pageId) {
            $queryBuilder->andWhere(
                $queryBuilder->expr()->orX(
                    $queryBuilder->expr()->eq(
                        'item_uid',
                        $queryBuilder->createNamedParameter($pageId, \PDO::PARAM_INT)
                    ),
                    $queryBuilder->expr()->eq(
                        'item_uid',
                        $queryBuilder->createNamedParameter(0, \PDO::PARAM_INT)
                    )
                )
            );
        }

        $presets = $queryBuilder->execute();

        $options = [''];
        while ($presetCfg = $presets->fetch()) {
            $options[$presetCfg['uid']] = $presetCfg['title'] . ' [' . $presetCfg['uid'] . ']'
                . ($presetCfg['public'] ? ' [Public]' : '')
                . ($presetCfg['user_uid'] === $this->getBackendUser()->user['uid'] ? ' [Own]' : '');
        }
        return $options;
    }

    /**
     * Get single preset record
     *
     * @param int $uid Preset record
     * @throws PresetNotFoundException
     * @return array Preset record
     */
    protected function getPreset(int $uid): array
    {
        $queryBuilder = $this->createQueryBuilder();

        $preset = $queryBuilder->select('*')
            ->from($this->table)
            ->where(
                $queryBuilder->expr()->eq(
                    'uid',
                    $queryBuilder->createNamedParameter($uid, \PDO::PARAM_INT)
                )
            )
            ->execute()
            ->fetch();

        if (!is_array($preset)) {
            throw new PresetNotFoundException(
                'ERROR: No valid preset #' . $uid . ' found.',
                1604608843
            );
        }

        return $preset;
    }

    protected function createPreset(array $data): void
    {
        $connection = $this->createConnection();
        $connection->insert(
            $this->table,
            [
                'user_uid' => $this->getBackendUser()->user['uid'],
                'public' => $data['preset']['public'],
                'title' => $data['preset']['title'],
                'item_uid' => (int)$data['pagetree']['id'],
                'preset_data' => serialize($data)
            ],
            ['preset_data' => Connection::PARAM_LOB]
        );
    }

    /**
     * @param int $uid
     * @param array $data
     * @throws InsufficientUserPermissionsException
     */
    protected function updatePreset(int $uid, array $data): void
    {
        $preset = $this->getPreset($uid);

        if (!($this->getBackendUser()->isAdmin() || $preset['user_uid'] === $this->getBackendUser()->user['uid'])) {
            throw new InsufficientUserPermissionsException(
                'ERROR: You were not the owner of the preset so you could not delete it.',
                1604584766
            );
        }

        $connection = $this->createConnection();
        $connection->update(
            $this->table,
            [
                'public' => $data['preset']['public'],
                'title' => $data['preset']['title'],
                'item_uid' => $data['pagetree']['id'],
                'preset_data' => serialize($data)
            ],
            ['uid' => $uid],
            ['preset_data' => Connection::PARAM_LOB]
        );
    }

    /**
     * @param int $uid
     * @throws MalformedPresetException
     * @return array
     */
    protected function loadPreset(int $uid): array
    {
        $preset = $this->getPreset($uid);

        $presetData = unserialize($preset['preset_data'], ['allowed_classes' => false]);
        if (!is_array($presetData)) {
            throw new MalformedPresetException(
                'ERROR: No configuration data found in preset record!',
                1604608922
            );
        }

        return $presetData;
    }

    /**
     * @param int $uid
     * @throws InsufficientUserPermissionsException
     */
    protected function deletePreset(int $uid): void
    {
        $preset = $this->getPreset($uid);

        if (!($this->getBackendUser()->isAdmin() || $preset['user_uid'] === $this->getBackendUser()->user['uid'])) {
            throw new InsufficientUserPermissionsException(
                'ERROR: You were not the owner of the preset so you could not delete it.',
                1604564346
            );
        }

        $connection = $this->createConnection();
        $connection->delete(
            $this->table,
            ['uid' => $uid]
        );
    }

    /**
     * Manipulate presets
     *
     * @param array $inData In data array, passed by reference!
     */
    public function processPresets(array &$inData): void
    {
        $presetData = GeneralUtility::_GP('preset');
        $inData['preset']['public'] = (int)$inData['preset']['public'];

        if (!is_array($presetData)) {
            return;
        }

        $err = false;
        $msg = '';
        $presetUid = (int)$presetData['select'];

        // Save preset
        if (isset($presetData['save'])) {
            // Update existing
            if ($presetUid > 0) {
                try {
                    $this->updatePreset($presetUid, $inData);
                    $msg = 'Preset #' . $presetUid . ' saved!';
                } catch (\Exception $e) {
                    $msg = $e->getMessage();
                    $err = true;
                }
            } else {
                // Insert new:
                $this->createPreset($inData);
                $msg = 'New preset "' . htmlspecialchars($inData['preset']['title']) . '" is created';
            }
        }
        // Delete preset:
        if (isset($presetData['delete'])) {
            if ($presetUid > 0) {
                try {
                    $this->deletePreset($presetUid);
                    $msg = 'Preset #' . $presetUid . ' deleted!';
                } catch (\Exception $e) {
                    $msg = $e->getMessage();
                    $err = true;
                }
            } else {
                $msg = 'ERROR: No preset selected for deletion.';
                $err = true;
            }
        }
        // Load preset
        if (isset($presetData['load']) || isset($presetData['merge'])) {
            if ($presetUid > 0) {
                try {
                    $inData_temp = $this->loadPreset($presetUid);
                    $msg = 'Preset #' . $presetUid . ' loaded!';
                    if (isset($presetData['merge'])) {
                        // Merge records in:
                        if (is_array($inData_temp['record'])) {
                            $inData['record'] = array_merge((array)$inData['record'], $inData_temp['record']);
                        }
                        // Merge lists in:
                        if (is_array($inData_temp['list'])) {
                            $inData['list'] = array_merge((array)$inData['list'], $inData_temp['list']);
                        }
                    } else {
                        $inData = $inData_temp;
                    }
                } catch (\Exception $e) {
                    $msg = $e->getMessage();
                    $err = true;
                }
            } else {
                $msg = 'ERROR: No preset selected for loading.';
                $err = true;
            }
        }

        // Show message:
        if ($msg !== '') {
            /** @var FlashMessage $flashMessage */
            $flashMessage = GeneralUtility::makeInstance(FlashMessage::class);
            $flashMessage->setTitle('Presets');
            $flashMessage->setMessage($msg);
            $flashMessage->setSeverity($err ? FlashMessage::ERROR : FlashMessage::INFO);
            /** @var FlashMessageService $flashMessageService */
            $flashMessageService = GeneralUtility::makeInstance(FlashMessageService::class);
            /** @var FlashMessageQueue $defaultFlashMessageQueue */
            $defaultFlashMessageQueue = $flashMessageService->getMessageQueueByIdentifier();
            $defaultFlashMessageQueue->enqueue($flashMessage);
        }
    }

    /**
     * @return Connection
     */
    protected function createConnection(): Connection
    {
        return GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable($this->table);
    }

    /**
     * @return QueryBuilder
     */
    protected function createQueryBuilder(): QueryBuilder
    {
        return GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($this->table);
    }

    /**
     * @return BackendUserAuthentication
     */
    protected function getBackendUser(): BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'];
    }
}
