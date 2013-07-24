<?php
/**
 * Abstraction so 
 * as to use any suitable S3 Client without changing the Behavior classes
 * drastically
 * Right now, the S3 Client assumed here is the AWS PHP SDK 2.0
 */
class S3UploadManager {
	public $client;

	public $settings = array();

	public $bucket;

	public $require = '../../../../../Vendor/autoload.php';

	public function __construct($settings = array()) {
		$this->settings = $settings;
		if (isset($settings['require'])) {
			$this->require = $settings['require'];
		}
		require($this->require);
		// instantiate the client
		if (isset($settings['key']) && isset($settings['secret'])) {
			$this->client = Aws\S3\S3Client::factory(array(
				'key' => $settings['key'],
				'secret' => $settings['secret']
			));
		}
		// instantiate the bucket
		if (isset($settings['bucket'])) {
			$this->bucket = $settings['bucket'];
		}
	}

	public function uploadFile($key, $absPathToFile, $options = array()) {
		$defaults = array(
			'acl' => 'public-read',
		);
		$options = array_merge($defaults, $options);

		if (!isset($options['mimetype'])) {
			$finfo = new finfo(FILEINFO_MIME); // return mime type ala mimetype extension

			/* get mime-type for a specific file */
			$options['mimetype'] = $finfo->file($absPathToFile);
		}

		extract($options, EXTR_OVERWRITE);

		// Upload an object by streaming the contents of a file
		// $pathToFile should be absolute path to a file on disk
		$result = $this->client->putObject(array(
			'Bucket'     => $this->bucket,
			'Key'        => $key,
			'SourceFile' => $absPathToFile,
			'ACL'           => $acl,
			'ContentType'  => $mimetype
		));

		// We can poll the object until it is accessible
		$this->client->waitUntilObjectExists(array(
		'Bucket' => $this->bucket,
		'Key'    => $key
		));

		// public read is https://bucketname.s3.amazonaws.com/path/to/file.png
		return true;
	}

	public function deleteFile($key) {
		// Upload an object by streaming the contents of a file
		// $pathToFile should be absolute path to a file on disk
		$result = $this->client->deleteObject(array(
			'Bucket'     => $this->bucket,
			'Key'        => $key
		));

		// public read is https://bucketname.s3.amazonaws.com/path/to/file.png
		return true;
	}

	public function isWritable($key) {
		try {
			$result = $this->client->getObjectAcl(array(
				'Bucket'     => $this->bucket,
				'Key'        => $key
			));
			// still waiting for https://github.com/aws/aws-sdk-php/issues/121
			// so just return true
			// HACK!
			return true;
			// end of HACK!
		} catch(Aws\S3\Exception\NoSuchKeyException $e) {
			// still waiting for https://github.com/aws/aws-sdk-php/issues/121
			// so just deliberately write stuff in
			// HACK!
			$result = $this->client->putObject(array(
			'Bucket'     => $this->bucket,
			'Key'        => $key,
			'Body' => 'test!',
			'ACL'           => 'public-read',
			));

			// We can poll the object until it is accessible
			$this->client->waitUntilObjectExists(array(
			'Bucket' => $this->bucket,
			'Key'    => $key
			));

			$result = $this->client->doesObjectExist($this->bucket, $key);
			if ($result) {
				$this->deleteFile($key);
				return true;
			} else {
				return false;
			}
			// end of HACK!
		}
		return false;
	}

}
