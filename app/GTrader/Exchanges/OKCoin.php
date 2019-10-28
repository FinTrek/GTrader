<?php

namespace GTrader\Exchanges;

use GTrader\Exchange;
use GTrader\Trade;
use GTrader\Log;
use GuzzleHttp\Client as HttpClient;
use OKCoinWrapper;

/* deprecated class */

class OKCoin extends Exchange
{
    protected function getConfiguredSymbols(array $options = []): array
    {
        return $this->getAllSymbols();
    }

    protected function request(string $method, ...$params)
    {
        $client = new HttpClient(['version' => 1.0, 'timeout' => 30]);
        $okcoin = new OKCoinWrapper($client);

        try {
            return call_user_func_array([$okcoin, $method], $params);
        } catch (\Exception $e) {
            Log::error('Exception '.$e->getCode().': '.$e->getMessage().
                ' call: '.get_class($okcoin).'->'.$method.'('.json_encode($params).')');
            return null;
        }
    }

    public function form(array $options = [])
    {
        return view('Exchanges/OKCoin_form', [
            'exchange' => $this,
            'options' => $options,
        ]);
    }

    public function saveFilledTrades(string $symbol, int $bot_id = null)
    {
        $order_types = $this->getParam('order_types');
        $statuscodes = $this->getParam('order_statuscodes');

        $orders = $this->getOrderHistory($symbol, 'filled');

        if (!is_object($orders)) {
            return $this;
        }
        if (!is_array($orders->orders)) {
            return $this;
        }
        if (!($user_id = $this->getParam('user_id'))) {
            throw new \Exception('need user id');
        }

        foreach ($orders->orders as $order) {
            $trade = Trade::firstOrNew(['remote_id' => $order->order_id]);
            $trade->time = substr($order->create_date, 0, -3);
            $trade->remote_id = $order->order_id;
            $trade->exchange_id = $this->getId();
            $trade->symbol_id = self::getSymbolIdByRemoteName($order->symbol);
            $trade->user_id = $user_id;
            $trade->bot_id = $bot_id;
            $trade->amount_ordered = $order->amount;
            $trade->amount_filled = $order->deal_amount;
            $trade->price = $order->price;
            $trade->avg_price = $order->price_avg;
            $trade->action = $order_types[$order->type];
            $trade->type = 'limit';
            $trade->fee = $order->fee;
            $trade->fee_currency = substr($order->symbol, 0, 3);
            $trade->status = $statuscodes[$order->status];
            $trade->leverage = intval($order->lever_rate);
            $trade->contract = $order->contract_name;
            $trade->save();
        }
        return $this;
    }


    public function cancelOpenOrders(string $symbol, int $before_timestamp = 0)
    {
        $orders = $this->getOrderHistory($symbol, 'unfilled');

        if (!is_object($orders)) {
            return $this;
        }
        if (!is_array($orders->orders)) {
            return $this;
        }
        foreach ($orders->orders as $order) {
            if ((int)substr($order->create_date, 0, -3) < $before_timestamp) {
                // status: -1 = cancelled, 0 = unfilled, 1 = partially filled,
                // 2 = fully filled, 4 = cancel request in process
                if ($order->status == 0 || $order->status == 1) {
                    $reply = $this->cancelOrder($symbol, $order->order_id);
                }
            }
        }
        return $this;
    }



    public function takePosition(
        string $symbol,
        array $signal,
        int $bot_id = null
    ) {
        if (!($symbol_arr = $this->getParam('symbols.'.$symbol))) {
            throw new \Exception('Symbol config not found for '.$symbol);
        }
        if (!($remote_symbol = $symbol_arr['remote_name'])) {
            throw new \Exception('Remote name not found');
        }

        $leverage       = $this->getUserOption('leverage');
        $market_orders  = $this->getUserOption('market_orders');
        $statuscodes    = $this->getParam('order_statuscodes');

        $userinfo = $this->getUserInfo();
        //var_export($userinfo);

        $positions = $this->getPositions($symbol);
        //var_export($positions);

        $currency = strtolower(substr($remote_symbol, 0, 3));
        $position_size = $userinfo->info->$currency->rights *
                            $this->getUserOption('position_size') / 100;
        //if ($position_size > $userinfo->info->$currency->balance) return null;
        $position_contracts = floor($position_size * $price / $leverage);
        if ($position_contracts > $this->getUserOption('max_contracts')) {
            $position_contracts = $this->getUserOption('max_contracts');
        }

        $short_contracts_open = $long_contracts_open = 0;
        if (is_array($positions->holding)) {
            foreach ($positions->holding as $open_position) {
                $short_contracts_open += $open_position->sell_amount;
                $long_contracts_open += $open_position->buy_amount;
            }
        }
        //echo "\nBalance OK. Trade contracts: ".$position_contracts.' '.
        //        'Open: '.$long_contracts_open.' long, '.$short_contracts_open." short.\n";


        if ('long' === $signal) {
            // close all short positions
            if (is_array($positions->holding)) {
                foreach ($positions->holding as $position) {
                    if ($position->sell_amount) {
                        $this->trade(
                            'close_short',
                            $price,
                            $position->sell_amount,
                            $symbol,
                            $leverage,
                            $market_orders,
                            $bot_id
                        );
                    }
                }
            }
            // open long position
            if ($long_contracts_open < $position_contracts
                                    && $position_contracts - $long_contracts_open > 0) {
                $reply = $this->trade(
                    'open_long',
                    $price,
                    $position_contracts - $long_contracts_open,
                    $symbol,
                    $leverage,
                    $market_orders,
                    $bot_id
                );
            }
        } elseif ('short' === $signal) {
            // close all long positions
            if (is_array($positions->holding)) {
                foreach ($positions->holding as $position) {
                    if ($position->buy_amount) {
                        $this->trade(
                            'close_long',
                            $price,
                            $position->buy_amount,
                            $symbol,
                            $leverage,
                            $market_orders,
                            $bot_id
                        );
                    }
                }
            }
            // open short position
            if ($short_contracts_open < $position_contracts
                && $position_contracts - $short_contracts_open > 0) {
                $reply = $this->trade(
                    'open_short',
                    $price,
                    $position_contracts - $short_contracts_open,
                    $symbol,
                    $leverage,
                    $market_orders,
                    $bot_id
                );
            }
        } else {
            throw new \Exception('unknown signal');
        }

        return $this;
    }


    /**
     * Get ticker.
     *
     * @param $params array
     * @return array
     */
    public function getTicker(string $symbol)
    {
        if (!($symbol_arr = $this->getParam('symbols.'.$symbol))) {
            throw new \Exception('Symbol config not found for '.$symbol);
        }
        if (!($remote_symbol = $symbol_arr['remote_name'])) {
            throw new \Exception('Remote name not found');
        }

        $reply = $this->request('getTicker', [
            'symbol' => $remote_symbol,
        ]);

        return get_object_vars($reply->ticker);
    }


    /**
     * Get candles from the exchange.
     *
     * @param $symbol string
     * @param $resolution int
     * @param $since int
     * @param $size int
     * @return array of Candles
     */
    public function fetchCandles(
        string $symbol,
        int $resolution,
        int $since = 0,
        int $size = 0
    ) {
        if (!($symbol_arr = $this->getParam('symbols.'.$symbol))) {
            throw new \Exception('Symbol config not found for '.$symbol);
        }
        if (!($remote_symbol = $symbol_arr['remote_name'])) {
            throw new \Exception('Remote name not found');
        }

        $type = $this->resolution2name($resolution);
        $since = $since.'000';

        $kline = $this->request('getKline', [
            'symbol' => $remote_symbol,
            'type' => $type,
            'since' => $since,
            'size' => $size,
        ]);

        if (!is_array($kline)) {
            return [];
        }
        if (!count($kline)) {
            return [];
        }

        $exchange_id = $this->getId();
        if (!($symbol_id = self::getSymbolIdByExchangeSymbolName(
            $this->getName(),
            $symbol
        ))) {
            throw new \Exception('Could not find symbol ID for '.$symbol);
        }

        $candles = [];
        foreach ($kline as $candle) {
            $new_candle = new \stdClass();
            $new_candle->open = $candle[1];
            $new_candle->high = $candle[2];
            $new_candle->low = $candle[3];
            $new_candle->close = $candle[4];
            $new_candle->volume = $candle[5];
            $new_candle->time = (int)substr($candle[0], 0, -3);
            $new_candle->exchange_id = $exchange_id;
            $new_candle->symbol_id = $symbol_id;
            $new_candle->resolution = $resolution;
            $candles[] = $new_candle;
        }
        return $candles;
    }


    protected function getUserInfo()
    {
        return $this->request(
            'postUserinfo4fix',
            $this->getUserOption('api_key'),
            $this->getUserOption('api_secret')
        );
    }



    protected function trade(
        string $action,
        float $price,
        int $num_contracts,
        string $symbol,
        int $leverage,
        int $market_orders,
        int $bot_id = null
    ) {
        $order_types = [    1 => 'open_long',
                            2 => 'open_short',
                            3 => 'close_long',
                            4 => 'close_short'];

        if (!($order_type = array_search($action, $order_types))) {
            throw new \Exception('Order type not found for '.$action);
        }
        if (!($symbol_arr = $this->getParam('symbols.'.$symbol))) {
            throw new \Exception('Symbol config not found for '.$symbol);
        }
        if (!($remote_symbol = $symbol_arr['remote_name'])) {
            throw new \Exception('Remote name not found');
        }
        if (!($user_id = $this->getParam('user_id'))) {
            throw new \Exception('need user id');
        }

        if ('production' !== config('app.env')) {
            echo "\nNot in production, not executing trade(".serialize(func_get_args()).")\n";
            return $this;
        }

        $reply = $this->request(
            'postTrade',
            $this->getUserOption('api_key'),
            $this->getUserOption('api_secret'),
            [
                'symbol'        => $remote_symbol,
                'type'          => $order_type,
                'price'         => round($price, 2),
                'amount'        => intval($num_contracts),
                'lever_rate'    => $leverage,
                // Match best counter party price (BBO)?
                // 0: No 1: Yes   If yes, the 'price' field is ignored
                'match_price'   => $market_orders,
            ]
        );
        if (!is_object($reply)) {
            throw new \Exception('Exchange->trade('.serialize(func_get_args()).
                                ') reply not an object: '.serialize($reply));
        }
        if (!$reply->result) {
            throw new \Exception('Exchange->trade('.serialize(func_get_args()).
                                ') no result: '.serialize($reply));
        }

        $trade = new Trade();
        $trade->time = time();
        $trade->remote_id = $reply->order_id;
        $trade->exchange_id = $this->getId();
        $trade->symbol_id = self::getSymbolIdByRemoteName($remote_symbol);
        $trade->user_id = $user_id;
        $trade->bot_id = $bot_id;
        $trade->amount_ordered = $num_contracts;
        $trade->amount_filled = 0;
        $trade->price = $price;
        $trade->avg_price = 0;
        $trade->action = $action;
        $trade->type = 'limit';
        $trade->fee = 0;
        $trade->fee_currency = substr($symbol, 0, 3);
        $trade->status = 'submitted';
        $trade->leverage = $leverage;
        $trade->save();

        return $this;
    }


    protected function cancelOrder(string $symbol, int $remote_order_id)
    {
        if (!($symbol_arr = $this->getParam('symbols.'.$symbol))) {
            throw new \Exception('Symbol config not found for '.$symbol);
        }
        if (!($remote_symbol = $symbol_arr['remote_name'])) {
            throw new \Exception('Remote name not found');
        }

        if ('production' !== config('app.env')) {
            echo "\nNot in production, not executing cancelOrder(".serialize(func_get_args()).")\n";
            return $this;
        }

        $reply = $this->request(
            'postCancel',
            $this->getUserOption('api_key'),
            $this->getUserOption('api_secret'),
            [
                'symbol' => $remote_symbol,
                'order_id' => $remote_order_id,
            ]
        );

        if (!is_object($reply)) {
            throw new \Exception('Exchange->cancelOrder('.serialize(func_get_args()).
                                ') reply not an object: '.serialize($reply));
        }
        if (!$reply->result) {
            throw new \Exception('Exchange->cancelOrder('.serialize(func_get_args()).
                                ') no result: '.serialize($reply));
        }
        return $this;
    }


    protected function getOrderHistory(
        string $symbol,
        string $status,
        int $current_page = 1,
        int $page_length = 200
    ) {
        $statuscodes = [1 => 'unfilled', 2 => 'filled'];

        if (!($statuscode = array_search($status, $statuscodes))) {
            throw new \Exception('Status code not found for '.$status);
        }
        if (!($symbol_arr = $this->getParam('symbols.'.$symbol))) {
            throw new \Exception('Symbol config not found for '.$symbol);
        }
        if (!($remote_symbol = $symbol_arr['remote_name'])) {
            throw new \Exception('Remote name not found');
        }

        return $this->request(
            'postOrderInfo',
            $this->getUserOption('api_key'),
            $this->getUserOption('api_secret'),
            [
                'status' => $statuscode, //query by order status 1: unfilled  2: filled
                'symbol' => $remote_symbol,
                'current_page' => $current_page,
                'page_length' => $page_length,
                'order_id'  => -1,  // -1: return the orders of the specified status,
                                    // otherwise return the order that the ID specified
            ]
        );
    }


    protected function getPositions(string $symbol)
    {
        if (!($symbol_arr = $this->getParam('symbols.'.$symbol))) {
            throw new \Exception('Symbol config not found for '.$symbol);
        }
        if (!($remote_symbol = $symbol_arr['remote_name'])) {
            throw new \Exception('Remote name not found');
        }

        return $this->request(
            'postPosition4fix',
            $this->getUserOption('api_key'),
            $this->getUserOption('api_secret'),
            [
                'symbol' => $remote_symbol,
                'type' => 1
            ]
        );
        // type -- by default, positions with leverage rate 10 are returned.
        // If type = 1, all positions are returned
    }


    protected function resolution2name($resolution)
    {
        $resolution_names = $this->getParam('resolution_names');
        if (!array_key_exists($resolution, $resolution_names)) {
            throw new \Exception('Resolution '.$resolution.' not supported by '.__CLASS__);
        }
        return $resolution_names[$resolution];
    }
}
