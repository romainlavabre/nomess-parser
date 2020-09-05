<?php


namespace Nomess\Components\EntityManager\EntityCache;


use Nomess\Components\EntityManager\EntityManager;
use Nomess\Components\EntityManager\Resolver\Instance;
use Nomess\Components\EntityManager\TransactionObserverInterface;
use Nomess\Components\EntityManager\TransactionSubjectInterface;

class CacheManager implements TransactionObserverInterface
{
    
    private TransactionSubjectInterface $transactionSubject;
    private Repository                  $repository;
    private Writer                      $writer;
    private Reader                      $reader;
    private array                       $config;
    
    
    /**
     * *
     * @param TransactionSubjectInterface $transactionSubject
     * @param Repository $repository
     * @param Writer $writer
     * @param Reader $reader
     */
    public function __construct(
        TransactionSubjectInterface $transactionSubject,
        Repository $repository,
        Writer $writer,
        Reader $reader )
    {
        $this->transactionSubject = $transactionSubject;
        $this->repository         = $repository;
        $this->writer             = $writer;
        $this->reader             = $reader;
        $this->config             = require Repository::PATH_CONFIG;
        
        $this->revalide();
        $this->subscribeToTransactionStatus();
    }
    
    
    /**
     * Return all entity for this classname
     *
     * @param string $classname
     * @param bool $lock
     * @return array|null
     */
    public function getAll( string $classname, bool $lock = FALSE ): ?array
    {
        if( !$this->isAccepted( $classname ) ) {
            return NULL;
        }
        
        if( $this->repository->isAllSelected( $classname ) ) {
            $list = array();
            
            $content = scandir( Repository::PATH_CACHE );
            
            if( !empty( $content ) ) {
                foreach( $content as $file ) {
                    if( $this->repository->getClassnameByFilename( $file ) === $classname ) {
                        $list[] = $this->get(
                            $classname,
                            $this->repository->getIdByFilename( $file ),
                            $lock );
                    }
                }
            }
            
            return $lock ? NULL : $list;
        }
        
        return NULL;
    }
    
    
    public function get( string $classname, int $id, bool $lock = FALSE ): ?object
    {
        if( !$this->isAccepted( $classname ) ) {
            return NULL;
        }
        
        if( $lock ) {
            $this->repository->removeFile( $this->repository->getFilename( $classname, $id ) );
            
            return NULL;
        }
        
        if( $this->repository->storeHas( $classname, $id ) ) {
            return $this->repository->getToStore( $classname, $id);
        }
        
        return $this->reader->read($classname, $id);
    }
    
    
    public function addAll( string $classname ): void
    {
        if( $this->isAccepted( $classname ) ) {
            $this->repository->addSelectAll( $classname );
        }
    }
    
    
    public function add( object $object ): void
    {
        if( $this->isAccepted( get_class( $object ) ) ) {
            $this->repository->addInStore($object);
        }
    }
    
    
    /**
     * Delete instance and push her filename in to remove, if transaction is commited,
     * file is deleted
     *
     * @param object $object
     */
    public function remove( object $object ): void
    {
        $this->repository->addRemoved( $object );
    }
    
    
    /**
     * When property is complete, the CacheManager is notified and he clone it.
     * Object is cloned in anticipation is not notified by entityManager, if that is the case,
     * the selected object is written by her clone for consistency data
     *
     * @param object $object
     */
    public function clonable( object $object ): void
    {
        if($this->isAccepted(get_class($object))) {
            $this->repository->addClone( $object );
        }
    }
    
    
    private function revalide(): void
    {
        
        if( $this->repository->mustRevalideCache() ) {
            foreach( $this->repository->scanCache() as $filename ) {
                $this->repository->removeFile( $filename );
            }
            
            $this->repository->resetStatus();
        }
    }
    
    
    private function isAccepted( string $classname ): bool
    {
        return $this->config['enable'] && in_array( $classname, $this->config );
    }
    
    
    public function subscribeToTransactionStatus(): void
    {
        $this->transactionSubject->addSubscriber( $this );
    }
    
    
    public function statusTransactionNotified( bool $status ): void
    {
        $this->writer->writerNotifiedEvent( $status );
    }
    
    
    public function __destruct()
    {
        $this->writer->writerDestructEvent();
    }
}
