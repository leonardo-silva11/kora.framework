<?php
namespace kora\lib\ViewTemplate;

use kora\lib\exceptions\DefaultException;

class TemplateReplace
{
    private Template $template;
    private array $config = [];


    public function __construct(Template $template)
    {
        $this->template = $template;
        $this->config = $template->getConfig();
        $this->config['replace'] = [
            'from' =>[],
            'to' => [],
            'replacement' => [],
            'tagsReplacement' => [],
            'file' => ''
        ];
    }

    public function add($key)
    {
        if(!empty($key))
        {
            dd($this->template->getFile());
        }
    }


    public function addAll(array $listReplacement)
    {
       // dd($listReplacement);
        foreach($listReplacement as $k => $v)
        {
             if(!empty($k) && is_scalar($v))
             {
                    $tag = "{{replace#$k}}";
                    array_push($this->config['replace']['from'],$tag);
                    array_push($this->config['replace']['to'],$v);
                    $this->config['replace']['replacement'][$k] = [
                        'tag' => $tag,
                        'val' => $v 
                    ];
             }
        }
    }

    public function replace()
    {
        $re = '`\{\{replace#(.*?)\}\}`m';
        preg_match_all($re,$this->template->getFile(), $matches, PREG_SET_ORDER, 0);


        $replacement = [
            'from' => [],
            'to' => []
        ];

        $this->config['replace']['tagsReplacement'] = array_unique($matches, SORT_REGULAR);


        foreach($this->config['replace']['tagsReplacement'] as $tag)
        {
            if(array_key_exists($tag[1],$this->config['replace']['replacement']))
            {
                array_push($replacement['from'],$this->config['replace']['replacement'][$tag[1]]['tag']);
                array_push($replacement['to'],$this->config['replace']['replacement'][$tag[1]]['val']);
            }
        }

        $this->config['replace']['file'] = str_ireplace($replacement['from'],$replacement['to'],$this->template->getFile());

        $this->template->update($this);
    }   

    public function getConfig()
    {
        return $this->config['replace'];
    }
}