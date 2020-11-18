<?php


namespace DI\AOP;


interface AspectInterface
{
    public function process(ProceedingJoinPoint $joinPoint);
}