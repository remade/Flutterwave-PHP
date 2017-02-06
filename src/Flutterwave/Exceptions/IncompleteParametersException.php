<?php

namespace Flutterwave\Exceptions;

use Exception;

class IncompleteParametersException extends Exception {

    /**
     * @var array
     */
    private $errors;

    /**
     * IncompleteParametersException constructor.
     *
     * @param string $message
     * @param array $errors
     * @param int $code
     * @param Exception|null $previous
     */
    public function __construct($message, $errors = [], $code = 0, Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->errors = $errors;
    }

    /**
     * Retrieve errors
     * @return array
     */
    public function getErrors() {
        return $this->errors;
    }
}
