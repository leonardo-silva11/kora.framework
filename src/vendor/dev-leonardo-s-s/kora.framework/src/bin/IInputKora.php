<?php
namespace kora\bin;

interface IInputKora
{
    public function validate(mixed $arg = null): void;
}