<?php

namespace Laravel\Cashier\Creem\Exceptions;

use Exception;

class CreemException extends Exception
{
    protected ?array $response = null;

    public function setResponse(?array $response): static
    {
        $this->response = $response;

        return $this;
    }

    public function getResponse(): ?array
    {
        return $this->response;
    }
}