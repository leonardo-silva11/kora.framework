<?php
namespace kora\lib\ViewTemplate;

use kora\lib\exceptions\DefaultException;

class Template
{
    private array $config = [];
    public TemplateReplace $replace; 

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->config['directorySeparator'] = DIRECTORY_SEPARATOR;
        $nameTemplate = $this->config['views']['currentTemplate'];
        $directoryView = $this->config['currentPage']['directoryView'];

        $this->config['viewsPath'] = "{$this->config['appPath']}{$this->config['directorySeparator']}views";
        $this->config['publicPath'] = "{$this->config['appPath']}{$this->config['directorySeparator']}public";
        $this->config['viewPath'] = "{$this->config['viewsPath']}{$this->config['directorySeparator']}{$directoryView}{$this->config['directorySeparator']}{$nameTemplate}";
        $this->config['templatePath'] = "{$this->config['publicPath']}{$this->config['directorySeparator']}templates{$this->config['directorySeparator']}{$nameTemplate}";
        $this->config['sectionsPath'] = "{$this->config['viewsPath']}{$this->config['directorySeparator']}sections{$this->config['directorySeparator']}{$nameTemplate}";

        if(!is_dir($this->config['viewPath']))
        {
            throw new DefaultException("{$nameTemplate}/{$this->config['cUrl']} not found in {$this->config['viewsPath']}!",404);
        }

        $this->config['pages'] = [];
        $this->config['pagesPath'] = [];

        $pageName = "{$this->config['currentPage']['action']}.{$this->config['views']['defaultPageExtension']}";
        $this->config['pageName'] = $pageName;
        $this->parsePages($pageName,'viewPath');
        $this->joinPages();

        $this->replace = new TemplateReplace($this);
    }

    private function parsePages(string $pageName, string $keyPathDirectory, string $parent = null, $tag = null)
    {
            $this->config['pagesPath'][$pageName] = "{$this->config[$keyPathDirectory]}/{$pageName}";
    
            if(!file_exists($this->config['pagesPath'][$pageName]))
            {
                throw new DefaultException("file {$pageName} not found in {$this->config[$keyPathDirectory]}!",404);
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
            $this->parsePages($matches[$i][1],'sectionsPath',$pageName,$matches[$i][0]);
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