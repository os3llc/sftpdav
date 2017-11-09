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

class SFTPDirectory extends DAV\Collection {

	private $myPath;
	private $mySSH;

	function __construct($myPath, $mySSH) {
		$this->myPath = $myPath;
		$this->mySSH = $mySSH;
	}

	function getChildren() {
		$fdPath = 'ssh2.sftp://' . intval($this->mySSH) . $this->myPath;
		$children = array();

		$contents = scandir($fdPath);
		if ($contents === false)
			throw new DAV\Exception\NotFound('Access denied.');
		foreach ($contents as $node) {
			if ($node[0] === '.')
				continue;
			$children[] = $this->getChild($node);
		}

		return $children;

	}

	function getChild($name) {
		$path = $this->myPath . '/' . $name;
		$fd_path = 'ssh2.sftp://' . intval($this->mySSH) . $path;

		if (!file_exists($fd_path))
			throw new DAV\Exception\NotFound('The file ' . $name . ' is not present on the SFTP server, ' . $fd_path);

		if ($name[0] == '.')
			throw new DAV\Exception\NotFound('Access denied.');

		if (is_dir($fd_path))
			return new SFTPDirectory($path, $this->mySSH);

		else
			return new SFTPFile($path, $this->mySSH);

	}

	function childExists($name) {

		if (!ssh2_sftp_stat($this->mySSH, $this->myPath . "/" . $name))
			return false;

		return true;

	}

	function getName() {

		return basename($this->myPath);

	}

	function delete() {
		return ssh2_sftp_rmdir($this->mySSH, $this->myPath);
	}

	function setName($newName) {
		return ssh2_sftp_rename($this->mySSH, $this->myPath, dirname($this->myPath) . '/' . $newName);
	}

	function createDirectory($name) {
		$newPath = $this->myPath . '/' . $name;
		if(ssh2_sftp_mkdir($this->mySSH, $newPath))
			return new SFTPDirectory($newPath);
		return false;
	}

	function createFile($name, $data = NULL) {
		$filePath = $this->myPath . '/' . $name;
		$fd_filePath = 'ssh2.sftp://' . intval($this->mySSH) . '/' . $filePath;
		if (file_exists($fd_filePath))
			return false;
		$fileStream = fopen($fd_filePath, 'w');
		if(fwrite($fileStream, $data) === true) {
			fclose($fileStream);
			return new SFTPFile($this->mySSH, $filePath);
		}
		return false;
	}

}

class SFTPFile extends DAV\File {

	private $myPath;
	private $mySSH;

	function __construct($myPath, $mySSH) {
		$this->myPath = $myPath;
		$this->mySSH = $mySSH;
	}

	function getName() {
		return basename($this->myPath);
	}

	function get() {
		$fdPath = 'ssh2.sftp://' . intval($this->mySSH) . '/' . $this->myPath;
		return fopen($fdPath, 'r');
	}

	function getSize() {
		$fdPath = 'ssh2.sftp://' . intval($this->mySSH) . '/' . $this->myPath;
		return filesize($fdPath);
	}

	function getETag() {
		$fdPath = 'ssh2.sftp://' . intval($this->mySSH) . '/' . $this->myPath;
		return '"' . md5_file($fdPath) . '"';
	}

	function put($data) {
                $fdPath = 'ssh2.sftp://' . intval($this->mySSH) . '/' . $this->myPath;
                if (!file_exists($fdPath))
                        return false;
                $fileStream = fopen($fdPath, 'w');
		$retval = stream_copy_to_stream($data, $fileStream);
		if($retval) {
			fclose($fileStream);
			fclose($data);
			return true;
		}
                return false;
	}

	function getContentType() {
		$fdPath = 'ssh2.sftp://' . intval($this->mySSH) . '/' . $this->myPath;
		return mime_content_type($fdPath);
	}

	function delete() {
		return ssh2_sftp_unlink($this->mySSH, $this->myPath);
	}

}

?>
