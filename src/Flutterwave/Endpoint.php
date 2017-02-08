<?php

namespace Remade\Flutterwave;

class Endpoint{

    /**
     * Endpoint Name
     * @var string
     */
    protected $name;

    /**
     * Endpoint Url
     * @var string
     */
    protected $url;

    /**
     * @var string
     */
    protected $method;

    protected $encrypt = [];

    protected $validation = [];

    public function __construct($name, $url, $method = 'POST'){
        $this->name = $name;
        $this->url = $url;
        $this->method = $method;
    }

    public function setValidation($parameter, $rules){
        $this->validation[$parameter] = $rules;
        return $this;
    }

    /**
     * Set parameters to be Triple DES encrypted
     * @param $parameter_array
     * @return $this
     */
    public function encryptParameters($parameter_array){
        if(is_array($parameter_array)){
            foreach ($parameter_array as $parameter){
                $this->encryptParameter($parameter);
            }
            $this->encrypt = array_merge($this->encrypt, $parameter_array);
        }
        else{
            throw new \InvalidArgumentException('The provided parameter should be an array of fields to be encrypted');
        }
        return $this;
    }

    /**
     * Set parameter to be Triple DES encrypted
     *
     * @param $parameter
     * @return $this
     */
    public function encryptParameter($parameter){
       if(is_string($parameter)){
            if(!in_array($parameter, $this->encrypt)){
                $this->encrypt[] = $parameter;
            }
        }
        else{
            throw new \InvalidArgumentException('The provided parameter should be a name of a field to be encrypted');
        }
        return $this;
    }

    /**
     * Get endpoint parameters to be encrypted
     *
     * @return array
     */
    public function getEncrypt()
    {
        return $this->encrypt;
    }

    /**
     * Get endpoint request method
     *
     * @return string
     */
    public function getMethod()
    {
        return $this->method;
    }

    /**
     * Get endpoint Validation rules
     *
     * @return array
     */
    public function getValidation()
    {
        return $this->validation;
    }

    /**
     * Get endpoint name
     *
     * @return mixed
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Get endpoint Url
     *
     * @return mixed
     */
    public function getUrl()
    {
        return $this->url;
    }
}