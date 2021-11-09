<?php

namespace Pushword\StaticGenerator\DependencyInjection;

use Pushword\StaticGenerator\Generator\CNAMEGenerator;
use Pushword\StaticGenerator\Generator\CopierGenerator;
use Pushword\StaticGenerator\Generator\ErrorPageGenerator;
use Pushword\StaticGenerator\Generator\HtaccessGenerator;
use Pushword\StaticGenerator\Generator\MediaGenerator;
use Pushword\StaticGenerator\Generator\PagesGenerator;
use Pushword\StaticGenerator\Generator\RobotsGenerator;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public const DEFAULT_APP_FALLBACK = [
        'static_generators',
        'static_symlink',
        'static_dir',
        'static_copy',
    ];

    public const DEFAULT_GENERATOR = [
        PagesGenerator::class,
        RobotsGenerator::class,
        ErrorPageGenerator::class,
        CopierGenerator::class,
        MediaGenerator::class,
        HtaccessGenerator::class,
    ];

    public const DEFAULT_GENERATOR_GITHUB = [
        PagesGenerator::class,
        RobotsGenerator::class,
        ErrorPageGenerator::class,
        CopierGenerator::class,
        MediaGenerator::class,
        CNAMEGenerator::class,
    ];

    public const DEFAULT_COPY = ['assets', 'bundles', 'media'];

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('static_generator');
        $treeBuilder->getRootNode()->children()
            ->variableNode('app_fallback_properties')->defaultValue(self::DEFAULT_APP_FALLBACK)->cannotBeEmpty()->end()
            ->booleanNode('static_symlink')
                ->info('For github pages, this params is forced to false (need a hard copy).')
                ->defaultTrue()
            ->end()
            ->variableNode('static_generators')
                ->defaultValue(static::DEFAULT_GENERATOR)
                ->validate()
                    ->ifInArray(['apache'])->then(function () { return static::DEFAULT_GENERATOR; })
                    ->ifInArray(['github'])->then(function () { return static::DEFAULT_GENERATOR_GITHUB; })
                ->end()
            ->end()

            ->scalarNode('static_dir')
                ->defaultValue('%kernel.project_dir%/%main_host%')
                ->info('If null or empty, static dir will be %kernel.project_dir%/host.tld/.')
                ->validate()
                    ->ifTrue(function ($value): bool { return false === self::isAbsolutePath($value); })
                    ->thenInvalid('Invalid static dir path `%s`, it must be absolute (eg: /home/pushword/host.tld/)')
                ->end()
            ->end()
            ->variableNode('static_copy')
                ->info('file or folder in your public dir to copy in static')
                ->defaultValue(static::DEFAULT_COPY)
            ->end()
        ->end();

        return $treeBuilder;
    }

    private static function isAbsolutePath(string $path): bool
    {
        return '' !== $path && (\in_array($path[0], [\DIRECTORY_SEPARATOR, '%'], true) || preg_match('#\A[A-Z]:(?![^/\\\\])#i', $path));
    }
}
