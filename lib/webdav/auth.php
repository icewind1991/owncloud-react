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
use OCP\IUserSession;
use Sabre\DAV\Auth\Backend\AbstractBasic;

class Auth extends AbstractBasic {
	/**
	 * @var \OCP\IUserSession
	 */
	protected $userSession;

	/**
	 * @var array
	 */
	protected $usersSetup = [];

	/**
	 * @param \OCP\IUserSession $userSession
	 */
	public function __construct(IUserSession $userSession) {
		$this->userSession = $userSession;
	}

	/**
	 * @param string $username
	 * @param string $password
	 * @return bool
	 */
	protected function validateUserPass($username, $password) {
		if ($this->userSession->login($username, $password)) {
			$userId = $this->userSession->getUser()->getUID();
			if (!isset($this->usersSetup[$userId])) {
				\OC_Util::setUpFS($userId);
				Filesystem::initMountPoints($userId);
				$this->usersSetup[$userId] = true;
			}
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Returns information about the currently logged in username.
	 *
	 * If nobody is currently logged in, this method should return null.
	 *
	 * @return string|null
	 */
	public function getCurrentUser() {
		$user = $this->userSession->getUser();
		if (!$user) {
			return null;
		}
		return $user->getUID();
	}

	/**
	 * Override function here. We want to cache authentication cookies
	 * in the syncing client to avoid HTTP-401 roundtrips.
	 * If the sync client supplies the cookies, then OC_User::isLoggedIn()
	 * will return true and we can see this WebDAV request as already authenticated,
	 * even if there are no HTTP Basic Auth headers.
	 * In other case, just fallback to the parent implementation.
	 *
	 * @param \Sabre\DAV\Server $server
	 * @param $realm
	 * @return bool
	 */
	public function authenticate(\Sabre\DAV\Server $server, $realm) {
		if ($this->getCurrentUser()) {
			return true;
		} else {
			return parent::authenticate($server, $realm);
		}
	}
}
