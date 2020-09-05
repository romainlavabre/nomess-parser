<?php

namespace Nomess\Components\EntityManager;

use App\Entities\Notification;
use Nomess\Annotations\Inject;
use Nomess\Components\EntityManager\Event\CreateEventInterface;
use Nomess\Components\EntityManager\Resolver\DeleteResolver;
use Nomess\Components\EntityManager\Resolver\Instance;
use Nomess\Components\EntityManager\Resolver\PersistsResolver;
use Nomess\Components\EntityManager\Resolver\SelectResolver;
use Nomess\Container\Container;
use Nomess\Exception\ORMException;
use Nomess\Helpers\DataHelper;
use Nomess\Http\HttpRequest;
use RedBeanPHP\R;

class EntityManager implements EntityManagerInterface, TransactionSubjectInterface
{
    
    use DataHelper;
    
    private const STORAGE_CACHE = ROOT . 'var/cache/em/';
    
    private const PERSISTS      = 'persists';
    
    private const DELETE        = 'delete';
    
    private array $entity        = array();
    private bool  $hasConfigured = FALSE;
    /**
     * @Inject()
     */
    private Container $container;
    /**
     * @Inject()
     */
    private HttpRequest $request;
    /**
     * @Inject()
     */
    private SelectResolver $selectResolver;
    /**
     * @Inject()
     */
    private PersistsResolver $persistsResolver;
    /**
     * @Inject()
     */
    private DeleteResolver $deleteResolver;
    /**
     * @Inject()
     */
    private CreateEventInterface $createEvent;
    /**
     * @Inject()
     */
    private Config $config;
    /**
     * @var TransactionObserverInterface[]
     */
    private array $transactionSubscriber;
    
    
    public function find( string $classname, ?string $idOrSql = NULL, ?array $parameters = NULL, bool $lock = FALSE )
    {
        $this->initConfig();
        return $this->selectResolver->resolve( $classname, $idOrSql, $parameters, $lock );
    }
    
    
    public function persists( object $object ): self
    {
        $this->entity[] = [
            'context' => self::PERSISTS,
            'data'    => $object,
        ];
        
        return $this;
    }
    
    
    public function delete( ?object $object ): self
    {
        if( $object !== NULL ) {
            $this->entity[] = [
                'context' => self::DELETE,
                'data'    => $object,
            ];
        }
        
        return $this;
    }
    
    
    public function has( object $object ): bool
    {
        return in_array( $object, $this->entity );
    }
    
    
    public function register(): bool
    {
        $this->initConfig();
        
        if( !empty( $this->entity ) ) {
            R::begin();
            
            try {
                
                foreach($this->entity as $key => &$data){
                    if($data['context'] === self::DELETE){
                        $bean = $this->deleteResolver->resolve( $data['data'] );
                        R::trash( $bean );
                        
                        unset($this->entity[$key]);
                    }
                    
                }
                
                foreach( $this->entity as $key => &$data ) {
                    
                    if( $data['context'] === self::PERSISTS ) {
                        
                        $bean = $this->persistsResolver->resolve( $data['data'] );
                        
                        if( !empty( $bean ) ) {
                            R::store( $bean );
                        }
                        
                        $this->createEvent->execute();
                        unset( $this->entity[$key] );
                    }
                }
                
                $this->notifySubscriber( TRUE );
                R::commit();
            } catch( \Throwable $e ) {
                R::rollback();
                $this->notifySubscriber( FALSE );
                
                if( NOMESS_CONTEXT === 'DEV' ) {
                    throw new ORMException( $e->getMessage() . ' in ' . $e->getFile() . ' line ' . $e->getLine() );
                } else {
                    $this->request->resetSuccess();
                    $this->request->setError( $this->get( 'orm_error' ) );
                }
                
                return FALSE;
            }
            
            R::close();
        }
        
        return TRUE;
    }
    
    
    public function addSubscriber( object $subscriber ): void
    {
        $this->transactionSubscriber[] = $subscriber;
    }
    
    
    public function notifySubscriber( bool $status ): void
    {
        
        if( !empty( $this->transactionSubscriber ) ) {
            /** @var TransactionObserverInterface $subscriber */
            foreach( $this->transactionSubscriber as $subscriber ) {
                $subscriber->statusTransactionNotified( $status );
            }
        }
        
    }
    
    
    private function initConfig(): void
    {
        if( !$this->hasConfigured ) {
            $this->hasConfigured = TRUE;
            $this->config->init();
        }
    }
}
