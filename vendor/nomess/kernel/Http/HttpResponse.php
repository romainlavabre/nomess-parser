<?php

namespace Nomess\Http;




class HttpResponse
{

    private ?array $action = array();

    private HttpRequest $request;


    /**
     * @Inject
     *
     * @param HttpRequest $request
     */
    public function __construct(HttpRequest $request)
    {
        $this->request = $request;
    }

    /**
     * Create an cookie accept an array with multiple entry
     *
     * @param string $name
     * @param mixed $value
     * @param int $expires
     * @param string $path
     * @param string $domain
     * @param bool $secure
     * @param bool $httponly
     * @return bool
     */
    public function addCookie(string $name, $value = "", int $expires = 0, string $path = "", string $domain = "", bool $secure = FALSE, bool $httponly = FALSE): bool
    {
        $result = false;

        if (is_array($value)) {
            foreach ($value as $key => $val) {
                $this->action['cookie'][] = ['setcookie' => [$name . '[' . $key . ']', $val, $expires, $path, $domain, $secure, $httponly]];
            }

        } else {

            $this->action['cookie'][] = ['setcookie' => [$name, (string)$value, $expires, $path, $domain, $secure, $httponly]];
        }

        return $result;
    }


    /**
     * Delete the cookie correspondence with index variable
     *
     * @param string $index
     */
    public function removeCookie(string $index): void
    {
        $cookie = null;
        $this->action['cookie'][] = ['setcookie' => [$index, null, -1, '/']];

    }


    /**
     * Execute pending operation
     */
    public function manage(): void
    {
        if (isset($this->action['cookie'])) {
            foreach ($this->action['cookie'] as $value) {
                foreach ($value as $method => $param) {
                    call_user_func_array($method, $param);
                }
            }
        }
    }
}
