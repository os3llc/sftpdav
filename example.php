<?php
/*

Copyright 2017 OS3 Consulting LLC

Licensed under the Apache License, Version 2.0 (the "License");
you may not use this file except in compliance with the License.
You may obtain a copy of the License at

    http://www.apache.org/licenses/LICENSE-2.0

Unless required by applicable law or agreed to in writing, software
distributed under the License is distributed on an "AS IS" BASIS,
WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
See the License for the specific language governing permissions and
limitations under the License.

*/

use Sabre\DAV;

require 'vendor/autoload.php';
require_once 'include/sftpdav.php';

$connection = ssh2_connect('sftp.example.com', 22);

if (!isset($_SERVER['PHP_AUTH_USER'])) {
	header('WWW-Authenticate: Basic realm="SFTP Server"');
	header('HTTP/1.0 401 Unauthorized');
	echo 'Please log in with your SFTP Server Credentials.';
	exit;
}
else {
	if (!ssh2_auth_password($connection, $_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW'])) {
		header('WWW-Authenticate: Basic realm="SFTP Server"');
		header('HTTP/1.0 401 Unauthorized');
		echo 'SFTP Server login failed, please try, again.';
		exit;
	}

	$sftp = ssh2_sftp($connection);

	$dir =  new SFTPDirectory('/.', $sftp);

	$server = new DAV\Server($dir);

	$server->setBaseUri('/sftp/index.php/');

	$lockBackend = new DAV\Locks\Backend\File('/tmp/dav.locks');
	$server->addPlugin(new DAV\Locks\Plugin($lockBackend));
	$server->addPlugin(new DAV\Browser\Plugin());

	$server->exec();
}

?>
