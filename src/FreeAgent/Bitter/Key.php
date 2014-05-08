<?php
/**
 * Created by PhpStorm.
 * User: tsarikau
 * Date: 07/05/2014
 * Time: 19:13
 */

namespace FreeAgent\Bitter;


abstract class Key {
    protected $key;

    public function __construct($key){
        if ($key instanceof self){
            $key->getKey();
        }
        $this->key=$key;
    }
    public function getKey(){
        return $this->key;
    }

    function __toString()
    {
        return $this->key;
    }
} 