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

// Create the SSH connection.
$connection = ssh2_connect('sftp.example.com', 22);

// This example uses built-in HTTP BASIC authentication to
// get a username and password, and then passes that onto the
// SSH authentication process.
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

    // Create the SFTP connection from the successful SSH authentication.
    $sftp = ssh2_sftp($connection);

    // Create the base SFTPDirectory object.  Note that if you're starting at
    // "/", you'll need the "/." work-around - else you'll get errors trying to
    // get the contents of the root directory.  This includes situations where
    // your are jailing SFTP users into their own home directories.
    $dir =  new SFTPDirectory('/.', $sftp);

    // Create the WebDAV server.
    $server = new DAV\Server($dir);

    // Sets the Base URI of the WebDAV instance. Adjust based on where you're installing things.
    $server->setBaseUri('/sftp/example.php/');

    // Initializes the locking backend.
    $lockBackend = new DAV\Locks\Backend\File('/tmp/dav.locks');
    $server->addPlugin(new DAV\Locks\Plugin($lockBackend));

    // Provides a basic web-based DAV client so that browsers can see the contents.
    $server->addPlugin(new DAV\Browser\Plugin());

    // Go run it.
    $server->exec();
}

?>
