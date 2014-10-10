<?php
/**
 * ownCloud - react
 *
 * This file is licensed under the MIT License. See the COPYING file.
 *
 * @author Robin Appelman <icewind@owncloud.com>
 * @copyright Robin Appelman 2014
 */

namespace OCA\React\WebDAV;

/**
 * Wrap a react response to make it usable by sabre/dav
 */
class Response extends \Sabre\HTTP\Response {
	/**
	 * @var \React\Http\Response
	 */
	protected $response;

	/**
	 * @var string[][]
	 */
	protected $headers = [];

	/**
	 * @var bool
	 */
	protected $headersSent = false;

	/**
	 * @var int
	 */
	protected $status;

	public function __construct(\React\Http\Response $response) {
		$this->response = $response;
	}

	/**
	 * Sends an HTTP status header to the client.
	 *
	 * @param int $code HTTP status code
	 * @return bool
	 */
	public function sendStatus($code) {
		if (!$this->headersSent) {
			$this->status = $code;
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Sets an HTTP header for the response
	 *
	 * @param string $name
	 * @param string $value
	 * @param bool $replace
	 * @return bool
	 */
	public function setHeader($name, $value, $replace = true) {
		$value = str_replace(array("\r", "\n"), array('\r', '\n'), $value);
		if (!$this->headersSent) {
			if (!isset($this->headers[$name]) or $replace) {
				$this->headers[$name] = [];
			}
			$this->headers[$name][] = $value;
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Sets a bunch of HTTP Headers
	 *
	 * headersnames are specified as keys, value in the array value
	 *
	 * @param array $headers
	 * @return void
	 */
	public function setHeaders(array $headers) {
		foreach ($headers as $key => $value) {
			$this->setHeader($key, $value);
		}
	}

	protected function sendHeaders() {
		if (!$this->headersSent) {
			$this->response->writeHead($this->status, $this->headers);
			$this->headersSent = true;
		}
	}

	/**
	 * Sends the entire response body
	 *
	 * This method can accept either an open stream, or a string.
	 *
	 * @param mixed $body
	 * @return void
	 */
	public function sendBody($body) {
		$this->sendHeaders();
		if (is_resource($body)) {
			while ($data = fread($body, 4096)) {
				$this->response->write($data);
			}
			$this->response->end();
		} else {
			// We assume a string
			$this->response->end($body);
		}
	}

	public function end() {
		$this->sendHeaders();
		$this->response->end();
	}
}
