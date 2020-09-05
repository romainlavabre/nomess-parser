<?php


namespace Nomess\Components\EntityManager;


use RedBeanPHP\R;

class Config
{
    private const CONNEXION_CONFIG          = ROOT . 'config/database.php';

    public function init(): void
    {
        $tab = require self::CONNEXION_CONFIG;
        $tab = $tab['default'];

        R::setup($tab['server'] . ':host=' . $tab['host'] . ';port=' . $tab['port'] . ';dbname=' . $tab['dbname'] . '', $tab['user'], $tab['password']);
        R::useWriterCache(TRUE);

        if(NOMESS_CONTEXT === 'PROD'){
            R::freeze(TRUE);
        }
    }
}
