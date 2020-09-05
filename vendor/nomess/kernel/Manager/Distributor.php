<?php

namespace Nomess\Manager;

use InvalidArgumentException;
use Nomess\Annotations\Inject;
use Nomess\Components\LightPersists\LightPersists;
use Nomess\Container\Container;
use Nomess\Exception\NotFoundException;
use Nomess\Helpers\ResponseHelper;
use Nomess\Http\HttpRequest;
use Nomess\Http\HttpResponse;
use Throwable;

abstract class Distributor
{

    use ResponseHelper;

    private const COMPONENT_CONFIGURATION           = ROOT . 'config/components.php';

    private const BASE_ENVIRONMENT                  = ROOT . 'templates';
    private const CACHE_TWIG                        = ROOT . 'var/cache/twig/';
    private const SESSION_DATA                      = 'nomess_persiste_data';

    const DEFAULT_DATA                              = 'php';
    const JSON_DATA                                 = 'json';


    private const SESSION_NOMESS_SCURITY            = 'nomess_session_security';

    private $engine;
    private HttpRequest $request;
    private HttpResponse $response;
    private ?array $form;


    /**
     * @Inject()
     */
    protected Container $container;

    private ?array $observer = array();

    private $data;


    /**
     * forward data and pending operation
     *
     *
     * @param HttpRequest|null $request
     * @param HttpResponse|null $response
     * @param string $dataType
     *
     * @return self
     */
    protected final function forward(?HttpRequest $request, ?HttpResponse $response, string $dataType = self::DEFAULT_DATA): self
    {

        if ($request !== null) {
            $this->data = $request->getParameters();

            unset($_SESSION[self::SESSION_NOMESS_SCURITY]);

            if ($dataType === 'json') {
                $this->data = json_encode($this->data);
            }
        }

        if ($response !== null) {
            $this->response = $response;
            $this->response->manage();
        }

        return $this;
    }


    /**
     *
     * Redirects to a local resource, if the forward method is called, pending operations
     * will be executed and the data will be presented in the following context
     *
     * @param string $routeName
     * @param array|null $parameters
     * @return self
     * @throws InvalidArgumentException
     * @throws NotFoundException
     */
    protected final function redirectLocal(string $routeName, ?array $parameters = NULL): self
    {
        if(isset($this->data)){
            unset($this->data['POST'], $this->data['GET']);
            $_SESSION[self::SESSION_DATA] = $this->data;
        }

        $this->redirectToLocalResource($routeName, $parameters);

        return $this;
    }


    /**
     * Redirects to an external resource, if the forward method is called, pending operations will be executed
     *
     * @param string $url
     * @return self
     */
    protected final function redirectOutside(string $url): self
    {
        $this->redirectToOutsideResource($url);

        return $this;
    }


    /**
     * Return an status code
     *
     * @param int $code
     */
    protected final function statusCode(int $code): void
    {
        $this->response_code($code);
    }


    /**
     * Binds the twig model engine to the response
     *
     * @param string $template
     * @return self
     */
    protected final function bindTwig(string $template): self
    {

        $this->bindTwigEngine(
            $template,
            $this->data);

        return $this;
    }


    /**
     * Binds a php file to the response
     *
     * @param string $template
     * @return Distributor
     */
    protected final function bindDefaultEngine(string $template): self
    {
        $this->bindPHPEngine(
            $template,
            (isset($this->data)) ? $this->data : NULL);

        return $this;
    }



    /**
     * Return data of request if forwarded or data passed by parameters
     *
     * @return Distributor
     */
    protected final function sendData($data = NULL): Distributor
    {
        if(isset($this->data)) {
            echo $this->data;
        }else{
            echo $data;
        }
        
        return $this;
    }


    /**
     * Kill the current process
     */
    protected function stopProcess(): void
    {
        die;
    }
}
