<?php
declare(strict_types=1);

namespace Core;

class Model extends Base
{
    /**
     * Input and filesystem validator.
     */
    public Validator $validator;

    public function __construct()
    {
        $this->validator = new Validator();

        parent::__construct();
    }

}
