phar Updater
============

You have a phar file to distribute, and it's all the rage to include a self-update
command. Do you really need to write that? Here at Humbug Central, our army of
minions have written one for you with the following features (to which we'll add
over time):

* Full support for SSL/TLS verification so your users will not get their downloads
replaced by cheeky people .
* Support for OpenSSL phar signatures.
* Version checking (currently to latest SHA-1).
* Simple API where it either updates or Exceptions will go wild.


Installation
============

```json
require: {
   "padraic/phar-updater": "~1.0@dev"
}
```
Usage
=====

Create your self-update command, or even an update command for some other phar
other than the current one, and include this.

```php
/**
 * The simplest usage assumes the currently running phar is to be updated and
 * that it has been signed with a private key (using OpenSSL)
 */

use Humbug\SelfUpdate\Updater;

$updater = new Updater();
$updater->setpharUrl('http://example.com/current.phar');
$updater->setVersionUrl('http://example.com/current.version');
try {
    $updater->update();
} catch (\Exception $e) {
    die('Well, something happened! Either an oopsie or something involving hackers.');
}
```

If you are not signing the phar using OpenSSL:

```php
/**
 * The second parameter to the constructor must be false if your phars are
 * not signed using OpenSSL.
 */

use Humbug\SelfUpdate\Updater;

$updater = new Updater(null, false);
$updater->setpharUrl('http://example.com/current.phar');
$updater->setVersionUrl('http://example.com/current.version');
try {
    $updater->update();
} catch (\Exception $e) {
    die('Well, something happened! Either an oopsie or something involving hackers.');
}
```

Update Strategies
=================

SHA-1 Hash Synchronisation
--------------------------

The phar-updater package only (that will change!) supports an update strategy
where phars are updated according to the SHA-1 hash of the current phar file
available remotely. This assumes the existence of only two files:

* myname.phar
* myname.version

The `myname.phar` is the most recently built phar.

The `myname.version` contains the SHA-1 hash of the most recently built phar where
the hash is the very first string (if not the only string). You can generate this
quite easily from bash using:

```sh
sha1sum myname.phar > myname.version
```

Remember to regenerate the version file for each new phar build you want to distribute.

If using OpenSSL signing, which is very much recommended, you can also put the
public key online as `myname.phar.pubkey`, for the initial installation of your
phar. However, please note that phar-updater will never download this key, will
never replace this key on your filesystem, and will never install a phar whose
signature cannot be verified by the locally cached public key.