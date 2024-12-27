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

    public function getResponse($key)
    {
        $keysArray = explode('.',$key);
        $k = $keysArray[0];
        $search = array_key_exists($k, $this->response) ? 
        $this->response[$k] : 
        throw new DefaultException("{$k} is not a valid key for response intermediator, check the search query: {$key}!",400);
      
        for($i = 1; $i < count($keysArray); ++$i)
        {
            $k = $keysArray[$i];

            if(array_key_exists($k,$search))
            {
                $search = $search[$k];
            }
            else
            {
                throw new DefaultException("{$k} is not a valid key for response intermediator, check the search query: {$key}!",400);
            }
        }

        return $search;
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