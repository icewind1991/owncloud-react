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

use OC\Files\Filesystem;
use OC\Files\View;
use OCA\React\StreamReader;
use React\EventLoop\LoopInterface;
use React\Http\Request as ReactRequest;
use React\Http\Response as ReactResponse;

class Server extends \OCA\React\Server {
	/**
	 * @var \Sabre\DAV\Server
	 */
	protected $sabre;

	/**
	 * @var string
	 */
	protected $root;

	public function __construct($root, LoopInterface $loop = null) {
		parent::__construct($loop);
		$this->root = $root;

		// Backends
		$userSession = \OC::$server->getUserSession();
		$authBackend = new Auth($userSession);
		$lockBackend = new \OC_Connector_Sabre_Locks();

		// Fire up server
		$objectTree = new \OC\Connector\Sabre\ObjectTree();
		$this->sabre = new \OC_Connector_Sabre_Server($objectTree);
		$this->sabre->setBaseUri($root);

		// Load plugins
		$defaults = new \OC_Defaults();
		$this->sabre->addPlugin(new \Sabre\DAV\Auth\Plugin($authBackend, $defaults->getName()));
		$this->sabre->addPlugin(new \Sabre\DAV\Locks\Plugin($lockBackend));
		$this->sabre->addPlugin(new \Sabre\DAV\Browser\Plugin(false)); // Show something in the Browser, but no upload
		$this->sabre->addPlugin(new \OC_Connector_Sabre_FilesPlugin());
		$this->sabre->addPlugin(new \OC_Connector_Sabre_MaintenancePlugin());
		$this->sabre->addPlugin(new \OC_Connector_Sabre_ExceptionLoggerPlugin('webdav'));

		// wait with registering these until auth is handled and the filesystem is setup
		$this->sabre->subscribeEvent('beforeMethod', function () use ($objectTree, $userSession) {
			$userId = $userSession->getUser()->getUID();
			$view = new View('/' . $userId . '/files');
			$rootInfo = $view->getFileInfo('');

			// Create ownCloud Dir
			$mountManager = \OC\Files\Filesystem::getMountManager();
			$rootDir = new \OC_Connector_Sabre_Directory($view, $rootInfo);
			$objectTree->init($rootDir, $view, $mountManager);

//			$this->sabre->addPlugin(new \OC_Connector_Sabre_QuotaPlugin($view));
		}, 30); // priority 30: after auth (10) and acl(20), before lock(50) and handling the request

		$this->sabre->subscribeEvent('beforeMethod', function ($method, $uri) {
			echo "$method '$uri'\n";
		});
	}


	/**
	 * @param \React\Http\Request $request
	 * @param \React\Http\Response $response
	 */
	public function handleRequest(ReactRequest $request, ReactResponse $response) {
		$this->initSession($request);
		$headers = $request->getHeaders();
		$length = isset($headers['Content-Length']) ? $headers['Content-Length'] : 0;
		$sink = new StreamReader($length);
		$request->pipe($sink);
		$sink->promise()->then(function ($body) use ($request, $response) {
			$sabreRequest = new Request($request, $body);
			$sabreResponse = new Response($response);
			$this->sabre->httpRequest = $sabreRequest;
			$this->sabre->httpResponse = $sabreResponse;
			try {
				$this->sabre->exec();
				$sabreResponse->end();
			} catch (\Exception $e) {
				$response->write("Exception: \n");
				$response->write($e->getMessage());
				$sabreResponse->end();
			}
		});
	}
}
