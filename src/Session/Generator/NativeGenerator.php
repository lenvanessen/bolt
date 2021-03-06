<?php

namespace Bolt\Session\Generator;

/**
 * Generator for session IDs with native random_bytes() function.
 *
 * @author Carson Full <carsonfull@gmail.com>
 */
class NativeGenerator implements GeneratorInterface
{
    /** @var int */
    private $length;

    /**
     * Constructor.
     *
     * @param int $length The length of the random string that should be returned in bytes.
     */
    public function __construct($length = 32)
    {
        $this->length = $length;
    }

    /**
     * {@inheritdoc}
     */
    public function generateId()
    {
        return substr(bin2hex(random_bytes($this->length)), 0, $this->length);
    }
}
