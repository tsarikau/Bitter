<?php
/**
 * Created by PhpStorm.
 * User: tsarikau
 * Date: 05/12/2014
 * Time: 04:09
 */

namespace FreeAgent\Bitter;


class Key implements KeyInterface
{

    protected $key;

    public function __construct($key)
    {
        $this->key = $key;
    }

    public function __toString()
    {
        return (string)$this->key;
    }
} 