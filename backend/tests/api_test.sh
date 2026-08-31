#!/bin/bash
BASE="http://127.0.0.1:8080/api/v1"
pass=0; fail=0
RAIZ="$(cd "$(dirname "$0")/.." && pwd)"

# Los limites de intentos se guardan en la base. Sin esto la segunda corrida
# del guion recibiria 429 en el registro y fallaria todo lo que viene despues.
limpiar_limites() {
  local env="$RAIZ/.env"
  [ -f "$env" ] || return 0
  local host port base usuario clave
  host=$(grep -m1 '^DB_HOST=' "$env" | cut -d= -f2-)
  port=$(grep -m1 '^DB_PORT=' "$env" | cut -d= -f2-)
  base=$(grep -m1 '^DB_DATABASE=' "$env" | cut -d= -f2-)
  usuario=$(grep -m1 '^DB_USERNAME=' "$env" | cut -d= -f2-)
  clave=$(grep -m1 '^DB_PASSWORD=' "$env" | cut -d= -f2-)
  command -v mysql >/dev/null 2>&1 || return 0
  MYSQL_PWD="$clave" mysql -h"${host:-127.0.0.1}" -P"${port:-3306}" -u"$usuario" "$base" \
    -e "DELETE FROM rate_limits;" >/dev/null 2>&1
}
limpiar_limites

check() {
  local desc="$1" expected="$2" actual="$3"
  if [ "$actual" = "$expected" ]; then printf '  OK  %-52s %s\n' "$desc" "$actual"; pass=$((pass+1));
  else printf '  ERR %-52s esperado=%s recibido=%s\n' "$desc" "$expected" "$actual"; fail=$((fail+1)); fi
}

code() { curl -s -o /dev/null -w '%{http_code}' "$@"; }

echo "== Endpoints publicos =="
check "GET /config" 200 "$(code $BASE/config)"
check "GET /categorias" 200 "$(code $BASE/categorias)"
check "GET /servicios" 200 "$(code $BASE/servicios)"
check "GET /profesionales" 200 "$(code $BASE/profesionales)"
check "GET /disponibilidad" 200 "$(code "$BASE/disponibilidad?service_ids[]=1")"
check "GET /preguntas" 200 "$(code $BASE/preguntas)"
check "GET /publicidad" 200 "$(code $BASE/publicidad)"

echo
echo "== Proteccion de endpoints privados =="
check "GET /perfil sin token" 401 "$(code $BASE/perfil)"
check "GET /citas sin token" 401 "$(code $BASE/citas)"
check "GET /pagos/cuentas sin token" 401 "$(code $BASE/pagos/cuentas)"
check "GET /perfil con token falso" 401 "$(code -H 'Authorization: Bearer falso.falso.falso' $BASE/perfil)"

echo
echo "== Registro e inicio de sesion =="
EMAIL="cliente$(date +%s)@ejemplo.test"
REG=$(curl -s -X POST $BASE/auth/registro -H 'Content-Type: application/json' -H 'X-App-Version: 1.0.0' \
  -d "{\"first_name\":\"Ana\",\"last_name\":\"Prueba\",\"email\":\"$EMAIL\",\"phone\":\"0999111222\",\"password\":\"MiClaveSegura#77\",\"accepts_terms\":true,\"accepts_marketing\":true}")
TOKEN=$(echo "$REG" | python3 -c "import sys,json; print(json.load(sys.stdin)['data']['access_token'])" 2>/dev/null)
REFRESH=$(echo "$REG" | python3 -c "import sys,json; print(json.load(sys.stdin)['data']['refresh_token'])" 2>/dev/null)
[ -n "$TOKEN" ] && { echo "  OK  registro devolvio token"; pass=$((pass+1)); } || { echo "  ERR registro: $REG"; fail=$((fail+1)); }

check "GET /perfil con token" 200 "$(code -H "Authorization: Bearer $TOKEN" $BASE/perfil)"
check "GET /pagos/cuentas con token" 200 "$(code -H "Authorization: Bearer $TOKEN" $BASE/pagos/cuentas)"
check "GET /fidelidad" 200 "$(code -H "Authorization: Bearer $TOKEN" $BASE/fidelidad)"
check "correo duplicado -> 409" 409 "$(code -X POST $BASE/auth/registro -H 'Content-Type: application/json' \
  -d "{\"first_name\":\"Ana\",\"email\":\"$EMAIL\",\"phone\":\"0999111222\",\"password\":\"MiClaveSegura#77\",\"accepts_terms\":true}")"
check "clave debil -> 422" 422 "$(code -X POST $BASE/auth/registro -H 'Content-Type: application/json' \
  -d '{"first_name":"Bob","email":"bob@x.test","phone":"0999000111","password":"123456","accepts_terms":true}')"
check "credenciales malas -> 401" 401 "$(code -X POST $BASE/auth/login -H 'Content-Type: application/json' \
  -d "{\"email\":\"$EMAIL\",\"password\":\"claveIncorrecta1!\"}")"

echo
echo "== Rotacion del token de refresco =="
R1=$(curl -s -X POST $BASE/auth/refrescar -H 'Content-Type: application/json' -d "{\"refresh_token\":\"$REFRESH\"}")
NEW=$(echo "$R1" | python3 -c "import sys,json; print(json.load(sys.stdin)['data']['refresh_token'])" 2>/dev/null)
[ -n "$NEW" ] && [ "$NEW" != "$REFRESH" ] && { echo "  OK  el refresco emite un token nuevo"; pass=$((pass+1)); } \
  || { echo "  ERR rotacion: $R1"; fail=$((fail+1)); }
check "reutilizar el refresco viejo -> 401" 401 "$(code -X POST $BASE/auth/refrescar -H 'Content-Type: application/json' \
  -d "{\"refresh_token\":\"$REFRESH\"}")"

echo
echo "== Agendamiento desde la app =="
SID=$(curl -s "$BASE/servicios" | python3 -c "import sys,json; print(json.load(sys.stdin)['data'][0]['id'])")
BID=$(curl -s "$BASE/config" | python3 -c "import sys,json; print(json.load(sys.stdin)['data']['branches'][0]['id'])")
DAY=$(curl -s "$BASE/disponibilidad?service_ids[]=$SID" | python3 -c "import sys,json; d=json.load(sys.stdin)['data']['days']; print(d[0]['date'] if d else '')")
SLOT=$(curl -s "$BASE/disponibilidad?service_ids[]=$SID&date=$DAY" | python3 -c "import sys,json; s=json.load(sys.stdin)['data']['slots']; print(s[0]['time'] if s else '')")
echo "  servicio=$SID sucursal=$BID dia=$DAY hora=$SLOT"

NEWTOKEN=$(echo "$R1" | python3 -c "import sys,json; print(json.load(sys.stdin)['data']['access_token'])" 2>/dev/null)
APPT=$(curl -s -X POST $BASE/citas -H 'Content-Type: application/json' -H "Authorization: Bearer $NEWTOKEN" \
  -d "{\"branch_id\":$BID,\"service_ids\":[$SID],\"date\":\"$DAY\",\"time\":\"$SLOT\",\"notes\":\"Prueba API\"}")
CODE=$(echo "$APPT" | python3 -c "import sys,json; print(json.load(sys.stdin)['data']['code'])" 2>/dev/null)
[ -n "$CODE" ] && { echo "  OK  cita creada: $CODE"; pass=$((pass+1)); } || { echo "  ERR cita: $APPT"; fail=$((fail+1)); }

APPT_ID=$(echo "$APPT" | python3 -c "import sys,json; print(json.load(sys.stdin)['data']['id'])" 2>/dev/null)
check "GET /citas" 200 "$(code -H "Authorization: Bearer $NEWTOKEN" $BASE/citas)"
check "GET /citas/{id}" 200 "$(code -H "Authorization: Bearer $NEWTOKEN" $BASE/citas/$APPT_ID)"
check "cita ajena -> 404" 404 "$(code -H "Authorization: Bearer $NEWTOKEN" $BASE/citas/999999)"

echo
echo "== Pago por transferencia =="
MID=$(curl -s -H "Authorization: Bearer $NEWTOKEN" $BASE/pagos/metodos | python3 -c "import sys,json; print([m['id'] for m in json.load(sys.stdin)['data'] if m['code']=='transferencia'][0])")
PAY=$(curl -s -X POST $BASE/pagos -H 'Content-Type: application/json' -H "Authorization: Bearer $NEWTOKEN" \
  -d "{\"appointment_id\":$APPT_ID,\"payment_method_id\":$MID,\"reference\":\"REF-APP-001\"}")
PID=$(echo "$PAY" | python3 -c "import sys,json; print(json.load(sys.stdin)['data']['id'])" 2>/dev/null)
[ -n "$PID" ] && { echo "  OK  pago registrado #$PID"; pass=$((pass+1)); } || { echo "  ERR pago: $PAY"; fail=$((fail+1)); }

# Comprobante enviado como imagen en base64 (lo que hace la camara del telefono)
python3 -c "
import base64, zlib, struct
def chunk(t,d):
    c=t+d
    return struct.pack('>I',len(d))+c+struct.pack('>I',zlib.crc32(c)&0xffffffff)
raw=b''.join(b'\x00'+bytes([200,160,40])*40 for _ in range(40))
png=b'\x89PNG\r\n\x1a\n'+chunk(b'IHDR',struct.pack('>IIBBBBB',40,40,8,2,0,0,0))+chunk(b'IDAT',zlib.compress(raw))+chunk(b'IEND',b'')
open('/tmp/comprobante.png','wb').write(png)
print(base64.b64encode(png).decode())
" > /tmp/proof_b64.txt
PROOF=$(curl -s -X POST $BASE/pagos/$PID/comprobante -H 'Content-Type: application/json' -H "Authorization: Bearer $NEWTOKEN" \
  -d "{\"proof_base64\":\"$(cat /tmp/proof_b64.txt)\",\"proof_name\":\"comprobante.png\"}")
PURL=$(echo "$PROOF" | python3 -c "import sys,json; print(json.load(sys.stdin)['data']['proof']['url'])" 2>/dev/null)
[ -n "$PURL" ] && { echo "  OK  comprobante subido: $PURL"; pass=$((pass+1)); } || { echo "  ERR comprobante: $PROOF"; fail=$((fail+1)); }

echo
echo "== Politica de cancelacion =="
# La primera cita se agendo en el hueco mas cercano, que puede estar dentro de
# las horas de antelacion exigidas: el sistema debe rechazar cancelarla.
CANCEL_NEAR=$(code -X POST $BASE/citas/$APPT_ID/cancelar -H 'Content-Type: application/json' \
  -H "Authorization: Bearer $NEWTOKEN" -d '{"reason":"prueba"}')
if [ "$CANCEL_NEAR" = "422" ]; then
  echo "  OK  cita demasiado proxima: cancelacion rechazada (422)"; pass=$((pass+1))
elif [ "$CANCEL_NEAR" = "200" ]; then
  echo "  OK  cita con antelacion suficiente: cancelada (200)"; pass=$((pass+1))
else
  echo "  ERR cancelacion devolvio $CANCEL_NEAR"; fail=$((fail+1))
fi

# Una cita con varios dias por delante siempre debe poder cancelarse.
DAY_FAR=$(curl -s "$BASE/disponibilidad?service_ids[]=$SID" | python3 -c "import sys,json; d=json.load(sys.stdin)['data']['days']; print(d[3]['date'] if len(d)>3 else (d[-1]['date'] if d else ''))")
SLOT_FAR=$(curl -s "$BASE/disponibilidad?service_ids[]=$SID&date=$DAY_FAR" | python3 -c "import sys,json; s=json.load(sys.stdin)['data']['slots']; print(s[0]['time'] if s else '')")
FAR=$(curl -s -X POST $BASE/citas -H 'Content-Type: application/json' -H "Authorization: Bearer $NEWTOKEN" \
  -d "{\"branch_id\":$BID,\"service_ids\":[$SID],\"date\":\"$DAY_FAR\",\"time\":\"$SLOT_FAR\"}")
FAR_ID=$(echo "$FAR" | python3 -c "import sys,json; print(json.load(sys.stdin)['data']['id'])" 2>/dev/null)
check "cancelar una cita con dias de antelacion" 200 "$(code -X POST $BASE/citas/$FAR_ID/cancelar \
  -H 'Content-Type: application/json' -H "Authorization: Bearer $NEWTOKEN" -d '{"reason":"prueba"}')"

echo
echo "== Entrega de archivos subidos =="
# La ruta de medios debe aceptar rutas de varios segmentos, exigir permiso en
# los comprobantes y bloquear el salto de directorio.
check "comprobante sin sesion -> 403" 403 "$(code "$PURL")"
check "comprobante con sesion del duenio -> 200" 200 "$(code -H "Authorization: Bearer $NEWTOKEN" "$PURL")"
check "salto de directorio -> 404" 404 "$(code "http://127.0.0.1:8080/media/../../.env")"
check "archivo inexistente -> 404" 404 "$(code "http://127.0.0.1:8080/media/servicios/2026/01/noexiste.jpg")"

echo
echo "RESULTADO: $pass correctas, $fail fallidas"
[ "$fail" -eq 0 ] || exit 1
