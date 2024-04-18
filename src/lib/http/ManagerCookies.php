<?php
namespace kora\lib\http;

use kora\lib\exceptions\DefaultException;
use kora\lib\strings\Strings;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ManagerCookies
{
    private Request $Request;

    public function __construct(Request $Request)
    {
        $this->Request = $Request;
    }

    public function exists($key)
    {
        return !empty($this->get($key));
    }

    public function get($key)
    {
        if(empty($key))
        {
            throw new DefaultException('The cookie key is null or empty!',500);
        }

        return $this->Request->cookies->get($key);
    }

    public function create(string $key,mixed $value, array $options)
    {
        if(!$this->exists($key))
        {
            $options['expire'] = !empty($options['expire']) ? intval($options['expire']) : time() + 3600;
            $options['path'] = !empty($options['path']) ? (string)$options['path'] : Strings::slash;
            $options['domain'] = !empty($options['domain']) ? (string)$options['domain'] : null;
            $options['secure'] = !empty($options['secure']) ? (bool)$options['secure'] : false;
            $options['httpOnly'] = !empty($options['httpOnly']) ? (bool)$options['httpOnly'] : false;
            $options['raw'] = !empty($options['raw']) ? (bool)$options['raw'] : false;
            $options['sameSite'] = !empty($options['sameSite']) ? (string)$options['sameSite'] : Cookie::SAMESITE_LAX;
            $options['partioned'] = !empty($options['partioned']) ? (bool)$options['partioned'] : false;

            $Response = new Response();
            $newCookie = new Cookie(
                $key, // Nome do cookie
                $value, // Valor do cookie
                $options['expire'], // Tempo de expiração (uma hora a partir de agora)
                $options['path'], // Caminho (disponível em todo o site)
                $options['domain'], // Domínio
                $options['secure'], // Secure (transmissão apenas via HTTPS)
                $options['httpOnly'], // HTTP Only (acessível apenas via solicitações HTTP)
                $options['raw'], // raw 
                $options['sameSite'], //SameSite (não especificado, navegador decide)
                $options['partioned']
            );

            $Response->headers->setCookie($newCookie);
            $Response->send();
        }
    }
}