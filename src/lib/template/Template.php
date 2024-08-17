<?php
namespace kora\lib\template;

use kora\lib\exceptions\DefaultException;

class Template
{
    private array $config = [];
    private $dirSep = DIRECTORY_SEPARATOR;
    public TemplateReplace $replace; 

    public function __construct(array $config)
    {
        $this->config = $config;
   
        $this->config['paths']['view'] = "{$this->config['paths']['views']}{$this->dirSep}{$this->config['currentPage']['directoryView']}";
        $this->config['paths']['template'] = "{$this->config['paths']['public']}{$this->dirSep}templates{$this->dirSep}{$this->config['currentPage']['template']}";
        $this->config['paths']['section'] = "{$this->config['paths']['views']}{$this->dirSep}sections{$this->dirSep}{$this->config['currentPage']['template']}";
        $this->config['paths']['viewTemplate'] = "{$this->config['paths']['view']}{$this->dirSep}{$this->config['currentPage']['template']}";

        if(!is_dir($this->config['paths']['viewTemplate']))
        {
            throw new DefaultException("Directory {{$this->config['currentPage']['directoryView']}/{$this->config['currentPage']['template']}} not found in {{$this->config['paths']['views']}}!",404);
        }
      
        $this->config['pages'] = [];
        $this->config['pagesPath'] = [];

        $pageName = "{$this->config['currentPage']['action']}.{$this->config['settings']['views']['defaultPageExtension']}";
        $this->config['pageName'] = $pageName;
        $this->parsePages($pageName,['paths','viewTemplate']);
        $this->joinPages();
    
        $this->replace = new TemplateReplace($this);
    }


    private function find(mixed $keys)
    {
        if(!is_array($keys))
        {
            $keys = explode('.',$keys);
        }

        $k = 0;

        $val = $this->config;

        while(array_key_exists($k,$keys) && array_key_exists($keys[$k],$val))
        {
            $val = $val[$keys[$k]];

            ++$k;
        }

        return $val;
    }

    private function parsePages(string $pageName, array $keys, string $parent = null, $tag = null)
    {
            $pathPage = $this->find($keys);

            $this->config['pagesPath'][$pageName] = "{$pathPage}/{$pageName}";
    
            if(!file_exists($this->config['pagesPath'][$pageName]))
            {
                throw new DefaultException("file {$pageName} not found in {$pathPage}!",404);
            }
     
            $this->config['pages'][$pageName] = [
                                                    'key' => sha1($pageName),
                                                    'parent' => $parent,
                                                    'tag' => $tag,
                                                    'pageName' => $pageName,
                                                    'path' => $this->config['pagesPath'][$pageName],
                                                    'file' => file_get_contents($this->config['pagesPath'][$pageName])
                                                ];
                               
        

        $re = '`\{\{section#(.*)\}\}`m';
        preg_match_all($re, $this->config['pages'][$pageName]['file'], $matches, PREG_SET_ORDER, 0);
 
        for($i = 0; $i < count($matches); ++$i)
        {
            $this->parsePages($matches[$i][1],['paths','section'],$pageName,$matches[$i][0]);
        }
    }

    private function joinPages()
    {
        $pages = array_reverse($this->config['pages'], true);

        foreach($pages as $page)
        {
            if(!empty($page['parent']) && array_key_exists($page['parent'],$this->config['pages']))
            {

                $this->config['pages'][$page['parent']]['file'] = 
                        str_ireplace
                        (
                            [$page['tag']],
                            [$this->config['pages'][$page['pageName']]['file']],
                            $this->config['pages'][$page['parent']]['file']
                        );        
            }
        }
    }

    public function getConfig()
    {
        return $this->config;
    }

    public function update(TemplateReplace $replace)
    {
        $config = $replace->getConfig();
        $this->config['pages'][$this->config['pageName']]['file'] = $config['file'];
    }

    public function getFile()
    {

        return array_key_exists($this->config['pageName'],$this->config['pages']) 
                &&
                array_key_exists('file',$this->config['pages'][$this->config['pageName']]) ? 
                $this->config['pages'][$this->config['pageName']]['file']
                :
                throw new DefaultException('Template not found',404);
    }

    public function show()
    {
        $template = $this->getFile();
        print($template);
    }
}