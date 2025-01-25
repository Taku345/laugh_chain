<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use SymbolRestClient\Api\NodeRoutesApi;
use SymbolRestClient\Api\NetworkRoutesApi;
use SymbolRestClient\Configuration;
use SymbolSdk\Facade\SymbolFacade;
use SymbolRestClient\Api\TransactionRoutesApi;
use SymbolRestClient\Api\AccountRoutesApi;
use SymbolRestClient\Api\MosaicRoutesApi;
use GuzzleHttp\Client;

class SymbolServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton('symbol.config', function () {
            $NODE_URL = env('NODE_URL');

            $config = new Configuration();
            $config->setHost($NODE_URL);
            $client = new Client();

            // 設定とAPIインスタンスをまとめて返す
            return [
                'config' => $config,
                'client' => $client,
                'facade' => new SymbolFacade('testnet'),
                'transactionRoutesApi' => new TransactionRoutesApi($client, $config),
                'accountRoutesApi' => new AccountRoutesApi($client, $config),
                'mosaicRoutesApi' => new MosaicRoutesApi($client, $config),
                'nodeRoutesApi' => new NodeRoutesApi($client, $config),
                'networkRoutesApi' => new NetworkRoutesApi($client, $config),
            ];
        });
    }

    public function boot()
    {
        //
    }
}
