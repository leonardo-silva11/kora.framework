<?php
namespace kora\bin;

use kora\lib\exceptions\DefaultException;
use ReflectionObject;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class IntermediatorResponseKora
{
    private array $response;
    private Request $Request;
    public function __construct(array $data, array $config, BagKora $filterResponseKoraAfter, Request $Request)
    {
        $this->Request = $Request;

        $this->response =  [
            'data' => $data,
            'config' => $config,
            'filter' => $filterResponseKoraAfter
        ];
    }

    public function isRequestAjax()
    {
        return $this->Request->isXmlHttpRequest();
    }

    public function getJsonReponse()
    {
        $ResponseJson = new Response(json_encode($this->response['data']));
        $ResponseJson->headers->set('Content-Type', 'application/json');
        return $ResponseJson;
    }

    public function reponseView
    (
        ReflectionObject $refIntermediator,
        string $nameMethod

    )
    {
        $refMethod = $refIntermediator->getMethod($nameMethod);
        $paramMethod = $refMethod->getParameters();

        if($paramMethod[0]->getType() != $this::class)
        {
            throw new DefaultException
                    (sprintf("the method {%s::%s} must provide for receiving parameter {%s}, {%s} given!",
                        $refIntermediator->getName(),
                        $nameMethod,
                        $this::class,
                        $paramMethod[0]->getType()
                    ),404);
        }

        $refMethod->invokeArgs($refIntermediator->newInstance(),[$this]);
    }

    public function getReponse($key)
    {
        return array_key_exists($key,$this->response) ? $this->response[$key] : throw new DefaultException("{$key} is not a valid key for reponse intermediator!",400);
    }

}