<?php


namespace DI\AOP;



use ZMiddleware\Pipeline\Pipeline;

class AspectPipeline extends Pipeline
{
    protected function getSlice()
    {
        return function($stack, $pipe){
            return function ($passable) use ($stack, $pipe) {
                if (is_string($pipe) && class_exists($pipe)) {
                    $pipe = new $pipe();
                }
                $passable->pipe = $stack;
                return method_exists($pipe, $this->method) ? $pipe->{$this->method}($passable) : $pipe($passable);
            };
        };
    }
}