<?php


namespace Nomess\Components\LightPersists;

use Nomess\Annotations\Inject;
use Nomess\Container\ContainerInterface;
use Nomess\Exception\NomessException;
use Nomess\Http\HttpRequest;
use Nomess\Http\HttpResponse;
use Throwable;



class LightPersists implements LightPersistsInterface
{

    private const COOKIE_NAME = 'psd_';
    private const STORAGE_PATH = '/var/nomess/';


    private ContainerInterface $container;

    private ?array $content = null;


    /**
     * Identifier of file
     */
    private ?string $id = null;


    /**
     * @Inject
     *
     * @param ContainerInterface $container
     * @throws NomessException
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->getContent();
    }
    
    
    /**
     * @param $index
     * @return bool
     */
    public function has($index): bool
    {
        return isset($this->content[$index]);
    }

    /**
     * Return value associate to index variable or null if doesn't exists
     *
     * @param mixed $index
     * @return mixed|void
     */
    public function &getReference($index)
    {

        if (isset($this->content[$index])) {
            return $this->content[$index];
        }
    }


    /**
     * Add value in container
     *
     * @param mixed $key
     * @param mixed $value
     * @param bool $reset Delete value associate to index before instertion
     *
     * @return void
     */
    public function set($key, $value, $reset = false): void
    {
        if ($reset === true) {
            unset($this->content[$key]);
        }

        if (\is_array($value)) {

            foreach ($value as $keyArray => $valArray) {

                $this->content[$key][$keyArray] = $valArray;
            }

        } else {
            $this->content[$key] = $value;
        }
    }


    /**
     * Return value associate to index variable or null if doesn't exists
     *
     * @param mixed $index
     * @return mixed
     */
    public function get($index)
    {

        if (isset($this->content[$index])) {
            return $this->content[$index];
        } else if ($index === '*') {
            return $this->content;
        } else {
            return null;
        }
    }


    /**
     * Delete an pair key/value
     *
     * @param string $index
     * @return void
     * @throws NomessException
     */
    public function delete(string $index)
    {

        if ($this->id === null) {
            $this->getContent();
        }

        if (array_key_exists($index, $this->content)) {
            unset($this->content[$index]);
        }
    }


    /**
     * Delete the persistence file
     */
    public function purge(): void
    {

        /**
         * @var HttpResponse
         */
        $response = $this->container->get(HttpResponse::class);

        $response->removeCookie(self::COOKIE_NAME);

        try {
            unlink(self::STORAGE_PATH . $this->id);
        } catch (Throwable $e) {
            throw new NomessException('Impossible of access to' . self::STORAGE_PATH . ' message: ' . $e->getMessage());
        }
    }


    /**
     * Save changes
     *
     * @return void
     * @throws NomessException
     */
    private function persists(): void
    {

        try {
            file_put_contents(self::STORAGE_PATH . $this->id, serialize($this->content));
        } catch (Throwable $e) {
            throw new NomessException('Impossible of access to' . self::STORAGE_PATH . ' message: ' . $e->getMessage());
        }
    }


    /**
     * Get content of file or create it
     *
     * @throws NomessException
     */
    private function getContent(): void
    {

        /**
         * @var HttpRequest
         */
        $request = $this->container->get(HttpRequest::class);

        $id = $request->getCookie(self::COOKIE_NAME);


        if ($id === null) {

            /**
             * @var HttpResponse
             */
            $response = $this->container->get(HttpResponse::class);

            $id = uniqid();

            $response->addCookie(self::COOKIE_NAME, $id, time() + 60 * 60 * 24 * 3650, '/');

            try {
                file_put_contents(self::STORAGE_PATH . $id, '');
            } catch (Throwable $e) {
                throw new NomessException('Impossible of access to ' . self::STORAGE_PATH . ' message: ' . $e->getMessage());
            }

        } else {
            try {
                $data = file_get_contents(self::STORAGE_PATH . $id);
                $this->content = unserialize($data);
            } catch (Throwable $e) {
                throw new NomessException('Impossible of access to ' . self::STORAGE_PATH . ' message: ' . $e->getMessage());
            }
        }

        $this->id = $id;
    }

    public function __destruct()
    {
        $this->persists();
    }
}
