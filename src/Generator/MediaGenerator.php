<?php

namespace Pushword\StaticGenerator\Generator;

class MediaGenerator extends AbstractGenerator
{
    public function generate(?string $host = null): void
    {
        parent::generate($host);

        $this->copyMediaToDownload();
    }

    /**
     * Copy or Symlink "not image" media to download folder.
     */
    protected function copyMediaToDownload(): void
    {
        $publicMediaDir = \strval($this->params->get('pw.public_media_dir'));
        $mediaDir = \strval($this->params->get('pw.media_dir'));
        $staticMediaDir = $this->getStaticDir().'/'.$publicMediaDir;

        $symlink = $this->mustSymlink();

        // TODO : fix when media symlink exist and then, we want to copy
        if (false === file_exists($staticMediaDir)) {
            $this->filesystem->mkdir($staticMediaDir);
        }

        $dir = dir($mediaDir);
        if (false === $dir) {
            return;
        }
        while (false !== $entry = $dir->read()) {
            if ('.' == $entry || '..' == $entry) {
                continue;
            }

            if (true === $symlink) {
                $this->filesystem->symlink($mediaDir.'/'.$entry, $staticMediaDir.'/'.$entry);

                continue;
            }

            $this->filesystem->copy($mediaDir.'/'.$entry, $staticMediaDir.'/'.$entry);
        }
    }
}
