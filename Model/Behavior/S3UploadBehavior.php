<?php
/**
 * Upload behavior to AMAZON S3 (experimental)
 * Extends the UploadBehavior class
 *
 * Copyright 2013, Kim Stacks
 * Singapore
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright Copyright 2013, Kim Stacks.
 * @author Kim Stacks <kim@stacktogether.com>
 * @package       upload
 * @subpackage    upload.models.behaviors
 * @link          http://github.com/josegonzalez/upload
 * @license       MIT License (http://www.opensource.org/licenses/mit-license.php)
 */
App::uses('UploadBehavior', 'Upload.Model/Behavior');
class S3UploadBehavior extends UploadBehavior {
	// needed to declare since UploadBehavior
	// has these in `private`
	private $__filesToRemove = array(); 
	private $__foldersToRemove = array();

	public function afterSave(Model $model, $created) {
		$temp = array($model->alias => array());
		foreach ($this->settings[$model->alias] as $field => $options) {
			if (!in_array($field, array_keys($model->data[$model->alias]))) continue;
			if (empty($this->runtime[$model->alias][$field])) continue;
			if (isset($this->_removingOnly[$field])) continue;

			$tempPath = $this->_getPath($model, $field);

			$path = $this->settings[$model->alias][$field]['path'];
			$thumbnailPath = $this->settings[$model->alias][$field]['thumbnailPath'];

			if (!empty($tempPath)) {
				$path .= $tempPath . DS;
				$thumbnailPath .= $tempPath . DS;
			}
			$tmp = $this->runtime[$model->alias][$field]['tmp_name'];
			$filePath = $path . $model->data[$model->alias][$field];
			if (!$this->handleUploadedFile($model->alias, $field, $tmp, $filePath)) {
				CakeLog::error(sprintf('Model %s, Field %s: Unable to move the uploaded file to %s', $model->alias, $field, $filePath));
				$model->invalidate($field, sprintf('Unable to move the uploaded file to %s', $filePath));
				$db = $model->getDataSource();
				$db->rollback();
				throw new UploadException('Unable to upload file');
			}

			$this->_createThumbnails($model, $field, $path, $thumbnailPath);
			if ($model->hasField($options['fields']['dir'])) {
				if ($created && $options['pathMethod'] == '_getPathFlat') {
				} else if ($options['saveDir']) {
					$temp[$model->alias][$options['fields']['dir']] = "'{$tempPath}'";
				}
			}
		}

		if (!empty($temp[$model->alias])) {
			$model->updateAll($temp[$model->alias], array(
				$model->alias.'.'.$model->primaryKey => $model->id
			));
		}

		if (empty($this->__filesToRemove[$model->alias])) return true;
		foreach ($this->__filesToRemove[$model->alias] as $file) {
			$result[] = $this->unlink($file);
		}
		return $result;
	}

	public function handleUploadedFile($modelAlias, $field, $tmp, $filePath) {
		return is_uploaded_file($tmp) && @move_uploaded_file($tmp, $filePath);
	}

	public function unlink($file) {
		return @unlink($file);
	}

	public function deleteFolder($model, $path) {
		if (!isset($this->__foldersToRemove[$model->alias])) {
			return false;
		}

		$folders = $this->__foldersToRemove[$model->alias];
		foreach ($folders as $folder) {
			$dir = $path . $folder;
			$it = new RecursiveDirectoryIterator($dir);
			$files = new RecursiveIteratorIterator($it, RecursiveIteratorIterator::CHILD_FIRST);
			foreach ($files as $file) {
				if ($file->getFilename() === '.' || $file->getFilename() === '..') {
					continue;
				}

				if ($file->isDir()) {
					@rmdir($file->getRealPath());
				} else {
					@unlink($file->getRealPath());
				}
			}
			@rmdir($dir);
		}
		return true;
	}

	public function afterDelete(Model $model) {
		$result = array();
		if (!empty($this->__filesToRemove[$model->alias])) {
			foreach ($this->__filesToRemove[$model->alias] as $file) {
				$result[] = $this->unlink($file);
			}
		}

		foreach ($this->settings[$model->alias] as $field => $options) {
			if ($options['deleteFolderOnDelete'] == true) {
				$this->deleteFolder($model, $options['path']);
				return true;
			}
		}
		return $result;
	}

}