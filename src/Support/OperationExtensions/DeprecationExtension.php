<?php

namespace Dedoc\Scramble\Support\OperationExtensions;

use Dedoc\Scramble\Extensions\OperationExtension;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\RouteInfo;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocTagNode;

/**
 * This extension is responsible for tagging an operation as deprecated when the associated
 * php doc tag "deprecated" is present within the route's doc-block
 *
 * @author ash-phoenix-wombat
 */
class DeprecationExtension extends OperationExtension
{
    public function handle(Operation $operation, RouteInfo $routeInfo)
    {
        $methodDocNode = $routeInfo->phpDoc();

        foreach ($methodDocNode->children as $child) {
            if ($child instanceof PhpDocTagNode && $child->name === '@deprecated') {
                $operation->deprecated = true;
                break;
            }
        }
    }
}
