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

// Class that extends the DAV\Collection class to provide a SFTP-
// specific implementation of the DAV Collection.
class SFTPDirectory extends DAV\Collection {

    // The path for this SFTPDirectory object.
    private $myPath;

    // The PECL SSH2 SFTP connection object for this
    // SFTPDirectory object.
    private $mySSH;

    // Constructor for the class, taking in a path (directory)
    // and a PECL SSH2 SFTP connection object.
    function __construct($myPath, $mySSH) {
        $this->myPath = $myPath;
        $this->mySSH = $mySSH;
    }

    // Returns an array of child objects of the current directory
    // recursing into each child and generating the appropriate
    // object type for each.
    function getChildren() {
        $fdPath = 'ssh2.sftp://' . intval($this->mySSH) . $this->myPath;
        $children = array();

        $contents = scandir($fdPath);
        if ($contents === false)
            throw new DAV\Exception\NotFound('Access denied.');
        foreach ($contents as $node) {
            // Skip hidden files and directory markers
            if ($node[0] === '.')
                continue;
            $children[] = $this->getChild($node);
        }

        return $children;

    }

    // Get the named child and return the appropriate object
    // type for the entry, either a SFTPDirectory or SFTPFile.
    function getChild($name) {
        $path = $this->myPath . '/' . $name;
        $fd_path = 'ssh2.sftp://' . intval($this->mySSH) . $path;

        // If the file does not exist, throw an exception.
        if (!file_exists($fd_path))
            throw new DAV\Exception\NotFound('The file ' . $name . ' is not present on the SFTP server, ' . $fd_path);

        // Do not provide access to hidden files and directory markers.
        if ($name[0] == '.')
            throw new DAV\Exception\NotFound('Access denied.');

        // If the entry is a directory, return a new SFTPDirectory object.
        if (is_dir($fd_path))
            return new SFTPDirectory($path, $this->mySSH);

        // Otherwise return a SFTPFile object.
        else
            return new SFTPFile($path, $this->mySSH);

    }

    // Checks to see if the named object exists.
    function childExists($name) {

        if (!ssh2_sftp_stat($this->mySSH, $this->myPath . "/" . $name))
            return false;

        return true;

    }

    // Get the base name of the current object.
    function getName() {

        return basename($this->myPath);

    }

    // Delete this object.
    function delete() {
        return ssh2_sftp_rmdir($this->mySSH, $this->myPath);
    }

    // Set the name of this object.
    function setName($newName) {
        return ssh2_sftp_rename($this->mySSH, $this->myPath, dirname($this->myPath) . '/' . $newName);
    }

    // Create a new directory under this object, and return the
    // SFTPDirectory object to that new directory.
    function createDirectory($name) {
        $newPath = $this->myPath . '/' . $name;
        if(ssh2_sftp_mkdir($this->mySSH, $newPath))
            return new SFTPDirectory($newPath);
        return false;
    }

    // Create a new file under this object, and return the
    // SFTPFile object for that file.  The first parameter is the
    // name of the file, the second is the data to place into the
    // file and is optional.
    function createFile($name, $data = NULL) {
        $filePath = $this->myPath . '/' . $name;
        $fd_filePath = 'ssh2.sftp://' . intval($this->mySSH) . '/' . $filePath;

        // If the file already exsists, we bail out.
        if (file_exists($fd_filePath))
            return false;
        $fileStream = fopen($fd_filePath, 'w');
        $retval = stream_copy_to_stream($data, $fileStream);

        // If the operation was successful, return the SFTPFile
        // object associated with the new file.
        if ($retval !== false) {
            fclose($fileStream);
            fclose($data);
            return new SFTPFile($this->mySSH, $filePath);
        }
        return false;
    }

}

// A class that extends DAV\File to implement SSH/SFTP-specific
// handlers for files located on a SFTP server.
class SFTPFile extends DAV\File {

    // The path to the current file.
    private $myPath;

    // The PECL SSH2 SFTP connection object.
    private $mySSH;

    // Construct for the class, takes the full path to the file
    // and the PECL SSH2 SFTP connection object.
    function __construct($myPath, $mySSH) {
        $this->myPath = $myPath;
        $this->mySSH = $mySSH;
    }

    // Return the name of the object, without the path.
    function getName() {
        return basename($this->myPath);
    }

    // Return an open stream for the file.
    function get() {
        $fdPath = 'ssh2.sftp://' . intval($this->mySSH) . '/' . $this->myPath;
        return fopen($fdPath, 'r');
    }

    // Return the size, in bytes, of the file.
    function getSize() {
        $fdPath = 'ssh2.sftp://' . intval($this->mySSH) . '/' . $this->myPath;
        return filesize($fdPath);
    }

    // Return the MD5 SUM of the file.
    function getETag() {
        $fdPath = 'ssh2.sftp://' . intval($this->mySSH) . '/' . $this->myPath;
        return '"' . md5_file($fdPath) . '"';
    }

    // Update data in the file, replacing the contents with the data specified.
    function put($data) {
        $fdPath = 'ssh2.sftp://' . intval($this->mySSH) . '/' . $this->myPath;

        // If the file does not exist, we cannot update it.
        if (!file_exists($fdPath))
            return false;

        $fileStream = fopen($fdPath, 'w');
        $retval = stream_copy_to_stream($data, $fileStream);
        if ($retval) {
            fclose($fileStream);
            fclose($data);
            return true;
        }
        return false;
    }

    // Return the MIME content type of the file.
    function getContentType() {
        $fdPath = 'ssh2.sftp://' . intval($this->mySSH) . '/' . $this->myPath;
        return mime_content_type($fdPath);
    }

    // Delete this object.
    function delete() {
        return ssh2_sftp_unlink($this->mySSH, $this->myPath);
    }

}

?>
