<?php


namespace Nomess\Component\Parser;


use ReflectionClass;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionProperty;

class AnnotationParser implements AnnotationParserInterface
{
    
    private const ARRAY_LINE     = '|';
    private const ARRAY_KEY_PAIR = '-@-';
    
    
    /**
     * @param string $annotation
     * @param ReflectionProperty|ReflectionClass|ReflectionFunction|ReflectionMethod $reflection
     * @return bool
     */
    public function has( string $annotation, $reflection ): bool
    {
        return strpos( $reflection->getDocComment(), "@$annotation" ) !== FALSE;
    }
    
    
    /**
     * @param string $annotation
     * @param ReflectionProperty|ReflectionClass|ReflectionFunction|ReflectionMethod $reflection
     * @return string|null
     */
    public function grossValue( string $annotation, $reflection ): ?string
    {
        if( ( $line = $this->getLine( $annotation, $reflection ) ) !== NULL ) {
            return trim( str_replace( [ '@', $annotation, '(', ')', '[]' ], '', $line ) );
        }
        
        return NULL;
    }
    
    
    /**
     * @param string $annotation
     * @param ReflectionProperty|ReflectionClass|ReflectionFunction|ReflectionMethod $reflection
     * @return array
     */
    public function getValue( string $annotation, $reflection ): array
    {
        $result = [];
        
        if( preg_match( '/' . $annotation . '\((.*)\)/i', $reflection->getDocComment(), $output ) ) {
            
            $output[1] = $this->compatibilityMultipleArray( $output[1]);
            
            $sections = explode( ',', $output[1] );
            if( is_array( $sections ) ) {
                foreach( $sections as $section ) {
                    
                    if( strpos( $section, '=' ) !== FALSE ) {
                        $pair = explode( '=', $section );
                        
                        $result[$key = $this->convertToString( $pair[0] )] = NULL;
                        
                        if( preg_match( '/^".*"$/', $pair[1] ) ) {
                            $result[$key] = $this->convertToString( $pair[1] );
                        } elseif( strpos( mb_strtolower( $pair[1] ), 'true' ) !== FALSE
                                  || strpos( mb_strtolower( $pair[1] ), 'false' ) ) {
                            
                            $result[$key] = (bool)$pair[1];
                        } elseif( preg_match( '/^ *[0-9]+ *$/', $pair[1] ) ) {
                            $result[$key] = (integer)$pair[1];
                        } elseif( preg_match( '/^ *[0-9]+\.[0-9]+ *$/', $pair[1] ) ) {
                            $result[$key] = (double)$pair[1];
                        } else {
                            
                            $pair[1] = preg_replace( '/^\{/', '', $pair[1]);
                            $pair[1] = preg_replace( '/}$/', '', $pair[1]);
                            $array   = explode( self::ARRAY_LINE, $pair[1] );
                            
                            if( is_array( $array ) ) {
                                foreach( $array as $item ) {
                                    $item = $this->convertToPair( $item );
                                    
                                    if( is_int( key( $item ) ) ) {
                                        $result[$key][] = current( $item );
                                    } else {
                                        $result[$key][key( $item )] = current( $item );
                                    }
                                }
                            } else {
                                $result[$key] = $this->convertToPair( $array );
                            }
                        }
                    } else {
                        $result[] = $this->convertToString( $section );
                    }
                }
            } else {
                $result[] = $this->convertToString( $sections );
            }
        } else {
            if( ( $value = $this->grossValue( $annotation, $reflection ) ) !== NULL ) {
                $result[] = $value;
            } else {
                return $result;
            }
        }
        
        return $result;
    }
    
    
    private function getLine( string $annotation, $reflection ): ?string
    {
        if( !$this->has( $annotation, $reflection ) ) {
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
    
    
    private function convertToString( string $value ): string
    {
        return trim( str_replace( '"', '', $value ) );
    }
    
    
    private function convertToPair( string $value ): array
    {
        if( strpos( $value, self::ARRAY_KEY_PAIR ) !== FALSE ) {
            $subPair = explode( self::ARRAY_KEY_PAIR, $value );
            
            if( is_array( $subPair ) ) {
                
                return [ $this->convertToString( $subPair[0] ) => $this->convertToString( $subPair[1] ) ];
            }
        }
        
        return [ $this->convertToString( $value ) ];
    }
    
    private function compatibilityMultipleArray(string $str): string
    {
        $str = preg_split( '/=\{/', $str);
        
        $str = preg_replace_callback( '/.*}/', function (array $found){
            return str_replace( ',', self::ARRAY_LINE, $found[0]);
        }, $str);
        
        return str_replace( '=>', self::ARRAY_KEY_PAIR, implode( '={', $str) );
    }
}
