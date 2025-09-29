<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Log;

/**
 * Servicio para calcular costos y construir ítems de cotización.
 *
 * Lee la configuración de servicios y combos desde `config/laia.php`.
 */
class QuoteService
{
    /**
     * Calcula el costo total para un conjunto de códigos de servicios,
     * aplicando combos cuando corresponda.
     */
    public function calculateCost(array $serviceCodes): array
    {
        try {
            $servicesConfig = config('laia.services', []);
            $combosConfig   = config('laia.combos', []);
            $currency       = config('laia.currency', 'COP');
            $symbol         = config('laia.currency_symbol', '$');
            $baseSum = 0.0;
            foreach ($serviceCodes as $code) {
                $baseSum += (float) ($servicesConfig[$code]['price'] ?? 0);
            }
            $totalCost    = $baseSum;
            $comboApplied = false;
            foreach ($combosConfig as $combo) {
                if (count($serviceCodes) == count($combo['services'])
                    && !array_diff($serviceCodes, $combo['services'])
                    && !array_diff($combo['services'], $serviceCodes)) {
                    $totalCost    = (float) $combo['total_price'];
                    $comboApplied = true;
                    break;
                }
            }
            return [
                'amount'    => $totalCost,
                'base_sum'  => $baseSum,
                'combo'     => $comboApplied,
                'currency'  => $currency,
                'symbol'    => $symbol,
                'formatted' => $symbol . number_format($totalCost, 0, ',', '.') . ' ' . $currency,
            ];
        } catch (Exception $e) {
            Log::error('QUOTE_CALCULATION_ERROR', mask_sensitive([
                'message'    => substr($e->getMessage(), 0, 200),
                'exception'  => get_class($e),
            ]));
            $default  = (float) config('laia.services.SVC_VEH.price', 500000);
            $currency = config('laia.currency', 'COP');
            $symbol   = config('laia.currency_symbol', '$');
            return [
                'amount'    => $default,
                'base_sum'  => $default,
                'combo'     => false,
                'currency'  => $currency,
                'symbol'    => $symbol,
                'formatted' => $symbol . number_format($default, 0, ',', '.') . ' ' . $currency,
            ];
        }
    }

    /**
     * Construye los ítems de una cotización y calcula totales.
     */
    public function buildItems(array $serviceCodes): array
    {
        $servicesConfig = config('laia.services', []);
        $calc  = $this->calculateCost($serviceCodes);
        $total = (float) $calc['amount'];
        $base  = (float) $calc['base_sum'];
        $items = [];
        foreach ($serviceCodes as $code) {
            $items[] = [
                'code'        => $code,
                'description' => $servicesConfig[$code]['name'] ?? $code,
                'unit_price'  => (float) ($servicesConfig[$code]['price'] ?? 0),
                'quantity'    => 1,
                'line_total'  => 0.0,
            ];
        }
        if ($base <= 0.0 || abs($base - $total) < 0.01) {
            foreach ($items as &$it) {
                $it['line_total'] = $it['unit_price'];
            }
            unset($it);
        } else {
            $acc = 0.0;
            $n   = count($items);
            foreach ($items as $i => &$it) {
                if ($i < $n - 1) {
                    $portion         = ($it['unit_price'] / $base) * $total;
                    $portion         = round($portion, 2);
                    $it['line_total']= $portion;
                    $acc            += $portion;
                } else {
                    $it['line_total'] = round($total - $acc, 2);
                }
            }
            unset($it);
        }
        return [
            'items'  => $items,
            'totals' => [
                'subtotal' => array_sum(array_column($items, 'line_total')),
                'tax_rate' => (float) config('laia.tax_rate', 0),
                'currency' => $calc['currency'],
                'symbol'   => $calc['symbol'],
            ],
        ];
    }
}