<?php

namespace Nomess\Initiator;


use Nomess\Container\Container;
use Nomess\Helpers\ResponseHelper;
use Nomess\Http\HttpRequest;
use Nomess\Http\HttpSession;
use Nomess\Initiator\Filters\FilterResolver;
use Nomess\Initiator\Route\RouteResolver;

class Initiator
{
    
    use ResponseHelper;
    
    private Container $container;
    private HttpRequest $request;
    
    
    public function __construct()
    {
        $this->container = new Container();
        $session         = $this->container->get( HttpSession::class );
        $session->initSession();
    }
    
    
    /**
     * @return mixed|void
     */
    public function initializer()
    {
        
        $arrayEntryPoint = $this->getRoute();
        $this->callFilters();
        
        if( $arrayEntryPoint !== NULL ) {
            
            if( $arrayEntryPoint['request_method'] === NULL || strpos( $arrayEntryPoint['request_method'], $_SERVER['REQUEST_METHOD'] ) !== FALSE ) {
                
                if( NOMESS_CONTEXT === 'DEV' ) {
                    $_SESSION['app']['toolbar'] = [
                        'controller' => basename( $arrayEntryPoint['controller'] ),
                        'method'     => $arrayEntryPoint['method']
                    ];
                }
                
                return $this->container->callController( $arrayEntryPoint['controller'], $arrayEntryPoint['method'] );
            }
            
            $this->response_code( 405 );
        }
        
        $this->response_code( 404 );
    }
    
    
    private function getRoute(): ?array
    {
        return $this->container->get( RouteResolver::class )->resolve();
    }
    
    
    private function callFilters(): void
    {
        $this->container->get( FilterResolver::class )->resolve( $_GET['p'] );
    }
}
