<?php
/**
 * Humbug
 *
 * @category   Humbug
 * @package    Humbug
 * @copyright  Copyright (c) 2015 Pádraic Brady (http://blog.astrumfutura.com)
 * @license    https://github.com/padraic/humbug/blob/master/LICENSE New BSD License
 *
 * This class is partially patterned after Composer's self-update.
 */

namespace Humbug\SelfUpdate;

use Humbug\SelfUpdate\Exception\RuntimeException;
use Humbug\SelfUpdate\Exception\InvalidArgumentException;
use Humbug\SelfUpdate\Exception\FilesystemException;
use Humbug\SelfUpdate\Exception\HttpRequestException;

class Updater
{

    /**
     * @var string
     */
    protected $localPharFile;

    /**
     * @var string
     */
    protected $localPharFileBasename;
    
    /**
     * @var string
     */
    protected $localPubKeyFile;

    /**
     * @var bool
     */
    protected $hasPubKey;

    /**
     * @var string
     */
    protected $pharUrl;

    /**
     * @var string
     */
    protected $versionUrl;

    /**
     * @var string
     */
    protected $tempDirectory;

    /**
     * @var string
     */
    protected $newVersion;

    /**
     * @var string
     */
    protected $oldVersion;

    /**
     * @var bool
     */
    protected $newVersionAvailable;

    /**
     * Constructor
     *
     * @param string $localPharFile
     * @param bool $hasPubKey
     */
    public function __construct($localPharFile = null, $hasPubKey = true)
    {
        ini_set('phar.require_hash', 1);
        $this->setLocalPharFile($localPharFile);
        if (!is_bool($hasPubKey)) {
            throw new InvalidArgumentException(
                'Constructor parameter $hasPubKey must be boolean or null'
            );
        } else {
            $this->hasPubKey = $hasPubKey;
        }
        if ($this->hasPubKey) {
            $this->setLocalPubKeyFile();
        }
        $this->hasPubKey = $hasPubKey;
        $this->setTempDirectory();
    }

    /**
     * Check for update
     *
     * @return bool
     */
    public function hasUpdate()
    {
        $this->newVersionAvailable = $this->newVersionAvailable();
        return $this->newVersionAvailable;
    }

    /**
     * Perform an update
     *
     * @return bool
     */
    public function update()
    {
        if ($this->newVersionAvailable === false
        || !is_bool($this->newVersionAvailable) && !$this->hasUpdate()) {
            return false;
        }
        $this->backupPhar();
        $this->downloadPhar();
        $this->replacePhar();
        return true;
    }

    /**
     * Set URL to phar file
     *
     * @param string $url
     */
    public function setPharUrl($url)
    {
        if (!$this->validateAllowedUrl($url)) {
            throw new InvalidArgumentException(
                sprintf('Invalid url passed as argument: %s', $url)
            );
        }
        $this->pharUrl = $url;
    }

    /**
     * Get URL for phar file
     *
     * @return string
     */
    public function getPharUrl()
    {
        return $this->pharUrl;
    }

    public function setVersionUrl($url)
    {
        if (!$this->validateAllowedUrl($url)) {
            throw new InvalidArgumentException(
                sprintf('Invalid url passed as argument: %s', $url)
            );
        }
        $this->versionUrl = $url;
    }

    public function getVersionUrl()
    {
        return $this->versionUrl;
    }

    public function getLocalPharFile()
    {
        return $this->localPharFile;
    }

    public function getLocalPharFileBasename()
    {
        return $this->localPharFileBasename;
    }

    public function getLocalPubKeyFile()
    {
        return $this->localPubKeyFile;
    }

    public function getTempDirectory()
    {
        return $this->tempDirectory;
    }

    public function getNewVersion()
    {
        return $this->newVersion;
    }

    public function getOldVersion()
    {
        return $this->oldVersion;
    }

    public function throwException($errno, $errstr)
    {
        throw new RuntimeException($errstr);
    }

    protected function hasPubKey()
    {
        return $this->hasPubKey;
    }

    protected function newVersionAvailable()
    {
        $version = humbug_get_contents($this->getVersionUrl());
        if (empty($version)) {
            throw new HttpRequestException(
                'Version request returned empty response'
            );
        }
        if (!preg_match('%^[a-z0-9]{40}%', $version, $matches)) {
            throw new HttpRequestException(
                'Version request returned incorrectly formatted response'
            );
        }

        $this->newVersion = $matches[0];
        $this->oldVersion = sha1_file($this->getLocalPharFile());

        if ($this->newVersion !== $this->oldVersion) {
            return true;
        }
        return false;
    }

    protected function backupPhar()
    {
        $result = copy($this->getLocalPharFile(), $this->getBackupPharFile());
        if ($result === false) {
            $this->cleanupAfterError();
            throw new FilesystemException(sprintf(
                    'Unable to backup %s to %s',
                    $this->getLocalPharFile(),
                    $this->getBackupPharFile()
            ));
        }
    }

    protected function downloadPhar()
    {
        file_put_contents(
            $this->getTempPharFile(),
            humbug_get_contents($this->getPharUrl())
        );

        if (!file_exists($this->getTempPharFile())) {
            throw new FilesystemException(
                'Creation of download file failed'
            );
        }

        $tmpVersion = sha1_file($this->getTempPharFile());
        if ($tmpVersion !== $this->getNewVersion()) {
            $this->cleanupAfterError();
            throw new HttpRequestException(sprintf(
                'Download file appears to be corrupted or outdated. The file '
                    . 'received does not have the expected SHA-1 hash: %s',
                $this->getNewVersion()
            ));
        }

        try {
            if ($this->hasPubKey()) {
                copy($this->getLocalPubKeyFile(), $this->getTempPubKeyFile());
            }
            chmod($this->getTempPharFile(), fileperms($this->getLocalPharFile()));
            if (!ini_get('phar.readonly')) {
                /** Switch invalid key errors to RuntimeExceptions */
                set_error_handler(array($this, 'throwException'));
                $phar = new \Phar($this->getTempPharFile());
                // check how the phar was signed and warn if not openssl
                unset($phar);
                restore_error_handler();
            } else {
                throw new RuntimeException(sprintf(
                    'The phar.readonly setting is %s. Unable to verify signature',
                    (string) ini_get('phar.readonly')
                ));
            }
            if ($this->hasPubKey()) {
                @unlink($this->getTempPubKeyFile());
            }
        } catch (\Exception $e) {
            restore_error_handler();
            $this->cleanupAfterError();
            throw $e;
        }
    }

    protected function replacePhar()
    {
        rename($this->getTempPharFile(), $this->getLocalPharFile());
    }

    protected function getBackupPharFile()
    {
        return $this->getTempDirectory()
            . '/'
            . sprintf('%s.%s.phar', $this->getLocalPharFileBasename(), $this->getOldVersion()
        );
    }

    protected function getTempPharFile()
    {
        return $this->getTempDirectory()
            . '/'
            . sprintf('%s.phar.temp', $this->getLocalPharFileBasename()
        );
    }

    protected function getTempPubKeyFile()
    {
        return $this->getTempDirectory()
            . '/'
            . sprintf('%s.phar.temp.pubkey', $this->getLocalPharFileBasename()
        );
    }

    protected function setLocalPharFile($localPharFile)
    {
        if (!is_null($localPharFile)) {
            $localPharFile = realpath($localPharFile);
        } else {
            $localPharFile = realpath($_SERVER['argv'][0]) ?: $_SERVER['argv'][0];
        }
        if (!file_exists($localPharFile)) {
            throw new RuntimeException(
                sprintf('The set phar file does not exist: %s', $localPharFile)
            );
        }
        if (!is_writable($localPharFile)) {
            throw new FilesystemException(
                sprintf(
                    'The current phar file is not writeable and cannot be replaced: %s',
                    $localPharFile
                )
            );
        }
        $this->localPharFile = $localPharFile;
        $this->localPharFileBasename = basename($localPharFile, '.phar');
    }

    protected function setLocalPubKeyFile()
    {
        $localPubKeyFile = $this->getLocalPharFile() . '.pubkey';
        if (!file_exists($localPubKeyFile)) {
            throw new RuntimeException(
                sprintf('The phar pubkey file does not exist: %s', $localPubKeyFile)
            );
        }
        $this->localPubKeyFile = $localPubKeyFile;
    }

    protected function setTempDirectory()
    {
        $tempDirectory = dirname($this->getLocalPharFile());
        if (!is_writable($tempDirectory)) {
            throw new FilesystemException(sprintf(
                'The directory is not writeable: %s', $tempDirectory
            ));
        }
        $this->tempDirectory = $tempDirectory;
    }

    protected function validateAllowedUrl($url)
    {
        if (filter_var($url, FILTER_VALIDATE_URL)
        && in_array(parse_url($url, PHP_URL_SCHEME), array('http', 'https', 'file'))) {
            return true;
        }
        return false;
    }

    protected function cleanupAfterError()
    {
        @unlink($this->getBackupPharFile());
        @unlink($this->getTempPharFile());
        @unlink($this->getTempPubKeyFile());
    }

}