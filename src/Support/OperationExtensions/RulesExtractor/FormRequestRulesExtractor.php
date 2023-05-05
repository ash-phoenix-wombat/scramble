<?php

namespace Dedoc\Scramble\Support\OperationExtensions\RulesExtractor;

use Dedoc\Scramble\Infer\Services\FileParser;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Arr;
use PhpParser\Node;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Param;
use PhpParser\NodeFinder;
use ReflectionClass;

class FormRequestRulesExtractor
{
    private ?FunctionLike $handler;

    public function __construct(?FunctionLike $handler)
    {
        $this->handler = $handler;
    }

    public function shouldHandle()
    {
        if (! $this->handler) {
            return false;
        }

        return collect($this->handler->getParams())
            ->contains(\Closure::fromCallable([$this, 'findCustomRequestParam']));
    }

    public function nodes()
    {
        $requestClassNames = $this->getFormRequestClassNames();
        $nodes = [];

        foreach ($requestClassNames as $requestClassName) {
            $nodes[] = $this->node($requestClassName);
        }

        return $nodes;
    }

    protected function node(string $requestClassName)
    {
        $result = resolve(FileParser::class)->parse((new ReflectionClass($requestClassName))->getFileName());

        /** @var Node\Stmt\ClassMethod|null $rulesMethodNode */
        $rulesMethodNode = $result->findMethod("$requestClassName@rules");

        if (! $rulesMethodNode) {
            return null;
        }

        return new ValidationNodesResult((new NodeFinder())->find(
            Arr::wrap($rulesMethodNode->stmts),
            fn (Node $node) => $node instanceof Node\Expr\ArrayItem
                && $node->key instanceof Node\Scalar\String_
                && $node->getAttribute('parsedPhpDoc')
        ));
    }

    public function extract(Route $route)
    {
        $requestClassNames = $this->getFormRequestClassNames();
        $requestRules = [];

        foreach ($requestClassNames as $requestClassName) {
            $requestRules = array_merge($requestRules, $this->extractRules($route, $requestClassName));
        }

        return $requestRules;
    }

    protected function extractRules(Route $route, string $requestClassName)
    {
        /** @var Request $request */
        $request = new $requestClassName();
        $request->setMethod($route->methods()[0]);
        return $request->rules();
    }

    private function findCustomRequestParam(Param $param)
    {
        $className = (string) $param->type;

        return method_exists($className, 'rules');
    }

    private function getFormRequestClassNames()
    {
        $requestParams = collect($this->handler->getParams())
            ->filter(\Closure::fromCallable([$this, 'findCustomRequestParam']));
        return $requestParams->map(fn ($requestParam) => (string) $requestParam->type)->toArray();
    }
}
