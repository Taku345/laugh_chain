<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\StoryService;
use Illuminate\Support\Facades\Log;

class TopController extends Controller
{
    public function allOfficialAccountMosaics()
    {
        Log::debug('allOfficialAccountMosaics() start');

        $allOfficialAccountMosaicsAry = StoryService::allOfficialAccountMosaics();
        return view('top', [
            'allOfficialAccountMosaicsAry' => $allOfficialAccountMosaicsAry,
        ]);
        // return view('top', StoryService::allOfficialAccountMosaics());
    }
}
