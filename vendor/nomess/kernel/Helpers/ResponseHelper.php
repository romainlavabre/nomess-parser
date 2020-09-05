<?php


namespace Nomess\Helpers;

use InvalidArgumentException;
use Nomess\Exception\NotFoundException;
use Nomess\Tools\Twig\Form\ComposeExtension;
use Nomess\Tools\Twig\Form\CsrfExtension;
use Nomess\Tools\Twig\Form\FieldExtension;
use Nomess\Tools\Twig\Form\ValueExtension;
use Nomess\Tools\Twig\PathExtension;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\Node\NodeOutputInterface;

trait ResponseHelper
{
    protected function response_code(int $code): void
    {
        http_response_code($code);
        
        $tabError = require ROOT . 'config/error.php';
        
        if(strpos($tabError[$code], '.twig') !== FALSE){
            if(file_exists(ROOT . 'templates/' . $tabError[$code])) {
                $this->bindTwigEngine($tabError[$code]);
            }
        }else{
            if(file_exists(ROOT . 'templates/' . $tabError[$code])) {
                $this->bindPHPEngine($tabError[$code]);
            }
        }
        die;
    }
    
    protected function bindTwigEngine(string $template, ?array $data = NULL) : void
    {
        $time = 0;
        
        if(NOMESS_CONTEXT === 'DEV') {
            $time = xdebug_time_index();
        }
        
        $loader = new FilesystemLoader(ROOT . 'templates');
        
        if(NOMESS_CONTEXT === 'DEV') {
            $engine = new Environment($loader, [
                'debug' => true,
                'cache' => false,
            ]);
            
            $engine->addExtension(new \Twig\Extension\DebugExtension());
        }else{
            $engine = new Environment($loader, [
                'cache' => ROOT . 'var/cache/twig/'
            ]);
        }
        
        $engine->addExtension(new PathExtension());
        $engine->addExtension(new CsrfExtension());
        $engine->addExtension(new ComposeExtension());
        
        if(is_array($data)) {
            $engine->addExtension( $valueExtension = new ValueExtension( $data['POST'] ) );
            $engine->addExtension( new FieldExtension( $valueExtension ) );
        }
        
        $this->addTwigExtension($engine);
        
        echo $engine->render($template, is_array($data) ? $data : []);
        
        if (NOMESS_CONTEXT === 'DEV') {
            $this->getDevToolbar($time);
        }
    }
    
    /**
     * Binds a php file to the response
     *
     * @param string $template
     * @return void
     */
    protected final function bindPHPEngine(string $template, ?array $param = NULL): void
    {
        $time = NULL;
        
        if(NOMESS_CONTEXT === 'DEV') {
            $time = xdebug_time_index();
        }
        
        echo require(ROOT . 'templates/' . $template);
        
        if(NOMESS_CONTEXT === 'DEV') {
            $this->getDevToolbar($time);
        }
        
    }
    
    /**
     *
     * Redirects to a local resource, if the forward method is called, pending operations
     * will be executed and the data will be presented in the following context
     *
     * @param string $routeName
     * @param array|null $parameters
     * @return void
     * @throws InvalidArgumentException
     * @throws NotFoundException
     */
    protected final function redirectToLocalResource(string $routeName, ?array $parameters): void
    {
        $routes = $this->getCacheRoute();
        
        foreach($routes as $key => $route){
            if($route['name'] === $routeName){
                if(strpos($key, '{') !== FALSE){
                    $sections = explode('/', $key);
                    
                    foreach($sections as &$section){
                        
                        if(strpos($section, '{') !== FALSE){
                            if(!empty($parameters) && array_key_exists(str_replace(['{', '}'], '', $section), $parameters)){
                                $section = $parameters[str_replace(['{', '}'], '', $section)];
                            }else{
                                throw new InvalidArgumentException('Missing an dynamic data in your url');
                            }
                        }
                    }
                    
                    $key = implode('/', $sections);
                }
                
                if(strpos($key, '{')){
                    throw new InvalidArgumentException('Missing an dynamic data in your url');
                }
                
                header("Location: $key");
                die;
            }
        }
        
        throw new NotFoundException("Your route $routeName has not found");
        
    }
    
    
    /**
     * Redirects to an external resource, if the forward method is called, pending operations will be executed
     *
     * @param string $url
     * @return void
     */
    protected final function redirectToOutsideResource(string $url): void
    {
        header("Location: $url");
    }
    
    /**
     * Return data
     *
     * @return array|null
     */
    protected final function sendData(): ?array
    {
        return $this->data;
    }
    
    private function getDevToolbar($time): void
    {
        $controller = NULL;
        $method = NULL;
        $action = NULL;
        
        if(isset($_SESSION['app']['toolbar'])) {
            $controller = $_SESSION['app']['toolbar']['controller'];
            $method = $_SESSION['app']['toolbar']['method'];
            $action = $_SERVER['REQUEST_METHOD'];
            
            unset($_SESSION['app']['toolbar']);
        }
        
        require ROOT . 'vendor/nomess/kernel/Tools/tools/toolbar.php';
    }
    
    private function getCacheRoute(): array
    {
        $filename = ROOT . 'var/cache/routes/route.php';
        
        if(file_exists($filename)){
            return require $filename;
        }else{
            throw new NotFoundException('Impossible to find the cache file of route');
        }
    }
    
    private function addTwigExtension(Environment $environment): void
    {
        $extensions = require ROOT . 'config/components/Twig.php';
        
        foreach($extensions as $extension){
            $environment->addExtension(new $extension());
        }
    }
}
