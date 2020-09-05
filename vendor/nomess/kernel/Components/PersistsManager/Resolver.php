<?php


namespace Nomess\Components\PersistsManager;


use \Database\IPDOFactory;
use Nomess\Exception\NomessException;
use Nomess\Exception\NotFoundException;


abstract class Resolver
{

    protected const STORAGE_CACHE         = ROOT . 'var/cache/pm/';

    protected static array $typeConstPDO = array(
        'float' => '\PDO::PARAM_STR',
        'double' => '\PDO::PARAM_STR',
        'string' => '\PDO::PARAM_STR',
        'mixed' => '\PDO::PARAM_STR',
        'int' => '\PDO::PARAM_INT',
        'bool' => '\PDO::PARAM_BOOL',
        'array' => '\PDO::PARAM_STR'
    );


    /**
     * Contains class name
     */
    public ?string $className;


    /**
     * Contains parameter of this request
     */
    public ?array $parameter;


    /**
     * Contains file cache
     */
    public ?array $cache;


    /**
     * Contains a configuration for this request
     */
    public ?array $config;


    /**
     * Request to execute
     */
    public ?string $request;


    /**
     * Object to persists (if not read method)
     */
    public ?object $object;


    /**
     * Id of database configuration
     */
    public ?string $idConfig;


    /**
     * Contains an array with configuration of dependency
     */
    public ?array $dependency;


    public IPDOFactory $instance;


    /**
     * Contains the main method
     */
    public ?string $method;


    /**
     * Contains returned value
     */
    public string $willReturn;

    /**
     * Contains a property mapped
     */
    protected array $propertyMapping;


    /**
     * Preparation
     * Contains data to insert, supports the alias
     * [column => alias]
     */
    protected array $setData;


    /**
     * Executable
     * Contains bindValue
     * [column => function]
     */
    protected array $bindValue;


    /**
     * Bring closer column and method
     *
     * @param string $column
     * @param string $accessor
     * @param string $mutator
     * @param string $typedProperty
     * @param string $scope
     * @param string $table
     * @param string $keyArray
     */
    public function mapping(string $column, string $accessor, string $mutator, string $typedProperty, string $scope, string $table, string $keyArray): void
    {
        $this->propertyMapping[$column] = array(
            'column' => $column,
            'accessor' => $accessor,
            'mutator' => $mutator,
            'type' => $typedProperty,
            'scope' => $scope,
            'table' => $table,
            'keyArray' => $keyArray
        );
    }


    /**
     * Parse request for build parameters
     *
     * @throws NotFoundException
     */
    protected function buildParameter(): void
    {
        //Take parameter to provides
        preg_match_all("/:[a-zA-Z0-9-_&\/\\\~@#]+/", $this->request, $output);


        //If parameters found, iterate this
        if (!empty($output[0])) {
            foreach ($output[0] as $value) {

                $columnName = str_replace(':', '', $value);
                $type = (string)$this->propertyMapping[$columnName]['type'];

                //If this column exists in array of configuration, create line, else, search a good object provider

                if(isset($this->parameter[str_replace(':', '', $columnName)])){
                    $this->bindValue[$value] = '$req->bindValue("' . $value . '", $' . $columnName . ');';
                }elseif (isset($this->propertyMapping[$columnName])) {

                    $scope = (string)$this->propertyMapping[$columnName]['scope'];
                    $accessor = (string)$this->propertyMapping[$columnName]['accessor'];

                    //If is array type, resolver must create a adaptator
                    if ($type !== 'array') {

                        if (isset($this->config[$this->method]['patch'][$columnName])) {
                            $this->bindValue[$value] = '$req->bindValue(\'' . $value . '\', ' . $this->config[$this->method]["patch"][$columnName] . ', ' . self::$typeConstPDO[$type] . ');';
                        } elseif ($scope === 'private' || $scope === 'protected') {

                            $this->bindValue[$value] = '$req->bindValue(\'' . $value . '\', $' . $this->getOnlyClassName($this->className) . '->' . $accessor . '(), ' . self::$typeConstPDO[$type] . ');';
                        } else {
                            $this->bindValue[$value] = '$req->bindValue(\'' . $value . '\', $' . $this->getOnlyClassName($this->className) . '->' . $accessor . ', ' . self::$typeConstPDO[$type] . ');';
                        }
                    } else {
                        if (isset($this->config[$this->method]['patch'][$columnName])) {
                            $this->bindValue[$value] = '$req->bindValue(\'' . $value . '\', ' . $this->config[$this->method]["patch"][$columnName] . ', ' . self::$typeConstPDO[$type] . ');';
                        } elseif ($scope === 'private' || $scope === 'protected') {

                            $this->bindValue[$value] = '$req->bindValue(\'' . $value . '\', serialize($' . $this->getOnlyClassName($this->className) . '->' . $accessor . '()), ' . self::$typeConstPDO[$type] . ');';
                        } else {
                            $this->bindValue[$value] = '$req->bindValue(\'' . $value . '\', serialize($' . $this->getOnlyClassName($this->className) . '->' . $accessor . '), ' . self::$typeConstPDO[$type] . ');';
                        }
                    }
                } elseif (!isset($this->propertyMapping[$columnName])) {

                    if (isset($this->config[$this->method]['patch'][$columnName])) {
                        $this->bindValue[$value] = '$req->bindValue(\'' . $value . '\', ' . $this->config[$this->method]["patch"][$columnName] . ', ' . self::$typeConstPDO[$type] . ');';
                    } else {
                        $tab = $this->recovery_columnNotFound($columnName);

                        if ($type !== 'array') {
                            $this->bindValue[$value] = '$req->bindValue(\'' . $value . '\', $' . $this->getOnlyClassName($this->className) . '->' . $tab["classname"] . $tab["method"] . ', ' . self::$typeConstPDO[$type] . ');';

                        } else {
                            $this->bindValue[$value] = '$req->bindValue(\'' . $value . '\', serialize($' . $this->getOnlyClassName($this->className) . '->' . $tab["classname"] . $tab["method"] . '), ' . self::$typeConstPDO[$type] . ');';

                        }
                    }
                }
            }
        }
    }


    /**
     * If column not found in original object, launch recovery process and suggest an alternative in case of failure
     *
     * @param string $column
     * @return string
     * @throws NotFoundException
     */
    protected function recovery_columnNotFound(string $column): string
    {
        $find = 0;
        $method = null;

        if(isset($this->dependency)) {
            foreach ($this->dependency as $className => $value) {
                if (isset($value[$column])) {
                    $find++;

                    //Extract class name by full name

                    $configColumn = $value[$column];

                    //Get method for access
                    if ($configColumn['scope'] === 'private' || $configColumn['scope'] === 'protected') {
                        $method = '$' . $this->getOnlyClassName($className) . '->' .
                            (
                            ($this->config['dependency'][$this->getOnlyClassName($className)]['scope'] === 'public')
                                ? $this->config['dependency'][$this->getOnlyClassName($className)]['accessor']
                                : $this->config['dependency'][$this->getOnlyClassName($className)]['accessor'] . '()'
                            ) . '->' . $configColumn['accessor'] . '()';
                    } else {
                        $method = '$' . $this->getOnlyClassName($className) . '->' . (
                            ($this->config['dependency'][$this->getOnlyClassName($className)]['scope'] === 'public')
                                ? $this->config['dependency'][$this->getOnlyClassName($className)]['accessor']
                                : $this->config['dependency'][$this->getOnlyClassName($className)]['accessor'] . '()'
                            ) . '->' . $configColumn['accessor'];
                    }
                }
            }
        }

        if ($find > 1) {
            throw new NotFoundException('Resolver encountered an error: the column ' . $column . ' associated with ' . $this->className . ' was not found and she\'s present in ' . $find . ' dependency, therefore,<br><br>
                you can create a patch with this id: "' . $column . '" and map to the image of this example:<br> $ObjectName->accessorName(), else, you can correct the issue');
        } else if ($find === 0) {
            throw new NotFoundException('Resolver encountered an error: the column ' . $column . ' associated with ' . $this->className . ' was not found and in its dependency also, therefore,<br><br>
                you can create a patch with this id: "' . $column . '" and map to the image of this example:<br> $ObjectName->accessorName(), else, you can correct the issue');
        }

        return $method;
    }


    /**
     * Build request class
     *
     * @return string
     */
    protected function buildFileRequest(): string
    {
        $content = "\$req = \$database->prepare('" . $this->request . "');";

        if(isset($this->bindValue)) {
            foreach ($this->bindValue as $line) {
                $content .= "\r\t\t\t\t" . $line;
            }
        }

        $content .= "\r\t\t\t\t\$req->execute();";

        return $content;
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
     * return of current object KeyArray
     *
     * @param string $className
     * @return string|null
     * @throws \Exception
     */
    protected function getKeyArray(string $className): ?string
    {

        if($this->cache[$className]['keyArray']) {
            return $this->cache[$className]['keyArray'];
        }else{
            throw new NotFoundException('Resolver encountered an error: not found keyArray for class "' . $className . '", please, specify this');
        }
    }


    /**
     * Add arbitrary parameters
     *
     * @param string $parameters
     * @return string
     */
    protected function adjustParameter(string $parameters): string
    {
        if(isset($this->parameter)){
            foreach ($this->parameter as $column => $arbitraryValue){
                $parameters .= ", $" . $column;
            }
        }

        return $parameters;
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


    /**
     * Register an cache file
     *
     * @param string $data
     * @param string $filename
     * @throws NomessException
     */
    protected function registerCache(string $data, string $filename): void
    {
        if(!@file_put_contents(self::STORAGE_CACHE . $filename . '.php', $data)){
            throw new NomessException('Resolver encountered an error: Impossible to register the cache file in ' . self::STORAGE_CACHE);
        }
    }


    /**
     * Register the initial congiguration
     *
     */
    protected function registerInitialConfig(): void
    {

        $filename = self::STORAGE_CACHE . $this->generateClassName('Config-' . $this->className . '::' . $this->method) . '.php';



        $data = "<?php\rreturn '" . serialize($this->config) . "';";
        file_put_contents($filename, $data);

        $this->purge();
    }


    /**
     * Purge property of class
     */
    protected function purge(): void
    {
        $this->bindValue = array();
        $this->className = null;
        $this->propertyMapping = array();
        $this->config = array();
        $this->method = null;
        $this->object = null;
        $this->cache = array();
        $this->request = null;
    }
}
