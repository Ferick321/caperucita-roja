<?php

declare(strict_types=1);

/**
 * Pruebas del nucleo y de la seguridad.
 *
 *   php tests/run.php
 *
 * No necesitan base de datos: comprueban la logica pura (validacion,
 * cifrado, tokens, escape, constructor de consultas y reglas de horarios),
 * que es donde un fallo silencioso hace mas dano.
 */

require dirname(__DIR__) . '/bootstrap.php';

use App\Core\Clock;
use App\Core\Env;
use App\Core\Migrator;
use App\Core\QueryBuilder;
use App\Core\Url;
use App\Core\Validator;
use App\Security\Crypto;
use App\Security\Hash;
use App\Security\Jwt;
use App\Security\TwoFactor;
use App\Services\AvailabilityService;

// Claves de prueba: nunca se usan en produccion.
Env::setForTesting('APP_KEY', Crypto::generateKey());
App\Core\Config::set('app.key', Env::get('APP_KEY'));
App\Core\Config::set('security.jwt.secret', bin2hex(random_bytes(32)));
App\Core\Config::set('security.password.pepper', 'pepper-de-prueba');
App\Core\Config::set('security.password.min_length', 10);
App\Core\Config::set('app.url', 'https://ejemplo.test');

$passed = 0;
$failed = 0;
$currentGroup = '';

function group(string $name): void
{
    global $currentGroup;
    $currentGroup = $name;
    echo PHP_EOL . '== ' . $name . ' ' . str_repeat('=', max(0, 56 - mb_strlen($name))) . PHP_EOL;
}

function check(string $description, bool $condition, string $extra = ''): void
{
    global $passed, $failed;

    if ($condition) {
        $passed++;
        printf("  OK  %s\n", $description);
    } else {
        $failed++;
        printf("  ERR %s%s\n", $description, $extra === '' ? '' : ' -> ' . $extra);
    }
}

function equals(string $description, mixed $expected, mixed $actual): void
{
    check(
        $description,
        $expected === $actual,
        sprintf('esperado=%s recibido=%s', var_export($expected, true), var_export($actual, true))
    );
}

// ---------------------------------------------------------------------------
group('Contrasenas');

$hash = Hash::make('MiClaveSegura#2026');
check('el hash no contiene la contrasena', !str_contains($hash, 'MiClaveSegura'));
check('verifica la contrasena correcta', Hash::verify('MiClaveSegura#2026', $hash));
check('rechaza la contrasena incorrecta', !Hash::verify('otraClave#2026', $hash));
check('dos hashes de la misma clave son distintos', Hash::make('abc') !== Hash::make('abc'));
check('un hash vacio no valida nada', !Hash::verify('cualquiera', ''));

$token = Hash::randomToken(32);
equals('el token aleatorio mide 64 caracteres', 64, strlen($token));
check('el hash del token es estable', Hash::hashToken($token) === Hash::hashToken($token));
check('tokens distintos dan hashes distintos', Hash::hashToken($token) !== Hash::hashToken(Hash::randomToken()));

// ---------------------------------------------------------------------------
group('Cifrado de datos bancarios');

$accountNumber = '2200123456789';
$encrypted = Crypto::encrypt($accountNumber);

check('el cifrado no deja el numero visible', !str_contains($encrypted, $accountNumber));
equals('descifra al valor original', $accountNumber, Crypto::decrypt($encrypted));
check('dos cifrados del mismo dato difieren', Crypto::encrypt($accountNumber) !== $encrypted);
check('reconoce un valor cifrado', Crypto::isEncrypted($encrypted));
check('un valor sin cifrar se devuelve tal cual', Crypto::decrypt('texto plano') === 'texto plano');
equals('enmascara dejando los ultimos digitos', '*********6789', Crypto::mask($accountNumber));

// Un dato manipulado no debe descifrarse.
$tampered = substr($encrypted, 0, -8) . 'AAAAAAAA';
equals('un dato manipulado no se descifra', '', Crypto::decrypt($tampered));

// ---------------------------------------------------------------------------
group('Tokens de la app movil (JWT)');

$jwt = Jwt::issue(['sub' => 42, 'type' => 'access'], 900);
$claims = Jwt::verify($jwt);

check('el token valido se verifica', $claims !== null);
equals('conserva el identificador del usuario', 42, $claims['sub'] ?? null);
check('incluye un identificador unico (jti)', isset($claims['jti']));

check('rechaza una firma manipulada', Jwt::verify(substr($jwt, 0, -4) . 'AAAA') === null);
check('rechaza un token con formato invalido', Jwt::verify('no.es.un.token') === null);
check('rechaza un token vacio', Jwt::verify('') === null);

// Ataque "alg: none": el atacante quita la firma y cambia la cabecera.
$parts = explode('.', $jwt);
$noneHeader = rtrim(strtr(base64_encode('{"alg":"none","typ":"JWT"}'), '+/', '-_'), '=');
check('bloquea el ataque alg:none', Jwt::verify($noneHeader . '.' . $parts[1] . '.') === null);

// Token ya caducado.
$expired = Jwt::issue(['sub' => 1], -3600);
check('rechaza un token caducado', Jwt::verify($expired) === null);

// ---------------------------------------------------------------------------
group('Verificacion en dos pasos');

$secret = TwoFactor::generateSecret();
equals('el secreto mide 32 caracteres', 32, strlen($secret));

$counter = intdiv(time(), 30);
$code = TwoFactor::code($secret, $counter);

equals('el codigo tiene 6 digitos', 6, strlen($code));
check('acepta el codigo actual', TwoFactor::verify($secret, $code));
check('rechaza un codigo inventado', !TwoFactor::verify($secret, '000000'));
check('rechaza un codigo con longitud incorrecta', !TwoFactor::verify($secret, '123'));
check(
    'el enlace del QR tiene el formato correcto',
    str_starts_with(TwoFactor::provisioningUri($secret, 'ana@test.com', 'Barberia'), 'otpauth://totp/'),
);
// Codigo de una ventana lejana: fuera de tolerancia.
check('rechaza un codigo de hace 5 minutos', !TwoFactor::verify($secret, TwoFactor::code($secret, $counter - 10)));

// ---------------------------------------------------------------------------
group('Validacion de entrada');

$validator = Validator::make(
    ['email' => 'Ana@Ejemplo.COM', 'phone' => '(099) 912-3456', 'age' => '30'],
    ['email' => 'required|email', 'phone' => 'required|phone', 'age' => 'required|int|between:18,99'],
);

check('acepta datos correctos', $validator->passes());
equals('normaliza el correo a minusculas', 'ana@ejemplo.com', $validator->validated()['email']);
equals('limpia el telefono', '0999123456', $validator->validated()['phone']);
equals('convierte el numero a entero', 30, $validator->validated()['age']);

$bad = Validator::make(
    ['email' => 'no-es-correo', 'phone' => '123'],
    ['email' => 'required|email', 'phone' => 'required|phone'],
);
check('rechaza un correo invalido', $bad->fails());
equals('senala los dos campos con error', 2, count($bad->errors()));

$missing = Validator::make([], ['name' => 'required|string']);
check('exige los campos obligatorios', $missing->fails());

// Politica de contrasenas.
foreach ([
    'corta1!' => 'rechaza una contrasena corta',
    'solominusculas' => 'rechaza una contrasena sin variedad',
    'password12345' => 'rechaza una contrasena predecible',
] as $weak => $description) {
    check($description, Validator::make(['p' => $weak], ['p' => 'required|password'])->fails());
}
check(
    'acepta una contrasena robusta',
    Validator::make(['p' => 'Corte#Barba2026'], ['p' => 'required|password'])->passes(),
);

// Etiquetas HTML en campos de texto.
check(
    'rechaza HTML en un campo de texto',
    Validator::make(['n' => '<script>alert(1)</script>'], ['n' => 'required|no_html'])->fails(),
);

// Fechas y horas.
check('acepta una fecha valida', Validator::make(['d' => '2026-02-28'], ['d' => 'required|date'])->passes());
check('rechaza el 30 de febrero', Validator::make(['d' => '2026-02-30'], ['d' => 'required|date'])->fails());
check('acepta una hora valida', Validator::make(['t' => '14:30'], ['t' => 'required|time'])->passes());
check('rechaza una hora imposible', Validator::make(['t' => '25:00'], ['t' => 'required|time'])->fails());

// Confirmacion de contrasena.
check(
    'exige que la confirmacion coincida',
    Validator::make(
        ['p' => 'Corte#Barba2026', 'p_confirmation' => 'otra'],
        ['p' => 'required|password|confirmed'],
    )->fails(),
);

// ---------------------------------------------------------------------------
group('Constructor de consultas');

$query = QueryBuilder::table('users')->where('email', 'ana@test.com')->where('status', 'active');
check('genera una consulta con parametros', str_contains($query->toSql(), 'WHERE `email` = :p1'));
check('no interpola valores en el SQL', !str_contains($query->toSql(), 'ana@test.com'));
equals('liga los dos valores', 2, count($query->bindings()));

// Identificadores: lista blanca estricta.
$rejected = 0;
foreach (['users; DROP TABLE x', 'users`--', "users' OR '1'='1", 'users)(', '1=1'] as $malicious) {
    try {
        QueryBuilder::table($malicious)->toSql();
    } catch (InvalidArgumentException) {
        $rejected++;
    }
}
equals('rechaza los 5 nombres de tabla maliciosos', 5, $rejected);

// Operadores: solo los permitidos.
$operatorRejected = false;
try {
    QueryBuilder::table('users')->where('id', 'UNION SELECT', 1)->toSql();
} catch (InvalidArgumentException) {
    $operatorRejected = true;
}
check('rechaza un operador no permitido', $operatorRejected);

// Un DELETE sin condiciones seria catastrofico.
$deleteBlocked = false;
try {
    QueryBuilder::table('users')->delete();
} catch (RuntimeException) {
    $deleteBlocked = true;
}
check('bloquea DELETE sin condiciones', $deleteBlocked);

// Busqueda: los comodines del usuario se escapan.
$search = QueryBuilder::table('users')->search('100%_admin', ['name']);
$bindings = array_values($search->bindings());
check('escapa los comodines de la busqueda', str_contains((string) $bindings[0], '\\%'));

// IN vacio no debe devolver toda la tabla.
check(
    'un IN vacio no coincide con nada',
    str_contains(QueryBuilder::table('users')->whereIn('id', [])->toSql(), '1 = 0'),
);

// ---------------------------------------------------------------------------
group('Redirecciones y enlaces');

equals('acepta una ruta interna', '/panel/citas', Url::safeRedirect('/panel/citas'));
equals('bloquea un dominio externo', 'https://ejemplo.test/', Url::safeRedirect('https://malicioso.com/robar'));
equals('bloquea el truco de la doble barra', 'https://ejemplo.test/', Url::safeRedirect('//malicioso.com'));
equals('bloquea la barra invertida', 'https://ejemplo.test/', Url::safeRedirect('/\\malicioso.com'));
equals('acepta el propio dominio', 'https://ejemplo.test/panel', Url::safeRedirect('https://ejemplo.test/panel'));

equals('genera identificadores de URL limpios', 'corte-de-cabello', Url::slug('Corte de Cabello'));
equals('quita los acentos', 'peluqueria-nino', Url::slug('Peluquería Niño'));
equals('no deja un identificador vacio', 'item', Url::slug('!!!'));

// ---------------------------------------------------------------------------
group('Escape de salida');

equals('escapa las etiquetas HTML', '&lt;script&gt;', e('<script>'));
equals('escapa las comillas', '&quot;x&quot;', e('"x"'));
equals('escapa el ampersand', '&amp;', e('&'));
equals('bloquea el esquema javascript:', '#', e_url('javascript:alert(1)'));
equals('bloquea el esquema data:', '#', e_url('data:text/html,<script>'));
equals('acepta https', 'https://ejemplo.test', e_url('https://ejemplo.test'));
equals('acepta rutas internas', '/agendar', e_url('/agendar'));
check('serializa a JSON seguro para <script>', !str_contains(e_js('</script>'), '</script>'));

// ---------------------------------------------------------------------------
group('Reloj y zonas horarias');

Clock::setBusinessTimezone('America/Guayaquil');
Clock::freeze('2026-06-15 14:00:00');

equals('convierte hora local a UTC', '2026-06-15 19:00:00', Clock::localToUtc('2026-06-15 14:00:00'));
equals('convierte UTC a hora local', '2026-06-15 09:00:00', Clock::utcToLocal('2026-06-15 14:00:00'));
equals('la fecha local es correcta', '2026-06-15', Clock::today());

Clock::setBusinessTimezone('Europe/Madrid');
equals('cambia de zona horaria', '2026-06-15 10:00:00', Clock::localToUtc('2026-06-15 12:00:00'));

Clock::setBusinessTimezone('America/Guayaquil');
Clock::freeze(null);

// ---------------------------------------------------------------------------
group('Horarios de la agenda');

equals('convierte la hora a minutos', 540, AvailabilityService::toMinutes('09:00'));
equals('convierte con minutos', 570, AvailabilityService::toMinutes('09:30:00'));
equals('medianoche son 0 minutos', 0, AvailabilityService::toMinutes('00:00'));
equals('las 23:59 son 1439 minutos', 1439, AvailabilityService::toMinutes('23:59'));

// ---------------------------------------------------------------------------
group('Migraciones SQL');

$statements = Migrator::splitStatements(
    "CREATE TABLE a (id INT); -- comentario\nCREATE TABLE b (t VARCHAR(10) DEFAULT 'a;b');"
);
equals('separa dos sentencias', 2, count($statements));
check('no corta dentro de una cadena', str_contains($statements[1], "'a;b'"));

$withComment = Migrator::splitStatements("-- solo un comentario\nCREATE TABLE c (id INT);");
equals('ignora los comentarios sueltos', 1, count($withComment));

// Los archivos reales del proyecto deben separarse limpiamente.
$totalTables = 0;
foreach (glob(dirname(__DIR__) . '/database/migrations/*.sql') ?: [] as $file) {
    foreach (Migrator::splitStatements((string) file_get_contents($file)) as $statement) {
        if (stripos(ltrim($statement), 'CREATE TABLE') === 0) {
            $totalTables++;
        }
    }
}
equals('las migraciones definen 50 tablas', 50, $totalTables);

// ---------------------------------------------------------------------------
group('Formato para el usuario');

App\Core\Config::set('app.debug', false);

equals('duracion menor a una hora', '45 min', minutes_to_human(45));
equals('duracion de una hora exacta', '1 h', minutes_to_human(60));
equals('duracion con horas y minutos', '2 h 30 min', minutes_to_human(150));
equals('iniciales de dos palabras', 'AP', initials('Ana Perez'));
equals('iniciales de una palabra', 'L', initials('Luis'));
equals('recorta el texto largo', 'Hola...', str_limit('Hola mundo', 4));
equals('no recorta el texto corto', 'Hola', str_limit('Hola', 10));

// ---------------------------------------------------------------------------
echo PHP_EOL . str_repeat('-', 62) . PHP_EOL;
printf("RESULTADO: %d correctas, %d fallidas\n", $passed, $failed);

exit($failed === 0 ? 0 : 1);
