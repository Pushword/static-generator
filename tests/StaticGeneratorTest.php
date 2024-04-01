<?php

namespace Pushword\StaticGenerator;

use Pushword\Core\Entity\Page;
use Pushword\Core\Repository\PageRepository;
use Pushword\StaticGenerator\Generator\CNAMEGenerator;
use Pushword\StaticGenerator\Generator\CopierGenerator;
use Pushword\StaticGenerator\Generator\ErrorPageGenerator;
use Pushword\StaticGenerator\Generator\GeneratorInterface;
use Pushword\StaticGenerator\Generator\HtaccessGenerator;
use Pushword\StaticGenerator\Generator\MediaGenerator;
use Pushword\StaticGenerator\Generator\PagesGenerator;
use Pushword\StaticGenerator\Generator\RedirectionManager;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Filesystem\Filesystem;

class StaticGeneratorTest extends KernelTestCase
{
    private ?StaticAppGenerator $staticAppGenerator = null;

    public function testStaticCommand()
    {
        $kernel = static::createKernel();
        $application = new Application($kernel);

        $command = $application->find('pushword:static:generate');
        $commandTester = new CommandTester($command);

        $this->assertTrue(true);

        $commandTester->execute(['localhost.dev']);

        // the output of the command in the console
        $output = $commandTester->getDisplay();
        $this->assertTrue(str_contains($output, 'success'));

        $this->assertTrue(file_exists(__DIR__.'/../../skeleton/localhost.dev/.htaccess'));
        $this->assertTrue(file_exists(__DIR__.'/../../skeleton/localhost.dev/index.html'));
        $this->assertTrue(file_exists(__DIR__.'/../../skeleton/localhost.dev/robots.txt'));

        $staticDir = __DIR__.'/../../skeleton/localhost.dev';
        $filesystem = new Filesystem();
        $filesystem->remove($staticDir);
    }

    private function getStaticAppGenerator()
    {
        if (null !== $this->staticAppGenerator) {
            return $this->staticAppGenerator;
        }

        $generatorBag = $this->getGeneratorBag();

        return new StaticAppGenerator(
            self::$kernel->getContainer()->get(\Pushword\Core\Component\App\AppPool::class),
            $generatorBag,
            $generatorBag->get(RedirectionManager::class)
        );
    }

    public function testIt()
    {
        self::bootKernel();

        $this->getStaticAppGenerator()->generate('localhost.dev');

        $this->assertTrue(file_exists(__DIR__.'/../../skeleton/localhost.dev'));

        $staticDir = __DIR__.'/../../skeleton/localhost.dev';
        $filesystem = new Filesystem();
        $filesystem->remove($staticDir);
        $filesystem->mkdir($staticDir);
    }

    private function getGenerator(string $name): GeneratorInterface
    {
        return $this->getGeneratorBag()->get($name)->setStaticAppGenerator($this->getStaticAppGenerator());
    }

    public function testGenerateHtaccess()
    {
        self::bootKernel();

        $generator = $this->getGenerator(HtaccessGenerator::class);

        $generator->generate('localhost.dev');

        $this->assertTrue(file_exists(__DIR__.'/../../skeleton/localhost.dev/.htaccess'));
    }

    public function testGenerateCNAME()
    {
        self::bootKernel();

        $generator = $this->getGenerator(CNAMEGenerator::class);

        $generator->generate('localhost.dev');

        $this->assertTrue(file_exists(__DIR__.'/../../skeleton/localhost.dev/CNAME'));
    }

    public function testCopier()
    {
        self::bootKernel();

        $generator = $this->getGenerator(CopierGenerator::class);

        $generator->generate('localhost.dev');

        $this->assertTrue(file_exists(__DIR__.'/../../skeleton/localhost.dev/assets'));
    }

    public function testError()
    {
        self::bootKernel();

        $generator = $this->getGenerator(ErrorPageGenerator::class);

        $generator->generate('localhost.dev');

        $this->assertFileExists(__DIR__.'/../../skeleton/localhost.dev/404.html');
    }

    public function testDownload()
    {
        self::bootKernel();

        $generator = $this->getGenerator(MediaGenerator::class);

        $generator->generate('localhost.dev');

        $this->assertFileExists(__DIR__.'/../../skeleton/localhost.dev/media');
    }

    public function testPages()
    {
        self::bootKernel();

        $generator = $this->getGenerator(PagesGenerator::class);

        $generator->generate('localhost.dev');

        $this->assertFileExists(__DIR__.'/../../skeleton/localhost.dev/index.html');
    }

    public function getGeneratorBag(): GeneratorBag
    {
        self::bootKernel();
        $container = static::getContainer();
        $generatorBag = $container->get(GeneratorBag::class);

        return $generatorBag;
    }

    public function getParameterBag()
    {
        $params = $this->createMock(ParameterBagInterface::class);

        $params->method('get')
             ->willReturnCallback([$this, 'getParams']);

        return $params;
    }

    public static function getParams($name)
    {
        if ('kernel.project_dir' == $name) {
            return __DIR__.'/../../skeleton';
        }

        if ('pw.public_media_dir' == $name) {
            return 'media';
        }

        if ('pw.media_dir' == $name) {
            return realpath(__DIR__.'/../../skeleton/media');
        }

        if ('pw.public_dir' == $name) {
            return realpath(__DIR__.'/../../skeleton/public');
        }
    }

    public function getPageRepo()
    {
        $page = (new Page())
            ->setH1('Welcome : this is your first page')
            ->setSlug('homepage')
            ->setLocale('en')
            ->setCreatedAt(new \DateTime('2 days ago'))
            ->setMainContent('...');

        $pageRepo = $this->createMock(PageRepository::class);
        $pageRepo->method('getPublishedPages')
                  ->willReturn([
                      $page,
                  ]);

        return $pageRepo;
    }
}
