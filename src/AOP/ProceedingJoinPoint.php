<?php


namespace DI\AOP;


use Closure;
use Exception;

class ProceedingJoinPoint
{

    /**
     * @var string
     */
    public $className;

    /**
     * @var string
     */
    public $method;

    /**
     * @var mixed[]
     */
    public $arguments;

    /**
     * @var Closure
     */
    public $originalMethod;

    /**
     * @var null|Closure
     */
    public $pipe;

    public function __construct(string $className,string $method, array $arguments, Closure $closure)
    {
        $this->className      = $className;
        $this->method         = $method;
        $this->arguments      = $arguments;
        $this->originalMethod = $closure;
    }

    public function processOriginalMethod()
    {
        $this->pipe = null;
        $closure = $this->originalMethod;
        return $closure(...array_values($this->arguments));
    }

    /**
     * Delegate to the next aspect.
     */
    public function process()
    {
        $closure = $this->pipe;
        if (! $closure instanceof Closure) {
            throw new Exception('The pipe is not instanceof \Closure');
        }

        return $closure($this);
    }
}