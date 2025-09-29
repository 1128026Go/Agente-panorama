Proyecto Chat IA Laia

Este repositorio contiene una aplicación Laravel que expone un chatbot con lógica de negocios para el cálculo de aforos, generación de cotizaciones en PDF y creación de enlaces de pago, además de la integración con calendarios de Outlook y la orquestación mediante n8n. El proyecto está pensado para funcionar como backend de un front ligero en Blade o cualquier cliente JavaScript que consuma la API REST.

Funcionalidades principales

Chat en lenguaje natural: un controlador ChatController procesa cada pregunta del usuario, valida la entrada y delega la interpretación a un AgentService que utiliza Gemini (o el modelo elegido) para detectar la intención y decidir si invocar herramientas (cálculos, cotizaciones, agenda, pagos) o responder directamente. El historial de diálogo se persiste en la tabla dialogs para mantener el contexto.

Cálculos de aforo: mediante CalculationApiClient la aplicación realiza peticiones POST a una API de cálculo (por ejemplo, para capacidad de recintos) y devuelve los resultados al usuario.

Generación de cotizaciones: QuoteService construye cotizaciones, aplica precios y combos definidos en config/laia.php y crea registros en las tablas quotes y quote_items. Se genera un PDF en formato A4 y se entrega un enlace al usuario.

Pagos: PaymentService crea registros de pago y, si se configura la pasarela ePayco, genera enlaces de pago reales. Incluye controladores para simular un flujo de pago y confirmar transacciones.

Agenda y calendario: GraphCalendarService (junto con un futuro GraphTokenService) permite consultar disponibilidad en Outlook y crear eventos según la intención del usuario.

Integración con n8n: se provee un cliente N8nClient para enviar eventos a flujos de n8n definidos por el desarrollador. Esto permite orquestar procesos más complejos sin acoplarlos al código PHP.

Estructura del proyecto
AgentePanorama/
├── app/
│   ├── Http/
│   │   ├── Controllers/   # Controladores HTTP y API
│   │   └── Middleware/    # Middlewares personalizados
│   ├── Models/            # Modelos Eloquent (Dialog, Quote, etc.)
│   └── Services/          # Lógica de negocio y clientes externos
├── config/
│   ├── app.php            # Configuración de Laravel
│   ├── laia.php           # Precios, combos y moneda
│   └── services.php       # URLs y tokens de APIs externas (n8n, Graph, ePayco, etc.)
├── database/
│   └── migrations/        # Migraciones de tablas dialogs, quotes, quote_items y payments
├── routes/
│   ├── api.php            # Rutas API (/api/laia/*)
│   └── web.php            # Rutas web (/chat, /pagos, etc.)
├── n8n/
│   ├── docker-compose.yml # Define servicio n8n para orquestación (usar docker compose)
│   ├── .env.example       # Variables de entorno de n8n (copiar a .env)
│   └── workflows/         # (Opcional) flujos exportados de n8n
├── composer.json          # Dependencias PHP
├── package.json           # Dependencias Node (si usa front-end)
└── README.md              # Este archivo

Carpeta n8n/

Para la orquestación se recomienda incluir un directorio n8n/ con al menos dos archivos:

docker-compose.yml: define un servicio n8n basado en la imagen oficial, expone el puerto 5678 y monta un volumen para persistir flujos.

.env.example: sirve de plantilla; incluye variables como:

N8N_BASIC_AUTH_USER y N8N_BASIC_AUTH_PASSWORD para proteger la instancia.

N8N_ENCRYPTION_KEY para cifrar credenciales almacenadas en n8n.

AGENTE_API_BASE con la URL base de tu aplicación Laravel (por ejemplo, http://host.docker.internal:8000).

No subas el archivo real .env de n8n al repositorio, ya que contiene credenciales. Asegúrate de añadir una entrada n8n/.env en .gitignore.

Instalación y puesta en marcha

A continuación se describe el flujo para levantar el proyecto en un entorno de desarrollo local:

Clonar el repositorio y abrirlo en tu IDE

git clone https://github.com/1128026Go/Agente.git
cd Agente


Copiar variables de entorno

Duplica .env.example a .env en la raíz y completa las variables necesarias:

APP_NAME, APP_URL y APP_KEY (puedes generar una nueva clave con php artisan key:generate).

CALCULATION_API_URL y CALCULATION_API_TOKEN para tu API de cálculos.

N8N_BASE_URL, N8N_BASIC_AUTH_USER, N8N_BASIC_AUTH_PASSWORD y LAIA_N8N_TOKEN para comunicarse con n8n.

Credenciales de Outlook (MSFT_GRAPH_TOKEN, MSFT_CALENDAR_EMAIL) si usas el calendario.

Claves de ePayco (EPAYCO_PUBLIC_KEY, EPAYCO_PRIVATE_KEY) en caso de usar pasarela de pagos.

En la carpeta n8n/, duplica .env.example a .env y asigna los mismos valores de usuario/contraseña y token. Nunca subas estos archivos .env a GitHub.

Instalar dependencias

composer install      # instala paquetes PHP
npm install           # instala dependencias JavaScript (si aplica)


Ejecutar migraciones

php artisan migrate   # crea las tablas dialogs, quotes, quote_items y payments


Levantar la instancia de n8n (si usas orquestación)

Entra a la carpeta n8n/ y ejecuta:

cd n8n
docker compose up -d


Accede a http://localhost:5678 en tu navegador y autentícate con el usuario y contraseña definidos en .env. Configura tus flujos (webhooks, llamadas a APIs internas, etc.) y toma nota de sus endpoints para usarlos en AgentService.

Iniciar el servidor Laravel

php artisan serve --host=127.0.0.1 --port=8000


La aplicación quedará disponible en http://127.0.0.1:8000. Puedes probar los endpoints con curl o un cliente Postman:

GET /api/healthz: comprueba la salud del servicio.

POST /api/chat/send: envía un JSON { "query": "Hola" } y recibe la respuesta del chatbot.

GET /api/version: devuelve la versión de la aplicación.

Pruebas y desarrollo

Usa php artisan route:list para ver las rutas disponibles.

Ejecuta pruebas de PHPUnit con php artisan test.

Para depurar, consulta los logs en storage/logs/laravel.log.

Buenas prácticas de repositorio

Añade las siguientes entradas a tu .gitignore si aún no existen:

# Archivos de entorno
.env
.env.*
n8n/.env
n8n/.env.*

# Artifacts y dependencias
/vendor/
/node_modules/
n8n/node_modules/
n8n/.n8n


No subas credenciales ni tokens a GitHub. Mantén los archivos .env fuera del control de versiones.

Versiona el contenido de n8n/docker-compose.yml y n8n/.env.example, así como los flujos exportados (n8n/workflows/*.json) si deseas compartirlos con tu equipo.

Actualizar tu proyecto con cambios del repositorio

Cuando edites el código localmente y hagas commit y push a GitHub, los cambios se reflejarán en el repositorio remoto. Para verlos en tu entorno de desarrollo:

Subir cambios: tras modificar archivos, ejecuta:

git add .
git commit -m "Describe brevemente los cambios realizados"
git push origin main


Actualizar tu copia: en otra máquina o repositorio clonado, basta con ejecutar git pull para traer los últimos cambios. Si tienes el servidor en ejecución, reinícialo para cargar la nueva versión.

Siguiendo estas instrucciones tendrás un proyecto Laravel estructurado, con soporte para chat inteligente, cotizaciones, pagos, n8n y calendario. Ajusta los valores de configuración según tu entorno y evita exponer datos sensibles al subirlos a GitHub.