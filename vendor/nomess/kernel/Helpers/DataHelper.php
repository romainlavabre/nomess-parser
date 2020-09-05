<?php

namespace Nomess\Helpers;

trait DataHelper
{

    private array $data;

    /**
     * Return value associate to the index variable (if not exists, return null)
     *
     * @param string|null $index
     * @return mixed
     */
    protected function get(?string $index)
    {
        $this->getDataCenter();
        return (isset($this->data[$index])) ? $this->data[$index] : NULL;
    }

    private function getDataCenter()
    {
        if(!isset($this->data)) {
            $this->data = require ROOT . 'config/datacenter.php';
        }
    }
}
