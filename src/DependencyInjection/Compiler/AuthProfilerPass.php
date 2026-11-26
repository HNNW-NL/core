<?php

namespace App\DependencyInjection\Compiler;

use App\Security\AuthProfilerMatcher;
use Symfony\Component\DependencyInjection\Attribute\Exclude;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

#[Exclude]
final class AuthProfilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if ($container->hasDefinition('profiler_listener')) {
            $container->getDefinition('profiler_listener')->replaceArgument(2, new Reference(AuthProfilerMatcher::class));
        }
    }
}
