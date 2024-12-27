<?php
namespace kora\lib\exceptions;

class DefaultException extends \Exception
{
    protected $message;
    protected $code;
    protected $details = ['info' => 'no further details'];

    public function __construct(string $message, int $code = 0, array $details = [])
    {
        parent::__construct($message, $code);
        $this->details = !empty($details) ? $details : $this->details;
    }

    public function getDetails(): array
    {
        return $this->details;
    }

    // Métodos getters e setters adicionais, se necessário
}
