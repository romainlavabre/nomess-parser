<?php


namespace Nomess\Components\EntityManager;


interface TransactionObserverInterface
{
    public function statusTransactionNotified(bool $status): void;
    
    public function subscribeToTransactionStatus(): void;
}
