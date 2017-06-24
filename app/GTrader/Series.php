<?php

namespace GTrader;

use Illuminate\Database\Eloquent\Collection;
use GTrader\Chart;
use GTrader\Exchange;
use GTrader\Candle;
use GTrader\Indicator;
use GTrader\Util;

class Series extends Collection
{
    use HasParams, HasIndicators, HasStrategy, ClassUtils;

    private $_loaded;
    private $_iter = 0;
    private $_map = [];

    public function __construct(array $params = [])
    {
        foreach (['exchange', 'symbol', 'resolution'] as $param) {
            if (isset($params[$param])) {
                $this->setParam($param, $params[$param]);
            }
            if (!$this->getParam($param)) {
                $this->setParam($param, Exchange::getDefault($param));
            }
        }

        $this->setParam('limit', isset($params['limit']) ? intval($params['limit']) : 200);
        $this->setParam('start', isset($params['start']) ? intval($params['start']) : 0);
        $this->setParam('end', isset($params['end']) ? intval($params['end']) : 0);
        $this->setParam('resolution', intval($this->getParam('resolution')));
        parent::__construct();
    }


    public function __sleep()
    {
        return ['params', 'indicators'];
    }

    public function __wakeup()
    {
    }


    public function key(string $signature = null)
    {
        if (in_array($signature, ['time', 'open', 'high', 'low', 'close', 'volume'])) {
            return $signature;
        }
        if (isset($this->_map[$signature])) {
            return $this->_map[$signature];
        }
        if (in_array($signature, $this->_map)) {
            return $signature;
        }
        if ('Constant' === Indicator::getClassFromSignature($signature)) {
            return $signature;
        }
        $this->_map[$signature] = Util::uniqidReal();
        return $this->_map[$signature];
    }


    public function getCandles()
    {
        $this->_load();
        return $this;
    }


    public function setCandles(Series $candles)
    {
        //$this->clean();
        $this->items = $candles->items;
        return $this;
    }

    public function byKey($key)
    {
        $this->_load();
        return isset($this->items[$key]) ? $this->items[$key] : null;
    }


    public function next($advance_iterator = true)
    {
        $this->_load();
        $ret = isset($this->items[$this->_iter]) ? $this->items[$this->_iter] : null;
        if ($advance_iterator) {
            $this->_iter++;
        }
        return $ret;
    }


    public function prev($stepback = 1, $redvance_iterator = false)
    {
        $this->_load();
        $ret = isset($this->items[$this->_iter-$stepback-1]) ?
                  $this->items[$this->_iter-$stepback-1] :
                  null;
        if ($redvance_iterator) {
            $this->_iter -= $stepback+1;
        }
        return $ret;
    }


    //public function last()
    //{
        //$this->_load();
        //return $this->items[$this->size()-1];
    //}

    public function set($candle = null)
    {
        if (!is_object($candle)) {
            throw new \Exception('set needs candle object');
        }
        $this->_load();
        if (isset($this->items[$this->_iter-1])) {
            $this->items[$this->_iter-1] = $candle;
            return true;
        }
        return false;
    }




    public function all()
    {
        $this->_load();
        return $this->items;
    }


    public function size()
    {
        $this->_load();
        return count($this->items);
    }


    public function add($candle)
    {
        $this->_load();
        $this->items[] = $candle;
    }


    public function reset()
    {
        $this->_load();
        $this->_iter = 0;
        return $this;
    }


    public function clean()
    {
        $this->items = array();
        $this->_loaded = false;
        $this->reset();
    }


    private function _load()
    {
        if ($this->_loaded) {
            return false;
        }
        $this->_loaded = true;

        $start = $this->getParam('start');
        if ($start < 0) {
            $start = 0;
        }
        $end = $this->getParam('end');
        $limit = $this->getParam('limit');
        $no_limit = $limit < 1 ? true : false;

        if (count($this->items)) {
            return;
        }

        $candles = Candle::select('time', 'open', 'high', 'low', 'close', 'volume')
            ->where('resolution', intval($this->getParam('resolution')))
            ->join('exchanges', 'candles.exchange_id', '=', 'exchanges.id')
            ->where('exchanges.name', $this->getParam('exchange'))
            ->join('symbols', function ($join) {
                $join->on('candles.symbol_id', '=', 'symbols.id')
                    ->whereColumn('symbols.exchange_id', '=', 'exchanges.id');
            })
            ->where('symbols.name', $this->getParam('symbol'))
            ->when($start, function ($query) use ($start) {
                    return $query->where('time', '>=', $start);
            })
            ->when($end, function ($query) use ($end) {
                    return $query->where('time', '<=', $end);
            })
            ->orderBy('time', 'desc')
            ->when(!$no_limit, function ($query) use ($limit) {
                    return $query->limit($limit);
            })
            ->get()
            ->reverse()
            ->values();

        //if ($candles->isEmpty()) throw new \Exception('Empty result');
        if (!count($candles->items)) {
            return $this;
        }

        $this->items = $candles->items;
        //$this->setParam('start', $this->next()->time);
        //$this->setParam('end', $this->last()->time);
        return $this->reset();
    }


    public function save()
    {
        $this->reset();
        while ($candle = $this->next()) {
            $candle->save();
        }
        return true;
    }


    public function getEpoch($resolution = null, $symbol = null, $exchange = null)
    {
        static $cache = [];

        foreach ([ 'resolution', 'symbol', 'exchange'] as $param) {
            if (is_null($$param)) {
                $$param = $this->getParam($param);
            }
        }

        if (isset($cache[$exchange][$symbol][$resolution])) {
            return $cache[$exchange][$symbol][$resolution];
        }

        $candle = Candle::select('time')
            ->join('exchanges', 'candles.exchange_id', '=', 'exchanges.id')
            ->where('exchanges.name', $exchange)
            ->join('symbols', function ($join) {
                $join->on('candles.symbol_id', '=', 'symbols.id')
                    ->whereColumn('symbols.exchange_id', '=', 'exchanges.id');
            })
            ->where('symbols.name', $symbol)
            ->where('resolution', $resolution)
            ->orderBy('time')
            ->first();

        $epoch = isset($candle->time) ? $candle->time : null;
        $cache[$exchange][$symbol][$resolution] = $epoch;

        return $epoch;
    }


    public function getLastInSeries($resolution = null, $symbol = null, $exchange = null)
    {
        static $cache = [];

        foreach ([ 'resolution', 'symbol', 'exchange'] as $param) {
            if (is_null($$param)) {
                $$param = $this->getParam($param);
            }
        }

        if (isset($cache[$exchange][$symbol][$resolution])) {
            return $cache[$exchange][$symbol][$resolution];
        }

        $candle = Candle::select('time')
                        ->join('exchanges', 'candles.exchange_id', '=', 'exchanges.id')
                        ->where('exchanges.name', $exchange)
                        ->join('symbols', function ($join) {
                            $join->on('candles.symbol_id', '=', 'symbols.id')
                                ->whereColumn('symbols.exchange_id', '=', 'exchanges.id');
                        })
                        ->where('symbols.name', $symbol)
                        ->where('resolution', $resolution)
                        ->orderBy('time', 'desc')->first();

        $last = isset($candle->time) ? $candle->time : null;
        $cache[$exchange][$symbol][$resolution] = $last;

        return $last;
    }


    public function extract(string $field)
    {
        $field = $this->key($field);
        $this->reset();
        $ret = [];
        while ($candle = $this->next()) {
            $ret[] = isset($candle[$field]) ? $candle[$field] : null;
        }
        return $ret;
    }



    public function setValues(string $field, array $values, $fill_value = null)
    {
        $field = $this->key($field);
        if (is_null($fill_value)) {
            // first valid value
            $fill_value = reset($values);
        }

        $key = 0;
        while ($candle = $this->byKey($key)) {
            $fill = $fill_value;
            if (in_array($fill, ['open', 'high', 'low', 'close'], true)) {
                $fill = $candle->$fill;
            }
            $candle->$field = isset($values[$key]) ? $values[$key] : $fill;
            $key++;
        }
        return $this;
    }



    public function realSlice(int $offset, int $length = null, bool $preserve_keys = false)
    {
        $this->_load();
        return array_slice($this->items, $offset, $length, $preserve_keys);
    }



















    /** Midrate */
    public static function ohlc4(Candle $candle)
    {
        if (isset($candle->open) && isset($candle->high) && isset($candle->low) && isset($candle->close)) {
            return ($candle->open + $candle->high + $candle->low + $candle->close) / 4;
        }
        throw new \Exception('Candle component missing');
    }



    // Series::crossover($prev_candle, $candle, 'rsi_4_close', 50)
    // Series::crossunder($prev_candle, $candle, 'close', 'bb_low_58_2_close')
    public static function crossover($prev_candle, $candle, $fish, $sea, $direction = 'over')
    {

        if (is_numeric($fish)) {
            $fish1 = $fish2 = $fish + 0;
        } elseif (is_string($fish)) {
            if (isset($prev_candle->$fish)) {
                $fish1 = $prev_candle->$fish;
            } else {
                throw new \Exception('Could not find fish1');
            }
            if (isset($candle->$fish)) {
                $fish2 = $candle->$fish;
            } else {
                throw new \Exception('Could not find fish2');
            }
        } else {
            throw new \Exception('Fish must either be string or numeric');
        }

        if (is_numeric($sea)) {
            $sea1 = $sea2 = $sea+0;
        } elseif (is_string($sea)) {
            if (isset($prev_candle->$sea)) {
                $sea1 = $prev_candle->$sea;
            } else {
                throw new \Exception('Could not find sea1');
            }
            if (isset($candle->$sea)) {
                $sea2 = $candle->$sea;
            } else {
                throw new \Exception('Could not find sea2');
            }
        } else {
            throw new \Exception('Sea must either be string or numeric');
        }

        return $direction == 'under' ?
            $fish1 > $sea1 && $fish2 < $sea2:
            $fish1 < $sea1 && $fish2 > $sea2;
    }


    public static function crossunder($prev_candle, $candle, $fish, $sea)
    {
        return self::crossover($prev_candle, $candle, $fish, $sea, 'under');
    }


    public static function normalize($in, $in_min, $in_max, $out_min = -1, $out_max = 1)
    {
        if ($in_max - $in_min == 0) {
            //error_log('Series::normalize() division by zero: '.$in.' '.$in_min.' '.$in_max.' '.$out_min.' '.$out_max);
            return $out_min + $out_max;
        }
        return ($out_max - $out_min) / ($in_max - $in_min) * ($in - $in_max) + $out_max;
    }
}
