<?php


namespace renegade\helper;


use Symfony\Component\Filesystem\Filesystem;

class DirectoryHelper {

    /**
     * @var $filesystem \Symfony\Component\Filesystem\Filesystem
     */
    protected $filesystem;

    protected $config;

    /**
     * @param Filesystem $filesystem
     * @param $config
     */
    function __construct(Filesystem $filesystem, $config) {
        $this->filesystem = $filesystem;
        $this->config = $config;
    }

    /**
     * Initialize directories of a given type (localized)
     *
     * @param $language
     * @param $type
     */
    public function init($language, $type) {
        if(!$this->filesystem->exists($this->get_dir($type))) {
            $this->filesystem->mkdir($this->get_dir($type));
        }

        if($this->filesystem->exists($this->get_localized_dir($type, $language))) {
            $this->filesystem->remove($this->get_localized_dir($type, $language));
        }

        $this->filesystem->mkdir($this->get_dir($type));
        $this->filesystem->mkdir($this->get_localized_dir($type, $language));
    }

    /**
     * Based on a given type return the localized directory
     *
     * @param $type
     * @param $locale
     * @return string
     */
    public function get_localized_dir($type, $locale) {
        return sprintf('%s/%s', $this->get_dir($type), $locale);
    }

    /**
     * Get a specified directory from the config
     *
     * @param $type
     * @return string
     */
    function get_dir($type) {
        switch($type) {
            case 'build':
                return sprintf('%s/%s', __DIR__, $this->config['application']['build_dir']);
                break;

            case 'dist':
                return sprintf('%s/%s', __DIR__, $this->config['application']['dist_dir']);
                break;

            case 'screenshots':
                return sprintf('%s/%s', __DIR__, $this->config['application']['screenshots_dir']);
                break;

            case 'pdf':
                return sprintf('%s/%s', __DIR__, $this->config['application']['pdf_dir']);
                break;
        }
    }

    /**
     * @param $contents
     * @param $path
     * @param $filename
     * @return bool|int
     */
    function save_page($contents, $path, $filename) {
        if(is_readable($path)) {
            return file_put_contents(sprintf('%s/%s', $path, $filename), $contents, LOCK_EX);
        } else {
            return false;
        }
    }

    

} 