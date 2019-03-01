<?php

namespace Idsign\Uploader;

use Illuminate\Http\Request;
use Intervention\Image\Facades\Image;
use Symfony\Component\HttpFoundation\File\File;


class Uploader
{
    private $data = null;
    private $request = null;
    private $config = [];
    private $isFile = false;
    public $id = 0;
    private $directory = '';
    private $ext = null;
    private $error = null;
    private $name;

    public function __construct()
    {
        $this->request = Request::capture();
    }

    public function getError()
    {
        return $this->error;
    }

    public function config($directory, $name = false, $id = 0, $config = [])
    {
        $this->request = Request::capture();
        $this->config = config('uploader', [
            'storage_dir' => 'public',
            'upload_dir' => 'uploads',
            'files_dir' => 'files',
            'images_dir' => 'images',
            'limit' => 1000,
        ]);
        $this->id = $id;
        $this->directory = $directory;
        $this->name = $name;
        $this->isFile = false;

        if ($config) {
            if (!is_array($config))
                return $this->_response();

            $this->config = array_merge($this->config, $config);
        }


        return $this;
    }
    public function init($directory, $id = 0, $isFile = false, $config = [])
    {
        $this->request = Request::capture();
        $this->config = config('uploader', [
            'storage_dir' => 'public',
            'upload_dir' => 'uploads',
            'files_dir' => 'files',
            'images_dir' => 'images',
            'limit' => 1000,
        ]);
        $this->id = $id;
        $this->directory = $directory;
        $this->isFile = $isFile;

        if ($config) {
            if (!is_array($config))
                return $this->_response();

            $this->config = array_merge($this->config, $config);
        }


        return $this;
    }

    public function file($data, $put = false)
    {
        if (!$this->_load($data, $put))
            return $this->_response();
        $this->isFile = true;
        $response = $put ? $this->_put($this->_getUri()) : $this->_move($this->_getUri());

        return $this->_response($response);
    }

    public function image($data, $put = false)
    {
        if (!$this->_load($data, $put))
            return $this->_response();
        $response = $put ? $this->_put($this->_getUri('original')) : $this->_move($this->_getUri('original'));
        $name = $this->_response($response);
        $original_dir = $this->_getUrl($this->_getUri('original'), $this->config['storage_dir']);
        $this->_imagesEach(
            function ($size, $dim) use ($name, $original_dir) {
                $directory = $this->_getUrl($this->_getUri($size), $this->config['storage_dir']);

                if ($this->_createDir($directory) < 0)
                    return false;

                if ($dim['width'] > $dim['height'])
                    list($dim['width'], $dim['height']) = array($dim['height'], $dim['width']);

                $original = Image::make($original_dir . $name);
                $width = $widthT = $original->width();
                $height = $heightT = $original->height();

                if (($dim['width'] <= $width) and ($dim['height'] <= $height)) {
                    $widthT = $dim['width'];
                    $heightT = $dim['height'];
                }

                if ($widthT > $heightT) {
                    $original->widen($widthT, function ($constraint) {
                        $constraint->upsize();
                    })->save($directory . $name);
                } else {
                    $original->heighten($heightT, function ($constraint) {
                        $constraint->upsize();
                    })->save($directory . $name);
                }
                return true;
            },
            false
        );
        return $name;
    }

    private function _getName()
    {
        if ($this->name) {
            return basename($this->name,'.'.$this->ext).'.'.$this->ext;
        }
        $name = (pow(10, 6) + intval($this->id)) . "_" . rand(1000, 9000) . "_" . time() . '.' . $this->ext;
        return $this->config['prefix'] . (
            $this->config['prefix']
                ? "_" . rand(1, 9) . "_" . rand(100, 900) . '.' . $this->ext
                : $name
            );
    }

    public function getWebUrl($name, $version = '')
    {
        return $this->_getUrl($this->_getUri($version)) . $name;
    }

    public function getWebUri($name, $version = '')
    {
        return $this->_getUri($version) . $name;
    }

    public function getPathUrl($name, $version = '', $type = 'public')
    {
        return $this->_getUrl($this->_getUri($version), $type) . $name;
    }

    private static function instance($directory, $id, $isFile)
    {
        $instance = new static();
        $instance->init($directory, $id, $isFile);
        return $instance;
    }

    public static function getUrl($id, $directory, $name, $isFile = false, $version = '')
    {
        $instance = self::instance($directory, $id, $isFile);
        return $instance->getWebUrl($name, $version);
    }

    public static function getPath($id, $directory, $name, $isFile = false, $version = '')
    {
        $instance = self::instance($directory, $id, $isFile);
        return $instance->getPathUrl($name, $version);
    }

    private function _getUrl($url, $type = 'url')
    {
        switch ($type) {
            default:
            case 'url':
                $path = asset($url);
                break;
            case 'public':
                $path = $this->_fixPath(public_path($url));
                break;
            case 'storage':
                $path = $this->_fixPath(storage_path($url));
                break;
        }
        return rtrim($path, '/') . '/';
    }

    private function _fixPath($path)
    {
        return str_replace("\\", "/", $path);
    }

    private function _getUri($version = false)
    {
        $url = $this->config['upload_dir'] . "/" . $this->config[$this->isFile ? 'files_dir' : 'images_dir'] . "/" . $this->directory . "/";
        if (!$this->isFile && $version) {
            $url .= $version . "/";
        }
        if ($this->config['limit'])
            $url .= (floor($this->id / $this->config['limit']) * $this->config['limit']) . "/";
        return str_replace('//', '/', $url);
    }

    private function _move($uri)
    {
        $name = $this->_getName();
        $uploaded = $this->data->move($this->_getUrl($uri, $this->config['storage_dir']), $name);
        if ($uploaded)
            $this->file = $uploaded;
        return $uploaded ? $name : false;
    }

    private function _put($uri)
    {
        $path = $this->_getUrl($uri, $this->config['storage_dir']);
        $name = $this->_getName();
        if ($this->_createDir($path) < 0)
            return false;
        $uploaded = file_put_contents($this->_getUrl($uri, $this->config['storage_dir']) . $name, $this->data);
        if (!$uploaded) {
            $this->error = 'Saving error';
            return false;
        }
        $this->file = new File($path . $name);
        return $name;

    }

    private function _load($input, $put = false)
    {
        if ($put && !is_array($input))
            $input = $this->_fromBase64($input);

        if (!$input) {
            $this->error = 'File non found!';
            return false;
        }

        $this->data = $put ? $input['data'] : $this->request->$input;
        $this->ext = $put
            ? last(
                explode(
                    '.',
                    $input['name']
                )
            )
            : $this->data->guessExtension();
        return true;
    }

    private function _fromBase64($data)
    {
        list($type, $data) = explode(';', $data);
        list($base64, $data) = explode(',', $data);
        list($start, $ext) = explode('/', $type);
        $data = base64_decode($data);

        $name = 'image.' . $ext;
        return [
            'data' => $data,
            'name' => $name
        ];
    }

    private function _getExt($data)
    {
        return 'image.' . last(explode('/', explode(';', $data)[0]));
    }

    public function _createDir($directory)
    {
        if (!is_dir($directory))
            if (mkdir($directory, 0777, true))
                return 1;
            else {
                $this->error = 'Permission denied: Create directory';
                return -1;
            }
        else
            return 0;
    }

    private function _imagesEach($callback, $original = true)
    {
        if ($original) {
            $this->config['sizes']['original'] = [
                'width' => 10000000,
                'height' => 10000000
            ];
        }
        foreach ($this->config['sizes'] as $size => $dim) {
            $callback($size, $dim);
        }
    }

    public function delete($name)
    {
        if ($this->isFile) {
            return $this->deleteFile($this->_getUrl($this->_getUri(), $this->config['storage_dir']) . $name);
        }
        $this->_imagesEach(function ($size) use ($name) {
            $this->deleteFile($this->_getUrl($this->_getUri($size), $this->config['storage_dir']) . $name);
        });
        return true;
    }

    public function deleteFile($file)
    {
        if (is_file($file) && file_exists($file))
            return unlink($file);

        return true;
    }

    private function _response($response = false)
    {
        return $response;
    }

}
