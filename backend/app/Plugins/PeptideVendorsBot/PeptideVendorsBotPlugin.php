<?php

namespace App\Plugins\PeptideVendorsBot;

use App\Plugins\Plugin;
use App\Core\Contracts\PluginInterface;

class PeptideVendorsBotPlugin extends Plugin implements PluginInterface
{
    public function __construct()
    {
        parent::__construct(app_path('Plugins/PeptideVendorsBot'));
    }

    public function register(): void
    {
        $this->app->singleton(Services\TelegramService::class, function ($app) {
            return new Services\TelegramService();
        });
        $this->app->singleton(Services\ReviewService::class, function ($app) {
            return new Services\ReviewService();
        });
    }

    public function boot(): void
    {
        $this->registerHooks();
    }

    public function install(): void
    {
        $this->log('info', 'PeptideVendorsBot plugin installed');
    }

    public function enable(): void
    {
        $this->log('info', 'PeptideVendorsBot plugin enabled');
    }

    public function disable(): void
    {
        $this->log('info', 'PeptideVendorsBot plugin disabled');
    }

    public function uninstall(): void
    {
        $this->log('info', 'PeptideVendorsBot plugin uninstalled');
    }

    public function upgrade(string $fromVersion, string $toVersion): void
    {
        $this->log('info', "PeptideVendorsBot upgraded from {$fromVersion} to {$toVersion}");
    }
}
