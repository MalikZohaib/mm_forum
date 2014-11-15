<?php

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Saltedpasswords\Utility\SaltedPasswordsUtility;

class tx_mmforum_FeUser extends tx_mmforum_data {

	var $table = 'fe_users';

	/**
	 * @param $uid
	 * @param $pid
	 * @return null|tx_mmforum_FeUser
	 */
	static function getByUID($uid, $pid = -1) {
		$user = GeneralUtility::makeInstance('tx_mmforum_FeUser');
		/* @var $user tx_mmforum_FeUser */
		$user->setUid($uid);
		$user->loadFromDB($pid);

		return $user->isNull() ? null : $user;
	}

	/**
	 * @param $username
	 * @param $pid
	 * @return null|tx_mmforum_FeUser
	 */
	static function getByUsername($username, $pid = -1) {
		$andWhere = '';
		if ($pid + 1) {
			$andWhere = ' AND pid=' . $pid;
		}

		$username = $GLOBALS['TYPO3_DB']->fullQuoteStr($username, 'fe_users');

		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', 'fe_users', 'username=' . $username . ' AND deleted=0 ' . $andWhere);

		if ($GLOBALS['TYPO3_DB']->sql_num_rows($res) > 0) {
			$user = GeneralUtility::makeInstance('tx_mmforum_FeUser');
			/* @var $user tx_mmforum_FeUser */
			$user->initFromArray($GLOBALS['TYPO3_DB']->sql_fetch_assoc($res));
			return $user;
		} else {
			return null;
		}
	}

	/*
	 * GETTER FUNCTIONS
	 */
	function getPostCount() {
		return intval($this->gD('tx_mmforum_posts'));
	}

	function getCity() {
		return $this->gD('city');
	}

	function getUsername() {
		return $this->gD('username');
	}

	function getPmNotifyMode() {
		return $this->gD('tx_mmforum_pmnotifymode');
	}

	/**
	 *
	 * @param integer $mode
	 * @return boolean
	 */
	function pmNotifyModeIs($mode) {
		return ($this->getPmNotifyMode() == $mode);
	}

	function checkPassword($password) {
		if ($this->loaded !== true) {
			$this->loadFromDB();
		}

		$saltedSv = null;
		if ( ExtensionManagementUtility::isLoaded('t3sec_saltedpw')) {
			require_once( ExtensionManagementUtility::extPath('t3sec_saltedpw', 'sv1/class.tx_t3secsaltedpw_sv1.php'));
			if (tx_t3secsaltedpw_div::isUsageEnabled()) {
				$saltedSv = GeneralUtility::makeInstance('tx_t3secsaltedpw_sv1');
			}
		}
		if (!$saltedSv && ExtensionManagementUtility::isLoaded('saltedpasswords')) {
			if ( SaltedPasswordsUtility::isUsageEnabled()) {
				$saltedSv = GeneralUtility::makeInstance('tx_saltedpasswords_sv1');
			}
		}
		if ($saltedSv) {
			$saltedSv->init();
			if (!$saltedSv->compareUident($this->data, array('uident_text' => $password))) {
				return false;
			}
		} else {
			if ( ExtensionManagementUtility::isLoaded('kb_md5fepw')) {
				$password = md5($password);
			}

			if ($password <> $this->data['password']) {
				if (md5($password) <> $this->data['tx_mmforum_md5']) {
					return false;
				}
			}
		}

		return true;
	}

	function setPassword($password) {
		$this->data['password'] = $password;
		$this->data['tx_mmforum_md5'] = md5($password);

		$objPHPass = null;
		if ( ExtensionManagementUtility::isLoaded('t3sec_saltedpw')) {
			require_once( ExtensionManagementUtility::extPath('t3sec_saltedpw') . 'res/staticlib/class.tx_t3secsaltedpw_div.php');
			if (tx_t3secsaltedpw_div::isUsageEnabled()) {
				require_once( ExtensionManagementUtility::extPath('t3sec_saltedpw') . 'res/lib/class.tx_t3secsaltedpw_phpass.php');
				$objPHPass = GeneralUtility::makeInstance('tx_t3secsaltedpw_phpass');
			}
		}
		if (!$objPHPass && ExtensionManagementUtility::isLoaded('saltedpasswords')) {
			if ( SaltedPasswordsUtility::isUsageEnabled()) {
				$objPHPass = GeneralUtility::makeInstance( SaltedPasswordsUtility::getDefaultSaltingHashingMethod());
			}
		}

		if ($objPHPass) {
			$this->data['password'] = $objPHPass->getHashedPassword($password);
		} else {
			if ( ExtensionManagementUtility::isLoaded('kb_md5fepw')) { //if kb_md5fepw is installed, crypt password
				$this->data['password'] = md5($password);
			}
		}
	}

	function getAvatar($avatarPath) {
		if ($this->data['tx_mmforum_avatar']) {
			return $avatarPath . $this->data['tx_mmforum_avatar'];
		} else {
			if ($this->data['image']) {
				if (strstr($this->data['image'], ',') !== false) {
					list($image_field) = GeneralUtility::trimExplode(',', $this->data['image']);
				} else {
					$image_field = $this->data['image'];
				}

				if (file_exists('uploads/pics/' . $image_field)) {
					return 'uploads/pics/' . $image_field;
				} else {
					if (file_exists('uploads/tx_srfeuserregister/' . $image_field)) {
						return 'uploads/tx_srfeuserregister/' . $image_field;
					}
				}
			}
		}

		return '';
	}

	/**
	 * Returns true, if user has avatar
	 *
	 * @return boolean
	 */
	function hasAvatar() {
		return strlen($this->getAvatar('')) > 0;
	}

	function setAvatar($file) {
		$this->data['tx_mmforum_avatar'] = $file;
	}

	function removeAvatar($avatarPath, $keepFile = false) {
		$avatar = $this->data['tx_mmforum_avatar'];
		$image = $this->data['image'];

		if ($avatar || $image) {

			// Delete avatar file
			if ($avatar && !$keepFile) {
				$absPath = GeneralUtility::getFileAbsFileName($avatarPath . $avatar);
				if (@file_exists($absPath)) {
					@unlink($absPath);
				}
			}

			// Delete user image file
			if ($image && !$keepFile) {
				$absPath = GeneralUtility::getFileAbsFileName('uploads/pics/' . $image);
				if (@file_exists($absPath)) {
					@unlink($absPath);
				} else {
					$absPath = GeneralUtility::getFileAbsFileName('uploads/tx_srfeuserregister/' . $image);
					if (@file_exists($absPath)) {
						@unlink($absPath);
					}
				}
			}

			// Remove avatar from user record
			$this->data['tx_mmforum_avatar'] = '';
			$this->data['image'] = '';
		}
	}
}
