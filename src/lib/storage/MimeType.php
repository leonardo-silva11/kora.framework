<?php
namespace kora\lib\storage;

class MimeType
{
    public static function mimeTypeFromBynary($binary)
    {
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->buffer($binary);

        return $mime;
    }

    public static function mimeTypeFromBase64($base64)
    {
        if (preg_match('/^data:image\/(\w+);base64,/', $base64, $result)) 
        {
            $base64 = substr($base64, strpos($base64, ',') + 1);
        }

        $base64 = base64_decode($base64);

        return MimeType::mimeTypeFromBynary($base64);
    }
}