<?php
namespace kora\lib\exceptions;

class InputException extends DefaultException
{
    protected $message;
    protected $code;
    protected $details = ['info' => 'no further details'];

    public function __construct(string $message, int $code = 0, array $details = [])
    {
        parent::__construct($message, $code,$details);
    }
}
