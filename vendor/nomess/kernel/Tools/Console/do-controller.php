<?php

require 'function-Installer.php';

(new Controller())->generator();

class Controller
{

    /**
     * Path of file
     *
     * @var string
     */
    private $dir;

    /**
     * Controller name
     *
     * @var string
     */
    private $controller;



    public function generator() : void
    {
        $dir = rdl("Precise path beginning 'App/src/Controller/', void for racine: ");

        if($dir !== null){
            $tabDir = explode('/', $dir);

            $dir = null;

            foreach($tabDir as $value){
                $dir = $dir . $value . '/';
            }

            $this->dir = $dir;
        }

        do{
            do {
                $controllerName = rdl("Precise name of controller: ");
            }while(empty($this->controller = $controllerName));

            file_put_contents('src/Controllers/' . $this->dir . ucfirst($this->controller) . 'Controller.php', $this->getContent());

            $restart = rdl("Pursue ? [N: n | O: Enter] ");

            if($restart === null){
                $restart = true;
            }else{
                $restart = false;
            }
        }while($restart === true);
    }

    private function getNamespace() : string
    {

        $namespace = 'App\Controllers';

        $tabDir = explode('/', $this->dir);

        foreach($tabDir as $value){
            if(!empty($value)){
                $namespace .= '\\' . ucfirst($value);
            }
        }

        return $namespace;
    }


    private function getContent() : string
    {
        $content = "<?php

namespace " . $this->getNamespace() . ";

use Nomess\Http\HttpResponse;
use Nomess\Http\HttpRequest;
use Nomess\Manager\Distributor;
use Nomess\Annotations\Route;
use Nomess\Components\EntityManager\EntityManagerInterface;
use Nomess\Annotations\Inject;


/**
 * @Route(\"/" . mb_strtolower($this->controller) . "\") 
 */
class " . ucfirst($this->controller) . "Controller extends Distributor
{
    
    /**
     * @Inject()
     */
    private EntityManagerInterface \$entityManager;

    /**
     * @Route(\"/\", name=\"" . mb_strtolower($this->controller) . ".index\", methods=\"GET\")
     * @param HttpRequest \$request
     * @param HttpResponse \$response
     * @return " . ucfirst($this->controller) . "Controller|Distributor|array
     */
    public function index(HttpResponse \$response, HttpRequest \$request)
    {
        return \$this->forward(\$request, \$response)->bindTwig(\$this->getTemplate('index'));
    }

    /**
     * @Route(\"/{id}\", name=\"" . mb_strtolower($this->controller) . ".show\", methods=\"GET\", requirements=[\"id\" => \"[0-9]+\"])
     * @param HttpRequest \$request
     * @param HttpResponse \$response
     * @return " . ucfirst($this->controller) . "Controller|Distributor|array
     */
    public function show(HttpResponse \$response, HttpRequest \$request)
    {
        return \$this->forward(\$request, \$response)->bindTwig(\$this->getTemplate('show'));
    }
    
    /**
     * @Route(\"/create\", name=\"" . mb_strtolower($this->controller) . ".create\", methods=\"GET,POST\")
     * @param HttpRequest \$request
     * @param HttpResponse \$response
     * @return " . ucfirst($this->controller) . "Controller|Distributor|array
     */
    public function create(HttpResponse \$response, HttpRequest \$request)
    {
        return \$this->forward(\$request, \$response)->bindTwig(\$this->getTemplate('create'));
    }
    
    /**
     * @Route(\"/edit/{id}\", name=\"" . mb_strtolower($this->controller) . ".edit\", methods=\"GET,POST\")
     * @param HttpRequest \$request
     * @param HttpResponse \$response
     * @return " . ucfirst($this->controller) . "Controller|Distributor|array
     */
    public function edit(HttpResponse \$response, HttpRequest \$request)
    {
        return \$this->forward(\$request, \$response)->bindTwig(\$this->getTemplate('edit'));
    }
    
    /**
     * @Route(\"/delete/{id}\", name=\"" . mb_strtolower($this->controller) . ".delete\", methods=\"GET\")
     * @param HttpRequest \$request
     * @param HttpResponse \$response
     * @return " . ucfirst($this->controller) . "Controller|Distributor|array
     */
    public function delete(HttpResponse \$response, HttpRequest \$request)
    {
        return \$this->forward(\$request, \$response)->redirectLocal('" . mb_strtolower($this->controller) . ".index');
    }
    
    private function getTemplate(string \$templateName): string
    {
        return \"" . mb_strtolower($this->controller) . "/\$templateName.html.twig\";
    }
}
        ";

        return $content;
    }
}
