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
    'storage_dir' => 'storage',
    'upload_dir' => 'app/public/uploads',
    'prefix' => '',
    'limit' => 1000,
    'add_id_directory' => true,
    'before_limit' => true,
];
