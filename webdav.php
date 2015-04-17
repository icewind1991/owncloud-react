<?php
/**
 * ownCloud - react
 *
 * This file is licensed under the MIT License. See the COPYING file.
 *
 * @author Robin Appelman <icewind@owncloud.com>
 * @copyright Robin Appelman 2014
 */

require_once __DIR__ . '/3rdparty/autoload.php';
require_once __DIR__ . '/../../lib/base.php';

\OC::init();

$server = new \OCA\React\WebDAV\Server('/');
$port = (int)\OC::$server->getConfig()->getAppValue('react', 'port', 8080);
$server->run($port);
