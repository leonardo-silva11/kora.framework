<?php
namespace kora\bin;

use kora\lib\exceptions\DefaultException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class DefaultResponseKora 
extends Response 
implements IMenssengerKora
{
    public function __construct(?string $content = '', int $status = 200, array $headers = [])
    {
        parent::__construct($content,$status,$headers);

        return $this;
    }

    public function send(): static
    {
       return parent::send();
    }

    public function parseThrowable(Throwable $th,array $headers = [], bool $interrupt = true)
    {
        $code = $th->getCode();
        $httpCode = is_int($code) && $code >= 100 && $code <= 599 ? $code : 500;

        $details = [];

        if($th instanceof DefaultException)
        {
            $details = $th->getDetails();
        }

        $file = mb_substr(strrchr($th->getFile(), DIRECTORY_SEPARATOR), 1);

        $this->content = json_encode(
            [
                'message' => $th->getMessage(),
                'file' => $file,
                'line' => $th->getLine(),
                'details' => $details
            ]
        );
        $this->statusCode = $httpCode;
        $this->headers->add($headers);
        $this->send();

        if($interrupt)
        {
            exit;
        }
    }
}