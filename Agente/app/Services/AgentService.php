<?php

namespace App\Services;

use Gemini\Laravel\Facades\Gemini;
use Gemini\Data\Content;
use Gemini\Data\FunctionDeclaration;
use Gemini\Data\FunctionResponse;
use Gemini\Data\Part;
use Gemini\Data\Schema;
use Gemini\Data\Tool;
use Gemini\Enums\DataType;
use Gemini\Enums\Role;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Servicio principal que implementa la lógica de conversaciones con el
 * modelo generativo de Gemini.  Gestiona el historial de chat,
 * inyecta instrucciones del sistema y ejecuta herramientas de
 * función tras la llamada al modelo.
 */
class AgentService
{
    private const TOOL_GET_CALCULATION  = 'get_calculation_result';
    private const TOOL_FIND_APPT        = 'find_available_appointment';
    private const TOOL_MU_BOOTSTRAP     = 'mu_bootstrap_from_address';
    private const TOOL_CAPTURE_CLIENT   = 'capture_client_info';
    private const TOOL_SET_CUSTOMER     = 'quote_set_customer';
    private const TOOL_SET_SERVICES     = 'quote_set_services';
    /** Saludo estándar controlado por backend */
    private const GREETING = 'Hola, soy David. Dime, ¿en qué te puedo ayudar?';

    /**
     * Genera la respuesta del modelo a partir del prompt del usuario y el
     * historial de chat.  Ejecuta un bucle de llamadas a herramientas
     * cuando el modelo lo solicita.
     */
    public function getResponse(string $prompt, array $chatHistory = []): array
    {
        try {
            // 0) Saludo único si no hay historial y el input es un saludo
            $user = trim($prompt);
            if (empty($chatHistory) && $this->looksLikeGreeting($user)) {
                $msg = self::GREETING;
                return [
                    'response' => $msg,
                    'history'  => [
                        ['role' => 'user',  'parts' => [['text' => $user]]],
                        ['role' => 'model', 'parts' => [['text' => $msg]]],
                    ],
                ];
            }
            // 1) Convertir historial a objetos Content
            $apiHistory = [];
            foreach ($chatHistory as $message) {
                if (is_array($message) && isset($message['role'], $message['parts']) && method_exists(Content::class, 'fromArray')) {
                    $apiHistory[] = Content::fromArray($message);
                } elseif (isset($message['role'], $message['content'])) {
                    $apiHistory[] = Content::parse(
                        role: $message['role'] === 'ai' ? Role::MODEL : Role::USER,
                        part: (string) ($message['content'] ?? '')
                    );
                }
            }
            // 2) Inyectar las instrucciones del sistema en el primer turno
            if (empty($apiHistory)) {
                $apiHistory[] = Content::parse(role: Role::USER,  part: $this->getSystemInstructions());
                $apiHistory[] = Content::parse(role: Role::MODEL, part: 'Entendido. Responderé siempre en español y seguiré el flujo de LAIA.');
            }
            // 3) Preparar el modelo con herramientas
            $tools    = $this->getAvailableTools();
            $modelName= config('gemini.model', 'gemini-1.5-pro-latest');
            $gm       = Gemini::generativeModel($modelName);
            if (method_exists($gm, 'withTool')) {
                foreach ($tools as $tool) { $gm = $gm->withTool($tool); }
            } elseif (method_exists($gm, 'withTools')) {
                $gm = $gm->withTools($tools);
            }
            // 4) Iniciar el chat y enviar el prompt
            $chat    = $gm->startChat(history: $apiHistory);
            $response= $chat->sendMessage($prompt);
            // 5) Ejecutar bucle de llamadas a función (máximo 3 rondas)
            $rounds  = 0;
            while ($rounds++ < 3) {
                $calls = $this->extractFunctionCalls($response);
                if (empty($calls)) break;
                $parts = [];
                foreach ($calls as $call) {
                    $fr    = $this->executeTool($call['name'], (array) ($call['args'] ?? []));
                    $parts[] = new Part(functionResponse: $fr);
                }
                $response = $chat->sendMessage(new Content(role: Role::USER, parts: $parts));
            }
            // 6) Extraer la respuesta de texto
            $answer   = '';
            $resParts = method_exists($response, 'parts') ? $response->parts() : ($response->candidates[0]->content->parts ?? []);
            if (is_iterable($resParts)) {
                foreach ($resParts as $p) {
                    $t = is_object($p) ? (string) ($p->text ?? '') : (is_array($p) ? (string) ($p['text'] ?? '') : '');
                    if ($t !== '') { $answer .= ($answer ? "\n" : '') . $t; }
                }
            }
            $answer = trim($answer) ?: "Listo. ¿Deseas que te envíe la cotización estimada y continuar al pago para generar los documentos de radicación?";
            // 7) Devolver historial actualizado
            $updatedHistory = [];
            if (method_exists($chat, 'history')) {
                foreach ($chat->history() as $content) {
                    if (!method_exists($content, 'toArray')) continue;
                    $arr   = $content->toArray();
                    $first = $arr['parts'][0]['text'] ?? '';
                    if (is_string($first) && (str_contains($first, '## Identidad') || str_contains($first, 'Entendido. Responderé siempre en español y seguiré el flujo de LAIA.'))) {
                        continue;
                    }
                    $updatedHistory[] = $arr;
                }
            }
            $updatedHistory[] = ['role' => 'model', 'parts' => [['text' => $answer]]];
            return ['response' => $answer, 'history' => $updatedHistory];
        } catch (Throwable $e) {
            Log::error('GEMINI_AGENT_ERROR', mask_sensitive([
                'message'   => substr($e->getMessage(), 0, 200),
                'exception' => get_class($e),
            ]));
            return [
                'response' => 'Lo siento, tuve un problema para procesar tu solicitud en este momento.',
                'history'  => $chatHistory,
            ];
        }
    }

    private function looksLikeGreeting(string $t): bool
    {
        $t = mb_strtolower(trim($t));
        return $t === 'hola'
            || str_starts_with($t, 'hola')
            || str_starts_with($t, 'buenos')
            || str_starts_with($t, 'buenas')
            || str_starts_with($t, 'hey');
    }

    /**
     * Extrae todas las llamadas a funciones del turno actual.
     */
    private function extractFunctionCalls($response): array
    {
        $calls = [];
        if (method_exists($response, 'parts')) {
            $parts = $response->parts();
            if (is_iterable($parts)) {
                foreach ($parts as $part) {
                    $fc = null;
                    if (is_object($part)) {
                        $fc = method_exists($part, 'functionCall') ? $part->functionCall() : ($part->functionCall ?? null);
                    }
                    if ($fc && isset($fc->name)) {
                        $calls[] = [
                            'name' => (string) $fc->name,
                            'args' => $this->normalizeArgs($fc->args ?? []),
                        ];
                    }
                }
            }
        }
        if (empty($calls)) {
            $fc = method_exists($response, 'functionCall') ? $response->functionCall() : ($response->functionCall ?? null);
            if ($fc && isset($fc->name)) {
                $calls[] = [
                    'name' => (string) $fc->name,
                    'args' => $this->normalizeArgs($fc->args ?? []),
                ];
            }
        }
        return $calls;
    }

    private function normalizeArgs($args): array
    {
        if (is_array($args)) return $args;
        if (is_object($args)) return (array) $args;
        $decoded = json_decode((string) $args, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Ejecuta la herramienta solicitada y devuelve una FunctionResponse.
     */
    private function executeTool(string $toolName, array $arguments): FunctionResponse
    {
        Log::info("IA solicitó herramienta: {$toolName}", mask_sensitive(['args' => $arguments]));
        $resultData = [];
        switch ($toolName) {
            case self::TOOL_SET_CUSTOMER: {
                $name  = trim((string) ($arguments['customer_name'] ?? ''));
                $email = trim((string) ($arguments['customer_email'] ?? ''));
                $addr  = trim((string) ($arguments['project_address'] ?? ''));
                if ($name === '') {
                    $resultData = ['status' => 'error', 'message' => 'Falta customer_name.'];
                    break;
                }
                session([
                    'laia.customer_name'   => $name,
                    'laia.customer_email'  => $email ?: null,
                    'laia.project_address' => $addr ?: null,
                ]);
                $resultData = ['status' => 'ok'];
                break;
            }
            case self::TOOL_SET_SERVICES: {
                $raw      = (array) ($arguments['services'] ?? []);
                $valid    = array_map('strval', array_keys((array) config('laia.services', [
                    'SVC_VEH'   => ['name' => 'Análisis Vehicular'],
                    'SVC_COLAS' => ['name' => 'Análisis de Colas'],
                    'SVC_PEA'   => ['name' => 'Análisis Peatonal'],
                ])));
                $services = array_values(array_intersect($valid, array_map('strval', $raw)));
                if (empty($services)) {
                    $resultData = ['status' => 'error', 'message' => 'Lista de servicios vacía/ inválida.'];
                    break;
                }
                session(['laia.services' => $services]);
                $resultData = ['status' => 'ok', 'services' => $services];
                break;
            }
            case self::TOOL_CAPTURE_CLIENT: {
                $customerName  = trim((string) ($arguments['customer_name'] ?? ''));
                $customerEmail = trim((string) ($arguments['customer_email'] ?? ''));
                $entityType    = strtolower((string) ($arguments['entity_type'] ?? ''));
                $companyName   = trim((string) ($arguments['company_name'] ?? ''));
                $address       = trim((string) ($arguments['address'] ?? ''));
                $requested     = array_values(array_filter(array_map('strval', (array) ($arguments['requested_services'] ?? []))));
                $add           = array_values(array_filter(array_map('strval', (array) ($arguments['add_services'] ?? []))));
                $remove        = array_values(array_filter(array_map('strval', (array) ($arguments['remove_services'] ?? []))));
                $includeQ      = (bool) ($arguments['include_queues'] ?? false);
                if ($customerName !== '') session(['laia.customer_name' => $customerName]);
                if ($customerEmail !== '') session(['laia.customer_email' => $customerEmail]);
                if ($entityType !== '')    session(['laia.entity_type' => in_array($entityType, ['persona','empresa']) ? $entityType : 'persona']);
                if ($companyName !== '')   session(['laia.company_name' => $companyName]);
                if ($address !== '')       session(['laia.project_address' => $address]);
                $services = session('laia.services', []);
                $services = $this->mergeServices($services, $requested);
                $services = $this->mergeServices($services, $add);
                if (!empty($remove)) {
                    $services = array_values(array_filter($services, fn ($s) => !in_array($s, $remove, true)));
                }
                if ($includeQ && in_array('SVC_VEH', $services, true) && !in_array('SVC_COLAS', $services, true)) {
                    $services[] = 'SVC_COLAS';
                }
                session(['laia.services' => $services]);
                $resultData = [
                    'status'   => 'ok',
                    'customer' => [
                        'name'    => session('laia.customer_name'),
                        'email'   => session('laia.customer_email'),
                        'entity'  => session('laia.entity_type', 'persona'),
                        'company' => session('laia.company_name'),
                        'address' => session('laia.project_address'),
                    ],
                    'services' => $services,
                ];
                break;
            }
            case self::TOOL_MU_BOOTSTRAP: {
                $address = (string) ($arguments['address'] ?? '');
                if ($address === '') {
                    $resultData = ['status' => 'error', 'message' => 'Falta la dirección.'];
                    break;
                }
                // En un entorno real deberías inyectar un servicio que inferencie datos a partir de la dirección.
                $map      = app(MapInfoService::class);
                $res      = $map->inferContextFromAddress($address);
                if (($res['status'] ?? 'no_data') !== 'ok') {
                    $resultData = ['status' => 'no_data', 'need_questions' => true];
                    break;
                }
                $prefill   = $res['data'] ?? [];
                $survey    = session('laia.mu_survey', []);
                $survey    = array_merge($survey, array_filter([
                    'tipo_via'      => $prefill['road_type']  ?? null,
                    'carriles'      => $prefill['lanes']      ?? null,
                    'zona_especial' => $prefill['zone']       ?? null,
                    'pendiente_pct' => $prefill['slope_pct']  ?? null,
                    'semaforo'      => $prefill['has_signal'] ?? null,
                    'g'             => $prefill['g']          ?? null,
                    'C'             => $prefill['C']          ?? null,
                ], fn ($v) => !is_null($v)));
                session(['laia.mu_survey' => $survey]);
                $resultData = [
                    'status'      => 'ok',
                    'prefilled'   => $survey,
                    'is_arterial' => $prefill['is_arterial'] ?? null,
                    'need_more'   => $this->needsMoreSurvey($survey),
                ];
                break;
            }
            case self::TOOL_GET_CALCULATION: {
                $serviceId = (string) ($arguments['service_id'] ?? '');
                $resultData= ['status' => 'ok', 'service_id' => $serviceId, 'message' => 'Cálculo ejecutado (placeholder).'];
                break;
            }
            case self::TOOL_FIND_APPT: {
                $duration = (int) ($arguments['duration_in_minutes'] ?? 60);
                try {
                    /** @var \App\Services\GraphCalendarService $cal */
                    $cal  = app(GraphCalendarService::class);
                    $slot = $cal->findAvailableSlot($duration);
                    $resultData = $slot ? ['status' => 'ok', 'slot' => $slot] : ['status' => 'no_slots', 'message' => 'No se encontraron citas disponibles en la próxima semana.'];
                } catch (Throwable $e) {
                    Log::error('FIND_APPOINTMENT_ERROR', mask_sensitive([
                        'message'   => substr($e->getMessage(), 0, 200),
                        'exception' => get_class($e),
                    ]));
                    $resultData = ['status' => 'error', 'message' => 'No se pudo consultar la agenda en este momento.'];
                }
                break;
            }
            default:
                $resultData = ['status' => 'error', 'message' => "Herramienta desconocida: {$toolName}"];
                break;
        }
        return new FunctionResponse(name: $toolName, response: $resultData);
    }

    /**
     * Fusiona servicios sin duplicados preservando el orden de llegada.
     */
    private function mergeServices(array $current, array $incoming): array
    {
        if (empty($incoming)) return array_values($current);
        $set = [];
        foreach (array_merge($current, $incoming) as $s) {
            $s = (string) $s;
            if ($s === '') continue;
            if (!isset($set[$s])) $set[$s] = true;
        }
        return array_keys($set);
    }

    /**
     * Comprueba si faltan datos en la encuesta de μ_h.
     */
    private function needsMoreSurvey(array $s): bool
    {
        if (empty($s['tipo_via']) || empty($s['carriles'])) return true;
        if (!empty($s['semaforo'])) {
            return empty($s['g']) || empty($s['C']);
        }
        return false;
    }

    /**
     * Devuelve el conjunto de herramientas disponibles para el modelo.
     */
    private function getAvailableTools(): array
    {
        return [
            new Tool(functionDeclarations: [
                new FunctionDeclaration(
                    name: self::TOOL_SET_CUSTOMER,
                    description: 'Guarda datos de cliente y proyecto para la cotización.',
                    parameters: new Schema(
                        type: DataType::OBJECT,
                        properties: [
                            'customer_name'   => new Schema(type: DataType::STRING, description: 'Nombre o empresa.'),
                            'customer_email'  => new Schema(type: DataType::STRING, description: 'Correo del cliente (opcional).'),
                            'project_address' => new Schema(type: DataType::STRING, description: 'Dirección del proyecto (opcional).'),
                        ],
                        required: ['customer_name']
                    )
                ),
                new FunctionDeclaration(
                    name: self::TOOL_SET_SERVICES,
                    description: 'Guarda los servicios confirmados por el cliente para la cotización.',
                    parameters: new Schema(
                        type: DataType::OBJECT,
                        properties: [
                            'services' => new Schema(
                                type: DataType::ARRAY,
                                description: 'Códigos confirmados: p.ej. ["SVC_VEH","SVC_COLAS"].',
                                items: new Schema(type: DataType::STRING)
                            ),
                        ],
                        required: ['services']
                    )
                ),
                new FunctionDeclaration(
                    name: self::TOOL_CAPTURE_CLIENT,
                    description: 'Guarda datos del cliente y fusiona servicios solicitados sin duplicados.',
                    parameters: new Schema(
                        type: DataType::OBJECT,
                        properties: [
                            'customer_name'  => new Schema(type: DataType::STRING, description: 'Nombre del cliente o razón social'),
                            'customer_email' => new Schema(type: DataType::STRING, description: 'Correo del cliente'),
                            'entity_type'    => new Schema(type: DataType::STRING, description: 'persona|empresa'),
                            'company_name'   => new Schema(type: DataType::STRING, description: 'Si entity_type=empresa'),
                            'address'        => new Schema(type: DataType::STRING, description: 'Dirección exacta del proyecto'),
                            'requested_services' => new Schema(
                                type: DataType::ARRAY,
                                description: 'Servicios a establecer/añadir (ej: ["SVC_VEH","SVC_COLAS"])',
                                items: new Schema(type: DataType::STRING)
                            ),
                            'add_services' => new Schema(
                                type: DataType::ARRAY,
                                description: 'Servicios adicionales a agregar',
                                items: new Schema(type: DataType::STRING)
                            ),
                            'remove_services' => new Schema(
                                type: DataType::ARRAY,
                                description: 'Servicios a remover del set actual',
                                items: new Schema(type: DataType::STRING)
                            ),
                            'include_queues' => new Schema(type: DataType::BOOLEAN, description: 'Si hay Vehicular, añadir Colas'),
                        ]
                    )
                ),
                new FunctionDeclaration(
                    name: self::TOOL_GET_CALCULATION,
                    description: 'Ejecuta un cálculo de ingeniería de tránsito (usa μ_h y λ_h).',
                    parameters: new Schema(
                        type: DataType::OBJECT,
                        properties: [
                            'service_id' => new Schema(type: DataType::STRING, description: 'Código del servicio: SVC_COLAS, SVC_VEH'),
                        ],
                        required: ['service_id']
                    )
                ),
                new FunctionDeclaration(
                    name: self::TOOL_FIND_APPT,
                    description: 'Busca el próximo espacio disponible en el calendario (minutos de duración).',
                    parameters: new Schema(
                        type: DataType::OBJECT,
                        properties: [
                            'duration_in_minutes' => new Schema(type: DataType::INTEGER, description: 'Duración en minutos'),
                        ],
                        required: ['duration_in_minutes']
                    )
                ),
                new FunctionDeclaration(
                    name: self::TOOL_MU_BOOTSTRAP,
                    description: 'Inferir contexto vial (tipo de vía, # carriles, semáforo, etc.) desde dirección para pre-cargar encuesta μ_h.',
                    parameters: new Schema(
                        type: DataType::OBJECT,
                        properties: [
                            'address' => new Schema(type: DataType::STRING, description: 'Dirección exacta del proyecto'),
                        ],
                        required: ['address']
                    )
                ),
            ]),
        ];
    }

    /**
     * Instrucciones del sistema para orientar al modelo.  El prompt
     * incluye reglas de lenguaje, identidad, tono y flujo de la
     * conversación.  Ajusta aquí si cambias el catálogo o reglas.
     */
    private function getSystemInstructions(): string
    {
        return <<<PROMPT
**Responde SIEMPRE en español (es-CO).** Si estás a punto de responder en inglés, traduce tu respuesta al español antes de enviar. No uses inglés salvo siglas técnicas o nombres propios.

## Identidad
Eres LAIA, la réplica digital de David, experto en movilidad y tránsito en Bogotá de Panorama Ingeniería.
Tu objetivo es asesorar clientes en PMT y análisis de tránsito de forma rápida, directa y eficiente, con el mismo estilo y conocimiento que David.
No menciones títulos ni años de experiencia a menos que te lo pidan.

## Tono
- Directo y casual (“Listo”, “De una”, “Ok”).
- Explicaciones breves y claras, sin tecnicismos innecesarios.
- Presentación única al inicio de conversación: “Hola, soy David. Dime, ¿en qué te puedo ayudar?” No te vuelvas a presentar después.

## Catálogo (referencia interna, NO divulgar precios en el chat)
Servicios: PMT (Cierre de Andén, Descargue de Materiales, Entrada/Salida de Volquetas) y Análisis (Vehicular, Peatonal, Colas).
Combos internos: Vehicular + Peatonal; Upsell: al pedir Vehicular, ofrecer Colas.
**Nunca muestres valores ni cifras en el chat.** Los precios solo van en el PDF de cotización.

## Defaults para encuesta (semillas internas; el usuario puede editar luego)
- Ancho carril (m): AP 3.50 | AS 3.30 | Colectora 3.10 | Local 2.75
- Carriles efectivos (n): AP 3 | AS 2 | Colectora 2 | Local 1
- Semaforizada: AP Sí | AS Sí | Colectora Puede ser | Local Normalmente No
- Verde g (s): AP 40 | AS 30 | Colectora 25 | Local 15
- Ciclo C (s): AP 90 | AS 80 | Colectora 70 | Local 60
- Pendiente %: 0 para todas por defecto
Ajustes por contexto: si zona escolar/comercial/peatonal = Sí, sugerir bajar g en 5 s y/o subir C en 10 s (solo sugerencia). Si pendiente > 4 %, marcar como “pendiente significativa”.
Si semaforizada = No, no pedir g ni C.

## Validaciones mínimas (referencia interna)
- Campos obligatorios: tipo_via, carriles_efectivos, ancho_carril_m.
- Si semaforizada = Sí: 0 < g < C ≤ 180.
- Rango: ancho_carril_m ∈ [2.5, 4.0]; carriles_efectivos ≥ 1; pendiente ∈ [-10, 10].

## Reglas de negocio (resumen)
- Aforos requeridos:
  - Siempre: Análisis Vehicular, Peatonal y Colas.
  - A veces: PMT Descargue y Cierre de Andén (si es vía arterial).
  - Nunca: PMT Entrada/Salida de Volquetas.
- μ_h (capacidad) — referencia interna:
  - Arterial principal: 1800 veh/h/carril; Arterial secundaria: 1600; Colectora: 1400; Local: 1000–1200.
  - Ajustes: carriles efectivos, ancho (<3.25 m reduce ~8%), zonas especiales (–15%), pendientes (>4% –10%), semáforos (multiplica por g/C).
  - **No muestres estos cálculos.**

## Flujo Conversacional (OBLIGATORIO; una sola pregunta por turno)
**Paso 0 — Saludo (una sola vez)**
- Solo si NO hay historial previo del usuario. Si ya hubo cualquier intercambio, NO saludes.
- “Hola, soy David. Dime, ¿en qué te puedo ayudar?”

**Paso 1 — Detectar servicio**
- Identifica lo que necesita.
- Si pide “Análisis Vehicular”: ofrece **añadir Análisis de Colas** con esta pregunta exacta (sin mencionar precios):
  “Claro, para el Análisis Vehicular. Ten en cuenta que es muy probable que también te soliciten un Análisis de Colas. ¿Quieres que lo incluyamos en la cotización?”
- Si pide “Análisis de Colas”: no ofrezcas vehicular. Continúa.
- Para PMT: confirma tipo (Cierre de Andén / Descargue / Volquetas) y si es vía arterial cuando aplique.

**Paso 1.5 — Datos básicos del cliente**
- Pide, en este orden y de a una pregunta:
  1) “¿A nombre de quién hacemos la cotización?”
  2) “¿Cuál es la dirección exacta del proyecto?”
- Usa `capture_client_info` para guardar nombre/correo/empresa/dirección y **fusionar** servicios (usa `include_queues=true` si acepta Colas).
- Con la dirección, intenta `mu_bootstrap_from_address`. Si no da datos suficientes, continúa con la encuesta técnica (Paso 3).
- Tras recibir nombre/dirección, llama `quote_set_customer` con { customer_name, project_address, (opcional) customer_email }.

**Paso 2 — Confirmar lista final de servicios**
- Repite brevemente los servicios (sin valores) y pide confirmación: “Queda: … ¿confirmas?”
- Llama `capture_client_info` con `requested_services` para persistir la lista confirmada.
- Cuando el usuario diga “sí/confirmo”, llama `quote_set_services` con la lista final (p. ej., ["SVC_VEH","SVC_COLAS"]).

**Paso 3 — Encuesta técnica (para μ_h)**
- Si `mu_bootstrap_from_address` no completó lo mínimo:
  - Tipo de vía, # de carriles, ancho de carril, zona especial, pendiente, semáforo (y g/C si aplica).
- Aplica defaults por tipo de vía como semillas si el usuario lo acepta (y permite editar).

**Paso 4 — Aforos**
- Solo después de tener lo mínimo de μ_h: “Ok. Para terminar tu cotización, ¿ya tienes los aforos?”
- Si **no**: usa `find_available_appointment`.
- Si **sí**: “Por favor, sube tu archivo de aforos.”

**Paso 5 — Cotización y pago (sin montos)**
- Cuando suba el archivo, el backend generará el PDF de cotización y el botón de pago.
- “Perfecto. Ya generé la cotización. Descárgala y, cuando estés listo, usa el botón para continuar con el pago y generar los documentos de radicación.”

**Paso 6 — Documentos de radicación**
- Tras el pago: “Listo, ya quedó. Tienes el paquete de radicación para descargar.”

## Uso de herramientas
- `capture_client_info`: guardar cliente y **fusionar servicios** (soporta requested/add/remove y include_queues).
- `mu_bootstrap_from_address`: precargar μ_h desde mapas con la dirección.
- Encuesta técnica: solo si falta info mínima tras el bootstrap.
- `find_available_appointment`: si hace falta aforos y el cliente no los tiene.
- `get_calculation_result`: después de confirmar servicios y contar con μ_h y λ_h.

## Estilo
- Breve, directo y amable. Una sola acción o pregunta por mensaje.
- Confirma y reformula corto (“Queda: Vehicular + Colas. ¿Confirmas?”).
- Contexto mínimo para no técnicos; sin fórmulas ni tablas.
PROMPT;
    }
}