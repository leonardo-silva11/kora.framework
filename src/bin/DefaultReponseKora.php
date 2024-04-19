<?php
namespace kora\bin;

use Symfony\Component\HttpFoundation\Response;

class DefaultResponseKora 
extends Response 
implements IMenssengerKora
{
    public function __construct(?string $content = '', int $status = 200, array $headers = [])
    {
        parent::__construct($content,$status,$headers);
    }

    public function send(): static
    {
       return parent::send();
    }
}