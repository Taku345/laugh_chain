<?php

namespace App\Http\Controllers;

use App\Services\NFTService;
use Illuminate\Http\Request;
use App\Services\StoryService;
use Illuminate\Support\Facades\Log;

class TopController extends Controller
{
    public function toppage()
    {
        $symbol = app('symbol.config');
        $accountNFTs = NFTService::accountNFTs($symbol['officialAccount']->address); //とりあえず公式垢の全モザイクを取得してます
        return view('top', [
            'accountNFTs' => $accountNFTs,
        ]);
        // return view('top', StoryService::allOfficialAccountMosaics());
    }
}
