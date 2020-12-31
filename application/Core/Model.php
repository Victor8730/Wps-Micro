<?php
declare(strict_types=1);

namespace Core;

class Model extends Base
{
    /**
     * Validator object
     * @var object
     */
    public object $validator;

    public function __construct()
    {
        $this->validator = new Validator();

        parent::__construct();
    }

}