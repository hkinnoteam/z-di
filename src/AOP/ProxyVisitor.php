<?php


namespace DI\AOP;

use PhpParser\Node;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Name;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\Stmt\Return_;
use PhpParser\Node\Stmt\TraitUse;
use PhpParser\NodeFinder;
use PhpParser\NodeVisitorAbstract;

class ProxyVisitor extends NodeVisitorAbstract
{
    protected $className;

    protected $proxyId;

    public function __construct($className, $proxyId = '')
    {
        $this->className = $className;
        $this->proxyId = $proxyId;
    }

    public function getProxyClassName(): string
    {
        return basename(str_replace('\\', '/', $this->className));
    }

    public function getClassName(): string
    {
        return '\\' . $this->className . '_' . $this->proxyId;
    }

    /**
     * @return \PhpParser\Node\Stmt\TraitUse
     */
    private function getAopTraitUseNode(): TraitUse
    {
        // Use AopTrait trait use node
        return new TraitUse([new Name('\DI\AOP\ProxyTrait')]);
    }

    public function leaveNode(Node $node)
    {
        // Proxy Class
        if ($node instanceof Class_) {
            // Create proxy class base on parent class
            return new Class_($this->getProxyClassName(), [
                'flags' => $node->flags,
                'stmts' => $node->stmts,
            ]);
        }

        // Rewrite public and protected methods, without static methods
        if ($node instanceof ClassMethod && ($node->isPublic() || $node->isProtected())) {
            $methodName = $node->name->toString();
            // Rebuild closure uses, only variable
            $uses = [];
            foreach ($node->params as $key => $param) {
                if ($param instanceof Param) {
                    $uses[$key] = new Param($param->var, null, null, true);
                }
            }

            $params = [
                new Variable('class'),
                new Variable('func'),
                new FuncCall(new Name('func_get_args')),
                // Add method to an closure
                new Closure([
                    'static' => $node->isStatic(),
                    'params' => $node->params,
                    'stmts' => $node->stmts,
                ])
            ];

            $stmts = [
                new Expression(new Node\Expr\Assign(new Variable('class'), new Node\Scalar\MagicConst\Class_())),
                new Expression(new Node\Expr\Assign(new Variable('func'), new Node\Scalar\MagicConst\Function_())),
                new Expression(new Node\Expr\Assign(new Variable('method'), new Node\Scalar\MagicConst\Method())),
                new Return_(new Node\Expr\StaticCall(new Name('self'), '__proxyCall', $params))
            ];
            $returnType = $node->getReturnType();
            if ($returnType instanceof Name && $returnType->toString() === 'self') {
                $returnType = new Name('\\' . $this->className);
            }
            return new ClassMethod($methodName, [
                'flags' => $node->flags,
                'byRef' => $node->byRef,
                'params' => $node->params,
                'returnType' => $returnType,
                'stmts' => $stmts,
            ], $node->getAttributes());
        }
    }

    public function afterTraverse(array $nodes)
    {
        $addEnhancementMethods = true;
        $nodeFinder = new NodeFinder();
        $nodeFinder->find($nodes, function (Node $node) use (
            &$addEnhancementMethods
        ) {
            if ($node instanceof TraitUse) {
                foreach ($node->traits as $trait) {
                    // Did AopTrait trait use ?
                    if ($trait instanceof Name && $trait->toString() === '\DI\AOP\ProxyTrait') {
                        $addEnhancementMethods = false;
                        break;
                    }
                }
            }
        });
        // Find Class Node and then Add Aop Enhancement Methods nodes and getOriginalClassName() method
        $classNode = $nodeFinder->findFirstInstanceOf($nodes, Class_::class);
        $addEnhancementMethods && array_unshift($classNode->stmts, $this->getAopTraitUseNode());
        return $nodes;
    }
}