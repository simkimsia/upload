<?php
App::uses('Upload.S3Upload', 'Model/Behavior');
App::uses('Folder', 'Utility');

class TestS3Upload extends CakeTestModel {
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

class TestS3UploadTwo extends CakeTestModel {
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
	public $TestS3Upload = null;
	public $MockUpload = null;
	public $data = array();
	public $currentTestMethod;

	function startTest($method) {
		$this->TestS3Upload = ClassRegistry::init('TestS3Upload');
		$this->TestS3UploadTwo = ClassRegistry::init('TestS3UploadTwo');
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


		$this->MockUpload->setup($this->TestS3Upload, $this->TestS3Upload->actsAs['Upload.S3Upload']);
		$this->TestS3Upload->Behaviors->set('S3Upload', $this->MockUpload);

		$this->MockUpload->setup($this->TestS3UploadTwo, $this->TestS3UploadTwo->actsAs['Upload.S3Upload']);
		$this->TestS3UploadTwo->Behaviors->set('S3Upload', $this->MockUpload);
	}

	function endTest($method) {
		$folder = new Folder(TMP);
		$folder->delete(ROOT . DS . APP_DIR . DS . 'webroot' . DS . 'files' . DS . 'test_s3_upload');
		$folder->delete(ROOT . DS . APP_DIR . DS . 'tmp' . DS . 'tests' . DS . 'path');
		Classregistry::flush();
		unset($this->TestS3Upload);
		unset($this->TestS3UploadTwo);
	}

	function testSimpleUpload() {
		$this->mockUpload();
		$this->MockUpload->expects($this->once())->method('handleUploadedFile')->will($this->returnValue(true));
		$this->MockUpload->expects($this->never())->method('unlink');
		$this->MockUpload->expects($this->once())->method('handleUploadedFile')->with(
			$this->TestS3Upload->alias,
			'photo',
			$this->data['test_ok']['photo']['tmp_name'],
			$this->MockUpload->settings['TestS3Upload']['photo']['path'] . 3 . '/' . $this->data['test_ok']['photo']['name']
		);
		$result = $this->TestS3Upload->save($this->data['test_ok']);
		$this->assertInternalType('array', $result);
		$newRecord = $this->TestS3Upload->findById($this->TestS3Upload->id);
		$expectedRecord = array(
			'TestS3Upload' => array(
				'id' => 3,
				'photo' => 'Photo.png',
				'dir' => 3,
				'type' => 'image/png',
				'size' => 8192,
				'other_field' => null
			)
		);

		$this->assertEqual($expectedRecord, $newRecord);
	}

	/**
	 * Tests Upload::save creates a new Upload record including
	 * an upload of an PNG image file using the Upload.S3Upload behavior
	 * with the default path and pathMethod (primaryKey)
	 */
	public function testSaveSuccessPngDefaultPathAndPathMethod() {
		$this->mockUpload();
		$next_id = (1+$this->TestS3UploadTwo->field('id', array(), array('TestS3UploadTwo.id' => 'DESC')));
		$destination_dir = 'files/test_s3_upload_two/photo/' .  $next_id . '/';

		$Upload = array(
			'TestS3UploadTwo' => array(
				'photo' => array(
					'name' => 'image-png.png',
					'type' => 'image/png',
					'tmp_name' => 'image-png-tmp.png',
					'error' => UPLOAD_ERR_OK,
					'size' => 8123,
				)
			)
		);

		$this->MockUpload->expects($this->never())
			->method('unlink');

		$this->MockUpload->expects($this->once())
			->method('handleUploadedFile')
			->with(
					$this->equalTo('TestS3UploadTwo'),
					$this->equalTo('photo'),
					$this->equalTo('image-png-tmp.png'),
					$this->equalTo($destination_dir . 'image-png.png')
			)
			->will($this->returnValue(true));

		$this->MockUpload->expects($this->once())
			->method('_createThumbnails')
			->with(
					$this->isInstanceOf('TestS3UploadTwo'),
					$this->equalTo('photo'),
					$this->equalTo($destination_dir),
					$this->equalTo($destination_dir)
			)
			->will($this->returnValue(true));

		$this->assertTrue(false !== $this->TestS3UploadTwo->save($Upload));
		$this->assertSame(array(), array_keys($this->TestS3UploadTwo->validationErrors));

		$this->assertSame('image-png.png', $this->TestS3UploadTwo->field('photo', array('TestS3UploadTwo.id' => $next_id)));
		$this->assertSame('image/png', $this->TestS3UploadTwo->field('type', array('TestS3UploadTwo.id' => $next_id)));
		$this->assertSame((string)$next_id, $this->TestS3UploadTwo->field('dir', array('TestS3UploadTwo.id' => $next_id)));
	}

	function testDeleteOnUpdate() {
		$this->TestS3Upload->actsAs['Upload.S3Upload']['photo']['deleteOnUpdate'] = true;
		$this->mockUpload();
		$this->MockUpload->expects($this->once())->method('handleUploadedFile')->will($this->returnValue(true));
		$this->MockUpload->expects($this->once())->method('unlink')->will($this->returnValue(true));

		$existingRecord = $this->TestS3Upload->findById($this->data['test_update']['id']);
		$this->MockUpload->expects($this->once())->method('unlink')->with(
			$this->MockUpload->settings['TestS3Upload']['photo']['path'] . $existingRecord['TestS3Upload']['dir'] . '/' . $existingRecord['TestS3Upload']['photo']
		);
		$this->MockUpload->expects($this->once())->method('handleUploadedFile')->with(
			$this->TestS3Upload->alias,
			'photo',
			$this->data['test_update']['photo']['tmp_name'],
			$this->MockUpload->settings['TestS3Upload']['photo']['path'] . $this->data['test_update']['id'] . '/' . $this->data['test_update']['photo']['name']
		);
		$result = $this->TestS3Upload->save($this->data['test_update']);
		$this->assertInternalType('array', $result);
	}

	function testDeleteOnUpdateWithoutNewUpload() {
		$this->TestS3Upload->actsAs['Upload.S3Upload']['photo']['deleteOnUpdate'] = true;
		$this->mockUpload();
		$this->MockUpload->expects($this->never())->method('unlink');
		$this->MockUpload->expects($this->never())->method('handleUploadedFile');
		$result = $this->TestS3Upload->save($this->data['test_update_other_field']);
		$this->assertInternalType('array', $result);
		$newRecord = $this->TestS3Upload->findById($this->TestS3Upload->id);
		$this->assertEqual($this->data['test_update_other_field']['other_field'], $newRecord['TestS3Upload']['other_field']);
	}

	function testUpdateWithoutNewUpload() {
		$this->mockUpload();
		$this->MockUpload->expects($this->never())->method('unlink');
		$this->MockUpload->expects($this->never())->method('handleUploadedFile');
		$result = $this->TestS3Upload->save($this->data['test_update_other_field']);
		$this->assertInternalType('array', $result);
		$newRecord = $this->TestS3Upload->findById($this->TestS3Upload->id);
		$this->assertEqual($this->data['test_update_other_field']['other_field'], $newRecord['TestS3Upload']['other_field']);
	}

	function testUnlinkFileOnDelete() {
		$this->mockUpload();
		$this->MockUpload->expects($this->once())->method('unlink')->will($this->returnValue(true));
		$existingRecord = $this->TestS3Upload->findById($this->data['test_update']['id']);
		$this->MockUpload->expects($this->once())->method('unlink')->with(
			$this->MockUpload->settings['TestS3Upload']['photo']['path'] . $existingRecord['TestS3Upload']['dir'] . '/' . $existingRecord['TestS3Upload']['photo']
		);
		$result = $this->TestS3Upload->delete($this->data['test_update']['id']);
		$this->assertTrue($result);
		$this->assertEmpty($this->TestS3Upload->findById($this->data['test_update']['id']));
	}


	function testDeleteFileOnTrueRemoveSave() {
		$this->mockUpload();
		$this->MockUpload->expects($this->once())->method('unlink')->will($this->returnValue(true));

		$data = array(
			'id' => 1,
			'photo' => array(
				'remove' => true
			)
		);

		$existingRecord = $this->TestS3Upload->findById($data['id']);
		$this->MockUpload->expects($this->once())->method('unlink')->with(
			$this->MockUpload->settings['TestS3Upload']['photo']['path'] . $existingRecord['TestS3Upload']['dir'] . '/' . $existingRecord['TestS3Upload']['photo']
		);
		$result = $this->TestS3Upload->save($data);
		$this->assertInternalType('array', $result);
	}
	
	function testKeepFileOnFalseRemoveSave() {
		$this->mockUpload();
		$this->MockUpload->expects($this->never())->method('unlink');

		$data = array(
			'id' => 1,
			'photo' => array(
				'remove' => false
			)
		);

		$existingRecord = $this->TestS3Upload->findById($data['id']);
		$result = $this->TestS3Upload->save($data);
		$this->assertInternalType('array', $result);
	}
	
	function testKeepFileOnNullRemoveSave() {
		$this->mockUpload();
		$this->MockUpload->expects($this->never())->method('unlink');

		$data = array(
			'id' => 1,
			'photo' => array(
				'remove' => null
			)
		);

		$existingRecord = $this->TestS3Upload->findById($data['id']);
		$result = $this->TestS3Upload->save($data);
		$this->assertInternalType('array', $result);
	}

	/**
	 * @expectedException UploadException
	 */
	function testMoveFileExecption() {
		$this->mockUpload(array('handleUploadedFile'));
		$this->MockUpload->expects($this->once())->method('handleUploadedFile')->will($this->returnValue(false));
		$result = $this->TestS3Upload->save($this->data['test_ok']);
	}

	function testIsUnderPhpSizeLimit() {
		$this->TestS3Upload->validate = array(
			'photo' => array(
				'isUnderPhpSizeLimit' => array(
					'rule' => 'isUnderPhpSizeLimit',
					'message' => 'isUnderPhpSizeLimit'
				),
			)
		);

		$data = array(
			'photo' => array(
				'tmp_name'  => 'Photo.png',
				'dir'   => '/tmp/php/file.tmp',
				'type'  => 'image/png',
				'size'  => 8192,
				'error' => UPLOAD_ERR_INI_SIZE,
			)
		);
		$this->TestS3Upload->set($data);
		$this->assertFalse($this->TestS3Upload->validates());
		$this->assertEqual(1, count($this->TestS3Upload->validationErrors));
		$this->assertEqual('isUnderPhpSizeLimit', current($this->TestS3Upload->validationErrors['photo']));

		$this->TestS3Upload->set($this->data['test_ok']);
		$this->assertTrue($this->TestS3Upload->validates());
		$this->assertEqual(0, count($this->TestS3Upload->validationErrors));

		$this->TestS3Upload->set($this->data['test_remove']);
		$this->assertTrue($this->TestS3Upload->validates());
		$this->assertEqual(0, count($this->TestS3Upload->validationErrors));
	}

	function testIsUnderFormSizeLimit() {
		$this->TestS3Upload->validate = array(
			'photo' => array(
				'isUnderFormSizeLimit' => array(
					'rule' => 'isUnderFormSizeLimit',
					'message' => 'isUnderFormSizeLimit'
				),
			)
		);

		$data = array(
			'photo' => array(
				'tmp_name'  => 'Photo.png',
				'dir'   => '/tmp/php/file.tmp',
				'type'  => 'image/png',
				'size'  => 8192,
				'error' => UPLOAD_ERR_FORM_SIZE,
			)
		);
		$this->TestS3Upload->set($data);
		$this->assertFalse($this->TestS3Upload->validates());
		$this->assertEqual(1, count($this->TestS3Upload->validationErrors));
		$this->assertEqual('isUnderFormSizeLimit', current($this->TestS3Upload->validationErrors['photo']));

		$this->TestS3Upload->set($this->data['test_ok']);
		$this->assertTrue($this->TestS3Upload->validates());
		$this->assertEqual(0, count($this->TestS3Upload->validationErrors));

		$this->TestS3Upload->set($this->data['test_remove']);
		$this->assertTrue($this->TestS3Upload->validates());
		$this->assertEqual(0, count($this->TestS3Upload->validationErrors));
	}

	function testIsCompletedUpload() {
		$this->TestS3Upload->validate = array(
			'photo' => array(
				'isCompletedUpload' => array(
					'rule' => 'isCompletedUpload',
					'message' => 'isCompletedUpload'
				),
			)
		);

		$data = array(
			'photo' => array(
				'tmp_name'  => 'Photo.png',
				'dir'   => '/tmp/php/file.tmp',
				'type'  => 'image/png',
				'size'  => 8192,
				'error' => UPLOAD_ERR_PARTIAL,
			)
		);
		$this->TestS3Upload->set($data);
		$this->assertFalse($this->TestS3Upload->validates());
		$this->assertEqual(1, count($this->TestS3Upload->validationErrors));
		$this->assertEqual('isCompletedUpload', current($this->TestS3Upload->validationErrors['photo']));

		$this->TestS3Upload->set($this->data['test_ok']);
		$this->assertTrue($this->TestS3Upload->validates());
		$this->assertEqual(0, count($this->TestS3Upload->validationErrors));

		$this->TestS3Upload->set($this->data['test_remove']);
		$this->assertTrue($this->TestS3Upload->validates());
		$this->assertEqual(0, count($this->TestS3Upload->validationErrors));
	}

	function testIsFileUpload() {
		$this->TestS3Upload->validate = array(
			'photo' => array(
				'isFileUpload' => array(
					'rule' => 'isFileUpload',
					'message' => 'isFileUpload'
				),
			)
		);

		$data = array(
			'photo' => array(
				'tmp_name'  => 'Photo.png',
				'dir'   => '/tmp/php/file.tmp',
				'type'  => 'image/png',
				'size'  => 8192,
				'error' => UPLOAD_ERR_NO_FILE,
			)
		);
		$this->TestS3Upload->set($data);
		$this->assertFalse($this->TestS3Upload->validates());
		$this->assertEqual(1, count($this->TestS3Upload->validationErrors));
		$this->assertEqual('isFileUpload', current($this->TestS3Upload->validationErrors['photo']));

		$this->TestS3Upload->set($this->data['test_ok']);
		$this->assertTrue($this->TestS3Upload->validates());
		$this->assertEqual(0, count($this->TestS3Upload->validationErrors));

		$this->TestS3Upload->set($this->data['test_remove']);
		$this->assertTrue($this->TestS3Upload->validates());
		$this->assertEqual(0, count($this->TestS3Upload->validationErrors));
	}

	function testTempDirExists() {
		$this->TestS3Upload->validate = array(
			'photo' => array(
				'tempDirExists' => array(
					'rule' => 'tempDirExists',
					'message' => 'tempDirExists'
				),
			)
		);

		$data = array(
			'photo' => array(
				'tmp_name'  => 'Photo.png',
				'dir'   => '/tmp/php/file.tmp',
				'type'  => 'image/png',
				'size'  => 8192,
				'error' => UPLOAD_ERR_NO_TMP_DIR,
			)
		);
		$this->TestS3Upload->set($data);
		$this->assertFalse($this->TestS3Upload->validates());
		$this->assertEqual(1, count($this->TestS3Upload->validationErrors));
		$this->assertEqual('tempDirExists', current($this->TestS3Upload->validationErrors['photo']));

		$this->TestS3Upload->set($this->data['test_ok']);
		$this->assertTrue($this->TestS3Upload->validates());
		$this->assertEqual(0, count($this->TestS3Upload->validationErrors));

		$this->TestS3Upload->set($this->data['test_remove']);
		$this->assertTrue($this->TestS3Upload->validates());
		$this->assertEqual(0, count($this->TestS3Upload->validationErrors));
	}

	function testIsSuccessfulWrite() {
		$this->TestS3Upload->validate = array(
			'photo' => array(
				'isSuccessfulWrite' => array(
					'rule' => 'isSuccessfulWrite',
					'message' => 'isSuccessfulWrite'
				),
			)
		);

		$data = array(
			'photo' => array(
				'tmp_name'  => 'Photo.png',
				'dir'   => '/tmp/php/file.tmp',
				'type'  => 'image/png',
				'size'  => 8192,
				'error' => UPLOAD_ERR_CANT_WRITE,
			)
		);
		$this->TestS3Upload->set($data);
		$this->assertFalse($this->TestS3Upload->validates());
		$this->assertEqual(1, count($this->TestS3Upload->validationErrors));
		$this->assertEqual('isSuccessfulWrite', current($this->TestS3Upload->validationErrors['photo']));

		$this->TestS3Upload->set($this->data['test_ok']);
		$this->assertTrue($this->TestS3Upload->validates());
		$this->assertEqual(0, count($this->TestS3Upload->validationErrors));

		$this->TestS3Upload->set($this->data['test_remove']);
		$this->assertTrue($this->TestS3Upload->validates());
		$this->assertEqual(0, count($this->TestS3Upload->validationErrors));
	}

	function testNoPhpExtensionErrors() {
		$this->TestS3Upload->validate = array(
			'photo' => array(
				'noPhpExtensionErrors' => array(
					'rule' => 'noPhpExtensionErrors',
					'message' => 'noPhpExtensionErrors'
				),
			)
		);

		$data = array(
			'photo' => array(
				'tmp_name'  => 'Photo.png',
				'dir'   => '/tmp/php/file.tmp',
				'type'  => 'image/png',
				'size'  => 8192,
				'error' => UPLOAD_ERR_EXTENSION,
			)
		);
		$this->TestS3Upload->set($data);
		$this->assertFalse($this->TestS3Upload->validates());
		$this->assertEqual(1, count($this->TestS3Upload->validationErrors));
		$this->assertEqual('noPhpExtensionErrors', current($this->TestS3Upload->validationErrors['photo']));

		$this->TestS3Upload->set($this->data['test_ok']);
		$this->assertTrue($this->TestS3Upload->validates());
		$this->assertEqual(0, count($this->TestS3Upload->validationErrors));

		$this->TestS3Upload->set($this->data['test_remove']);
		$this->assertTrue($this->TestS3Upload->validates());
		$this->assertEqual(0, count($this->TestS3Upload->validationErrors));
	}

	function testIsValidMimeType() {
		$this->TestS3Upload->Behaviors->detach('Upload.S3Upload');
		$this->TestS3Upload->Behaviors->attach('Upload.S3Upload', array(
			'photo' => array(
				'mimetypes' => array('image/bmp', 'image/jpeg')
			)
		));

		$this->TestS3Upload->validate = array(
			'photo' => array(
				'isValidMimeType' => array(
					'rule' => 'isValidMimeType',
					'message' => 'isValidMimeType'
				),
			)
		);

		$this->TestS3Upload->set($this->data['test_ok']);
		$this->assertFalse($this->TestS3Upload->validates());
		$this->assertEqual(1, count($this->TestS3Upload->validationErrors));
		$this->assertEqual('isValidMimeType', current($this->TestS3Upload->validationErrors['photo']));

		$this->TestS3Upload->Behaviors->detach('Upload.S3Upload');
		$this->TestS3Upload->Behaviors->attach('Upload.S3Upload', array(
			'photo' => array(
				'mimetypes' => array('image/png', 'image/jpeg')
			)
		));

		$this->TestS3Upload->set($this->data['test_ok']);
		$this->assertTrue($this->TestS3Upload->validates());
		$this->assertEqual(0, count($this->TestS3Upload->validationErrors));

		$this->TestS3Upload->set($this->data['test_remove']);
		$this->assertTrue($this->TestS3Upload->validates());
		$this->assertEqual(0, count($this->TestS3Upload->validationErrors));

		$this->TestS3Upload->validate = array(
			'photo' => array(
				'isValidMimeType' => array(
					'rule' => array('isValidMimeType', 'image/png'),
					'message' => 'isValidMimeType',
				),
			)
		);

		$this->TestS3Upload->set($this->data['test_ok']);
		$this->assertTrue($this->TestS3Upload->validates());
		$this->assertEqual(0, count($this->TestS3Upload->validationErrors));
	}

	function testIsValidExtension() {
		$this->TestS3Upload->Behaviors->detach('Upload.S3Upload');
		$this->TestS3Upload->Behaviors->attach('Upload.S3Upload', array(
			'photo' => array(
				'extensions' => array('jpeg', 'bmp')
			)
		));

		$this->TestS3Upload->validate = array(
			'photo' => array(
				'isValidExtension' => array(
					'rule' => 'isValidExtension',
					'message' => 'isValidExtension'
				),
			)
		);

		$this->TestS3Upload->set($this->data['test_ok']);
		$this->assertFalse($this->TestS3Upload->validates());
		$this->assertEqual(1, count($this->TestS3Upload->validationErrors));
		$this->assertEqual('isValidExtension', current($this->TestS3Upload->validationErrors['photo']));

		$data = $this->data['test_ok'];
		$data['photo']['name'] = 'Photo.bmp';
		$this->TestS3Upload->set($data);
		$this->assertTrue($this->TestS3Upload->validates());
		$this->assertEqual(0, count($this->TestS3Upload->validationErrors));

		$this->TestS3Upload->Behaviors->detach('Upload.S3Upload');
		$this->TestS3Upload->Behaviors->attach('Upload.S3Upload', array(
			'photo'
		));

		$this->TestS3Upload->validate['photo']['isValidExtension']['rule'] = array('isValidExtension', 'jpg');
		$this->TestS3Upload->set($this->data['test_ok']);
		$this->assertFalse($this->TestS3Upload->validates());
		$this->assertEqual(1, count($this->TestS3Upload->validationErrors));
		$this->assertEqual('isValidExtension', current($this->TestS3Upload->validationErrors['photo']));

		$this->TestS3Upload->validate['photo']['isValidExtension']['rule'] = array('isValidExtension', array('jpg'));
		$this->TestS3Upload->set($this->data['test_ok']);
		$this->assertFalse($this->TestS3Upload->validates());
		$this->assertEqual(1, count($this->TestS3Upload->validationErrors));
		$this->assertEqual('isValidExtension', current($this->TestS3Upload->validationErrors['photo']));

		$this->TestS3Upload->validate['photo']['isValidExtension']['rule'] = array('isValidExtension', array('jpg', 'bmp'));
		$this->TestS3Upload->set($this->data['test_ok']);
		$this->assertFalse($this->TestS3Upload->validates());
		$this->assertEqual(1, count($this->TestS3Upload->validationErrors));
		$this->assertEqual('isValidExtension', current($this->TestS3Upload->validationErrors['photo']));

		$this->TestS3Upload->validate['photo']['isValidExtension']['rule'] = array('isValidExtension', array('jpg', 'bmp', 'png'));
		$this->TestS3Upload->set($this->data['test_ok']);
		$this->assertTrue($this->TestS3Upload->validates());
		$this->assertEqual(0, count($this->TestS3Upload->validationErrors));

		$this->TestS3Upload->validate = array(
			'photo' => array(
				'isFileUpload' => array(
					'rule' => 'isFileUpload',
					'message' => 'isFileUpload'
				),
				'isValidExtension' => array(
					'rule' => array('isValidExtension', array('jpg')),
					'message' => 'isValidExtension'
				),
			)
		);

		$this->TestS3Upload->set($this->data['test_ok']);
		$this->assertFalse($this->TestS3Upload->validates());
		$this->assertEqual(1, count($this->TestS3Upload->validationErrors));
		$this->assertEqual('isValidExtension', current($this->TestS3Upload->validationErrors['photo']));

		$data['photo']['name'] = 'Photo.jpg';
		$this->TestS3Upload->set($this->data['test_ok']);
		$this->assertFalse($this->TestS3Upload->validates());
		$this->assertEqual(1, count($this->TestS3Upload->validationErrors));
		$this->assertEqual('isValidExtension', current($this->TestS3Upload->validationErrors['photo']));

		$this->TestS3Upload->set($this->data['test_remove']);
		$this->assertTrue($this->TestS3Upload->validates());
		$this->assertEqual(0, count($this->TestS3Upload->validationErrors));
	}

	/* need to rewrite for S3 
	function testIsWritable() {
		$this->TestS3Upload->validate = array(
			'photo' => array(
				'isWritable' => array(
					'rule' => 'isWritable',
					'message' => 'isWritable'
				),
			)
		);

		$this->TestS3Upload->set($this->data['test_ok']);
		debug($this->data['test_ok']);
		$this->TestS3Upload->
		//$this->TestS3Upload->log($this->TestS3Upload);
		$this->assertFalse($this->TestS3Upload->validates());

		$this->assertEqual(1, count($this->TestS3Upload->validationErrors));
		$this->assertEqual('isWritable', current($this->TestS3Upload->validationErrors['photo']));

		$this->TestS3Upload->Behaviors->detach('Upload.S3Upload');
		$this->TestS3Upload->Behaviors->attach('Upload.S3Upload', array(
			'photo' => array(
				'path' => TMP
			)
		));

		$data = array(
			'photo' => array(
				'tmp_name'  => 'Photo.bmp',
				'dir'   => '/tmp/php/file.tmp',
				'type'  => 'image/bmp',
				'size'  => 8192,
				'error' => UPLOAD_ERR_OK,
			)
		);
		$this->TestS3Upload->set($data);
		$this->assertTrue($this->TestS3Upload->validates());
		$this->assertEqual(0, count($this->TestS3Upload->validationErrors));

		$this->TestS3Upload->set($this->data['test_remove']);
		$this->assertTrue($this->TestS3Upload->validates());
		$this->assertEqual(0, count($this->TestS3Upload->validationErrors));
	}

	function testIsValidDir() {
		$this->TestS3Upload->validate = array(
			'photo' => array(
				'isValidDir' => array(
					'rule' => 'isValidDir',
					'message' => 'isValidDir'
				),
			)
		);

		$this->TestS3Upload->set($this->data['test_ok']);
		$this->assertFalse($this->TestS3Upload->validates());

		$this->assertEqual(1, count($this->TestS3Upload->validationErrors));
		$this->assertEqual('isValidDir', current($this->TestS3Upload->validationErrors['photo']));

		$this->TestS3Upload->Behaviors->detach('Upload.S3Upload');
		$this->TestS3Upload->Behaviors->attach('Upload.S3Upload', array(
			'photo' => array(
				'path' => TMP
			)
		));

		$data = array(
			'photo' => array(
				'tmp_name'  => 'Photo.bmp',
				'dir'   => '/tmp/php/file.tmp',
				'type'  => 'image/bmp',
				'size'  => 8192,
				'error' => UPLOAD_ERR_OK,
			)
		);
		$this->TestS3Upload->set($data);
		$this->assertTrue($this->TestS3Upload->validates());
		$this->assertEqual(0, count($this->TestS3Upload->validationErrors));

		$this->TestS3Upload->set($this->data['test_remove']);
		$this->assertTrue($this->TestS3Upload->validates());
		$this->assertEqual(0, count($this->TestS3Upload->validationErrors));
	}*/

	function testIsImage() {
		$this->TestS3Upload->Behaviors->detach('Upload.S3Upload');
		$this->TestS3Upload->Behaviors->attach('Upload.S3Upload', array(
			'photo' => array(
				'mimetypes' => array('image/bmp', 'image/jpeg')
			)
		));

		$result = $this->TestS3Upload->Behaviors->S3Upload->_isImage($this->TestS3Upload, 'image/bmp');
		$this->assertTrue($result);

		$result = $this->TestS3Upload->Behaviors->S3Upload->_isImage($this->TestS3Upload, 'image/jpeg');
		$this->assertTrue($result);

		$result = $this->TestS3Upload->Behaviors->S3Upload->_isImage($this->TestS3Upload, 'application/zip');
		$this->assertFalse($result);
	}

	function testIsMedia() {
		$this->TestS3Upload->Behaviors->detach('Upload.S3Upload');
		$this->TestS3Upload->Behaviors->attach('Upload.S3Upload', array(
			'pdf_file' => array(
				'mimetypes' => array('application/pdf', 'application/postscript')
			)
		));

		$result = $this->TestS3Upload->Behaviors->S3Upload->_isMedia($this->TestS3Upload, 'application/pdf');
		$this->assertTrue($result);

		$result = $this->TestS3Upload->Behaviors->S3Upload->_isMedia($this->TestS3Upload, 'application/postscript');
		$this->assertTrue($result);

		$result = $this->TestS3Upload->Behaviors->S3Upload->_isMedia($this->TestS3Upload, 'application/zip');
		$this->assertFalse($result);

		$result = $this->TestS3Upload->Behaviors->S3Upload->_isMedia($this->TestS3Upload, 'image/jpeg');
		$this->assertFalse($result);
	}

	function testGetPathFlat() {
		$basePath = 'tests' . '/' . 'path' . '/' . 'flat' . '/';
		$result = $this->TestS3Upload->Behaviors->S3Upload->_getPathFlat($this->TestS3Upload, 'photo', TMP . $basePath);

		$this->assertInternalType('string', $result);
		$this->assertEqual(0, strlen($result));
	}

	function testGetPathPrimaryKey() {
		$this->TestS3Upload->id = 5;
		$basePath = 'tests' . '/' . 'path' . '/' . 'primaryKey' . '/';
		$result = $this->TestS3Upload->Behaviors->S3Upload->_getPathPrimaryKey($this->TestS3Upload, 'photo', TMP . $basePath);

		$this->assertInternalType('integer', $result);
		$this->assertEqual(1, strlen($result));
		$this->assertEqual($result, $this->TestS3Upload->id);
		$this->assertTrue(is_dir(TMP . $basePath . $result));
	}

	function testGetPathRandom() {
		$basePath = 'tests' . '/' . 'path' . '/' . 'random' . '/';
		$result = $this->TestS3Upload->Behaviors->S3Upload->_getPathRandom($this->TestS3Upload, 'photo', TMP . $basePath);

		$this->assertInternalType('string', $result);
		$this->assertEqual(8, strlen($result));
		$this->assertTrue(is_dir(TMP . $basePath . $result));
	}

	function testReplacePath() {
		$result = $this->TestS3Upload->Behaviors->S3Upload->_path($this->TestS3Upload, 'photo', array(
			'path' => 'files/{model}\\{field}{DS}',
		));

		$this->assertInternalType('string', $result);
		$this->assertEqual('files/test_s3_upload/photo/', $result);

		$result = $this->TestS3Upload->Behaviors->S3Upload->_path($this->TestS3Upload, 'photo', array(
			'path' => 'files//{size}/{model}\\{field}{DS}{geometry}///',
		));

		$this->assertInternalType('string', $result);
		$this->assertEqual('files/{size}/test_s3_upload/photo/{geometry}/', $result);


		$result = $this->TestS3Upload->Behaviors->S3Upload->_path($this->TestS3Upload, 'photo', array(
			'isThumbnail' => false,
			'path' => 'files//{size}/{model}\\\\{field}{DS}{geometry}///',
		));

		$this->assertInternalType('string', $result);
		$this->assertEqual('files/test_s3_upload/photo/', $result);
	}
}
