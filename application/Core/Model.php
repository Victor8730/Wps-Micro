<?php
declare(strict_types=1);

namespace Core;

class Model implements face\Base
{
    /**
     * @var Validator|object
     */
    public object $validator;

    public function __construct()
    {
        $this->validator = new Validator();
    }

}