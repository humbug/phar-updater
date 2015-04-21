<?php
/**
 * Humbug
 *
 * @category   Humbug
 * @package    Humbug
 * @copyright  Copyright (c) 2015 Pádraic Brady (http://blog.astrumfutura.com)
 * @license    https://github.com/padraic/phar-updater/blob/master/LICENSE New BSD License
 *
 * This class is partially patterned after Composer's self-update.
 */

namespace Humbug\SelfUpdate;

use Humbug\SelfUpdate\Exception\RuntimeException;
use Humbug\SelfUpdate\Exception\InvalidArgumentException;
use Humbug\SelfUpdate\Exception\FilesystemException;
use Humbug\SelfUpdate\Exception\HttpRequestException;
use Humbug\SelfUpdate\Exception\NoSignatureException;
use Symfony\Component\Finder\Finder;

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
     * @var string
     */
    protected $backupExtension = '-old.phar';

    /**
     * @var string
     */
    protected $backupPath;

    /**
     * @var string
     */
    protected $restorePath;

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
                'Constructor parameter $hasPubKey must be boolean or null.'
            );
        } else {
            $this->hasPubKey = $hasPubKey;
        }
        if ($this->hasPubKey) {
            $this->setLocalPubKeyFile();
        }
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
        || (!is_bool($this->newVersionAvailable) && !$this->hasUpdate())) {
            return false;
        }
        $this->backupPhar();
        $this->downloadPhar();
        $this->replacePhar();
        return true;
    }

    /**
     * Perform an rollback to previous version
     *
     * @return bool
     */
    public function rollback()
    {
        if (!$this->restorePhar()) {
            return false;
        }
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
                sprintf('Invalid url passed as argument: %s.', $url)
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

    /**
     * Set URL to version file
     *
     * @param string $url
     */
    public function setVersionUrl($url)
    {
        if (!$this->validateAllowedUrl($url)) {
            throw new InvalidArgumentException(
                sprintf('Invalid url passed as argument: %s.', $url)
            );
        }
        $this->versionUrl = $url;
    }

    /**
     * Get URL for version file
     *
     * @return string
     */
    public function getVersionUrl()
    {
        return $this->versionUrl;
    }

    /**
     * Set backup extension for old phar versions
     *
     * @param string $extension
     */
    public function setBackupExtension($extension)
    {
        $this->backupExtension = $extension;
    }

    /**
     * Get backup extension for old phar versions
     *
     * @return string
     */
    public function getBackupExtension()
    {
        return $this->backupExtension;
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

    /**
     * Set backup path for old phar versions
     *
     * @param string $filePath
     */
    public function setBackupPath($filePath)
    {
        $path = realpath(dirname($filePath));
        if (!is_dir($path)) {
            throw new FilesystemException(sprintf(
                'The backup directory does not exist: %s.', $path
            ));
        }
        if (!is_writable($path)) {
            throw new FilesystemException(sprintf(
                'The backup directory is not writeable: %s.', $path
            ));
        }
        $this->backupPath = $filePath;
    }

    /**
     * Get backup path for old phar versions
     *
     * @return string
     */
    public function getBackupPath()
    {
        return $this->backupPath;
    }

    /**
     * Set path for the backup phar to rollback/restore from
     *
     * @param string $path
     */
    public function setRestorePath($path)
    {
        $path = realpath(dirname($path));
        if (!file_exists($path)) {
            throw new FilesystemException(sprintf(
                'The restore phar does not exist: %s.', $path
            ));
        }
        if (!is_readable($path)) {
            throw new FilesystemException(sprintf(
                'The restore file is not readable: %s.', $path
            ));
        }
        $this->restorePath = $path;
    }

    /**
     * Get path for the backup phar to rollback/restore from
     *
     * @return string
     */
    public function getRestorePath()
    {
        return $this->restorePath;
    }

    public function throwRuntimeException($errno, $errstr)
    {
        throw new RuntimeException($errstr);
    }

    public function throwHttpRequestException($errno, $errstr)
    {
        throw new HttpRequestException($errstr);
    }

    protected function hasPubKey()
    {
        return $this->hasPubKey;
    }

    protected function newVersionAvailable()
    {
        /** Switch remote request errors to HttpRequestExceptions */
        set_error_handler(array($this, 'throwHttpRequestException'));
        $version = humbug_get_contents($this->getVersionUrl());
        restore_error_handler();
        if (false === $version) {
            throw new HttpRequestException(sprintf(
                'Request to URL failed: %s', $this->getVersionUrl()
            ));
        }
        if (empty($version)) {
            throw new HttpRequestException(
                'Version request returned empty response.'
            );
        }
        if (!preg_match('%^[a-z0-9]{40}%', $version, $matches)) {
            throw new HttpRequestException(
                'Version request returned incorrectly formatted response.'
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
                'Unable to backup %s to %s.',
                $this->getLocalPharFile(),
                $this->getBackupPharFile()
            ));
        }
    }

    protected function downloadPhar()
    {
        /** Switch remote request errors to HttpRequestExceptions */
        set_error_handler(array($this, 'throwHttpRequestException'));
        $result = humbug_get_contents($this->getPharUrl());
        restore_error_handler();
        if (false === $result) {
            throw new HttpRequestException(sprintf(
                'Request to URL failed: %s', $this->getPharUrl()
            ));
        }

        file_put_contents($this->getTempPharFile(), $result);

        if (!file_exists($this->getTempPharFile())) {
            throw new FilesystemException(
                'Creation of download file failed.'
            );
        }

        $tmpVersion = sha1_file($this->getTempPharFile());
        if ($tmpVersion !== $this->getNewVersion()) {
            $this->cleanupAfterError();
            throw new HttpRequestException(sprintf(
                'Download file appears to be corrupted or outdated. The file '
                    . 'received does not have the expected SHA-1 hash: %s.',
                $this->getNewVersion()
            ));
        }

        try {
            $this->validatePhar($this->getTempPharFile());
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

    protected function restorePhar()
    {
        $backup = $this->getRestorePharFile();
        if (!file_exists($backup)) {
            throw new RuntimeException(sprintf(
                'The backup file does not exist: %s.', $backup
            ));
        }
        $this->validatePhar($backup);
        return rename($backup, $this->getLocalPharFile());
    }

    protected function getBackupPharFile()
    {
        if (null !== $this->getBackupPath()) {
            return $this->getBackupPath();
        }
        return $this->getTempDirectory()
            . '/'
            . sprintf('%s%s', $this->getLocalPharFileBasename(), $this->getBackupExtension());
    }

    protected function getRestorePharFile()
    {
        if (null !== $this->getRestorePath()) {
            return $this->getRestorePath();
        }
        return $this->getTempDirectory()
            . '/'
            . sprintf('%s%s', $this->getLocalPharFileBasename(), $this->getBackupExtension()
        );
    }

    protected function getTempPharFile()
    {
        return $this->getTempDirectory()
            . '/'
            . sprintf('%s.phar.temp', $this->getLocalPharFileBasename());
    }

    protected function getTempPubKeyFile()
    {
        return $this->getTempDirectory()
            . '/'
            . sprintf('%s.phar.temp.pubkey', $this->getLocalPharFileBasename());
    }

    protected function setLocalPharFile($localPharFile)
    {
        if (!is_null($localPharFile)) {
            $localPharFile = realpath($localPharFile);
        } else {
            $localPharFile = realpath($_SERVER['argv'][0]) ?: $_SERVER['argv'][0];
        }
        if (!file_exists($localPharFile)) {
            throw new RuntimeException(sprintf(
                'The set phar file does not exist: %s.', $localPharFile
            ));
        }
        if (!is_writable($localPharFile)) {
            throw new FilesystemException(sprintf(
                'The current phar file is not writeable and cannot be replaced: %s.',
                $localPharFile
            ));
        }
        $this->localPharFile = $localPharFile;
        $this->localPharFileBasename = basename($localPharFile, '.phar');
    }

    protected function setLocalPubKeyFile()
    {
        $localPubKeyFile = $this->getLocalPharFile() . '.pubkey';
        if (!file_exists($localPubKeyFile)) {
            throw new RuntimeException(sprintf(
                'The phar pubkey file does not exist: %s.', $localPubKeyFile
            ));
        }
        $this->localPubKeyFile = $localPubKeyFile;
    }

    protected function setTempDirectory()
    {
        $tempDirectory = dirname($this->getLocalPharFile());
        if (!is_writable($tempDirectory)) {
            throw new FilesystemException(sprintf(
                'The directory is not writeable: %s.', $tempDirectory
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

    protected function validatePhar($phar)
    {
        $phar = realpath($phar);
        if ($this->hasPubKey()) {
            copy($this->getLocalPubKeyFile(), $phar . '.pubkey');
        }
        chmod($phar, fileperms($this->getLocalPharFile()));
        /** Switch invalid key errors to RuntimeExceptions */
        set_error_handler(array($this, 'throwRuntimeException'));
        $phar = new \Phar($phar);
        $signature = $phar->getSignature();
        if ($this->hasPubKey() && strtolower($signature['hash_type']) !== 'openssl') {
            throw new NoSignatureException(
                'The downloaded phar file has no OpenSSL signature.'
            );
        }
        restore_error_handler();
        if ($this->hasPubKey()) {
            @unlink($phar . '.pubkey');
        }
        unset($phar);
    }

    protected function cleanupAfterError()
    {
        //@unlink($this->getBackupPharFile());
        @unlink($this->getTempPharFile());
        @unlink($this->getTempPubKeyFile());
    }

}
