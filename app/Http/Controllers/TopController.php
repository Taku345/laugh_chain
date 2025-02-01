<?php

namespace App\Http\Controllers;

use App\Services\AccountService;
use App\Services\NFTService;
use Illuminate\Http\Request;
use App\Services\StoryService;
use Illuminate\Support\Facades\Log;
use SymbolSdk\CryptoTypes\PrivateKey;

class TopController extends Controller
{
    public function toppage()
    {
        $symbol = app('symbol.config');
        $accountNFTs = AccountService::getAccountMosaics($symbol['testUserAccount']->address); //とりあえずNFTに限らず全モザイクを取得してます


        //テスト用、ユーザーにクレデンシャルモザイクを送る
        $testUserAccount = $symbol['testUserAccount'];
        $tmp = AccountService::sendUserCridencialMosaic($testUserAccount->address);
        Log::debug("ddddd");
        Log::debug(strval($tmp));
        //テスト用ここまで

        return view('top', [
            'accountNFTs' => $accountNFTs,
        ]);
        // return view('top', StoryService::allOfficialAccountMosaics());
    }
}
