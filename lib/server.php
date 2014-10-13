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

use OC\Session\Memory;
use React\EventLoop\Factory;
use React\EventLoop\LoopInterface;
use React\Http\Request;
use React\Http\Response;

abstract class Server {
	/**
	 * @var \React\EventLoop\LoopInterface
	 */
	protected $loop;

	/**
	 * @var \React\Socket\Server
	 */
	protected $socket;

	/**
	 * @var \React\Http\Server
	 */
	protected $http;

	/**
	 * @var \OCP\ISession[]
	 */
	protected $sessions = [];

	/**
	 * @param \React\EventLoop\LoopInterface $loop
	 */
	public function __construct(LoopInterface $loop = null) {
		if (is_null($loop)) {
			$this->loop = Factory::create();
		} else {
			$this->loop = $loop;
		}
		$this->socket = new \React\Socket\Server($this->loop);
		$this->http = new \React\Http\Server($this->socket);
		$this->http->on('request', array($this, 'handleRequest'));
	}

	protected function loadSession($id) {
		if (!isset($this->sessions[$id])) {
			$this->sessions[$id] = new Memory($id);
		}
		\OC::$server->setSession($this->sessions[$id]);
	}

	protected function initSession(Request $request) {
		$headers = $request->getHeaders();
		if (isset($headers['Cookie'])) {
			$header = $headers['Cookie'];
			$parts = explode(';', $header);
			foreach ($parts as $part) {
				$part = trim($part);
				list($name, $value) = explode('=', $part);
				if ($name === 'PHPSESSID') {
					$this->loadSession($value);
					return;
				}
			}
		}
		\OC::$server->setSession(new Memory(''));
	}

	/**
	 * @param \React\Http\Request $request
	 * @param \React\Http\Response $response
	 */
	abstract public function handleRequest(Request $request, Response $response);

	/**
	 * @param int $port
	 * @throws \React\Socket\ConnectionException
	 */
	public function run($port) {
		echo "Listening on port $port\n";
		$this->socket->listen($port);
		$this->loop->run();
	}
}
