PHAR Updater
============

[![Build Status](https://travis-ci.org/padraic/phar-updater.svg?branch=master)](https://travis-ci.org/padraic/phar-updater)

You have a phar file to distribute, and it's all the rage to include a self-update
command. Do you really need to write that? Here at Humbug Central, our army of
minions (all ten of them) have written one for you with the following features
(to which we'll add over time):

* Full support for SSL/TLS verification so your users will not get their downloads
replaced by cheeky people .
* Support for OpenSSL phar signatures.
* Version checking (currently to latest SHA-1).
* Simple API where it either updates or Exceptions will go wild.

Development continues so...give it a whirl and complain loudly in the issues
section if needed.

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
 *
 * The first constructor parameter is the path to a phar if you are not updating
 * the currently running phar.
 */

use Humbug\SelfUpdate\Updater;

$updater = new Updater();
$updater->setPharUrl('http://example.com/current.phar');
$updater->setVersionUrl('http://example.com/current.version');
try {
    $result = $updater->update();
    $result ? exit('Updated!') : exit('No update needed!');
} catch (\Exception $e) {
    exit('Well, something happened! Either an oopsie or something involving hackers.');
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
$updater->setPharUrl('http://example.com/current.phar');
$updater->setVersionUrl('http://example.com/current.version');
try {
    $result = $updater->update();
    $result ? exit('Updated!') : exit('No update needed!');
} catch (\Exception $e) {
    exit('Well, something happened! Either an oopsie or something involving hackers.');
}
```

If you need version information:

```php
use Humbug\SelfUpdate\Updater;

$updater = new Updater();
$updater->setPharUrl('http://example.com/current.phar');
$updater->setVersionUrl('http://example.com/current.version');
try {
    $result = $updater->update();
    if ($result) {
        $new = $updater->getNewVersion();
        $old = $updater->getOldVersion();
        exit(sprintf(
            'Updated from SHA-1 %s to SHA-1 %s', $old, $new
        ))
    } else {
        exit('No update needed!')
    }
} catch (\Exception $e) {
    exit('Well, something happened! Either an oopsie or something involving hackers.');
}
```

The Updater automatically copies a backup of the original phar to myname-old.phar.
You can trigger a rollback quite easily using this convention:

```php
use Humbug\SelfUpdate\Updater;

/**
 * Same constructor parameters as you would use for updating. Here, just defaults.
 */
$updater = new Updater();
try {
    $result = $updater->rollback();
    $result ? exit('Success!') : exit('Failure!');
} catch (\Exception $e) {
    exit('Well, something happened! Either an oopsie or something involving hackers.');
}
```

As users may have diverse requirements in naming and locating backups, you can
explicitly manage the precise path to where a backup should be written, or read
from using the `setBackupPath()` function when updating a current phar or the
`setRestorePath()` prior to triggering a rollback. These will be used instead
of the simple built in convention.

### Constructor Parameters

The Updater constructor is fairly simple. The three basic variations are:

```php
/**
 * Default: Update currently running phar which has been signed.
 */
$updater = new Updater;
```

```php
/**
 * Default: Update currently running phar which has NOT been signed.
 */
$updater = new Updater(null, false);
```

```php
/**
 * Default: Update a different phar which has NOT been signed.
 */
$updater = new Updater('/path/to/impersonatephil.phar', false);
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