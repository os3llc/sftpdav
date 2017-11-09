# sftpdav

I was searching for a basic bridge for being able to view/browser/manipulate
files on an SFTP server via a web browser and could not come up with any
good solutions, so I wrote a very, very basic implementation of one in PHP.

This extends the sabre/dav API and uses the PHP SSH2 library to do the
translations between WebDAV and SFTP.  Feel free to contribute and submit
Pull Requests for it, if you like, or take it and use it.

This is licensed under the Apache 2.0 license.
