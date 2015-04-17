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
use Sabre\HTTP\ResponseInterface;

class Sapi {
	/**
	 * @var \OCA\React\WebDAV\Request
	 */
	private $request;

	/**
	 * This static method will create a new Request object, based on the
	 * current PHP request.
	 *
	 * @return \Sabre\HTTP\Request
	 */
	public function getRequest() {
		return $this->request;
	}

	public function __construct(Request $request){
		$this->request = $request;
	}

	/**
	 * Sends the HTTP response back to a HTTP client.
	 *
	 * This calls php's header() function and streams the body to php://output.
	 *
	 * @param \OCA\React\WebDAV\Response $response
	 * @return void
	 */
	public function sendResponse(Response $response) {
		$response->end();
	}
}
