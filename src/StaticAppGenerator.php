<?php

namespace Pushword\StaticGenerator;

use Pushword\Core\Component\App\AppPool;
use Pushword\StaticGenerator\Generator\GeneratorInterface;
use Pushword\StaticGenerator\Generator\PagesGenerator;
use Pushword\StaticGenerator\Generator\RedirectionManager;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Generate 1 App.
 */
final class StaticAppGenerator
{
    private AppPool $apps;

    private GeneratorBag $generatorBag;

    private RedirectionManager $redirectionManager;

    private bool $abortGeneration = false;

    /** @var array<string> */
    private array $errors = [];

    public function __construct(
        AppPool $appPool,
        GeneratorBag $generatorBag,
        RedirectionManager $redirectionManager
    ) {
        $this->apps = $appPool;
        $this->generatorBag = $generatorBag;
        $this->redirectionManager = $redirectionManager;
    }

    /**
     * @param ?string $hostToGenerate if null, generate all apps
     *
     * @return int the number of site generated
     */
    public function generate(?string $hostToGenerate = null): int
    {
        $i = 0;
        foreach ($this->apps->getHosts() as $host) {
            if (null !== $hostToGenerate && $hostToGenerate != $host) {
                continue;
            }

            $this->generateHost($host);
            $this->redirectionManager->reset();
            ++$i;
        }

        return $i;
    }

    public function generatePage(string $host, string $page): void
    {
        $this->apps->switchCurrentApp($host)->get();

        /** @var PagesGenerator $pagesGenerator */
        $pagesGenerator = $this->getGenerator(PagesGenerator::class);
        $pagesGenerator->generatePageBySlug($page);

        // Warning: no net if the generation failed !
    }

    /**
     * @throws \RuntimeException
     * @throws \LogicException
     * @psalm-suppress  UndefinedPropertyAssignment
     */
    private function generateHost(string $host): void
    {
        $app = $this->apps->switchCurrentApp($host)->get();

        $staticDir = \strval($app->get('static_dir'));
        $app->staticDir = $staticDir.'~'; // @phpstan-ignore-line

        $filesystem = new Filesystem();
        $filesystem->remove($staticDir.'~');
        $filesystem->mkdir($staticDir.'~');

        foreach ($app->get('static_generators') as $generator) { // @phpstan-ignore-line
            //dump($generator);
            $this->getGenerator(\strval($generator))->generate();
        }

        if (false === $this->abortGeneration) {
            $filesystem->remove($staticDir);
            $filesystem->rename($staticDir.'~', $staticDir);
            $filesystem->remove($staticDir.'~');
        }

        $this->abortGeneration = false;
    }

    private function getGenerator(string $name): GeneratorInterface
    {
        return $this->generatorBag->get($name)->setStaticAppGenerator($this);
    }

    public function setError(string $errorMessage): void
    {
        $this->errors[] = $errorMessage;
        $this->abortGeneration = true;
    }

    /**
     * @return array<string>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
