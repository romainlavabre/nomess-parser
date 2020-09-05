<?php

namespace Nomess\Database;

class PDOFactory implements IPDOFactory
{

    private const DATA = ROOT . 'App/config/database.php';

    /**
     * Configuration
     */
    private ?string $config;

    private ?array $tabConfiguration;

    private string $transaction;

    public function __construct()
    {
        $this->tabConfiguration = require self::DATA;
    }


    /**
     * Select an configuration by id
     *
     * @param string $id
     */
    public function selectConfiguration(string $idConfig): void
    {
        $this->config = $idConfig;
        $this->createConnection();
    }


    /**
     * Manage instance of PDO
     *
     * @return \PDO
     */
    public function getConnection(string $idConfig = 'default'): \PDO
    {
        if (empty(Instance::$instance) || !isset(Instance::$instance[$idConfig])) {
            $this->config = $idConfig;
            $this->createConnection();
        }

        return Instance::$instance[$idConfig];
    }


    /**
     * Initialize an connection
     */
    private function createConnection(): void
    {

        if (!isset($this->tabConfiguration[$this->config])) {
            throw new \Exception('PDOFactory encountered an error: impossible of find configuration for "' . $this->config . '"');
        } else {
            $tab = $this->tabConfiguration[$this->config];
        }

        $db = new \PDO($tab['server'] . ':host=' . $tab['host'] . ';port=' . $tab['port'] . ';dbname=' . $tab['dbname'] . '', $tab['user'], $tab['password'], array(
            \PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8'
        ));

        $db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        Instance::$instance[$this->config] = $db;

    }

}
