<?php


namespace DI\AOP;


use DI\ProxyClassLoader;
use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard;

class Ast
{
    /**
     * @var \PhpParser\Parser
     */
    private $astParser;

    /**
     * @var PrettyPrinterAbstract
     */
    private $printer;

    public function __construct()
    {
        $parserFactory = new ParserFactory();
        $this->astParser = $parserFactory->create(ParserFactory::ONLY_PHP7);
        $this->printer = new Standard();
    }
    
    public function putProxy($class)
    {
        $code = $this->getCodeByClassName($class);
        $ast =  $this->astParser->parse($code);
        $visitor = new ProxyVisitor($class);
        $traverser = new NodeTraverser();
        $traverser->addVisitor($visitor);
        $proxyAst = $traverser->traverse($ast);
        return $this->printer->prettyPrintFile($proxyAst);
    }
    
    private function getCodeByClassName($class)
    {
        $file = ProxyClassLoader::getLoader()->findFile($class);
        if (! $file) {
            return '';
        }
        return file_get_contents($file);
    }
}