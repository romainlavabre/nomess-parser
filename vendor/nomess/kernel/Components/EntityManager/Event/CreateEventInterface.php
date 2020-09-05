<?php


namespace Nomess\Components\EntityManager\Event;


use RedBeanPHP\OODBBean;

interface CreateEventInterface
{
    public function add(object $target, OODBBean $bean): void;

    public function execute(): void;
}
