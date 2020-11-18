<?php


namespace DI\AOP;


class Scanner
{
    /**
     * aop config
     * @var array
     */
    private $config;
    
    public function __construct(array $config)
    {
        $this->config = $config;
    }
    
    public function scan()
    {
        $proxyGenerator = new ProxyGenerator();
        foreach ($this->config as $aspect) {
            $proxyGenerator->generateProxyFile($aspect);
        }
    }
}