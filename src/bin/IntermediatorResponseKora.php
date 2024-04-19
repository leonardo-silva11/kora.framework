<?php
namespace kora\bin;

use kora\lib\exceptions\DefaultException;
use ReflectionMethod;
use ReflectionObject;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class IntermediatorResponseKora implements IMenssengerKora
{
    private array $config;
    private array $response;
    private Request $Request;
    private ReflectionObject $refIntermediator;
    private IntermediatorKora $instIntermediator;
    private ReflectionMethod $refMethod;

    public function __construct
    (
        array $data, 
        array $config, 
        BagKora $filterResponseKoraAfter, 
        Request $Request,
        IntermediatorKora $Intermediator
    )
    {
        $this->config = $config;
        $this->Request = $Request;
        $this->instIntermediator = $Intermediator;
        $this->refIntermediator = new ReflectionObject($Intermediator);
        
        $this->makeReponse($data,$config,$filterResponseKoraAfter);


    }

    private function makeReponse
        (
            $data,
            $filterResponseKoraAfter
        )
    {
        $santize = 
        [
            'clientCredentials'
        ];

        foreach($santize as $item)
        {
            if(array_key_exists($item,$this->config))
            {
                unset($this->config[$item]);
            }
        }


        $this->response =  [
            'data' => $data,
            'config' => $this->config,
            'filter' => $filterResponseKoraAfter
        ];
    }

    public function isRequestAjax()
    {
        return $this->Request->isXmlHttpRequest();
    }

    public function getJsonReponse()
    {
        $ResponseJson = new Response(json_encode($this->response['data']),$this->instIntermediator->getCode());
        $ResponseJson->headers->set('Content-Type', 'application/json');
        return $ResponseJson->send();
    }

    public function reponseView
    (
        string $nameMethod
    )
    {
     
        $this->refMethod = $this->refIntermediator->getMethod($nameMethod);
        $paramMethod = $this->refMethod->getParameters();

        if($paramMethod[0]->getType() != $this::class)
        {
            throw new DefaultException
                    (sprintf("the method {%s::%s} must provide for receiving parameter {%s}, {%s} given!",
                        $this->refIntermediator->getName(),
                        $nameMethod,
                        $this::class,
                        $paramMethod[0]->getType()
                    ),404);
        }
        
        $this->refMethod->invokeArgs($this->instIntermediator,[$this]);

        return $this;
       
    }

    public function getReponse($key)
    {
        return 
        array_key_exists($key,$this->response) ? 
        $this->response[$key] 
        : throw new DefaultException("{$key} is not a valid key for reponse intermediator!",400);
    }

    public function getAll()
    {
        return $this->response;
    }

    public function send()
    {
        if(!$this->isRequestAjax())
        {
            $nameMethod = $this->config['currentPage']['action'];

            return $this->reponseView
            (
                $nameMethod
            );
        }
      
        return $this->getJsonReponse();
    }

}