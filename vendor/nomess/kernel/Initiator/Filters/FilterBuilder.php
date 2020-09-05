<?php


namespace Nomess\Initiator\Filters;


use Nomess\Exception\MissingConfigurationException;

class FilterBuilder
{
    private const FILTERS           = ROOT . 'src/Filters/';

    public function build(): array
    {
        if(is_dir(self::FILTERS)) {
            $filters = scandir( self::FILTERS );
            $found   = array();
    
            foreach( $filters as $filter ) {
                if( $filter !== '.' && $filter !== '..' && $filter !== '.gitkeep' ) {
                    $filterName = 'App\\Filters\\' . str_replace( '.php', '', $filter );
                    $regex      = $this->getAnnotation( $filterName );
            
                    $found[$filterName] = $regex;
                }
            }
    
            return $found;
        }
        
        return [];
    }

    /**
     * @param string $classname
     * @return string
     * @throws MissingConfigurationException
     * @throws \ReflectionException
     */
    private function getAnnotation(string $classname): string
    {
        if(preg_match('/@Filter\("(.+)"\)/', (new \ReflectionClass($classname))->getDocComment(), $output)){
            return $output[1];
        }

        throw new MissingConfigurationException('You filter annotation is incomplete in ' . $classname);
    }
}
