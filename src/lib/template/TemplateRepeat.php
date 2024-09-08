<?php
namespace kora\lib\template;
use kora\lib\exceptions\DefaultException;
use kora\lib\strings\Strings;

class TemplateRepeat
{
    private Template $template;
    private array $config = [];
    private string $newFile;


    public function __construct(Template $template)
    {
        $this->template = $template;
        $this->newFile = $template->getFile();
        $this->config = $template->getConfig();
        $this->config['repeat'] = [
            'file' => ''
        ];
    }

    private function repeat(string $key, array $values, string $r, string $paper, int $end = 0)
    {

        foreach($values as $k => $v)
        {

   
            if(!is_scalar($v))
            {
                $paper = $this->repeat($key, $v, $r, $paper, $end);
            }
            else
            {
              

                $end = $end == 0 ? count($values) : $end;
                $paper .= ($end == count($values))  ? $r : Strings::empty;

                $tag = "{{_repeat#_$key:$k}}";

                $paper = str_ireplace([$tag],[$v],$paper);
                --$end;
            }
        }

        return $paper;
    }

    public function exec(string $key, array $values)
    {
        $re = "/ \{\{@_repeat#_$key\}\}(.*)\{\{_repeat#_$key@\}\}/is";

        $matches = [];

        preg_match_all($re, $this->template->getFile(), $matches, PREG_SET_ORDER, 0);

        if(!empty($matches[0]) && array_key_exists(1,$matches[0]))
        {
            $r = $matches[0][1];
            $s = $matches[0][0];

            $paper = Strings::empty;

            $part = $this->repeat($key, $values, $r, $paper);

            $this->config['repeat']['file'] = str_ireplace([$s],[$part],$this->template->getFile());

            $this->template->update($this);
        }

    }

    public function getConfig()
    {
        return $this->config['repeat'];
    }
}