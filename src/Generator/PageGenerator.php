<?php

namespace Pushword\StaticGenerator\Generator;

use Exception;
use Pushword\Admin\PushwordAdminBundle;
use Pushword\Core\Entity\PageInterface;
use Pushword\Core\Utils\F;

use function Safe\preg_match;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Service\Attribute\Required;

class PageGenerator extends AbstractGenerator
{
    #[Required]
    public RedirectionManager $redirectionManager;

    public function generate(?string $host = null): void
    {
        parent::generate($host);

        if (self::class == static::class) {
            throw new \Exception('no plan to call generate, maybe you want to call generatePage ?');
        }
    }

    public function generatePage(PageInterface $page): void
    {
        if ($page->hasRedirection()) {
            $this->redirectionManager->addPage($page);

            return;
        }

        $this->saveAsStatic($this->generateLivePathFor($page), $this->generateFilePath($page), $page);

        $this->generateFeedFor($page);
    }

    protected function generateFilePath(PageInterface $page, ?int $pager = null): string
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
    protected function generateFeedFor(PageInterface $page): void
    {
        $liveUri = $this->generateLivePathFor($page, 'pushword_page_feed');
        $staticFile = F::preg_replace_str('/.html$/', '.xml', $this->generateFilePath($page));
        if (\count($page->getChildrenPages()) < 1) {
            return;
        }

        $this->saveAsStatic($liveUri, $staticFile, $page);
    }

    protected function saveAsStatic(string $liveUri, string $destination, ?PageInterface $page = null): void
    {
        $request = Request::create($liveUri);
        // $request->headers->set('host', $this->app->getMainHost());

        $response = static::getKernel()->handle($request);

        if ($response->isRedirect()) {
            if (null !== $response->headers->get('location')) {
                $this->redirectionManager->add($liveUri, $response->headers->get('location'), $response->getStatusCode());
            }

            return;
        }

        if (Response::HTTP_OK != $response->getStatusCode()) {
            if (Response::HTTP_INTERNAL_SERVER_ERROR === $response->getStatusCode() && 'dev' == $this->kernel->getEnvironment()) {
                $this->setErrorFor($liveUri, $page, 'status code '.$response->getStatusCode());
            }

            return;
        }

        $content = $response->getContent();
        if (false === $content) {
            $this->setErrorFor($liveUri, $page, 'no content');

            return;
        }

        if ($this->responseIsHtml($response) && null !== $page) {
            if (str_contains($content, '<!-- pager:')) {
                $this->extractPager($page, $content);
            }

            $content = $this->compress($content);
        }

        $this->filesystem->dumpFile($destination, $content);
    }

    private function setErrorFor(string $liveUri, ?PageInterface $page = null, string $msg = ''): void
    {
        $identifier = null !== $page && class_exists(PushwordAdminBundle::class) ?
                     '['.$liveUri.']('.$this->router->getRouter()->generate('admin_page_edit', ['id' => $page->getId()]).')'
                     : $liveUri;
        $this->setError('An error occured when generating '.$identifier.('' !== $msg ? ' ('.$msg.')' : ''));
        // throw new Exception('An error occured when generating `'.$liveUri.'`'); //exit($this->kernel->handle($request));
    }

    private function responseIsHtml(Response $response): bool
    {
        return str_contains($response->headers->all()['content-type'][0] ?? '', 'html');
    }

    private function extractPager(PageInterface $page, string $content): void
    {
        preg_match('#<!-- pager:(\d+) -->#', $content, $match);
        $pager = (int) $match[1];
        $this->saveAsStatic(rtrim($this->generateLivePathFor($page), '/').'/'.$pager, $this->generateFilePath($page, $pager), $page);
    }

    protected function compress(string $html): string
    {
        return HtmlCompressor::compress($html);
    }
}
