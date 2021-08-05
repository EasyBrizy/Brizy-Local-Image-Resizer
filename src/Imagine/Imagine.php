<?php

namespace App\Imagine;

class Imagine
{
    const BASIC_CROP_TYPE    = 1;
    const ADVANCED_CROP_TYPE = 2;

    const CACHE_CONTROL_RESPONSE_HEADER_VALUE = 'max-age=2592000, public';

    const ORIGINAL_FILTER_NAME = 'original';

    const ADVANCED_FILTER_PATTERN = '/^iW=[0-9]{1,4}&iH=[0-9]{1,4}&oX=[0-9]{1,4}&oY=[0-9]{1,4}&cW=[0-9]{1,4}&cH=[0-9]{1,4}$/is';
    const BASIC_FILTER_PATTERN = '/^iW=[0-9]{1,4}&iH=([0-9]{1,4}|any|\*{1})$/is';


    static public function resizeableMimeTypes(): array
    {
        return [
            "image/pjpeg",
            "image/jpeg",
            "image/png",
            "image/x-png"
        ];
    }

    static public function normalizeOutput($output)
    {
        foreach ($output as $key => $value) {
            if ($key != 'ox' && $key != 'oy' && $value != "any" && $value != "*" && $value < 1) {
                $output[$key] = 1;
            }
        }

        return $output;
    }
}

