<?php

namespace Nomess\Initiator\Route;

use Nomess\Internal\Scanner;

class RouteResolver
{
    use Scanner;
    
    private const CACHE     = ROOT . 'var/cache/routes/route.php';
    
    public function resolve(): ?array
    {
        $routes = $this->getCache();
        
        if($routes === NULL){
            $routes = (new RouteBuilder())->build();
            $this->setCache($routes);
        }
        
        
        foreach($routes as $key => $route){
            
            
            if($key === '/' . $_GET['p']){
                return $route;
            }
            
            $arrayRoute = explode('/', $key);
            $arrayUrl = explode('/', $_GET['p']);
            
            unset($arrayRoute[0]);
            
            $success = TRUE;
            $i = 0;
            
            foreach($arrayRoute as $key => $section){
                if(!empty($section)) {
                    if( strpos( $section, '{' ) === FALSE ) {
                        
                        if( isset( $arrayUrl[$i] ) ) {
                            if( strpos( $arrayUrl[$i], '?' ) !== FALSE ) {
                                $arrayUrl[$i] = explode( '?', $arrayUrl[$i] )[0];
                            }
                            
                            if( strpos( $arrayUrl[$i], '&' ) !== FALSE ) {
                                $arrayUrl[$i] = explode( '&', $arrayUrl[$i] )[0];
                            }
                            
                            if( ( isset( $arrayUrl[$i] ) && $section !== $arrayUrl[$i] )
                                || !isset( $arrayUrl[$i] ) ) {
                                
                                $success = FALSE;
                                break 1;
                            }
                        } else {
                            $success = FALSE;
                            break 1;
                        }
                    } else {
                        if( empty( $arrayUrl[$i] ) ) {
                            $success = FALSE;
                            break 1;
                        }
                        
                        $sectionPurged = $this->getIdSection( $section );
                        
                        if( isset( $route['requirements'][$sectionPurged] ) ) {
                            if( !preg_match( '/' . $route['requirements'][$sectionPurged] . '/', $arrayUrl[$i] ) ) {
                                $success = FALSE;
                                break 1;
                            }
                        }
                        
                        $_GET[$sectionPurged] = $arrayUrl[$i];
                    }
                    
                    unset($arrayUrl[$i]);
                    $i++;
                }else{
                    $success = FALSE;
                    break 1;
                }
                
            }
            
            if($success === TRUE && empty($arrayUrl)){
                return $route;
            }
        }
        
        return NULL;
    }
    
    private function getCache(): ?array
    {
        if(NOMESS_CONTEXT === 'PROD' && file_exists(self::CACHE)){
            return require self::CACHE;
        }
        
        return NULL;
    }
    
    private function setCache(array $routes): void
    {
        file_put_contents(self::CACHE, '<?php return unserialize(\'' . serialize($routes) . '\');');
    }
    
    private function getIdSection(string $section): string
    {
        return str_replace(['{', '}'], '', $section);
    }
    
}
