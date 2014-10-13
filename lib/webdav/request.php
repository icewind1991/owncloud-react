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
 * Wrap a react request to make it usable by sabre/dav
 */
class Request extends \Sabre\HTTP\Request {
	/**
	 * @var \React\Http\Request
	 */
	protected $request;

	/**
	 * @var bool
	 */
	protected $https;

	/**
	 * @var resource
	 */
	protected $body;

	public function __construct(\React\Http\Request $request, $body, $https = false) {
		$this->request = $request;
		$this->body = $body;
		$this->root = $root;
		$this->https = $https;
	}

	/**
	 * Returns the value for a specific http header.
	 *
	 * This method returns null if the header did not exist.
	 *
	 * @param string $name
	 * @return string
	 */
	public function getHeader($name) {
		$headers = $this->request->getHeaders();
		return isset($headers[$name]) ? $headers[$name] : null;
	}

	/**
	 * Returns all (known) HTTP headers.
	 *
	 * All headers are converted to lower-case, and additionally all underscores
	 * are automatically converted to dashes
	 *
	 * @return array
	 */
	public function getHeaders() {
		$headers = array();
		foreach ($this->request->getHeaders() as $key => $value) {
			switch ($key) {
				case 'CONTENT_LENGTH' :
				case 'CONTENT_TYPE' :
					$headers[strtolower(str_replace('_', '-', $key))] = $value;
					break;
				default :
					if (strpos($key, 'HTTP_') === 0) {
						$headers[substr(strtolower(str_replace('_', '-', $key)), 5)] = $value;
					}
					break;
			}
		}
		return $headers;
	}

	/**
	 * Returns the HTTP request method
	 *
	 * This is for example POST or GET
	 *
	 * @return string
	 */
	public function getMethod() {
		return $this->request->getMethod();
	}

	/**
	 * Returns the requested uri
	 *
	 * @return string
	 */
	public function getUri() {
		return $this->request->getPath();
	}

	/**
	 * Will return protocol + the hostname + the uri
	 *
	 * @return string
	 */
	public function getAbsoluteUri() {
		return ($this->https ? 'https' : 'http') . '://' . $this->getHeader('Host') . $this->getUri();

	}

	/**
	 * Returns everything after the ? from the current url
	 *
	 * @return string
	 */
	public function getQueryString() {
		return http_build_query($this->request->getQuery(), null, '&');

	}

	/**
	 * Returns the HTTP request body body
	 *
	 * This method returns a readable stream resource.
	 * If the asString parameter is set to true, a string is sent instead.
	 *
	 * @param bool $asString
	 * @return resource
	 */
	public function getBody($asString = false) {
		if ($asString) {
			return stream_get_contents($this->body);
		} else {
			return $this->body;
		}

	}

	/**
	 * Sets the contents of the HTTP request body
	 *
	 * This method can either accept a string, or a readable stream resource.
	 *
	 * If the setAsDefaultInputStream is set to true, it means for this run of the
	 * script the supplied body will be used instead of php://input.
	 *
	 * @param mixed $body
	 * @param bool $setAsDefaultInputStream
	 * @return void
	 */
	public function setBody($body, $setAsDefaultInputStream = false) {

		if (is_resource($body)) {
			$this->body = $body;
		} else {

			$stream = fopen('php://temp', 'r+');
			fputs($stream, $body);
			rewind($stream);
			// String is assumed
			$this->body = $stream;
		}
		if ($setAsDefaultInputStream) {
			parent::$defaultInputStream = $this->body;
		}

	}

	/**
	 * Returns PHP's _POST variable.
	 *
	 * The reason this is in a method is so it can be subclassed and
	 * overridden.
	 *
	 * @return array
	 */
	public function getPostVars() {
		parse_str($this->getBody(true), $vars);
		return $vars;
	}

	/**
	 * @return string[]
	 */
	private function getBasicAuth() {
		$header = $this->getHeader('Authorization');
		if (!$header) {
			return [null, null];
		}
		list($scheme, $coded) = explode(' ', $header, 2);
		if ($scheme !== 'Basic') {
			return [null, null];
		}
		return explode(':', base64_decode($coded));
	}

	/**
	 * Returns a specific item from the _SERVER array.
	 *
	 * Do not rely on this feature, it is for internal use only.
	 *
	 * @param string $field
	 * @return string
	 */
	public function getRawServerValue($field) {
		switch ($field) {
			case 'PHP_AUTH_USER':
				list($user) = $this->getBasicAuth();
				return $user;
			case 'PHP_AUTH_PW':
				list(, $pass) = $this->getBasicAuth();
				return $pass;
			case 'REDIRECT_HTTP_AUTHORIZATION':
				//no need for this since it's for fastcgi compatibility
				return null;
		}
		echo "raw server value not supported($field)\n";
		return null;
	}

	/**
	 * Returns the HTTP version specified within the request.
	 *
	 * @return string
	 */
	public function getHTTPVersion() {
		return $this->request->getHttpVersion();
	}
}
