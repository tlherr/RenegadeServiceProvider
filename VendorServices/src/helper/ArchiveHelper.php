<?php


namespace renegade\helper;

use ZipArchive;

class ArchiveHelper extends ZipArchive {

    /**
     * Add files and sub-directories in a folder to zip file.
     * @param string $folder
     */
    function folderToZip($folder) {
        $handle = opendir($folder);
        while (false !== $f = readdir($handle)) {
            if ($f != '.' && $f != '..') {
                $filePath = "$folder/$f";
                // Remove prefix from file path before add to zip.
                $localPath = $filePath;
                if (is_file($filePath)) {
                    $this->addFile($filePath, $localPath);
                } elseif (is_dir($filePath)) {
                    // Add sub-directory.
                    $this->addEmptyDir($localPath);
                    $this->folderToZip($filePath);
                }
            }
        }
        closedir($handle);
    }
} 