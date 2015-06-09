CHANGELOG
=========

0.0.2 (2015-06-09)
------------------

* An exception is now immediately thrown curl error rather than letting later
  status code checks throw an exception. This improves the exception message
  greatly.
* If this library is inside a phar archive, the CA certs are copied out of the
  archive to a temporary file so that curl can use them.

0.0.1 (2015-06-01)
------------------

* Initial release.
