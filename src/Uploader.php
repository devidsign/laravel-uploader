<?php

namespace Idsign\Uploader;

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Intervention\Image\Facades\Image;
use Intervention\Image\ImageManager;
use Symfony\Component\HttpFoundation\File\File;


class Uploader
{
    private $isFile = false;
    private $config = [];
    public $id = 0;
    private $directory = '';
    private $ext = null;
    private $error = null;
    private $name = false;
    private $savePath = false;
    private $file = null;
    private $imgManager = null;

    public function __construct($config = [])
    {
        $this->request = request();
        $this->imgManager = new ImageManager();
        $this->config = config('uploader', [
            'storage_dir' => 'public',
            'upload_dir' => 'uploads',
            'before_limit' => true,
            'limit' => 1000,
        ]);
        $this->_config($config);
    }

    public function getImgManager()
    {
        return $this->imgManager;
    }

    private function _config($config = [])
    {
        if ($config) {
            if (!is_array($config))
                return false;

            $this->config = array_merge($this->config, $config);
        }
        return true;
    }

    private function _getUri()
    {
        //dd($this->config);
        $url = $this->config['upload_dir'] . "/" . ($this->config['before_limit'] ? ($this->directory . "/") : '');
        if ($this->config['limit']) {
            $url .= (floor($this->id / $this->config['limit']) * $this->config['limit']) . "/";
            if ($this->config['add_id_directory'] && $this->id) {
                $url .= $this->id . "/";
            }
        }
        $url .= (!$this->config['before_limit'] ? ($this->directory . "/") : '');
        return str_replace('//', '/', $url);
    }

    public function setPath($path = false)
    {
        if ($path) {
            if (!$this->createDir($path))
                return false;

            $this->savePath = $path;
            return true;
        }
        return false;
    }

    public function setName($name = false, $extension = false)
    {
        $name = explode('.', $name);
        $ext = last($name);
        $name = array_shift($name);
        $this->name = $name;
        if ($extension)
            $this->ext .= $ext;
        return $this;
    }

    private function _getName()
    {
        if ($this->name) {
            return basename($this->name, '.' . $this->ext) . '.' . $this->ext;
        }
        $name = (pow(10, 6) + intval($this->id)) . "_" . rand(1000, 9000) . "_" . time() . '.' . $this->ext;
        $this->name = $this->config['prefix']
            ? $this->config['prefix'] . "_" . rand(1, 9) . "_" . rand(100, 900) . '.' . $this->ext
            : $name;
        return $this->name;
    }

    public function getPath()
    {
        if (!$this->savePath)
            $this->savePath = $this->_getUri();

        $this->createDir($this->savePath);

        return $this->savePath;
    }

    public function reset()
    {
        $this->savePath = false;
        $this->name = false;
    }

    public function init($directory, $id = 0, $isFile = false, $config = [])
    {
        $this->reset();
        $this->_config($config);
        $this->isFile = $isFile;
        $this->directory = $directory;
        $this->id = $id;
        return $this;
    }

    public function isFile()
    {
        $this->isFile = true;
    }

    public function isImage()
    {
        $this->isFile = false;
    }

    public function directory($directory)
    {
        $this->directory = $directory;
    }

    public function make($data, $manager = false)
    {
        if ($this->isFile)
            $this->file = $this->_makeFile($data);
        else
            $this->file = $this->imgManager->make($data);

        return $manager ? $this->file : $this;
    }

    public function _makeFile($data)
    {
        if ($data instanceof UploadedFile) {
            return $data;
        }
        if (is_file($data)) {
            return new UploadedFile($data, 'file');
        }
        return null;
    }

    public function image($data = false, $modifiers = [], $quality = 100)
    {
        $this->isFile = false;
        if ($data)
            $this->make($data);

        if ($modifiers) {
            foreach ($modifiers as $modifier => $parameters) {
                $parameters = is_array($parameters) ? $parameters : [$parameters];
                $this->file->$modifier(...$parameters);
            }
        }
        if (!$this->file)
            return false;
        $this->ext = $this->file->extension
            ? $this->file->extension
            : last(explode('/', $this->file->mime));

        $filename = $this->getPath() . $this->_getName();
        return $this->file->save($filename, $quality);
    }

    public function file($data = false)
    {
        $this->isFile = true;
        if ($data)
            $this->make($data);

        if (!$this->file)
            return false;

        $this->ext = $this->file->guessExtension();
        $filename = $this->getPath() . $this->_getName();

        return $this->file->move($filename);
    }

    public function getInfo()
    {
        $filename = $this->getPath() . $this->_getName();
        if ($this->isFile)
            return $this->_response($this->_makeFile($filename));

        return $this->_response($this->imgManager->make($filename));
    }

    private function _response($driver)
    {
        if ($this->isFile) {
            return [
                'mime' => $driver->getMimeType(),
                'path' => $driver->getPath(),
                'name' => $driver->getFilename(),
                'extension' => $driver->getExtension(),
                'fullpath' => $driver->getPathname(),
                'driver' => $driver
            ];
        }

        $ext = $driver->extension
            ? $driver->extension
            : last(explode('/', $driver->mime));

        return [
            'mime' => $driver->mime,
            'path' => $driver->dirname,
            'name' => $driver->basename,
            'extension' => $ext,
            'fullpath' => $driver->dirname . '/' . $driver->basename,
            'partial' => ltrim(str_replace($this->config['upload_dir'], '', $driver->dirname . '/' . $driver->basename), '/'),
            'driver' => $driver
        ];
    }

    public function createDir($directory)
    {
        if (is_dir($directory))
            return true;

        if (mkdir($directory, 0777, true))
            return true;

        $this->error = 'Permission denied: Create directory';
        return false;
    }

    public function delete($name)
    {
        return $this->deleteFile($this->getPath() . $name);
    }

    public function deleteFile($file)
    {
        if (is_file($file) && file_exists($file))
            return unlink($file);

        return true;
    }
}
