<?php

namespace Remade\Flutterwave;

use Remade\Flutterwave\Exceptions\IncompleteParametersException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use Valitron\Validator;

class Flutterwave{

    /**
     * Base Url for Flutterwave
     * @var string
     */
    protected $baseUrl = 'http://staging1flutterwave.co:8080/pwc/rest/';

    protected $aliases = [];

    protected $endpoints = [
        'binCheck' => [
            'url'           => 'fw/check',
            'validation'    => [
                'card6' => ['integer','length:6', 'required']
            ],
        ],

        'verifyBVN' => [
            'url'           => 'bvn/verify',
            'validation'    => [
                'bvn'   => ['required', 'integer', 'length:11'],
                'otpoption' => ['required'],
                'merchantid' => ['required']
            ],
            'encrypt'       => ['otpoption', 'bvn']
        ],

        'validateBVN' => [
            'url'           => 'bvn/validate',
            'validation'    => [
                'bvn'   => ['required', 'integer', 'length:11'],
                'transactionreference' => ['required'],
                'otp' => ['required'],
                'merchantid' => ['required']
            ],
            'encrypt' => ['bvn', 'transactionreference', 'otp']
        ],

        'resendBvnVerificationOtp' => [
            'url'           => 'bvn/verify',
            'validation'    => [
                'bvn'   => ['required', 'integer', 'length:11']
            ],
        ],


    ];

    protected $merchantKey = '';

    protected $apiKey = '';

    /**
     * Flutterwave constructor.
     *
     * @param $apiKey
     * @param $merchantKey
     */
    public function __construct($apiKey, $merchantKey)
    {
        $this->apiKey = $apiKey;
        $this->merchantKey = $merchantKey;
    }

    public function __call($method, $arguments){
        //Check if method exist in aliases or endpoint definition
        if(!isset($this->endpoints[$method]) && !in_array($method, $this->aliases)){
            throw new \Exception('Invalid Function');
        }

        //If it is an alias, set method name
        if(!isset($this->endpoints[$method])){
            $method = $this->aliases[$method];
        }

        //get endpoint data
        $endpoint = $this->endpoints[$method];

        //Validate Data before sending request
        $validator = $this->validator($method, $arguments[0]);
        if(!$validator->validate()){
            throw new IncompleteParametersException('The Provided parameters are not valid', $validator->errors());
        }

        $request_data = $arguments[0];

        //Encrypt
        if(isset($endpoint['encrypt']) && is_array($endpoint['encrypt'])){
            foreach ($endpoint['encrypt'] as $encrypt) {
                $request_data[$encrypt] = $this->encrypt3Des($request_data[$encrypt], $this->apiKey);
            }
        }

        //Request Options
        $options = [
            'json' => $request_data
        ];

        //POST is the default request format. Otherwise to be indicated in endpoint 'method' data
        if(!isset($endpoint['method'])){
            $endpoint['method'] = 'POST';
        }

        //Make Request
        $response = $this->makeRequest($endpoint['method'], $endpoint['url'], $options);

        return json_decode($response->getBody());

    }

    protected function makeRequest($method, $url, $options = []){
        try {
            $client = new Client(['base_uri' => $this->baseUrl]);

            //Prepare Guzzle Options
            $default_options = [
                'headers' => ['Content-Type' => 'application/json']
            ];
            $request_options = array_merge($default_options, $options);

            $response = $client->request($method, $url, $request_options);

            return $response;
        }
        catch (ClientException $exception){
            return ['status' => 'error', 'data' => $exception->getMessage()];
        }
        catch (ServerException $exception){
            return ['status' => 'error', 'data' => $exception->getMessage()];
        }
        catch (\Exception $exception){
            return ['status' => 'error', 'data' => $exception->getMessage()];
        }
    }


    /**
     * @param $method
     * @param $alias
     * @return $this
     * @throws \Exception
     */
    public function setAlias($method, $alias){
        //check if method exists
        if(!method_exists($this, $method)){
            throw new \Exception('THis method/Endpoint does not exist');
        }

        //set alias
        $this->aliases[$alias] = $method;

        return $this;
    }
    /**
     * Set Base Url
     *
     * @param $base_url
     * @return $this
     */
    public function setBaseUrl($base_url)
    {
        $this->baseUrl = $base_url;
        return $this;
    }

    /**
     * Add new Endpoint
     *
     * @param Endpoint $endpoint
     * @return $this
     */
    public function addNewEndpoint(Endpoint $endpoint){

        $endpoint_definition = [
            'method'        => $endpoint->getMethod(),
            'url'           => $endpoint->getUrl(),
            'validation'    => $endpoint->getValidation(),
            'encrypt'       => $endpoint->getEncrypt(),
        ];

        $this->endpoints[$endpoint->getName()] = $endpoint_definition;
        return $this;
    }

    /**
     * Triple DES Encryption
     *
     * @param $data
     * @param $key
     * @return string
     */
    public function encrypt3Des($data, $key){
        //Generate a key from a hash
        $key = md5(utf8_encode($key), true);

        //Take first 8 bytes of $key and append them to the end of $key.
        $key .= substr($key, 0, 8);

        //Pad for PKCS7
        $blockSize = @mcrypt_get_block_size('tripledes', 'ecb');
        $len = strlen($data);
        $pad = $blockSize - ($len % $blockSize);
        $data = $data.str_repeat(chr($pad), $pad);

        //Encrypt data
        $encData = @mcrypt_encrypt('tripledes', $key, $data, 'ecb');

        //return $this->strToHex($encData);

        return base64_encode($encData);
    }

    /**
     * Triple DES decryption
     *
     * @param $data
     * @param $secret
     * @return string
     */
    public function decrypt3Des($data, $secret){
        //Generate a key from a hash
        $key = md5(utf8_encode($secret), true);

        //Take first 8 bytes of $key and append them to the end of $key.
        $key .= substr($key, 0, 8);

        $data = base64_decode($data);

        $data = mcrypt_decrypt('tripledes', $key, $data, 'ecb');

        $block = mcrypt_get_block_size('tripledes', 'ecb');
        $len = strlen($data);
        $pad = ord($data[$len-1]);

        return substr($data, 0, strlen($data) - $pad);
    }

    /**
     * Validate data for each endpoint
     *
     * @param $endpoint
     * @param $data
     * @return Validator
     */
    protected function validator($endpoint, $data){
        //Get Validation Definition for the given endpoint
        $validation = $this->endpoints[$endpoint]['validation'];

        $validator =  new Validator($data);
        $rules = [];

        //Parse Definition to Validation Library format
        foreach ($validation as $field=>$rule){
            foreach ($rule as $r){
                $bits = explode(':', $r);

                $rule_parameters = [];
                $rule_parameters[] = $field;

                if(isset($bits[1])){
                    $bits2 = explode('|', $bits[1]);
                    foreach ($bits2 as $bit){
                        $rule_parameters[] = $bit;
                    }
                }

                $rules[$bits[0]][] = $rule_parameters;
            }
        }

        //Declare validation rules
        $validator->rules($rules);

        //Return validator instance
        return $validator;
    }
}