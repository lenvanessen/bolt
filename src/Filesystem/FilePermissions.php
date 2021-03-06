<?php

namespace Bolt\Filesystem;

use Bolt\Config;
use Bolt\Filesystem\Exception\IOException;

/**
 * Use to check if an access to a file is allowed.
 *
 * @author Benjamin Georgeault <benjamin@wedgesama.fr>
 */
class FilePermissions
{
    /** @var Config */
    protected $config;
    /** @var string[] List of Filesystem prefixes that are editable. */
    protected $allowedPrefixes = [];
    /** @var array Regex list represented editable resources. */
    protected $allowed = [];
    /** @var array Regex list represented resources forbidden for edition. */
    protected $blocked = [];
    /** @var double Maximum upload size allowed by PHP, in bytes. */
    protected $maxUploadSize;

    /**
     * Constructor, initialize filters rules.
     *
     * @param Config $config
     */
    public function __construct(Config $config)
    {
        $this->config = $config;

        $this->allowedPrefixes = [
            'config',
            'extensions_config',
            'files',
            'theme',
            'themes',
        ];

        $this->blocked = [
            '#.php$#',
            '#\.htaccess#',
            '#\.htpasswd#',
        ];
    }

    /**
     * Check if you can do something with the given file or directory.
     *
     * @param string $prefix
     * @param string $path
     *
     * @return bool
     */
    public function authorized($prefix, $path)
    {
        // Check blocked resources
        foreach ($this->blocked as $rule) {
            if (preg_match($rule, $path)) {
                return false;
            }
        }

        // Check allowed filesystems
        foreach ($this->allowedPrefixes as $allowedPrefix) {
            if ($allowedPrefix === $prefix) {
                return true;
            }
        }

        // Check allowed resources
        foreach ($this->allowed as $rule) {
            if (preg_match($rule, $path)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Checks if a given file is acceptable for upload.
     *
     * @param string $originalFilename
     *
     * @throws IOException
     *
     * @return bool
     */
    public function allowedUpload($originalFilename)
    {
        // Check if file_uploads ini directive is true
        if (ini_get('file_uploads') != 1) {
            throw new IOException('File uploads are not allowed, check the file_uploads ini directive.');
        }
        // no UNIX-hidden files
        if ($originalFilename[0] === '.') {
            return false;
        }
        // only whitelisted extensions
        $extension = pathinfo($originalFilename, PATHINFO_EXTENSION);
        $allowedExtensions = $this->getAllowedUploadExtensions();

        return in_array($extension, $allowedExtensions);
    }

    /**
     * Get the array of configured acceptable file extensions.
     *
     * @return array
     */
    public function getAllowedUploadExtensions()
    {
        return $this->config->get('general/accept_file_types');
    }

    /**
     * Get the maximum upload size the server is configured to accept.
     *
     * @return double
     */
    public function getMaxUploadSize()
    {
        if (!isset($this->maxUploadSize)) {
            $size = $this->filesizeToBytes(ini_get('post_max_size'));

            $uploadMax = $this->filesizeToBytes(ini_get('upload_max_filesize'));
            if (($uploadMax > 0) && ($uploadMax < $size)) {
                $size = $uploadMax;
            } else {
                // This reduces the reported max size by a small amount to take account of the difference between
                // the uploaded file size and the size of the eventual post including other data.
                $size = $size * 0.995;
            }

            $this->maxUploadSize = $size;
        }

        return $this->maxUploadSize;
    }

    /**
     * Get the max upload value in a formatted string.
     *
     * @return string
     */
    public function getMaxUploadSizeNice()
    {
        return $this->formatFilesize($this->getMaxUploadSize());
    }

    /**
     * Format a filesize like '10.3 KiB' or '2.5 MiB'.
     *
     * @param integer $size
     *
     * @return string
     */
    private function formatFilesize($size)
    {
        if ($size > 1024 * 1024) {
            return sprintf('%0.2f MiB', ($size / 1024 / 1024));
        } elseif ($size > 1024) {
            return sprintf('%0.2f KiB', ($size / 1024));
        } else {
            return $size . ' B';
        }
    }

    /**
     * Convert a size string, such as 5M to bytes.
     *
     * @param string $size
     *
     * @return double
     */
    private function filesizeToBytes($size)
    {
        $unit = preg_replace('/[^bkmgtpezy]/i', '', $size);
        $size = preg_replace('/[^0-9\.]/', '', $size);

        if ($unit) {
            return round($size * pow(1024, stripos('bkmgtpezy', $unit[0])));
        } else {
            return round($size);
        }
    }
}
