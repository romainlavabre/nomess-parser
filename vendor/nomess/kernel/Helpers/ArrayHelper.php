<?php


namespace Nomess\Helpers;


trait ArrayHelper
{
    protected function isEmptyArray(?array $array): bool
    {
        return !is_null($array) ? empty($array) : FALSE;
    }
    
    protected function countArray(?array $array): bool
    {
        return !is_null($array) ? count($array) : 0;
    }
    
    protected function arrayContainsValue($value, ?array $array, bool $strict = FALSE): bool
    {
        return !is_null($array) ? in_array($value, $array, $strict) : FALSE;
    }
    
    protected function arrayContainsKey($key, ?array $array): bool
    {
        return !is_null($array) ? array_key_exists($key, $array) : FALSE;
    }
    
    protected function keysArray(?array $array): array
    {
        return !is_null($array) ? array_keys($array) : [];
    }
    
    protected function valuesArray(?array $array): array
    {
        return !is_null($array) ? array_values($array) : [];
    }
    
    protected function indexOf($value, ?array $array)
    {
        return !is_null($array) ? array_search($value, $array) : NULL;
    }
    
    protected function prepareArray(?array $array): array
    {
        return !is_null($array) ? $array : [];
    }
}
