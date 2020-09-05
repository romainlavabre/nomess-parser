<?php
namespace Nomess\Components\DataManager\Builder;


class DocComment
{

    /**
     * Nom de la class
     */
    private ?string $className;

    /**
     * Clé en session (unique)
     */
    private ?string  $key;


    /**
     * Clé du tableau en session
     */
    private ?string $keyArray;


    /**
     * dependence de session
     */
    private array $sesDepend = array();

    /**
     * table de l'objet
     */
    private ?string $base;

    /**
     * Insertion avec le retour de la  mise en base
     */
    private ?string $insert;

    /**
     * Dépendance de la base de donnée
     */
    private array $dbDepend = array();

    /**
     * Contient les noInsert, noUpdate, noDelete
     */
    private array $noTransaction = array();


    /**
     * Contient les alias de method
     */
    private ?array $alias;



    /**
     * @param string $className
     */
    public function __construct(string $className)
    {
        $this->className = $className;
    }


    /**
     * @return string
     */
    public function getClassName() : string
    {
        return $this->className;
    }

    /**
     * @param string $setter
     * @return void
     */
    public function setKey(string $setter) : void
    {
        $this->key = $setter;
    }

    /** 
     * @return string|null
     */
    public function getKey() : ?string
    {
        return $this->key;
    }
    
    /**
     * @param string $setter
     * @return void
     */
    public function setKeyArray(string $setter) : void
    {
        if($this->keyArray === null){
            $this->keyArray = $setter;
        }
    }

    /**
     *
     * @return string|null
     */
    public function getKeyArray() : ?string
    {
        return $this->keyArray;
    }

    /**
     *
     * @param string $getter
     * @param string $setter
     * @return void
     */
    public function setSesDepend(string $getter, string $setter) : void
    {
        $this->sesDepend[$getter] = $setter;
    }

    /**
     *
     * @return array|null
     */
    public function getSesDepend() : ?array
    {
        return $this->sesDepend;
    }

    /**
     * @param string $setter
     * @return void
     */
    public function setBase(string $setter) : void
    {
        if($this->base === null){
            $this->base = $setter;
        }else{
            throw new \InvalidArgumentException('For class ' . $this->className . ', only once table excepted');
        }
    }

    /**
     * @return string|null
     */
    public function getBase() : ?string
    {
        return $this->base;
    }

    /**
     * @param string $setter
     * @return void
     */
    public function setInsert(string $setter) : void
    {
        if($this->insert === null){
            $this->insert = $setter;
        }
    }

    /**
     * @return string|null
     */
    public function getInsert() : ?string
    {
        return $this->insert;
    }

    /**
     * @param string $getter
     * @param string $setter
     * @return void
     */
    public function setDbDepend(string $getter, string $setter) : void
    {
        $this->dbDepend[$getter] =  $setter;
    }

    public function getDbDepend() : ?array
    {
        return $this->dbDepend;
    }

    public function getNoTransaction() : ?array
    {
        return $this->noTransaction;
    }

    public function setNoTransaction(?array $setter) : void
    {
        if(!empty($setter)){
            $this->noTransaction = $setter;
        }
    }

    public function setAlias(string $key, string $value) : void
    {
        $this->alias[$key] = $value;
    }

    public function getAlias() : ?array
    {
        return $this->alias;
    }

    public function __destruct(){}

}
