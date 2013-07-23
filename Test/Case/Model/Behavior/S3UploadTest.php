<?php
App::uses('Upload.Upload', 'Model/Behavior');
App::uses('Folder', 'Utility');

class TestUpload extends CakeTestModel {
	public $useTable = 'uploads';
	public $actsAs = array(
		'Upload.S3Upload' => array(
			'photo' => array(
				'thumbnailMethod' => '_bad_thumbnail_method_',
				'pathMethod' => '_bad_path_method_',
			)
		)
	);
}

class TestUploadTwo extends CakeTestModel {
	public $useTable = 'uploads';
	public $actsAs = array(
		'Upload.S3Upload' => array(
			'photo' => array(
				'fields' => array(
					'type' => 'type',
					'dir' => 'dir'
				),
				'mimetypes' => array(
					'image/png',
					'image/jpeg',
					'image/gif'
				),
				'thumbnailSizes' => array(
					'thumb' => '80h'
				)
			)
		)
	);
}

class S3UploadBehaviorTest extends CakeTestCase {

	public $fixtures = array('plugin.upload.upload');
	public $TestUpload = null;
	public $MockUpload = null;
	public $data = array();
	public $currentTestMethod;

	function startTest($method) {
		$this->TestUpload = ClassRegistry::init('TestUpload');
		$this->TestUploadTwo = ClassRegistry::init('TestUploadTwo');
		$this->currentTestMethod = $method;
		$this->data['test_ok'] = array(
			'photo' => array(
				'name'  => 'Photo.png',
				'tmp_name'  => 'Photo.png',
				'dir'   => '/tmp/php/file.tmp',
				'type'  => 'image/png',
				'size'  => 8192,
				'error' => UPLOAD_ERR_OK,
			)
		);
		$this->data['test_update'] = array(
			'id' => 1,
			'photo' => array(
				'name'  => 'NewPhoto.png',
				'tmp_name'  => 'PhotoTmp.png',
				'dir'   => '/tmp/php/file.tmp',
				'type'  => 'image/png',
				'size'  => 8192,
				'error' => UPLOAD_ERR_OK,
			)
		);
		$this->data['test_update_other_field'] = array(
			'id' => 1,
			'other_field' => 'test',
			'photo' => array()
		);
		$this->data['test_remove'] = array(
			'photo' => array(
				'remove' => true,
			)
		);
	}

	function mockUpload($methods = array()) {
		if (!is_array($methods)) {
			$methods = (array) $methods;
		}
		if (empty($methods)) {
			$methods = array('handleUploadedFile', 'unlink', '_getMimeType', '_createThumbnails');
		}
		$this->MockUpload = $this->getMock('S3UploadBehavior', $methods);


		$this->MockUpload->setup($this->TestUpload, $this->TestUpload->actsAs['Upload.S3Upload']);
		$this->TestUpload->Behaviors->set('S3Upload', $this->MockUpload);

		$this->MockUpload->setup($this->TestUploadTwo, $this->TestUploadTwo->actsAs['Upload.S3Upload']);
		$this->TestUploadTwo->Behaviors->set('S3Upload', $this->MockUpload);
	}

	function endTest($method) {
		$folder = new Folder(TMP);
		$folder->delete(ROOT . DS . APP_DIR . DS . 'webroot' . DS . 'files' . DS . 'test_upload');
		$folder->delete(ROOT . DS . APP_DIR . DS . 'tmp' . DS . 'tests' . DS . 'path');
		Classregistry::flush();
		unset($this->TestUpload);
		unset($this->TestUploadTwo);
	}

	function testReplacePath() {
		$result = $this->TestUpload->Behaviors->S3Upload->_path($this->TestUpload, 'photo', array(
			'path' => 'files/{model}\\{field}{DS}',
		));

		$this->assertInternalType('string', $result);
		$this->assertEqual('files/test_upload/photo/', $result);

		$result = $this->TestUpload->Behaviors->S3Upload->_path($this->TestUpload, 'photo', array(
			'path' => 'files//{size}/{model}\\{field}{DS}{geometry}///',
		));

		$this->assertInternalType('string', $result);
		$this->assertEqual('files/{size}/test_upload/photo/{geometry}/', $result);


		$result = $this->TestUpload->Behaviors->S3Upload->_path($this->TestUpload, 'photo', array(
			'isThumbnail' => false,
			'path' => 'files//{size}/{model}\\\\{field}{DS}{geometry}///',
		));

		$this->assertInternalType('string', $result);
		$this->assertEqual('files/test_upload/photo/', $result);
	}
}
