<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

/**
 * Servicio para validar archivos de aforos y extraer datos numéricos.
 *
 * Este servicio utiliza `maatwebsite/excel` para leer archivos Excel,
 * detecta la fila de encabezados y las columnas pertinentes y suma
 * los diferentes tipos de vehículos.  Devuelve λ_h y μ_h.
 */
class AforoValidatorService
{
    public function validateAndExtractData(string $filePath, ?string $disk = null): array
    {
        try {
            $absolutePath = $this->resolvePath($filePath, $disk);
            if (!is_file($absolutePath)) {
                throw new Exception('El archivo no se encuentra en la ruta especificada.');
            }
            $import     = Excel::toCollection(null, $absolutePath);
            $firstSheet = $import[0] ?? null;
            if (!$firstSheet || $firstSheet->isEmpty()) {
                throw new Exception('El archivo Excel está vacío o no se pudo leer.');
            }
            $validationResult = $this->validateStructure($firstSheet);
            if (!($validationResult['success'] ?? false)) {
                return $validationResult;
            }
            $headerRowIndex = $validationResult['data']['header_row_index'];
            $headerMap      = $validationResult['data']['header_map'];
            $extractedData  = $this->extractNumericalData($firstSheet, $headerRowIndex, $headerMap);
            return [
                'success' => true,
                'message' => 'Archivo validado y datos extraídos correctamente.',
                'data'    => $extractedData,
            ];
        } catch (Throwable $e) {
            Log::error('AforoValidatorService error', mask_sensitive([
                'message'   => substr($e->getMessage(), 0, 200),
                'exception' => get_class($e),
            ]));
            return [
                'success' => false,
                'message' => 'Ocurrió un error técnico al procesar el archivo.',
                'data'    => [],
            ];
        }
    }

    private function resolvePath(string $filePath, ?string $disk = null): string
    {
        if (is_file($filePath)) {
            return $filePath;
        }
        if ($disk && Storage::disk($disk)->exists($filePath)) {
            return Storage::disk($disk)->path($filePath);
        }
        if (Storage::exists($filePath)) {
            return Storage::path($filePath);
        }
        return $filePath;
    }

    private function extractNumericalData(Collection $sheet, int $headerRowIndex, array $headerMap): array
    {
        $totalVehiculos = 0;
        for ($i = $headerRowIndex + 1; $i < $sheet->count(); $i++) {
            $row = $sheet[$i];
            $totalVehiculos += (int) ($row[$headerMap['AUTOS']]     ?? 0);
            $totalVehiculos += (int) ($row[$headerMap['BUSES']]     ?? 0);
            $totalVehiculos += (int) ($row[$headerMap['CAMIONES']]  ?? 0);
            $totalVehiculos += (int) ($row[$headerMap['MOTOS']]     ?? 0);
        }
        $lambdaH = $totalVehiculos;
        $muH     = 600; // valor predeterminado que puede ajustarse según la encuesta
        Log::info('Datos extraídos del aforo', mask_sensitive(['lambda_h' => $lambdaH, 'mu_h' => $muH]));
        return [
            'lambda_h' => $lambdaH,
            'mu_h'     => $muH,
        ];
    }

    private function validateStructure(Collection $sheet): array
    {
        $headerRow     = null;
        $headerRowIndex= -1;
        $maxRowsToCheck= min(10, $sheet->count());
        for ($i = 0; $i < $maxRowsToCheck; $i++) {
            $row    = $sheet[$i]->toArray();
            $filled = array_filter($row, fn ($value) => is_string($value) && trim($value) !== '');
            if (count($filled) >= 3) {
                $headerRow      = array_map('strval', $row);
                $headerRowIndex = $i;
                break;
            }
        }
        if (!$headerRow) {
            return ['success' => false, 'message' => 'No se pudo detectar la fila de encabezados en el archivo.'];
        }
        $normalize = static function ($str) {
            $str = strtolower(trim($str));
            $replacements = [
                '\u{00E1}' => 'a',
                '\u{00E9}' => 'e',
                '\u{00ED}' => 'i',
                '\u{00F3}' => 'o',
                '\u{00FA}' => 'u',
                '\u{00F1}' => 'n',
            ];
            $str = strtr($str, $replacements);
            return preg_replace('/[^a-z0-9]/', '', $str);
        };
        $normalizedHeaders = array_map($normalize, $headerRow);
        $columnKeywords    = [
            'PERIODO'    => ['periodo', 'hora', 'franja'],
            'AUTOS'      => ['auto', 'carro', 'vehiculo', 'autos', 'carros', 'vehiculos'],
            'BUSES'      => ['bus', 'buses', 'omnibus', 'buseta', 'busetas'],
            'CAMIONES'   => ['camion', 'camiones', 'camioneta', 'camionetas'],
            'MOTOS'      => ['moto', 'motocicleta', 'motocicletas', 'motos'],
            'BICICLETAS' => ['bici', 'bicicleta', 'bicicletas', 'ciclista', 'ciclistas'],
        ];
        $missing   = [];
        $headerMap = [];
        foreach ($columnKeywords as $requiredColumn => $keywords) {
            $found = false;
            foreach ($normalizedHeaders as $index => $header) {
                foreach ($keywords as $keyword) {
                    if (strpos($header, $normalize($keyword)) !== false) {
                        $headerMap[$requiredColumn] = $index;
                        $found = true;
                        break 2;
                    }
                }
            }
            if (!$found) {
                $missing[] = $requiredColumn;
            }
        }
        if (empty($missing)) {
            return [
                'success' => true,
                'message' => 'El formato del archivo de aforos es correcto.',
                'data'    => [
                    'header_row_index' => $headerRowIndex,
                    'header_map'       => $headerMap,
                ],
            ];
        }
        $missingList = implode(', ', $missing);
        return [
            'success' => false,
            'message' => 'Validación fallida. Faltan las siguientes columnas obligatorias: ' . $missingList . '.',
        ];
    }
}