<?php


namespace Nomess\Component\Parser;


use Nomess\Component\Parser\Exception\ParseException;

class YamlParser implements YamlParserInterface
{
    
    /**
     * @inheritDoc
     */
    public function parse( string $filename )
    {
        $value = yaml_parse_file( $filename );
        
        if(is_array($value)){
            return $this->parseArray($value);
        }elseif(is_string($value)){
            return $this->parseString($value);
        }
        
        if($value === false){
            throw new ParseException('The parser encountered an error with file "' . $filename . '"');
        }
        
        return $value;
    }
    
    /**
     * Replace variable of str
     *
     * @param array $array
     * @return array
     */
    private function parseArray( array $array ): array
    {
        array_walk_recursive( $array, function ( &$value ) {
            if( is_string( $value ) ) {
                if( strpos( $value, '%ROOT%' ) !== FALSE ) {
                    $value = str_replace( '%ROOT%', ROOT, $value );
                }
            }
        } );
        
        return $array;
    }
    
    private function parseString(string $str): string
    {
        return str_replace( '%ROOT%', ROOT, $str );
    }
}
