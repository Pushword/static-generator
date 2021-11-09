<?php

namespace Pushword\StaticGenerator\Generator;

use Exception;
use Pushword\Admin\PushwordAdminBundle;
use Pushword\Core\Entity\PageInterface as Page;
use Symfony\Component\HttpFoundation\Request;

class PageGenerator extends AbstractGenerator
{
    /** @required */
    public RedirectionManager $redirectionManager;

    public function generate(?string $host = null): void
    {
        parent::generate($host);

        if (self::class == static::class) {
            throw new Exception('no plan to call generate, maybe you want to call generatePage ?');
        }
    }

    public function generatePage(Page $page): void
    {
        if (false !== $page->getRedirection()) {
            $this->redirectionManager->addPage($page);

            return;
        }

        $this->saveAsStatic($this->generateLivePathFor($page), $this->generateFilePath($page), $page);

        $this->generateFeedFor($page);
    }

    protected function generateFilePath(Page $page, ?int $pager = null): string
    {
        $slug = '' == $page->getRealSlug() ? 'index' : $page->getRealSlug();

        if (preg_match('/.+\.(json|xml)$/i', $page->getRealSlug()) >= 1) {
            return $this->getStaticDir().'/'.$slug;
        }

        $filePath = $this->getStaticDir().'/';
        if ($pager >= 1) {
            $filePath .= 'index' == $slug ? '' : rtrim($slug, '/');

            return $filePath.'/'.$pager.'.html';
        }

        return $filePath.$slug.'.html';
    }

    /**
     * Generate static file for feed indexing children pages
     * (only if children pages exists).
     */
    protected function generateFeedFor(Page $page): void
    {
        $liveUri = $this->generateLivePathFor($page, 'pushword_page_feed');
        $staticFile = (string) preg_replace('/.html$/', '.xml', $this->generateFilePath($page));
        if (null === $page->getChildrenPages() || \count($page->getChildrenPages()) < 1) {
            return;
        }

        $this->saveAsStatic($liveUri, $staticFile, $page);
    }

    protected function saveAsStatic(string $liveUri, string $destination, ?Page $page = null): void
    {
        $request = Request::create($liveUri);
        //$request->headers->set('host', $this->app->getMainHost());

        $response = static::$appKernel->handle($request);

        if ($response->isRedirect()) {
            if ($response->headers->get('location')) {
                $this->redirectionManager->add($liveUri, $response->headers->get('location'), $response->getStatusCode());
            }

            return;
        } elseif (200 != $response->getStatusCode()) {
            //$this->kernel = static::$appKernel;
            if (500 === $response->getStatusCode() && 'dev' == $this->kernel->getEnvironment()) {
                $identifier = null !== $page && class_exists(PushwordAdminBundle::class) ?
                     '['.$liveUri.']('.$this->router->getRouter()->generate('admin_app_page_edit', ['id' => $page->getId()]).')'
                     : $liveUri;
                $this->setError('An error occured when generating '.$identifier.'');
                //throw new Exception('An error occured when generating `'.$liveUri.'`'); //exit($this->kernel->handle($request));
            }

            return;
        }

        if (false !== strpos($response->headers->all()['content-type'][0] ?? '', 'html') && null !== $page) {
            if (false !== strpos($response->getContent(), '<!-- pager:')) {
                $this->extractPager($page, $response->getContent());
            }
            $content = $this->compress($response->getContent());
        } else {
            $content = $response->getContent();
        }

        $this->filesystem->dumpFile($destination, $content);
    }

    private function extractPager(Page $page, string $content): void
    {
        preg_match('#<!-- pager:([0-9]+) -->#', $content, $match);
        $pager = (int) $match[1];
        $this->saveAsStatic(rtrim($this->generateLivePathFor($page), '/').'/'.$pager, $this->generateFilePath($page, $pager), $page);
    }

    protected function compress($html)
    {
        return $this->parser->compress($html);
    }
}
