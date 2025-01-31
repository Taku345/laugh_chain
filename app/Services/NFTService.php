<?php

namespace App\Services;

use SymbolSdk\CryptoTypes\PrivateKey;
use SymbolSdk\Symbol\Models\PublicKey;
use Illuminate\Support\Facades\Log;

/**
 * NFT関連のサービスクラス
 */
class NFTService
{
    /**
     * 指定アドレスアカウントのNFT一覧を取得
     * 存在しない場合は null, NFT がない場合は空配列を返す
     *
     * @param string $addressStr
     * @return array|null
     */
    public static function accountNFTs(String $addressStr)
    {
        // ServiceProviderからsymbol操作用クラスを取得
        $symbol = app('symbol.config');
        $facade = $symbol['facade'];
        $accountRoutesApi = $symbol['accountRoutesApi'];
        $mosaicRoutesApi = $symbol['mosaicRoutesApi'];

        try {
            $accountInfo = $accountRoutesApi->getAccountInfo($addressStr);
        } catch (\Exception $e) {
            Log::error($e);
            return null; // アカウントが存在しない場合はnullを返すのでこれで判別してください
        }

        $accountNFTs = [];
        foreach($accountInfo->getAccount()->getMosaics() as $mosaic) {

            //getMosaics()で得られるモザイク情報はid, amountしかないため詳細情報を取得
            $mosaicInfo = $mosaicRoutesApi->getMosaic($mosaic->getId());

            if (true) { // TODO:NFTのみを取得する条件を追加
                $accountNFTs[] = $mosaicInfo;
            }
        }
        return $accountNFTs;
    }
}


