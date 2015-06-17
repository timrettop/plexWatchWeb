<?php
ini_set('auto_detect_line_endings', true);
define('PWW_MAJOR_VERSION', 1);
define('PWW_MINOR_VERSION', 7);
define('PWW_RELEASE_VERSION', 0);
define('PWW_DEVELOPMENT', true);

class Setting {
	public $modTime;
	public $value;
	public $filter;
	public $filterOpts;

	public function __construct($filter = NULL, $opts = NULL) {
		if ($filter) {
			$this->filter = $filter;
		}
		if ($opts) {
			$this->filterOpts = $opts;
		} else {
			$this->filterOpts = array();
		}
	}

	public function set($value) {
		if (is_array($value)) {
			$this->value = $value['value'];
			$this->modTime = $value['mod'];
		} else {
			if ($this->filter) {
				$this->value = filter_var($value, $this->filter, $this->filterOpts);
			} else {
				$this->value = $value;
			}
			$this->modTime = time();
		}
	}

	public function getData() {
		return array('value'=>$this->value, 'mod'=>$this->modTime);
	}
}

/**
* Suppress PHPMD warnings about the complexity of this class
* @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
*/
class ConfigClass {
	private $path;
	private $plexWatchDb;
	private $dateFormat;
	private $timeFormat;
	private $pmsIp;
	private $pmsPort;
	private $plexUser;
	private $plexPass;
	private $plexAuthToken;
	private $pmsUrl;
	private $globalGrouping;
	private $userGrouping;
	private $chartsGrouping;
	private $newerSettings;

	public function __construct($path = NULL) {
		// Set up the settings
		$this->plexWatchDb = new Setting(FILTER_SANITIZE_STRING);
		$this->dateFormat = new Setting(FILTER_SANITIZE_STRING,
			array('flags'=>FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH |
				FILTER_FLAG_ENCODE_LOW | FILTER_FLAG_ENCODE_HIGH | FILTER_FLAG_ENCODE_AMP
		));
		$this->timeFormat = new Setting(FILTER_SANITIZE_STRING,
			array('flags'=>FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH |
				FILTER_FLAG_ENCODE_LOW | FILTER_FLAG_ENCODE_HIGH | FILTER_FLAG_ENCODE_AMP
		));
		$this->pmsIp = new Setting(FILTER_SANITIZE_STRING,
			array('flags'=>FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH |
				FILTER_FLAG_ENCODE_LOW | FILTER_FLAG_ENCODE_HIGH | FILTER_FLAG_ENCODE_AMP
		));
		$this->pmsPort = new Setting(FILTER_VALIDATE_INT,
			array('min_range'=>1, 'max_range'=>65535)
		);
		$this->plexUser = new Setting(FILTER_UNSAFE_RAW);
		$this->plexPass = new Setting(FILTER_UNSAFE_RAW);
		$this->plexAuthToken = new Setting();
		$this->pmsUrl = new Setting();
		$this->globalGrouping = new Setting(FILTER_VALIDATE_BOOLEAN,
			array('flags'=>FILTER_NULL_ON_FAILURE));
		$this->userGrouping = new Setting(FILTER_VALIDATE_BOOLEAN,
			array('flags'=>FILTER_NULL_ON_FAILURE));
		$this->chartsGrouping = new Setting(FILTER_VALIDATE_BOOLEAN,
			array('flags'=>FILTER_NULL_ON_FAILURE));

		// Set the flag for whether we are processing a settings file newer than us
		$this->newerSettings = false;

		// If a path was specified, attempt to read it in
		if (!empty($path)) {
			$this->path = $path;
			$this->readFromFile();
		}
	}

	public function save() {
		$this->setFromPOST();
		$this->writeToFile();
	}

	public function getPlexWatchDb() {
		return $this->plexWatchDb->value;
	}

	public function getDateFormat() {
		return $this->dateFormat->value;
	}

	public function getTimeFormat() {
		return $this->timeFormat->value;
	}

	public function getPmsIp() {
		return $this->pmsIp->value;
	}

	public function getPmsPort() {
		return $this->pmsPort->value;
	}

	public function getPlexUser() {
		return $this->plexUser->value;
	}

	public function getPlexPass() {
		return base64_decode($this->plexPass->value);
	}

	public function getPlexAuthToken() {
		return $this->plexAuthToken->value;
	}

	public function getPmsUrl() {
		return $this->pmsUrl->value;
	}

	public function getGlobalGrouping() {
		return $this->globalGrouping->value;
	}

	public function getUserGrouping() {
		return $this->userGrouping->value;
	}

	public function getChartsGrouping() {
		return $this->chartsGrouping->value;
	}

	// ************** Private Functions *****************
	private function writeToFile() {
		if ($this->newerSettings) {
			// Never overwrite a settings file that is newer than us
			return;
		}
		$data = array(
			'plexWatchDb'=>$this->plexWatchDb->getData(),
			'dateFormat'=>$this->dateFormat->getData(),
			'timeFormat'=>$this->timeFormat->getData(),
			'pmsIp'=>$this->pmsIp->getData(),
			'pmsPort'=>$this->pmsPort->getData(),
			'plexUser'=>$this->plexUser->getData(),
			'plexPass'=>$this->plexPass->getData(),
			'plexAuthToken'=>$this->plexAuthToken->getData(),
			'globalGrouping'=>$this->globalGrouping->getData(),
			'userGrouping'=>$this->userGrouping->getData(),
			'chartsGrouping'=>$this->chartsGrouping->getData(),
			'majorVersion'=>PWW_MAJOR_VERSION,
			'minorVersion'=>PWW_MINOR_VERSION,
			'releaseVersion'=>PWW_RELEASE_VERSION
		);
		$json_opts = JSON_NUMERIC_CHECK;
		if (defined(JSON_PRETTY_PRINT)) {
			// Pretty print the config file if we are operating under PHP >= 5.4.0.
			$json_opts = $json_opts | JSON_PRETTY_PRINT;
		}
		$json_data = json_encode($data, $json_opts);
		if ($json_data === false) {
			$error_msg = 'Error converting settings to JSON: ' . json_last_error_msg();
			sendError($error_msg);
		}
		if (isOpenable($this->path)) {
			$file = file_put_contents($this->path, $json_data);
		} else {
			$file = false;
		}
		if ($file === false) {
			$error_msg = 'Failed to write the configuration to disk.';
			sendError($error_msg);
		}
	}

	private function readFromFile() {
		if (!file_exists($this->path)) {
			$error_msg = 'Attempted to read non-existent settings!';
			sendError($error_msg);
		}
		$config = file_get_contents($this->path);
		if ($config === false) {
			$error_msg = 'ConfigClass :: Error reading config file.';
			sendError($error_msg);
		}
		// Attempt to read the settings into an associative array
		$data = json_decode($config, true);
		if ($data === NULL) {
			// Original setting file, or broken
			readOldSettings($config);
			return;
		}
		$fileVersion = '0.0.0';
		if (array_key_exists('majorVersion', $data) &&
			array_key_exists('minorVersion', $data) &&
			array_key_exists('releaseVersion', $data)) {
			$fileVersion = $this->getVersionString($data['majorVersion'],
				$data['minorVersion'], $data['releaseVersion']);
		}
		$currentVersion = $this->getVersionString();
		$versionCompare = version_compare($fileVersion, $currentVersion);
		if ($versionCompare > 0) {
			// Newer settings file, disable overwriting
			$this->newerSettings = true;
		} else if ($versionCompare < 0) {
			// Settings older than our current version
			$this->readOldSettings($config, $data['majorVersion'],
				$data['minorVersion'], $data['releaseVersion']);
			return;
		}
		$this->setSettings($data);
	}

	/**
	* Suppress PHPMD warnings about the complexity of this function
	* @SuppressWarnings(PHPMD.CyclomaticComplexity)
	* @SuppressWarnings(PHPMD.NPathComplexity)
	*/
	private function setSettings($data) {
		if (array_key_exists('plexWatchDb', $data)) {
			$this->setPlexWatchDb($data['plexWatchDb']);
		}
		if (array_key_exists('dateFormat', $data)) {
			$this->setDateFormat($data['dateFormat']);
		}
		if (array_key_exists('timeFormat', $data)) {
			$this->setTimeFormat($data['timeFormat']);
		}
		if (array_key_exists('pmsIp', $data)) {
			$this->setPmsIP($data['pmsIp']);
		}
		if (array_key_exists('pmsPort', $data)) {
			$this->setPmsPort($data['pmsPort']);
		}
		if (array_key_exists('plexUser', $data)) {
			$this->setPlexUser($data['plexUser']);
		}
		if (array_key_exists('plexPass', $data)) {
			$this->setPlexPass($data['plexPass']);
		}
		if (array_key_exists('plexAuthToken', $data)) {
			$this->setAuthToken($data['plexAuthToken']);
		}
		if (array_key_exists('globalGrouping', $data)) {
			$this->setGlobalGrouping($data['globalGrouping']);
		}
		if (array_key_exists('userGrouping', $data)) {
			$this->setUserGrouping($data['userGrouping']);
		}
		if (array_key_exists('chartsGrouping', $data)) {
			$this->setChartsGrouping($data['chartsGrouping']);
		}
		// Generate a PMS URL, validating the other related settings
		$this->setPmsUrl();
	}

	/**
	 * Suppress warnings about unused variables, remove when we have functions
	 * for specific versions in the future.
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
	*/
	private function readOldSettings($config, $maj = NULL, $min = NULL, $rel = NULL) {
		if (empty($maj)) {
			// Parsing an original settings file
			$this->readOrigSettings($config);
		}
	}

	/**
	* Suppress PHPMD warnings about the complexity of this function
	* @SuppressWarnings(PHPMD.CyclomaticComplexity)
	* @SuppressWarnings(PHPMD.NPathComplexity)
	*/
	private function readOrigSettings($config) {
		$origData = array();
		foreach (preg_split("/((\r?\n)|(\r\n?))/", $config) as $line) {
			if (substr($line, 0, 1) === '$') {
				$config_line = explode(" = ", $line);
				preg_match("/\[\'([^\]]*)\'\]/", $config_line[0], $matches);
				preg_match("/\'(.*)\'/", $config_line[1], $matches2);
				$origData[$matches[1]] = $matches2[1];
			}
		}
		$data = array(
			'plexWatchDb'=>(array_key_exists('plexWatchDb', $origData) ?
				$origData['plexWatchDb'] : null),
			'dateFormat'=>(array_key_exists('dateFormat', $origData) ?
				$origData['dateFormat'] : null),
			'timeFormat'=>(array_key_exists('timeFormat', $origData) ?
				$origData['timeFormat'] : null),
			'pmsIp'=>(array_key_exists('pmsIp', $origData) ?
				$origData['pmsIp'] : null),
			'pmsPort'=>(array_key_exists('pmsHttpPort', $origData) ?
				$origData['pmsHttpPort'] : null),
			'plexUser'=>(array_key_exists('myPlexUser', $origData) ?
				$origData['myPlexUser'] : null),
			'plexPass'=>(array_key_exists('myPlexPass', $origData) ?
				base64_decode($origData['myPlexPass']) : null),
			'plexAuthToken'=>(array_key_exists('myPlexAuthToken', $origData) ?
				$origData['myPlexAuthToken'] : null),
			'globalGrouping'=>(array_key_exists('globalHistoryGrouping', $origData) ?
				$origData['globalHistoryGrouping'] : null),
			'userGrouping'=>(array_key_exists('userHistoryGrouping', $origData) ?
				$origData['userHistoryGrouping'] : null),
			'chartsGrouping'=>(array_key_exists('chartsHistoryGrouping', $origData) ?
				$origData['chartsHistoryGrouping'] : null)
		);
		$this->setSettings($data);
	}

	/**
	* Suppress PHPMD warnings about the complexity of this function
	* @SuppressWarnings(PHPMD.CyclomaticComplexity)
	* @SuppressWarnings(PHPMD.NPathComplexity)
	*/
	private function setFromPOST() {
		// Validation is handled in the set functions
		$plexWatchDb = array_key_exists('plexWatchDb', $_POST) ?
			$_POST['plexWatchDb'] : null;
		$dateFormat = array_key_exists('dateFormat', $_POST) ?
			$_POST['dateFormat'] : null;
		$timeFormat = array_key_exists('timeFormat', $_POST) ?
			$_POST['timeFormat'] : null;
		$pmsIp = array_key_exists('pmsIp', $_POST) ?
			$_POST['pmsIp'] : null;
		$pmsPort = array_key_exists('pmsPort', $_POST) ?
			$_POST['pmsPort'] : null;
		$plexUser = array_key_exists('plexUser', $_POST) ?
			$_POST['plexUser'] : null;
		$plexPass = array_key_exists('plexPass', $_POST) ?
			$_POST['plexPass'] : null;
		$globalGrouping = array_key_exists('globalGrouping', $_POST) ?
			$_POST['globalGrouping'] : false;
		$userGrouping = array_key_exists('userGrouping', $_POST) ?
			$_POST['userGrouping'] : false;
		$chartsGrouping = array_key_exists('chartsGrouping', $_POST) ?
			$_POST['chartsGrouping'] : false;

		$this->setPlexWatchDb($plexWatchDb);
		$this->setDateFormat($dateFormat);
		$this->setTimeFormat($timeFormat);
		$this->setPmsIP($pmsIp);
		$this->setPmsPort($pmsPort);
		if (($plexUser != '') && ($plexPass != '')) {
			$this->plexAuthToken->set('');
			$this->setPlexUser($plexUser);
			$this->setPlexPass($plexPass);
		} else {
			if (!($this->getPlexUser()) || !($this->getPlexPass())) {
				trigger_error('User or pass completely missing.');
			}
		}
		$this->setAuthToken();
		$this->setPmsUrl();
		$this->setGlobalGrouping($globalGrouping);
		$this->setUserGrouping($userGrouping);
		$this->setChartsGrouping($chartsGrouping);
		$this->path = dirname(__FILE__) . '/../config/config.php';
	}

	// *********** Setter functions ***********
	private function setPlexWatchDb($path) {
		if (is_array($path)) {
			$this->plexWatchDb->set($path);
			return;
		}
		if ($this->plexWatchDb->value === $path) {
			return;
		}
		if (!isOpenable($path)) {
			sendError('Database path is not able to be opened');
		}
		try {
			$database = new PDO('sqlite:' . $path);
			$database = null;
		} catch (PDOException $e) {
			sendError('Database is not valid: ' . $e->getMessage());
		}
		$this->plexWatchDb->set($path);
	}

	private function setDateFormat($format) {
		if (is_array($format)) {
			$this->dateFormat->set($format);
			return;
		}
		if ($this->dateFormat->value === $format) {
			return;
		}
		$this->dateFormat->set($format);
		// FIXME: Validate format?
	}

	private function setTimeFormat($format) {
		if (is_array($format)) {
			$this->timeFormat->set($format);
			return;
		}
		if ($this->timeFormat->value === $format) {
			return;
		}
		// Check if the date format is still using the old PHP formats
		if (strpos($format, 'g') !== false ||
			strpos($format, 'G') !== false) {
			sendError('Invalid time format');
		}
		$this->timeFormat->set($format);
		// FIXME: Validate format?
	}

	private function setPmsIP($ipAddr) {
		if (is_array($ipAddr)) {
			$this->pmsIp->set($ipAddr);
			return;
		}
		if ($this->pmsIp->value === $ipAddr) {
			return;
		}
		$this->pmsIp->set($ipAddr);
		// FIXME: Validate as valid hostname/IP.
	}

	private function setPmsPort($port) {
		if (is_array($port)) {
			$this->pmsPort->set($port);
			return;
		}
		if ($this->pmsPort->value === $port) {
			return;
		}
		$this->pmsPort->set($port);
	}

	private function setPlexUser($user) {
		if (is_array($user)) {
			$this->plexUser->set($user);
			return;
		}
		if ($this->plexUser->value === $user) {
			return;
		}
		$this->plexUser->set($user);
	}

	private function setPlexPass($pass) {
		if (is_array($pass)) {
			$this->plexPass->set($pass);
			return;
		}
		if ($this->plexPass->value === $pass) {
			return;
		}
		$this->plexPass->set(base64_encode($pass));
	}

	private function setGlobalGrouping($enabled) {
		if (is_array($enabled)) {
			$this->globalGrouping->set($enabled);
			return;
		}
		if ($this->globalGrouping->value === $enabled) {
			return;
		}
		$this->globalGrouping->set($enabled);
	}

	private function setUserGrouping($enabled) {
		if (is_array($enabled)) {
			$this->userGrouping->set($enabled);
			return;
		}
		if ($this->userGrouping->value === $enabled) {
			return;
		}
		$this->userGrouping->set($enabled);
	}

	private function setChartsGrouping($enabled) {
		if (is_array($enabled)) {
			$this->chartsGrouping->set($enabled);
			return;
		}
		if ($this->chartsGrouping->value === $enabled) {
			return;
		}
		$this->chartsGrouping->set($enabled);
	}

	// Authenitcation Token Functions
	private function setAuthToken($token = NULL) {
		if (is_array($token)) {
			$this->plexAuthToken->set($token);
			return;
		}
		if ($this->plexAuthToken->value === $token) {
			return;
		}
		if (!empty($token) && $this->checkAuthToken($token)) {
			$this->plexAuthToken->set($token);
			return;
		}
		if (empty($this->plexAuthToken->value) || !$this->checkAuthToken()) {
			$this->plexAuthToken->set($this->getNewAuthToken());
			return;
		}
	}

	private function checkAuthToken($token = NULL) {
		$currentToken = $this->plexAuthToken->getData();
		if (!empty($token)) {
			$this->plexAuthToken->set($token);
		}
		$valid = $this->setPmsUrl(true);
		if (!$valid) {
			$this->plexAuthToken->set($currentToken);
		}
		return $valid;
	}

	private function getNewAuthToken() {
		$plexAuthToken = '';
		$user = $this->getPlexUser();
		$pass = $this->getPlexPass();
		if (empty($user) || empty($pass)) {
			return $plexAuthToken;
		}
		$host = 'https://plex.tv/users/sign_in.xml';
		$process = curl_init($host);
		curl_setopt($process, CURLOPT_HTTPHEADER, array(
			'Content-Type: application/xml; charset=utf-8',
			'Content-Length: 0',
			'X-Plex-Device-Name: plexWatch/Web',
			'X-Plex-Product: plexWatch/Web',
			'X-Plex-Version: v' . $this->getVersionString(),
			'X-Plex-Client-Identifier: ' . uniqid('plexWatchWeb', true)
		));
		curl_setopt($process, CURLOPT_HEADER, false);
		curl_setopt($process, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
		curl_setopt($process, CURLOPT_USERPWD,
			$user . ':' . $pass);
		curl_setopt($process, CURLOPT_TIMEOUT, 30);
		curl_setopt($process, CURLOPT_HTTPGET, true);
		curl_setopt($process, CURLOPT_POST, true);
		curl_setopt($process, CURLOPT_RETURNTRANSFER, true);
		$data = curl_exec($process);
		$authCode = curl_getinfo($process, CURLINFO_HTTP_CODE);
		$curlError = curl_error($process);
		curl_close($process);
		if ($authCode == 401) {
			// Authentication failure
			$error_msg = 'Plex.tv authentication failed. Check your Plex.tv ' .
				'username and password.';
			sendError($error_msg);
		} else if ($curlError != 0) {
			// cURL error
			$error_msg = 'cURL error while retrieving data from plex.tv: ' . $curlError;
			sendError($error_msg);
		} else {
			$xml = simplexml_load_string($data);
			if ($xml === false) {
				$errorCode = 'Error: Could not parse Plex.tv XML to retrieve ' .
					'authentication code.';
				sendError($error_msg);
			}
			$plexAuthToken = (string) $xml['authenticationToken'][0];
			if (empty($plexAuthToken)) {
				$errorCode = 'Error: Could not find authentication code in the Plex.tv ' .
					'response.';
				sendError($error_msg);
			}
		}
		return $plexAuthToken;
	}

	// Utility Functions
	public function getVersionString($maj = NULL, $min = NULL, $rel = NULL) {
		if (empty($maj) && empty($min) && empty($rel)) {
			return PWW_MAJOR_VERSION . '.' . PWW_MINOR_VERSION . '.' . PWW_RELEASE_VERSION;
		} else {
			return $maj . '.' . $min . '.' . $rel;
		}
	}

	private function setPmsUrl($checking = false) {
		$prefixList = array('https://', 'http://');
		foreach ($prefixList as $prefix) {
			$pmsUrl = $prefix . $this->pmsIp->value . ':' . $this->pmsPort->value;
			if ($this->verifyPmsUrl($pmsUrl)) {
				if (!$checking) {
					$this->pmsUrl->set($pmsUrl);
				}
				return true;
			} else {
				continue;
			}
		}
		if (empty($this->pmsUrl->value) && !$checking) {
			$error_msg = 'Error: Unable to determine a valid URL for the PMS server.';
			sendError($error_msg);
		}
		return false;
	}

	private function verifyPmsUrl($pmsUrl) {
		if (empty($pmsUrl)) {
			return false;
		}
		if (!empty($this->plexAuthToken->value)) {
			$myPlexAuthToken = '?X-Plex-Token='.$this->plexAuthToken->value;
		} else {
			$myPlexAuthToken = '';
		}
		$curlHandle = curl_init($pmsUrl . '/' . $myPlexAuthToken);
		curl_setopt($curlHandle, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curlHandle, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($curlHandle, CURLOPT_SSL_VERIFYHOST, false);
		$data = curl_exec($curlHandle);
		if ($data === false || curl_getinfo($curlHandle, CURLINFO_HTTP_CODE) >= 400) {
			curl_close($curlHandle);
			return false;
		}
		curl_close($curlHandle);
		$xml = simplexml_load_string($data);
		if ($xml === false) {
			$error_msg = 'Error: Could not parse XML from PMS server.';
			sendError($error_msg);
		}
		$machineId = $xml['machineIdentifier'];
		if (empty($machineId) || strlen($machineId) < 1) {
			$error_msg = 'Error: Could not parse Plex.tv XML to retrieve ' .
				'authentication code.';
			sendError($error_msg);
		}
		return true;
	}
}

// Get the base path of the current site
function getBase($previous = NULL) {
	if ($previous) {
		$current = dirname($previous);
		if ($current == '/' || $current == '\\') {
			// If the site is already at the base $previous will also be /
			return $previous;
		} else {
			return getBase($current);
		}
	} else {
		return getBase($_SERVER['REQUEST_URI']);
	}
}

// Redirect the user to the settings page with an error message
function sendError($error_msg) {
	// FIXME: Redirect properly if on settings page
	header('Location: ' . getBase() . '/settings.php?e=' . urlencode($error_msg));
	trigger_error($error_msg, E_USER_ERROR);
}


// Attempts to determine whether a path is readable
function isOpenable($path) {
	$basedir = ini_get('open_basedir');
	if ($basedir == '') {
		return true;
	} else {
		$info = pathinfo($path, PATHINFO_DIRNAME);
		$bd_paths = explode(PATH_SEPARATOR, $basedir);
		foreach ($bd_paths as $bd_path) {
			if (strstr($info, $bd_path) !== false) {
				if (realpath($path) !== false) {
					return true;
				} else {
					return false;
				}
			}
		}
	}
	return false;
}
?>