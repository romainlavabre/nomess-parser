<?php


namespace Nomess\Component\Parser;


interface ParserInterface
{
    
    /**
     * @param string $filename
     * @return mixed
     */
    public function parse(string $filename);
}
