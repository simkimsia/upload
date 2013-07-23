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

	public function uploadFile($key, $absPathToFile, $acl = 'public-read') {
		// Upload an object by streaming the contents of a file
		// $pathToFile should be absolute path to a file on disk
		$result = $this->client->putObject(array(
			'Bucket'     => $this->bucket,
			'Key'        => $key,
			'SourceFile' => $absPathToFile,
			'ACL'           => $acl,
		));

		// We can poll the object until it is accessible
		$this->client->waitUntilObjectExists(array(
		'Bucket' => $this->bucket,
		'Key'    => $key
		));

		// public read is https://bucketname.s3.amazonaws.com/path/to/file.png
		return true;
	}

}
