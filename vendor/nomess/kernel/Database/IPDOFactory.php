<?php

namespace Nomess\Database;

interface IPDOFactory
{
    public function getConnection() : \PDO;
} 
