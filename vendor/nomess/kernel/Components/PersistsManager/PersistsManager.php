<?php

namespace Nomess\Components\PersistsManager;


use Nomess\Annotations\Inject;
use Nomess\Components\PersistsManager\Builder\BuilderPersistsManager;
use Nomess\Components\PersistsManager\ResolverRequest\ResolverCreate;
use Nomess\Components\PersistsManager\ResolverRequest\ResolverDelete;
use Nomess\Components\PersistsManager\ResolverRequest\ResolverSelect;
use Nomess\Components\PersistsManager\ResolverRequest\ResolverUpdate;
use Nomess\Container\Container;
use Nomess\Database\IPDOFactory;
use Nomess\Exception\MissingConfigurationException;
use Nomess\Exception\NomessException;


class PersistsManager
{

    private const STORAGE_CONFIGURATION = ROOT . 'App/config/components/PersistsManager.php';
    private const STORAGE_CACHE = ROOT . 'App/var/cache/pm/';


    /**
     * Number of loop
     */
    private int $loop = 0;

    /**
     * Contains available configuration
     */
    private array $file;


    /**
     * Contains a configuration for request
     */
    private array $config;


    /**
     * Contains reference method
     */
    private string $internalMethod;


    /**
     * Contains alias to method to call
     */
    private string $method;


    /**
     * Contains cursor on configuration (xml file)
     */
    private string $cursorXML;



    /**
     * Contains object to persists
     *
     * @var mixed
     */
    private $object;



    /**
     * Contains parameter of request
     */
    private ?array $parameter;


    /**
     * Contains the class name
     */
    private string $className;



    /**
     * Contains the configuration for object
     */
    private array $cache;


    private IPDOFactory $IPDOFactory;
    private Container $container;


    /**
     * @Inject()
     *
     * PersistsManager constructor.
     * @param IPDOFactory $IPDOFactory
     * @param Container $container
     */
    public function __construct(IPDOFactory $IPDOFactory,
                                Container $container)
    {

        $this->IPDOFactory = $IPDOFactory;
        $this->container = $container;
        $this->file = require self::STORAGE_CONFIGURATION;
    }


    /**
     * Initialize an transaction read
     *
     * @param string $fullNameClass Full name of object target
     * @param array|null $parameter Parameters for request, must be an array with ['parameter' => $value]
     * @param string $idMethod id of the method to call, it's 'read' by default
     * @return PersistsManager
     */
    public function read(string $fullNameClass, ?array $parameter = null, string $idMethod = 'read'): PersistsManager
    {
        try {
            $this->parameter = $parameter;
            $this->className = $fullNameClass;
            $this->cursorXML = $fullNameClass;
            $this->config = $this->file[$fullNameClass];
        } catch (\Throwable $th) {}

        $this->internalMethod = 'read';
        $this->method = $idMethod;

        return $this;
    }


    /**
     * Initialize an transaction create
     *
     * @param object $object Object target
     * @param array|null $parameter Parameters for request, must be an array with ['parameter' => $value]
     * @param string $idMethod id of the method to call, it's 'create' by default
     * @return PersistsManager
     */
    public function create(object $object, ?array $parameter = null, string $idMethod = 'create'): PersistsManager
    {
        try {
            $this->className = get_class($object);
            $parameter[$this->getOnlyClassName($this->className)] = $object;
            $this->parameter = $parameter;
            $this->cursorXML = $this->className;
            $this->config = $this->file[$this->className];
        } catch (\Throwable $th) {}

        $this->internalMethod = 'create';
        $this->method = $idMethod;
        $this->object = $object;

        return $this;

    }


    /**
     * Initialize an transaction update
     *
     * @param object $object Object target
     * @param array|null $parameter Parameters for request, must be an array with ['parameter' => $value]
     * @param string $idMethod id of the method to call, it's 'update' by default
     * @return PersistsManager
     */
    public function update(object $object, ?array $parameter = null, string $idMethod = 'update'): PersistsManager
    {
        try {
            $this->className = get_class($object);
            $parameter[$this->getOnlyClassName($this->className)] = $object;
            $this->parameter = $parameter;
            $this->cursorXML = $this->className;
            $this->config = $this->file[$this->className];
        } catch (\Throwable $th) {}

        $this->internalMethod = 'update';
        $this->method = $idMethod;
        $this->object = $object;

        return $this;

    }


    /**
     * Initialize an transaction delete
     *
     * @param object $object Object target
     * @param array|null $parameter Parameters for request, must be an array with ['parameter' => $value]
     * @param string $idMethod id of the method to call, it's 'delete' by default
     * @return PersistsManager
     */
    public function delete(object $object, ?array $parameter = null, string $idMethod = 'delete'): PersistsManager
    {
        try {

            $this->className = get_class($object);
            $parameter[$this->getOnlyClassName($this->className)] = $object;
            $this->parameter = $parameter;
            $this->cursorXML = $this->className;
            $this->config = $this->file[$this->className];
        } catch (\Throwable $th) {}

        $this->internalMethod = 'delete';
        $this->method = $idMethod;
        $this->object = $object;

        return $this;

    }


    /**
     * Launch transaction
     *
     * @return mixed
     * @throws MissingConfigurationException
     * @throws NomessException
     */
    public function execute()
    {

        if(!$this->revalideCache()){
            return $this->loadResolver();
        }elseif ($this->getCache()) {


            $className = $this->generateClassName($this->className . "::" . $this->method);
            $parameter = array($this->IPDOFactory, $this->container);

            if(isset($this->parameter)){

                foreach ($this->parameter as $column => $value){
                    $parameter[] = $value;

                }
            }

            $cache = new $className();

            return call_user_func_array([$cache, 'execute'], $parameter);


        } else {
            return $this->loadResolver();
        }

    }


    /**
     * Take file cache to this class::request
     *
     * @return bool
     * @throws NomessException
     */
    private function getCache(): ?bool
    {
        $filename = self::STORAGE_CACHE . $this->generateClassName($this->className . '::' . $this->method) . '.php';

        if(!file_exists($filename)){
            return false;
        }

        try {

            require_once $filename;

            return true;
        } catch (\Throwable $th) {

            if(strpos($th->getMessage(), 'No such file or directory') === false){
                throw new NomessException('PersistsManager encountered an error: when we try to take file ' . $filename . '.php , we have received this message: "' . $th->getMessage() . ' in line ' . $th->getLine() . '"');
            }

            return false;
        }
    }


    /**
     * Control that configuration hasn't change
     *
     * @return bool
     * @throws NomessException
     */
    private function revalideCache(): bool
    {
        $lastConfig = self::STORAGE_CACHE . $this->generateClassName('Config-' . $this->className . '::' . $this->method) . '.php';

        try{

            $lastConfig = require $lastConfig;
            $lastConfig = unserialize($lastConfig);
        }catch (\Throwable $th){
            if(strpos($th->getMessage(), 'No such file or directory') === false){
                throw new NomessException('PersistsManager encountered an error: when we try to take file ' . self::STORAGE_CACHE . $this->generateClassName('Config-' . $this->className . '::' . $this->method) . '.php , we have received this message: "' . $th->getMessage() . ' in line ' . $th->getLine() . '"');
            }

            return false;
        }

        if (!empty(array_diff_assoc($lastConfig[$this->method], $this->config[$this->method]))) {

            unlink(self::STORAGE_CACHE . $this->generateClassName('Config-' . $this->className . '::' . $this->method) . '.php');
            return false;
        } else {
            return true;
        }
    }


    /**
     * @return mixed
     * @throws MissingConfigurationException
     * @throws NomessException
     */
    private function loadResolver()
    {

        $this->cache = unserialize($this->loadCache($this->cursorXML));

        $resolver = null;

        //Search class to instantiate
        if($this->internalMethod === 'read') {
            $resolver = $this->container->get(ResolverSelect::class);
            $resolver->className = $this->className;
        }elseif($this->internalMethod === 'update'){
            $resolver = $this->container->get(ResolverUpdate::class);
        }elseif($this->internalMethod === 'create'){
            $resolver = $this->container->get(ResolverCreate::class);
        }elseif($this->internalMethod === 'delete'){
            $resolver = $this->container->get(ResolverDelete::class);
        }

        //Map property for target class
        foreach($this->cache[$this->className]['property'] as $property){
            $resolver->mapping(
                $property['column'],
                $property['accessor'],
                $property['mutator'],
                $property['type'],
                $property['scope'],
                $this->cache[$this->className]['table'],
                $this->cache[$this->className]['keyArray']
            );
        }

        //Map the dependency for target class
        if(!empty($this->cache[$this->className]['dependency'])){
            foreach($this->cache[$this->className]['dependency'] as $classNameDependency => $dependency){

                $this->cache = unserialize($this->loadCache($classNameDependency));

                //If cache for dependency doesn't exists, rebuild
                if(array_key_exists($classNameDependency, $this->cache)) {
                    foreach ($this->cache[$classNameDependency]['property'] as $property) {
                        $resolver->dependency[$classNameDependency][$property['column']] = [
                            'column' => $property['column'],
                            'mutator' => $property['mutator'],
                            'type' => $property['type'],
                            'scope' => $property['scope'],
                            'table' => $this->cache[$classNameDependency]['table'],
                            'keyArray' => $this->cache[$classNameDependency]['keyArray']
                        ];
                    }
                }else{
                    $this->loadCache($classNameDependency);
                }
            }
        }

        $resolver->method = $this->method;
        $resolver->cache = $this->cache;
        $resolver->instance = $this->IPDOFactory;
        $resolver->className = $this->className;
        $resolver->parameter = $this->parameter;

        if(isset($this->config)) {
            $resolver->config = $this->config;
        }else{
            throw new MissingConfigurationException('PersistsManager encountered an error: configuration not found for ' . $this->className . ' in persists manager configuration (' . str_replace(ROOT, '', self::STORAGE_CONFIGURATION) . '), <br> please, make an configuration or verify your syntax');
        }
        $resolver->request = $this->config[$this->method]['request'];

        if(isset($this->object)){
            $resolver->object = $this->object;
        }

        if(isset($this->config[$this->method]['return'])){
            $resolver->willReturn = $this->config[$this->method]['return'];
        }

        if(isset($this->config[$this->method]['id_config_database'])){
            $resolver->idConfig = $this->config[$this->method]['id_config_database'];
        }elseif(isset($this->config['id_config_database'])){
            $resolver->idConfig = $this->config['id_config_database'];
        }else{
            $resolver->idConfig = 'default';
        }

        $resolver->execute();
        $this->loop++;

        if($this->loop > 2){
            throw new NomessException('PersistsManager encountered an error: Unknow error');
        }

        return $this->execute();

    }


    /**
     * Build or rebuild cache and return new cache
     *
     * @param $className
     * @return \SimpleXMLElement
     */
    private function loadCache($className) : string
    {
        $builder = $this->container->get(BuilderPersistsManager::class);
        $builder->build($className);

        return require self::STORAGE_CACHE . '/persistsmanager.php';
    }


    /**
     * Return only className
     *
     * @param string $fullclassName
     * @return string
     */
    protected function getOnlyClassName(string $fullclassName): string
    {

        $tmp = explode('\\', $fullclassName);

        return $tmp[count($tmp) - 1];
    }


    /**
     *
     * @param string $unformated
     * @return string
     */
    protected function generateClassName(string $unformated): string
    {
        $str = str_replace(['\\', '::'], '_', $unformated);
        $str = mb_strtolower($str);

        $array = str_split($str);

        if(count($array) > 250){
            $iterate = count($array) - 250;

            for($i = 0; $i < $iterate; $i++){
                unset($array[$i]);
            }

            $str = implode('', $array);
        }

        return $str;

    }
}
