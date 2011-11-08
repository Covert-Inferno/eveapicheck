<?php

header('Content-Type:text/plain;charset=UTF-8');
header('Cache-Control: no-cache');

$action = !empty($_GET['action']) ? $_GET['action'] : '';
$allowedActions = array('load', 'status', 'check');

if (!in_array($action, $allowedActions, TRUE)) {
	header('HTTP/1.1 404 Not found');
	echo 'Requested resource not found';
	exit;
}

header('X-JSON: true');
$result = array();
$result['error'] = false;
$access = false;
$id = 0;

define('ENCRYPT_KEY', '{{key}}');

try {

$db = new PDO('{{dsn}}', '{{user}}', '{{password}}', array(
	PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
	PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
));

$loginData = '';

if (!empty($_POST['login'])) {
	$loginData = $_POST['login'];
	$loginQuery = $db->prepare('SELECT `id` FROM `users` WHERE `login` = SHA1(?)');
	$loginQuery->execute(array($loginData));
	$data = $loginQuery->fetch();
	$loginQuery->fetchAll();
	unset($loginQuery);
	if (!empty($data['id'])) {
		$access = true;
		$id = (int) $data['id'];
		$value = crypter(ENCRYPT_KEY, $loginData);
		setcookie('login', $value, time() + (3600 * 24 * 30));
		unset($value, $time);
	}
	unset($data);
}

if (!empty($_COOKIE['login']) && !$access) {
	$login = crypter(ENCRYPT_KEY, $_COOKIE['login'], TRUE);
	$loginQuery = $db->prepare('SELECT `id` FROM `users` WHERE `login` = SHA1(?)');
	$loginQuery->execute(array($login));
	$data = $loginQuery->fetch();
	$loginQuery->fetchAll();
	unset($loginQuery);

	if (!empty($data['id'])) {
		$access = true;
		$id = (int) $data['id'];
	}
	unset($data, $login);
}
if ($action == 'status') {
	$result['login'] = $access;
}
elseif (!$access || $id < 1) {
	header('HTTP/1.1 403 Forbidden');
	echo 'Empty';
	exit;
}



if ($action === 'load') {
	$loadQuery = $db->prepare('SELECT `keyId`, `vCode`, DATE_FORMAT(`timeChecked`, \'%W, %d. %b %Y %T\') AS `date` FROM `credentials` WHERE `user` = ? ORDER BY `timeChecked` DESC LIMIT 100');
	$loadQuery->execute(array($id));
	$result['recent'] = $loadQuery->fetchAll();
}


if ($action === 'check') {
	if (empty($_POST['keyId']) || empty($_POST['vCode'])) {
		$result['error'] = true;
		$result['msg'] = 'Key ID and / or vCode is missing';
	}
	else {
		$context = stream_context_create(array(
			'http' => array(
				'method' => 'GET',
				'ignore_errors' => TRUE,
				'timeout' => 10
			)
		));
		$url = 'http://api.eveonline.com//account/APIKeyInfo.xml.aspx?'
			 	. http_build_query(array(
			'keyID' => $_POST['keyId'],
			'vCode' => $_POST['vCode']
		));
		$data = file_get_contents($url, NULL, $context);
		unset($url, $context);
		$xml = NULL;

		try {
			$xml = new SimpleXMLElement($data);
			unset($data);
		}
		catch (Exception $e) {
			$result['error'] = true;
			$result['msg'] = $e->getMessage();
		}

		if ($xml instanceof SimpleXMLElement) {
			if (!empty($xml->error)) {
				$result['error'] = true;
				$result['msg'] = (string) $xml->error;
			}
			else {
				$selectQuery = $db->prepare('SELECT COUNT(*) AS `count` FROM `credentials` WHERE `user` = ? AND `keyId` = ? AND `vCode` = ?');
				$selectQuery->execute(array($id, $_POST['keyId'], $_POST['vCode']));
				$data = $selectQuery->fetch();
				if ($data['count'] < 1) {
					$query = $db->prepare('INSERT INTO `credentials` (`user`, `keyId`, `vCode`) VALUES (?, ?, ?)');
					$query->execute(array($id, $_POST['keyId'], $_POST['vCode']));
				}
				else {
					$query = $db->prepare('UPDATE `credentials` SET `keyId` = ?, `vCode` = ? WHERE `user` = ? AND `keyId` = ? AND `vCode` = ?');
					$query->execute(array($_POST['keyId'], $_POST['vCode'], $id, $_POST['keyId'], $_POST['vCode']));
				}
				unset($selectQuery, $data, $query);


				$infos = $xml->result->key->attributes();
				$accessMask = (int) $infos->accessMask;
				$result['type'] = (string) $infos->type;
				$result['expires'] = false;

				if ((string) $infos->expires) {
					$now = new DateTime();
					$expires = new DateTime();
					$expires->setTimestamp(strtotime((string) $infos->expires));
					$diff = $now->diff($expires);
					$result['expires'] = $expires->format('d.m.Y') . ' (' . $diff->format('%m months and %d days') . ' left)';
					unset($now, $expires, $diff);
				}
				unset($infos);
				$result['access'] = array(
					'Wallet Transactions' 	 => ($accessMask & 4194304)  > 0,
					'Wallet Journal' 		 => ($accessMask & 2097152) > 0,
					'Market Orders' 			 => ($accessMask & 4096) > 0,
					'Account Balance' 		 => ($accessMask & 1) > 0,
					'Notification Texts' 	 => ($accessMask & 32768) > 0,
					'Notifications' 		 => ($accessMask & 16384) > 0,
					'Mail Messages' 			 => ($accessMask & 2048) > 0,
					'Mailing Lists' 			 => ($accessMask & 1024) > 0,
					'Mail Bodies' 			 => ($accessMask & 512) > 0,
					'Contact Notifications' 	 => ($accessMask & 32) > 0,
					'Contact List' 			 => ($accessMask & 16) > 0,
					'Contracts' 			 => ($accessMask & 67108864) > 0,
					'Account Status' 		 => ($accessMask & 33554432) > 0,
					'Character Info' 		 => ($accessMask & 16777216) > 0,
					'Upcoming Calendar Events' => ($accessMask & 1048576) > 0,
					'Skill Queue' 			 => ($accessMask & 262144) > 0,
					'Skill In Training' 		 => ($accessMask & 131072) > 0,
					'Character Sheet' 		 => ($accessMask & 8) > 0,
					'Calendar Event Attendees' => ($accessMask & 4) > 0,
					'Asset List' 			 => ($accessMask & 2) > 0,
					'Character Info' 		 => ($accessMask & 8388608) > 0,
					'Standings'				 => ($accessMask & 524288) > 0,
					'Medals' 				 => ($accessMask & 8192) > 0,
					'KillLog' 				 => ($accessMask & 256) > 0,
					'Fac War Stats' 			 => ($accessMask & 64) > 0,
					'Research' 				 => ($accessMask & 65536) > 0,
					'Industry Jobs' 			 => ($accessMask & 128) > 0
				);

				$result['characters'] = array();
				foreach ($xml->result->key->rowset->children() as $node) {
					$char = $node->attributes();
					$result['characters'][] = array(
						'name' => (string) $char->characterName,
						'id' => (int) $char->characterID,
						'corp' => (string) $char->corporationName,
						'corpId' => (int) $char->corporationID
					);
				}
				unset($xml);
			}
		}

	}
}

} catch (Exception $e) {
	$result['error'] = true;
	$result['msg'] = 'An error occured, did you install it?';
}


$result = json_encode($result);
header('Content-Length: ' . strlen($result));
echo $result;





function crypter($key, $str, $decrypt = FALSE) {
	$td = mcrypt_module_open('rijndael-256', '', 'ecb', '');
	$iv = mcrypt_create_iv(mcrypt_enc_get_iv_size($td), MCRYPT_RAND);
	$key = substr($key, 0, mcrypt_enc_get_key_size($td));
	mcrypt_generic_init($td, $key, $iv);

	if(!$decrypt) {
		$result = mcrypt_generic($td, $str);
	}
	else {
		$result = mdecrypt_generic($td, $str);
		$result = str_replace("\0", '', $result);
	}

	mcrypt_generic_deinit($td);
	mcrypt_module_close($td);

	return $result;
}
