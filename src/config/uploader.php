<?php

return [
    'sizes' => [
        'thumb' => [
            'width' => 150,
            'height' => 150
        ]
    ],
    'valid' => [
        'files' => ['pdf','doc','docx','odt', 'jpg', 'png', 'jpeg'],
        'images' => ['jpg','jpeg','png']
    ],
    'storage_dir' => 'public',
    'upload_dir' => 'upload',
    'files_dir' => 'files',
    'images_dir' => 'images',
    'prefix' => '',
    'limit' => 1000,
];