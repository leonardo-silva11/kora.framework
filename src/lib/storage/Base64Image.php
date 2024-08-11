<?php
namespace kora\lib\storage;

use kora\lib\exceptions\DefaultException;
use kora\lib\strings\Strings;
use kora\lib\support\Guid;

class Base64Image
{
    private $image = [];
    public function __construct(string $imageBase64, $allowedMimes = [])
    {
        $this->image['allowedMimes'] = $allowedMimes;
        $this->image['original'] = $imageBase64;
        $this->image['ext'] = Strings::empty;
        $this->parseImage();
    }

    private function parseImage()
    {
        $this->image['modified'] = $this->image['original'];
      
        if (preg_match('/^data:image\/(\w+);base64,/', $this->image['original'], $result)) 
        {
            $this->image['modified'] = substr($this->image['original'], strpos($this->image['original'], ',') + 1);
            $this->image['ext'] = strtolower($result[1]);
        }

        $this->validateMimeImage();

        $this->image['binary'] =  base64_decode($this->image['modified']);
    }

    public function validateMimeImage()
    {
        $mimeType = MimeType::mimeTypeFromBase64($this->image['modified']);

        if(!array_key_exists($mimeType,$this->image['allowedMimes']))
        {
            throw new DefaultException("The Mime Type: {$mimeType} is not allowed!", 403);
        }

        $this->image['mime'] = $mimeType;
        $this->image['mimePrefix'] = explode('/',$this->image['mime'])[0];
        $this->image['mimeSufix'] = explode('/',$this->image['mime'])[1];
        $this->image['ext'] = $this->image['allowedMimes'][$mimeType];
    }
    

    public function resize(FileManager $fileManager,$nameImage = null, int $newWidth, $newHeight = null)
    {
        $image = \imagecreatefromstring($this->image['binary']);

        if ($image === false) {
            throw new DefaultException("Error while resize image, failed ocurred in function {imagecreatefromstring}!",403);
        }

        $newHeight = is_int($newHeight) ? $newHeight : $newWidth;

        list($width, $height) = getimagesizefromstring($this->image['binary']);


        $resizedImage = imagecreatetruecolor($newWidth, $newHeight);

        imagecopyresampled($resizedImage, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        $ext = $this->image['ext'];
        $sufix = $this->image['mimeSufix'];
        $method = "image$sufix";
        $this->image['nameImage'] = !empty($nameImage) ? $nameImage : Guid::generateGUID();
        $this->image['nameImageExt'] = $this->image['nameImage'].".$ext";
        $path = $fileManager->Storage->getCurrentStorage().DIRECTORY_SEPARATOR.$this->image['nameImageExt'];
        
       
        $method($resizedImage,$path);

        imagedestroy($image);
        imagedestroy($resizedImage);
    }

    public function delete(FileManager $fileManager)
    {
        $fileManager->remove($this->image['nameImageExt']);
    }

    public function getImage() : array
    {
        return $this->image;
    }
}