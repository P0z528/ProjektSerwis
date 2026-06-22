<?php

namespace App\Console\Commands;

use Illuminate\Foundation\Console\ServeCommand as BaseServeCommand;

class ServeCommand extends BaseServeCommand
{
    /**
     * {@inheritdoc}
     */
    protected function startProcess($hasEnvironment)
    {
        if (windows_os() && $hasEnvironment && ! $this->option('no-reload')) {
            $hasEnvironment = false;
        }

        return parent::startProcess($hasEnvironment);
    }
}
