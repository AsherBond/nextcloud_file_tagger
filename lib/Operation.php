<?php
/**
 * @copyright Copyright (c) 2016 Joas Schilling <coding@schilljs.com>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\FilesAutomatedTagging;


use OCP\Files\IHomeStorage;
use OCP\Files\Storage\IStorage;
use OCP\IConfig;
use OCP\IL10N;
use OCP\SystemTag\ISystemTagManager;
use OCP\SystemTag\ISystemTagObjectMapper;
use OCP\SystemTag\TagNotFoundException;
use OCP\WorkflowEngine\IManager;
use OCP\WorkflowEngine\IOperation;

class Operation implements IOperation {

	/** @var ISystemTagObjectMapper */
	protected $objectMapper;

	/** @var ISystemTagManager */
	protected $tagManager;

	/** @var IManager */
	protected $checkManager;

	/** @var IL10N */
	protected $l;

	private $config;

	/**
	 * @param ISystemTagObjectMapper $objectMapper
	 * @param ISystemTagManager $tagManager
	 * @param IManager $checkManager
	 * @param IL10N $l
	 * @param IConfig $config
	 */
	public function __construct(ISystemTagObjectMapper $objectMapper, ISystemTagManager $tagManager, IManager $checkManager, IL10N $l, IConfig $config) {
		$this->objectMapper = $objectMapper;
		$this->tagManager = $tagManager;
		$this->checkManager = $checkManager;
		$this->l = $l;
		$this->config = $config;
	}

	/**
	 * @param IStorage $storage
	 * @param int $fileId
	 * @param string $file
	 */
	public function checkOperations(IStorage $storage, $fileId, $file) {
		$this->checkManager->setFileInfo($storage, $file);
		$matches = $this->checkManager->getMatchingOperations('OCA\FilesAutomatedTagging\Operation', false);

		foreach ($matches as $match) {
			$this->objectMapper->assignTags($fileId, 'files', explode(',', $match['operation']));
		}
	}

	/**
	 * @param string $name
	 * @param array[] $checks
	 * @param string $operation
	 * @throws \UnexpectedValueException
	 */
	public function validateOperation($name, array $checks, $operation) {
		if ($operation === '') {
			throw new \UnexpectedValueException($this->l->t('No tags given'));
		}

		$systemTagIds = explode(',', $operation);
		try {
			$this->tagManager->getTagsByIds($systemTagIds);
		} catch (TagNotFoundException $e) {
			throw new \UnexpectedValueException($this->l->t('Tag(s) could not be found: %s', implode(', ', $e->getMissingTags())));
		} catch (\InvalidArgumentException $e) {
			throw new \UnexpectedValueException($this->l->t('At least one of the given tags is invalid'));
		}
	}

	public function isTaggingPath(IStorage $storage, string $file): bool {
		if (substr_count($file, '/') === 0) {
			return false;
		}
		if ($storage->instanceOfStorage(IHomeStorage::class)) {
			list($folder) = explode('/', $file, 2);
			return $folder === 'files';
		} else {
			[$folder, $subPath] = explode('/', $file, 2);
			// the root folder only contains appdata and home mounts
			// anything in a non homestorage and not in the appdata folder
			// should be a mounted folder
			return $folder !== $this->getAppDataFolderName() && ($folder !== '__groupfolders' || substr_count($subPath, '/') >= 1);
		}
	}

	private function getAppDataFolderName() {
		$instanceId = $this->config->getSystemValue('instanceid', null);
		if ($instanceId === null) {
			throw new \RuntimeException('no instance id!');
		}

		return 'appdata_' . $instanceId;
	}
}
