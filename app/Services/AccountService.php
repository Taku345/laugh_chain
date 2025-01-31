<?php

namespace App\Services;

use Exception;
use SymbolSdk\Symbol\Models\MosaicSupplyRevocationTransactionV1;
use SymbolSdk\Symbol\Models\TransferTransactionV1;
use SymbolSdk\Symbol\Models\MosaicFlags;
use SymbolSdk\Symbol\Models\MosaicNonce;
use SymbolSdk\Symbol\Models\BlockDuration;
use SymbolSdk\Symbol\Models\UnresolvedMosaicId;
use SymbolSdk\Symbol\Models\UnresolvedMosaic;
use SymbolSdk\Symbol\Models\MosaicSupplyChangeAction;
use SymbolSdk\Symbol\Models\Amount;
use SymbolSdk\Symbol\IdGenerator;
use SymbolSdk\Symbol\Models\EmbeddedMosaicDefinitionTransactionV1;
use SymbolSdk\Symbol\Models\EmbeddedMosaicSupplyChangeTransactionV1;
use SymbolSdk\Symbol\Models\EmbeddedTransferTransactionV1;
use SymbolSdk\Symbol\Models\MosaicId;
use SymbolSdk\CryptoTypes\PrivateKey;
use SymbolSdk\Symbol\Models\AggregateCompleteTransactionV2;
use SymbolSdk\Symbol\Models\NetworkType;
use SymbolSdk\Symbol\Models\Timestamp;
use SymbolSdk\Symbol\Models\UnresolvedAddress;
use SymbolRestClient\Configuration;
use SymbolRestClient\Api\TransactionRoutesApi;
use SymbolRestClient\Api\TransactionStatusRoutesApi;
use SymbolRestClient\Api\AccountRoutesApi;
use SymbolRestClient\Api\MosaicRoutesApi;
use Illuminate\Support\Facades\Log;


class AccountService
{
    public static function NeverUseTwiceCreateUserCridencialMosaics(){ // ! 一回しか使わない前提
        // ServiceProviderからsymbol操作用クラスを取得
        $symbol = app('symbol.config');
        $facade = $symbol['facade'];
        $transactionRoutesApi = $symbol['transactionRoutesApi'];
        $officialAccount = $symbol['officialAccount'];

        $f = MosaicFlags::NONE;
        $f += MosaicFlags::SUPPLY_MUTABLE; // 供給量変更可能
        // $f += MosaicFlags::TRANSFERABLE; // 第三者への譲渡可否
        $f += MosaicFlags::RESTRICTABLE; //制限設定の可否
        $f += MosaicFlags::REVOKABLE; //発行者からの還収可否
        $flags = new MosaicFlags($f);

        $mosaicId = IdGenerator::generateMosaicId($officialAccount->address);
        // 桁数のチェック（15桁なら先頭に0を付ける）
        $hexMosaicId = strtoupper(dechex($mosaicId['id']));
        if (strlen($hexMosaicId) === 15) {
            $hexMosaicId = '0' . $hexMosaicId;
        }

        // モザイク定義
        $mosaicDefTx = new EmbeddedMosaicDefinitionTransactionV1(
            network: new NetworkType(NetworkType::TESTNET),
            signerPublicKey: $officialAccount->publicKey, // 署名者公開鍵
            id: new MosaicId($mosaicId['id']), // モザイクID
            divisibility: 0, // 分割可能性
            duration: new BlockDuration(0), //duration:有効期限
            nonce: new MosaicNonce($mosaicId['nonce']),
            flags: $flags,
        );

        //モザイク変更
        $mosaicChangeTx = new EmbeddedMosaicSupplyChangeTransactionV1(
            network: new NetworkType(NetworkType::TESTNET),
            signerPublicKey: $officialAccount->publicKey, // 署名者公開鍵
            mosaicId: new UnresolvedMosaicId($mosaicId['id']),
            delta: new Amount(5300000000),
            action: new MosaicSupplyChangeAction(MosaicSupplyChangeAction::INCREASE),
        );

        // マークルハッシュの算出
        $embeddedTransactions = [$mosaicDefTx, $mosaicChangeTx];
        $merkleHash = $facade->hashEmbeddedTransactions($embeddedTransactions);

        // アグリゲートTx作成
        $aggregateTx = new AggregateCompleteTransactionV2(
        network: new NetworkType(NetworkType::TESTNET),
        signerPublicKey: $officialAccount->publicKey,
        deadline: new Timestamp($facade->now()->addHours(2)),
        transactionsHash: $merkleHash,
        transactions: $embeddedTransactions
        );
        $facade->setMaxFee($aggregateTx, 100);  // 手数料

        // 署名
        $sig = $officialAccount->signTransaction($aggregateTx);
        $payload = $facade->attachSignature($aggregateTx, $sig);

        /**
         * アナウンス
         */

        try {
            $result = $transactionRoutesApi->announceTransaction($payload);
            echo $result . PHP_EOL;
        } catch (Exception $e) {
            echo 'Exception when calling TransactionRoutesApi->announceTransaction: ', $e->getMessage(), PHP_EOL;
        }
    }

    public static function sendUserCridencialMosaic(String $newUserAddress)
    {
        // ServiceProviderからsymbol操作用クラスを取得
        $symbol = app('symbol.config');
        $facade = $symbol['facade'];
        $transactionRoutesApi = $symbol['transactionRoutesApi'];
        $accountRoutesApi = $symbol['accountRoutesApi'];
        $mosaicRoutesApi = $symbol['mosaicRoutesApi'];
        $officialAccount = $symbol['officialAccount'];
        $testUserAccount = $symbol['testUserAccount'];

        // $newUserAddressのユーザーが既にUserCridencialMosaicを持っていないことを確認
        $isExistAccountInfo = false;
        try {
            $accountInfo = $accountRoutesApi->getAccountInfo($newUserAddress);
            $isExistAccountInfo = true;

            // アカウントが存在しない、一度もTxに関わっていない場合catchに入る
            // ここはSSS Extentionが作成したアカウントをチェーンに認識されるまでやってくれてるか確認してから考える
            // もしかしたらUnresolvedAddressクラスを使うべき？そっちの方が楽かも
        } catch (\Exception $e) {
            //アカウントが存在しないor一度もTxに関わっていないか判別方法判別方法を知らないので、暫定で後者とみなす
        }

        //$account
        if($isExistAccountInfo){
            foreach($accountInfo->getAccount()->getMosaics() as $mosaic) {
                //TODO : 以下の条件分が働いてない、多分16か10か文字列かの問題、あとアドレスってデータ構造の中に二つあるからそれかも
                if($mosaic->getId() == env('USER_CREDENTIAL_MOSAIC_ID')) return throw New Exception("既に同じモザイクを持っています");
            }
        }

        $temp = hexdec(env('USER_CREDENTIAL_MOSAIC_ID'));

        // モザイク送信
        $messageData = "\0このモザイクは所有アカウントがLaughChainユーザーであることを示します";
        $transferTransaction = new TransferTransactionV1(
        network: new NetworkType(NetworkType::TESTNET),
        signerPublicKey: $officialAccount->publicKey,
        deadline: new Timestamp($facade->now()->addHours(2)),
        recipientAddress: New UnresolvedAddress($newUserAddress),
        mosaics: [
            new UnresolvedMosaic(
            mosaicId: new UnresolvedMosaicId(env('USER_CREDENTIAL_MOSAIC_ID')),
            amount: new Amount(1)
            )
        ],
        message: $messageData
        );

        $facade->setMaxFee($transferTransaction, 100);  // 手数料

        $signature = $officialAccount->signTransaction($transferTransaction);
        $payload = $facade->attachSignature($transferTransaction, $signature);

        try {
            $result = $transactionRoutesApi->announceTransaction($payload);
            echo $result . PHP_EOL;
        } catch (Exception $e) {
            echo 'Exception when calling TransactionRoutesApi->announceTransaction: ', $e->getMessage(), PHP_EOL;
        }

        // Log::debug("address表示");
        // Log::debug($officialAccount->address);
        // Log::debug($testUserAccount->address);

        return true;

    }

    public static function getAccountMosaics(String $accountAddressStr)
    {
        // ServiceProviderからsymbol操作用クラスを取得
        $symbol = app('symbol.config');
        $facade = $symbol['facade'];
        $accountRoutesApi = $symbol['accountRoutesApi'];
        $mosaicRoutesApi = $symbol['mosaicRoutesApi'];

        try {
            $accountInfo = $accountRoutesApi->getAccountInfo($accountAddressStr);
        } catch (\Exception $e) {
            Log::error($e);
            return null; // アカウントが存在しない場合はnullを返すのでこれで判別してください
        }

        $accountMosaics = [];
        foreach($accountInfo->getAccount()->getMosaics() as $mosaic) {

            //getMosaics()で得られるモザイク情報はid, amountしかないため詳細情報を取得
            $mosaicInfo = $mosaicRoutesApi->getMosaic($mosaic->getId());

            if (true) { // TODO:NFTのみを取得する条件を追加
                $accountMosaics[] = $mosaicInfo;
            }
        }
        return $accountMosaics;
    }


}
