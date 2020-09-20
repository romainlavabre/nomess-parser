<?php


namespace Nomess\Component\Parser;


use ReflectionClass;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionProperty;

class AnnotationParser implements AnnotationParserInterface
{
    
    /**
     * @param string $annotation
     * @param ReflectionProperty|ReflectionClass|ReflectionFunction|ReflectionMethod $reflection
     * @return bool
     */
    public function has( string $annotation, $reflection ): bool
    {
        return strpos( $reflection->getDocComment( $reflection ), "@$annotation" ) !== FALSE;
    }
    
    
    /**
     * @param string $annotation
     * @param ReflectionProperty|ReflectionClass|ReflectionFunction|ReflectionMethod $reflection
     * @return string|null
     */
    public function grossValue( string $annotation, $reflection ): ?string
    {
        if(($line = $this->getLine( $annotation, $reflection)) !== NULL){
            return str_replace( ['@', $annotation, '(', ')', '[]'], '', $line);
        }
        
        return NULL;
    }
    
    
    /**
     * @param string $annotation
     * @param ReflectionProperty|ReflectionClass|ReflectionFunction|ReflectionMethod $reflection
     * @return array
     */
    public function getValue(string $annotation, $reflection): array
    {
        $result = [];
        
        if(preg_match( '/.*\((.*)\)/', $reflection->getDocComment(), $output)){
            $sections = explode( ',', $output[1]);
            
            if(is_array( $sections)){
                foreach($sections as $section){
                    if(strpos( $section, '=') !== FALSE){
                        $pair = explode( '=', $section);
                        
                        $result[$key = $this->convertToString( $pair[0])] = NULL;
                        
                        if(preg_match( '/".*"/', $pair[1])){
                            $result[$key] = $this->convertToString( $pair[1]);
                        }else{
                            $array = explode( ',', $pair[1]);
                            
                            if(is_array( $array)){
                                foreach($array as $item){
                                    $item = $this->convertToPair( $item);
                                    
                                    $result[$key][key($item)] = current( $item);
                                }
                            }else{
                                $result[$key] = $this->convertToPair( $array);
                            }
                        }
                    }else{
                        $result[] = $this->convertToString( $section);
                    }
                }
            }else{
                $result[] = $this->convertToString( $sections);
            }
        }else{
            $result[] = $this->grossValue( $annotation, $reflection);
        }
        
        return $result;
    }
    
    private function getLine(string $annotation, $reflection): ?string
    {
        if( !$this->has( "$annotation", $reflection ) ) {
            return NULL;
        }
    
        $brokenComment = explode( '*', $reflection->getDocComment() );
    
        foreach( $brokenComment as $line ) {
            if( strpos( $line, "@$annotation" ) !== FALSE ) {
                return trim( str_replace( '*', '', $line ) );
            }
        }
        
        return NULL;
    }
    
    private function convertToString(string $value): string
    {
        return trim( str_replace( '"', '', $value));
    }
    
    private function convertToPair(string $value): array
    {
        $subPair = explode( '=>', $value);
    
        if(is_array( $subPair)) {
            return [$this->convertToString( $subPair[0] ) => $this->convertToString( $subPair[1] )];
        }
        
        return [$this->convertToString( $subPair)];
        
    }
}
