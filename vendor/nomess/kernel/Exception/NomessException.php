<?php


namespace Nomess\Exception;


use Twig\Loader\FilesystemLoader;
use Twig\Environment;
use Nomess\Tools\Twig\PathExtension;

class NomessException extends \ErrorException
{
    public function __toString() {
        switch ($this->severity) {
            case E_USER_ERROR : // Si l'utilisateur émet une erreur fatale.
                $type = 'Erreur fatale';
                break;
            
            case E_WARNING : // Si PHP émet une alerte.
            case E_USER_WARNING : // Si l'utilisateur émet une alerte.
                $type = 'Attention';
                break;
            
            case E_NOTICE : // Si PHP émet une notice.
            case E_USER_NOTICE : // Si l'utilisateur émet une notice.
                $type = 'Note';
                break;
            
            default : // Erreur inconnue.
                $type = 'Erreur inconnue';
                break;
        }
        
        return '<strong>' . $type . '</strong> : [' . $this->code . '] ' . $this->message . '<br /><strong>' . $this->file . '</strong> à la ligne <strong>' . $this->line . '</strong><br>';
    }
}

function error2exception($code, $message, $fichier, $ligne) {
    file_put_contents(ROOT . 'var/log/error.log', "[" . date('d/m/Y H:i:s') . "] " . $code . ": " . $message . "\n line " . $ligne . " in " . $fichier . "\n---------------------------------------------------------\n", FILE_APPEND);
    throw new NomessException($message, 0, $code, $fichier, $ligne);
}

function customException($e) {
    
    file_put_contents(ROOT . 'var/log/error.log', "[" . date('d/m/Y H:i:s') . "] Line " . $e->getLine() . ": " . $e->getFile() . "\nException: " . $e->getMessage() . "\n---------------------------------------------------------\n", FILE_APPEND);
    
    if(NOMESS_CONTEXT === 'DEV') {
        require ROOT . 'vendor/nomess/kernel/Tools/Exception/exception.php';
        
        $time = xdebug_time_index();
        
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
    }else{
        
        http_response_code(500);
        
        $tabError = require ROOT . 'config/error.php';
        
        if(strpos($tabError[500], '.twig') !== false){
            if(file_exists(ROOT . 'templates/' . $tabError[500])) {
                $loader = new FilesystemLoader(ROOT . 'templates');
                $engine = new Environment($loader, [
                    'debug' => true,
                    'cache' => false,
                ]);
                $engine->addExtension(new PathExtension());
                echo $engine->render($tabError[500]);
            }
        }else{
            if(file_exists(ROOT . $tabError[500])) {
                include(ROOT . $tabError[500]);
            }
        }
        die;
    }
}


set_error_handler('NoMess\Exception\error2exception');
set_exception_handler('NoMess\Exception\customException');
