<?php
/**
 * ownCloud - react
 *
 * This file is licensed under the MIT License. See the COPYING file.
 *
 * @author Robin Appelman <icewind@owncloud.com>
 * @copyright Robin Appelman 2014
 */

namespace OCA\React;

use React\Promise\Deferred;
use React\Promise\PromisorInterface;
use React\Stream\ReadableStreamInterface;
use React\Stream\Util;
use React\Stream\WritableStream;

/**
 * Read a react stream into a php temporary stream
 */
class StreamReader extends WritableStream implements PromisorInterface {
	/**
	 * @var resource
	 */
	private $temp;

	/**
	 * @var \React\Promise\Deferred
	 */
	private $deferred;

	/**
	 * @var int
	 */
	private $contentLength;

	/**
	 * @var int
	 */
	private $received = 0;

	/**
	 * @param int $contentLength
	 */
	public function __construct($contentLength) {
		$this->deferred = new Deferred();
		$this->contentLength = $contentLength;

		$this->on('pipe', array($this, 'handlePipeEvent'));
		$this->on('error', array($this, 'handleErrorEvent'));
		$this->temp = fopen('php://temp', 'r+');
	}

	public function handlePipeEvent($source) {
		Util::forwardEvents($source, $this, array('error'));
	}

	public function handleErrorEvent($e) {
		$this->deferred->reject($e);
	}

	public function write($data) {
		$this->received += strlen($data);
		fwrite($this->temp, $data);
		$this->deferred->progress($data);
		if ($this->received >= $this->contentLength) {
			$this->close();
		}
	}

	public function close() {
		if ($this->closed) {
			return;
		}

		parent::close();
		rewind($this->temp);
		$this->deferred->resolve($this->temp);
	}

	public function promise() {
		return $this->deferred->promise();
	}

	public static function createPromise(ReadableStreamInterface $stream) {
		$sink = new static();
		$stream->pipe($sink);

		return $sink->promise();
	}
}
