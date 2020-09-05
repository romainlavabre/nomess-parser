<?php


namespace Nomess\Components\EntityManager;


interface TransactionSubjectInterface
{
    public function addSubscriber(object $subscriber): void;
    
    public function notifySubscriber(bool $status): void;
}
