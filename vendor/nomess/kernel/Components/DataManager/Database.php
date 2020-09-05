<?php

namespace Nomess\Components\DataManager;

use Nomess\Annotations\Inject;
use Nomess\Components\DataManager\Builder\BuilderDataManager;
use Nomess\Container\Container;
use Nomess\Exception\NomessException;

class Database
{

    private const CACHE_DATA_MANAGER        = ROOT . 'App/var/cache/dm/datamanager.xml';

    private const CREATE                    = 'create:';
    private const UPDATE                    = 'update:';
    private const DELETE                    = 'delete:';


    private array $data;
    private int $cursor = -1;

    private string $idConfigDatabase = 'default';


    private DataManager $datamanager;

    private Container $container;



    /**
     * @Inject
     *
     * @param DataManager $dataManager
     * @param Container $container
     */
    public function __construct(DataManager $dataManager,
                                Container $container)
    {

        $this->datamanager = $dataManager;
        $this->container = $container;
        $this->controlCache();
    }


    /**
     * ### Push a  ***creation*** of the target object in pile
     *
     *
     * @param array $param Object Array, by default, target is the first object found by DataManager in the parameters,<br>
     * If, for some reason, you can't optimize postion of object in the parameters, you can explicitly specify the type
     * that DataManager should search. (see $type option for more).
     * <br><br>
     * @param string $type if you can't fulfill a condition of $param, specify an object type that DataManager should search.
     *
     * @return self
     */
    public function create(array $param, string $type = null): self
    {
        $this->instanceData([self::CREATE . $type => $param]);
        return $this;
    }


    /**
     * ### Push a  ***update*** of the target object in pile
     *
     * @param array $param
     * @param string|null $type
     * @return self
     */
    public function update(array $param, string $type = null): self
    {

        $this->instanceData([self::UPDATE . $type => $param]);

        return $this;
    }


    /**
     * ### Push a  ***delete*** of the target object in pile
     *
     * @param array $param
     * @param string|null $type
     * @return self
     */
    public function delete(array $param, string $type = null): self
    {

        $this->instanceData([self::DELETE . $type => $param]);

        return $this;
    }


    /**
     *
     * Push an arbitrary method in pile
     *
     * It allow execute an transaction for alias method to "C.R.U.D."
     *
     * WARNING if you use update or delete method:
     * If you work with session, for guarentee a consistency data, pass always a copy of object and not by reference,
     * the original object that find inside session scope will be overwrite by new value (provided the keyArray are identical)
     *
     * @param string $method Name of method
     *
     * @param array $param
     * @param string|null $type
     * @return self
     */
    public function aliasMethod(string $method, array $param, string $type = null): self
    {

        $this->instanceData([$method . ':' . $type => $param]);

        return $this;

    }



    /**
     * Modify the insert configuration
     * Insert:<br>
     *  &nbsp;&nbsp;&nbsp;&nbsp; - false : Insertion disabled<br>
     *  &nbsp;&nbsp;&nbsp;&nbsp; - nomess_backTransaction : The inserted value is returned by transaction<br>
     *  &nbsp;&nbsp;&nbsp;&nbsp; - Mixded value : All arbitrary value, if is an array, DataManager will attempt insertion as a block, then if an error has occured it will iterate your array<br>
     *  &nbsp;&nbsp;&nbsp;&nbsp; - Array object : This parameter is an extention of full name of class, because, the data taken is defer, but it more, you can pass an array data for unique setter method, consequently, you must pass an reference of object dependency if you need defer method and pass an array data, this is option is good choice
     *
     * Format: [setterMethod => value ]
     *
     * @param array $configuration
     * @return $this
     */
    public function setInsertConfiguration(array $configuration): Database
    {
        $this->data[$this->cursor]['runtimeConfig']['insert'] = $configuration;

        return $this;
    }

    /**
     * Modify the depend configuration
     * Depend:<br>
     * &nbsp;&nbsp;&nbsp;&nbsp; - false : disable an dependency<br>
     * &nbsp;&nbsp;&nbsp;&nbsp; - Full\Quanlified\class::methodName : Use specified class::method to inject value<br>
     * &nbsp;&nbsp;&nbsp;&nbsp; - Mixed value : All arbitrary value, if is an array, DataManager will attempt insertion as a block, then if an error has occured it will iterate your array<br>
     * &nbsp;&nbsp;&nbsp;&nbsp; - Array object : This parameter is an extention of full name of class, because, the data taken is defer, but it more, you can pass an array data for unique setter method, consequently, you must pass an reference of object dependency
     *      if you need defer method and pass an array data, this is option is good choice
     *
     * Format: [ setterMethod => value ]
     *
     * @param array $configuration
     * @return self
     */
    public function setDependConfiguration(array $configuration): self
    {
        $this->data[$this->cursor]['runtimeConfig']['depend'] = $configuration;

        return $this;
    }

    /**
     * Modify the transaction configuration
     * Transaction:<br>
     * &nbsp;&nbsp;&nbsp;&nbsp; - false : Disables an transaction for this encapsed object<br>
     * &nbsp;&nbsp;&nbsp;&nbsp; - true : Enables an transaction for this encapsed object<br>
     *
     * Format: [ className => true|false ]
     *
     * @param array $configuration
     * @return self
     */
    public function setTransactionConfiguration(array $configuration): self
    {
        $this->data[$this->cursor]['runtimeConfig']['transaction'] = $configuration;

        return $this;
    }


    /**
     * @param array $dependency By deflaut, the dependance of target object will search in object group that has incur a
     * treatment only in the current request.<br>
     * If a object of identical type exists in group, only first will be keep.<br>
     * For modified this behaviour, you should pass by reference the dependency of target object.
     *
     * @return self
     */
    public function setDependency(array $dependency): self
    {

        $this->data[$this->cursor]['depend'] = $dependency;
        return $this;
    }



    /**
     * Return a builder for persists manager class
     *
     * @param string|null $className
     * @param array|null $parameter
     * @param string|null $idMethod
     * @return self
     */
    public function buildPM(?string $className = null, ?array $parameter = null, ?string $idMethod = null): self
    {


        if($className !== null && ($parameter !== null || $idMethod !== null)) {
            $this->data[$this->cursor]['persistsManager'][$className] = [
                'parameters' => $parameter,
                'idMethod' => $idMethod
            ];
        }elseif(!isset($this->data[$this->cursor]['persistsManager'])){
            $this->data[$this->cursor]['persistsManager'] = true;
        }

        return $this;
    }


    /**
     * Modify pointer of configuration database
     *
     * @param string $idConfigurationDatabase
     */
    public function setDatabaseConfiguration(string $idConfigurationDatabase): void
    {

        $this->idConfigDatabase = $idConfigurationDatabase;

    }


    /**
     * Launch transaction process, by running request in pile, if an error occured, return false, else return true
     *
     * @return bool
     * @throws NomessException
     */
    public function manage(): bool
    {
        return $this->datamanager->database($this->data, $this->idConfigDatabase);
    }


    /**
     * Launch builder if cache file doesn't exists
     */
    private function controlCache(): void
    {
        if(!file_exists(self::CACHE_DATA_MANAGER)){
            $buildMonitoring = $this->container->get(BuilderDataManager::class);
            $buildMonitoring->builderManager();
        }
    }


    /**
     * Create line of data to persists
     *
     * @param array $request
     */
    private function instanceData(array $request): void
    {
        $this->data[] = [
            'request' => $request,
            'depend' => null,
            'runtimeConfig' => null,
            'persistsManager' => null
        ];

        $this->cursor++;
    }

}
