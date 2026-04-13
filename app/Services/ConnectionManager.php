<?php

namespace App\Services;

use App\Adapters\JumpsellerAdapter;
use App\Adapters\WooCommerceAdapter;

/**
 * Manages platform credentials and constructs adapter instances.
 * Reads configuration from .env variables.
 * Can be extended to persist credentials in the DB for multi-store scenarios.
 */
class ConnectionManager
{
    public function getJumpsellerConfig(): array
    {
        return [
            'login'     => env('jumpseller.login', ''),
            'authtoken' => env('jumpseller.authtoken', ''),
        ];
    }

    public function getWooCommerceConfig(): array
    {
        return [
            'url'             => env('woocommerce.url', ''),
            'consumer_key'    => env('woocommerce.consumer_key', ''),
            'consumer_secret' => env('woocommerce.consumer_secret', ''),
        ];
    }

    public function isJumpsellerConfigured(): bool
    {
        $config = $this->getJumpsellerConfig();
        return !empty($config['login']) && !empty($config['authtoken']);
    }

    public function isWooCommerceConfigured(): bool
    {
        $config = $this->getWooCommerceConfig();
        return !empty($config['url'])
            && !empty($config['consumer_key'])
            && !empty($config['consumer_secret']);
    }

    public function makeJumpsellerAdapter(): JumpsellerAdapter
    {
        $config = $this->getJumpsellerConfig();
        return new JumpsellerAdapter($config['login'], $config['authtoken']);
    }

    public function makeWooCommerceAdapter(): WooCommerceAdapter
    {
        $config = $this->getWooCommerceConfig();
        return new WooCommerceAdapter(
            $config['url'],
            $config['consumer_key'],
            $config['consumer_secret']
        );
    }
}
