<?php

namespace Pushword\StaticGenerator\Generator;

class CopierGenerator extends AbstractGenerator
{
    public function generate(?string $host = null): void
    {
        parent::generate($host);

        $symlink = $this->mustSymlink();
        $entries = $this->app->get('static_copy');

        if (false === \is_array($entries)) {
            return;
        }

        foreach ($entries as $entry) {
            if (false === file_exists($this->publicDir.'/'.$entry)) {
                continue;
            }
            if (true === $symlink) {
                $this->filesystem->symlink(
                    str_replace(
                        \strval($this->params->get('kernel.project_dir')).'/',
                        '../',
                        $this->publicDir.'/'.$entry
                    ),
                    $this->getStaticDir().'/'.$entry
                );

                continue;
            }
            if (is_file($this->publicDir.'/'.$entry)) {
                $this->filesystem->copy($this->publicDir.'/'.$entry, $this->getStaticDir().'/'.$entry);
            } else {
                $this->filesystem->mirror($this->publicDir.'/'.$entry, $this->getStaticDir().'/'.$entry);
            }
        }
    }
}
