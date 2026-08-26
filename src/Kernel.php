<?php

namespace App;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    // Covers every SAPI (FrankenPHP, symfony serve, CLI, PHPUnit); the ini directive
    // in frankenphp/conf.d/ only applies to the Docker image.
    public function boot(): void
    {
        parent::boot();

        date_default_timezone_set($this->getContainer()->getParameter('app.timezone'));
    }
}
