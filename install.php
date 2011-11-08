<?php
/*
	Copyright (c) 2011, Georg Großberger (georg@grossberger.at>
	All rights reserved.

	Licensed under the terms of the Modified BSD License
	http://www.opensource.org/licenses/BSD-3-Clause
*/

$error = array();
$success = array();
$dbsuccess = false;
$startupError = false;

$dbcon = NULL;

$dbhost = '';
$dbname = '';
$dbuser = '';
$dbpwd  = '';
$dsn 	= '';
$encryptionKey = '';

if (version_compare(phpversion(), '5.3.0', '<')) {
	$errors[] = "API Check needs PHP 5.3, you have " . phpversion();
}

if (!extension_loaded('mcrypt')) {
	$errors[] = "API Check needs the mcrypt module for security reasons, but it does not seem to be installed";
}

if (!extension_loaded('pdo_mysql')) {
	$errors[] = "API Check needs the PDO extension with support for MySQL";
}

if (!empty($errors)) {
	$startupError = true;
}


class FileDeleter {
	private $file;
	public function __construct() {
		$this->file = __FILE__;
	}
	public function __destruct() {
		unlink($this->file);
	}
}

if (!$startupError) {

	if (empty($_POST['encryptionKey'])) {
		$encryptionKey = substr(sha1(microtime(true) . uniqid('')), 0, 10);
	}
	else {
		$encryptionKey = $_POST['encryptionKey'];
	}

	if (!empty($_POST['dbuser']) && !empty($_POST['dbuser']) && !empty($_POST['dbpwd']) && !empty($_POST['dbname'])) {

		$dsn = 'mysql://host=' . $_POST['dbhost'] . ';dbname=' . $_POST['dbname'];
		try {
			$dbcon = new PDO($dsn, $_POST['dbuser'], $_POST['dbpwd'], array(
				PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
				PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
			));
			$success[] = "Connected to database";

			$dbcon->query('
CREATE TABLE IF NOT EXISTS `users` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `login` varchar(64) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8');

			$dbcon->query('CREATE TABLE IF NOT EXISTS `credentials` (
  `user` int(10) unsigned NOT NULL,
  `keyId` int(10) unsigned NOT NULL,
  `vCode` varchar(70) NOT NULL,
  `timeChecked` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `apiXml` longblob,
  PRIMARY KEY (`keyId`,`vCode`),
  KEY `user` (`user`),
  CONSTRAINT `credentials_ibfk_1` FOREIGN KEY (`user`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8');

			$success[] = "Installed database tables";

			$dbsuccess = TRUE;
			$dbuser = $_POST['dbuser'];
			$dbpwd = $_POST['dbpwd'];
			$dbhost = $_POST['dbhost'];
			$dbname = $_POST['dbname'];

		}
		catch(Exception $e) {
			$error[] = $e->getMessage();
		}
	}


	if ($dbsuccess) {
		if (!empty($_POST['new_login'])) {
			try {
				$insert = $dbcon->prepare('INSERT INTO `users` (`login`) VALUES (SHA1(?))');
				$insert->execute(array($_POST['new_login']));
				unset($delete);
				$success[] = "Created user";
			} catch (Exception $e) {
				$error[] = "Cannot create user. {$e->getMessage()}";
			}
		}
		if (!empty($_POST['del_login'])) {
			try {
				$insert = $dbcon->prepare('DELETE FROM `users` WHERE `login` = SHA1(?)');
				$insert->execute(array($_POST['del_login']));
				unset($delete);
				$success[] = "Deleted user";
			} catch (Exception $e) {
				$error[] = "Cannot delete user. {$e->getMessage()}";
			}
		}
	}

	if (!empty($_POST['proceed'])) {
		if (!empty($errors)) {
			$error[] = "Cannot proceed due to the errors listed above";
		}
		elseif (!$dbsuccess) {
			$error[] = "Cannot proceed without valid database credentials";
		}
		elseif (empty($encryptionKey) || empty($dsn) || empty($dbuser) || empty($dbpwd)) {
			$error[] = "Some necessary values are missing for proceeding";
		}
		else {
			$unlinker = new FileDeleter();
			$file = __DIR__ . DIRECTORY_SEPARATOR . 'request.php';
			$values = array(
				'key' => $encryptionKey,
				'dsn' => $dsn,
				'user' => $dbuser,
				'password' => $dbpwd
			);
			$data = file_get_contents($file);
			foreach ($values as $k => $v) {
				$data = str_replace('{{' . $k . '}}', $v, $data);
			}
			file_put_contents($file, $data, LOCK_EX);
			$url = 'http://' . $_SERVER['SERVER_NAME'] . $_SERVER['PHP_SELF'];
			$url = str_replace('install.php', '', $url);
			header('Location: ' . $url);
			exit;
		}
	}
}

?><!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<meta name="copyright" content="2011 by Georg Großberger (georg@grossberger.at)">
<meta name="license" content="Modified BSD License (http://www.opensource.org/licenses/BSD-3-Clause)">
<!--
	Copyright (c) 2011, Georg Großberger (georg@grossberger.at>
	All rights reserved.

	Licensed under the terms of the Modified BSD License
	http://www.opensource.org/licenses/BSD-3-Clause
-->
<title>API Check</title>
<link rel="stylesheet" type="text/css" href="https://ajax.googleapis.com/ajax/libs/jqueryui/1.8.16/themes/redmond/jquery-ui.css">
<link rel="stylesheet" type="text/css" href="main.css">
<!--[if gte IE 9]>
  <style type="text/css">
    .important, .important:hover, .access-list .no-access, .access-list .access {
       filter: none;
    }
  </style>
<![endif]-->
<style type="text/css">
	.col input {
		margin-top: 10px;
	}
</style>
<script type="text/javascript" src="https://ajax.googleapis.com/ajax/libs/jquery/1.6.4/jquery.min.js"></script>
<script type="text/javascript" src="https://ajax.googleapis.com/ajax/libs/jqueryui/1.8.16/jquery-ui.min.js"></script>
<script>
	$(function() {
		$("#doProceed").click(function() {
			$("#proceedContent").dialog({
				resizable: false,
				height:180,
				modal: true,
				buttons: {
					"Proceed": function() {
						$("#proceed").val("1");
						document.forms[0].submit();
					},
					Cancel: function() {
						$( this ).dialog( "close" );
					}
				}
			});
		});
	});
</script>
</head>
<body>
<div class="outer-wrapper">
	<hgroup class="title">
		<h1>API Check Installation</h1>
	</hgroup>
	<div class="inner-wrapper">
			<form action="install.php" method="post">
			<input type="hidden" name="encryptionKey" value="<?php echo $encryptionKey; ?>">
			<?php
			if (!empty($error) || !empty($success)) {
			?>
		<section>
			<ul class="access-list">
			<?php if (!empty($success)):?><li class="access"><?php echo implode('<br>', $success); ?></li><?php endif; ?>
			<?php if (!empty($error)):?><li class="no-access"><?php echo implode('<br>', $error); ?></li><?php endif; ?>
			</ul>
		</section>
			<?php
			}
			?>
		<section>
			<h2>Important</h2>
			<p>When everything has been set up, use the proceed button at the bottom of this page to save the configuration.<br><strong>Once the data is saved, this install script will be deleted for security reasons.</strong></p>
		</section>
		<section id="login">
			<h1>Please enter your database credentails</h1>
			<div class="col">
				<input type="text" name="dbhost" placeholder="Host" value="<?php echo $dbhost; ?>">
				<input type="text" name="dbuser" placeholder="User" value="<?php echo $dbuser; ?>">
				<input type="text" name="dbpwd" placeholder="Password" value="<?php echo $dbpwd; ?>">
				<input type="text" name="dbname" placeholder="Database" value="<?php echo $dbname; ?>">
				</div>
			<div class="col">
				<input type="submit" value="Check">
				</div>
		</section>
		<?php if ($dbsuccess): ?>
		<section id="new">
			<h1>Enter a new Login</h1>
			<fieldset>
				<input type="text" name="new_login" placeholder="Login ID">
				<input type="submit" value="Create">
			</fieldset>
		</section>
		<section id="new">
			<h1>Enter a Login you want to delete</h1>
			<fieldset>
				<input type="text" name="del_login" placeholder="Login ID">
				<input type="submit" value="Delete">
			</fieldset>
		</section>
		<section>
		<h1>Save settings and quit the install</h1>
			<input type="button" id="doProceed" value="Save and proceed">
			<input type="hidden" name="proceed" id="proceed" value="">
		</section>
		<?php endif; ?>
			</form>
			<div style="visibility:hidden;">
<div id="proceedContent" title="Proceed">
	<p><span class="ui-icon ui-icon-alert" style="float:left; margin:0 7px 20px 0;"></span>These values will be saved and this script will be delete. Are you sure to proceed?</p>
</div></div>
	</div>
</div>

</body>
</html>