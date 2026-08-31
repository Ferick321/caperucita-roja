#!/bin/bash
#
# Recorre el panel de administracion con una sesion real y comprueba que
# cada pantalla responda y que cada operacion haga lo que dice.
#
#   bash tests/panel_test.sh
#
# Necesita el servidor levantado en 127.0.0.1:8080 y la base configurada
# en backend/.env.

BASE="http://127.0.0.1:8080"
RAIZ="$(cd "$(dirname "$0")/.." && pwd)"
TMP="$(mktemp -d)"
J="$TMP/cookies.txt"
pass=0; fail=0

trap 'rm -rf "$TMP"' EXIT

# ---- Acceso a la base -------------------------------------------------------
env_valor() { grep -m1 "^$1=" "$RAIZ/.env" | cut -d= -f2-; }
DB_HOST=$(env_valor DB_HOST); DB_PORT=$(env_valor DB_PORT)
DB_NAME=$(env_valor DB_DATABASE); DB_USER=$(env_valor DB_USERNAME)
DB_PASS=$(env_valor DB_PASSWORD)

sql() {
  MYSQL_PWD="$DB_PASS" mysql -h"${DB_HOST:-127.0.0.1}" -P"${DB_PORT:-3306}" \
    -u"$DB_USER" "$DB_NAME" -N -e "$1" 2>/dev/null
}

ADMIN_EMAIL="${PANEL_EMAIL:-admin@mibarberia.com}"
ADMIN_PASS="${PANEL_PASSWORD:-ClaveAdmin#2026}"

# ---- Utilidades -------------------------------------------------------------
check() {
  local desc="$1" esperado="$2" real="$3"
  if [ "$real" = "$esperado" ]; then
    printf '  OK  %-52s %s\n' "$desc" "$real"; pass=$((pass+1))
  else
    printf '  ERR %-52s esperado=%s recibido=%s\n' "$desc" "$esperado" "$real"; fail=$((fail+1))
  fi
}

# Comprueba un valor leido de la base.
check_db() {
  local desc="$1" esperado="$2" real="$3"
  if [ "$real" = "$esperado" ]; then
    printf '  OK  %-52s %s\n' "$desc" "$real"; pass=$((pass+1))
  else
    printf '  ERR %-52s esperado=%s recibido=%s\n' "$desc" "$esperado" "$real"; fail=$((fail+1))
  fi
}

get() { curl -s -b "$J" -o /dev/null -w '%{http_code}' "$BASE$1"; }

# post <pagina_del_formulario> <destino> [campo=valor ...]
post() {
  local form="$1" dest="$2"; shift 2
  local token
  token=$(curl -s -b "$J" -c "$J" "$BASE$form" \
    | grep -o 'name="csrf_token" value="[^"]*"' | head -1 | sed 's/.*value="//;s/"//')
  local args=(--data-urlencode "csrf_token=$token")
  local campo
  for campo in "$@"; do args+=(--data-urlencode "$campo"); done
  curl -s -b "$J" -c "$J" -o /dev/null -w '%{http_code}' -X POST "$BASE$dest" \
    -H "Origin: $BASE" "${args[@]}"
}

entrar() {
  rm -f "$J"
  # El limitador de intentos vive en la base: sin esto la segunda corrida
  # del guion recibiria 429 al entrar.
  sql "DELETE FROM rate_limits;" >/dev/null
  local pagina token rendered
  pagina=$(curl -s -c "$J" "$BASE/ingresar")
  token=$(echo "$pagina" | grep -o 'name="csrf_token" value="[^"]*"' | head -1 | sed 's/.*value="//;s/"//')
  rendered=$(echo "$pagina" | grep -o 'name="form_rendered_at" value="[^"]*"' | head -1 | sed 's/.*value="//;s/"//')
  curl -s -b "$J" -c "$J" -o /dev/null -X POST "$BASE/ingresar" -H "Origin: $BASE" \
    --data-urlencode "csrf_token=$token" --data-urlencode "form_rendered_at=$rendered" \
    --data-urlencode "email=$ADMIN_EMAIL" --data-urlencode "password=$ADMIN_PASS"
}

# ---- Sesion -----------------------------------------------------------------
echo "== Acceso al panel =="
entrar
check "el administrador entra" 200 "$(get /panel)"

if [ "$(get /panel)" != "200" ]; then
  echo
  echo "No se pudo entrar al panel. Revisa el correo y la clave"
  echo "(PANEL_EMAIL / PANEL_PASSWORD) o crea el usuario con:"
  echo "  php cli/console.php usuario:clave --email=$ADMIN_EMAIL --password=TuClave"
  exit 1
fi

# ---- Pantallas --------------------------------------------------------------
echo
echo "== Pantallas del panel =="
for ruta in \
  /panel /panel/citas /panel/citas/agenda /panel/citas/nueva \
  /panel/clientes /panel/servicios /panel/servicios/nuevo /panel/servicios/categorias \
  /panel/personal /panel/personal/nuevo \
  /panel/sucursales /panel/sucursales/nueva \
  /panel/pagos /panel/pagos/cuentas \
  /panel/publicidad /panel/publicidad/nuevo \
  /panel/campanas /panel/campanas/nueva \
  /panel/cupones /panel/cupones/nuevo \
  /panel/suscriptores /panel/espera \
  /panel/usuarios /panel/usuarios/nuevo \
  /panel/contenido /panel/contenido/galeria /panel/contenido/resenas \
  /panel/contenido/preguntas /panel/contenido/mensajes \
  /panel/reportes /panel/ajustes/plantillas \
  /panel/mantenimiento /panel/mantenimiento/tablas /panel/mantenimiento/archivos \
  /panel/mantenimiento/copias /panel/mantenimiento/retencion \
  /panel/mantenimiento/retencion/nueva /panel/mantenimiento/simular-limpieza \
  /panel/mantenimiento/auditoria /panel/mantenimiento/accesos
do
  check "GET $ruta" 200 "$(get "$ruta")"
done

echo
echo "== Pestanas de ajustes =="
for grupo in $(sql "SELECT DISTINCT group_name FROM settings ORDER BY group_name"); do
  check "GET /panel/ajustes/$grupo" 200 "$(get "/panel/ajustes/$grupo")"
done

# ---- Sucursales -------------------------------------------------------------
echo
echo "== Sucursales: crear, horario, feriados =="
post /panel/sucursales/nueva /panel/sucursales \
  "name=Sucursal De Prueba" "city=Quito" "phone=0999888777" \
  "timezone=America/Guayaquil" "is_active=1" >/dev/null
SUC=$(sql "SELECT id FROM branches WHERE name='Sucursal De Prueba' AND deleted_at IS NULL")
check_db "la sucursal se creo" "1" "$([ -n "$SUC" ] && echo 1 || echo 0)"
check_db "se le creo el horario semanal" "7" "$(sql "SELECT COUNT(*) FROM branch_hours WHERE branch_id=$SUC")"
check_db "el domingo queda cerrado" "1" "$(sql "SELECT is_closed FROM branch_hours WHERE branch_id=$SUC AND weekday=0")"

check "rechaza una zona horaria inventada" 422 \
  "$(post /panel/sucursales/nueva /panel/sucursales "name=Zona Mala" "timezone=Marte/Olimpo")"

post "/panel/sucursales/$SUC/editar" "/panel/sucursales/$SUC/horario" \
  "abre_1=10:00" "cierra_1=16:00" "descanso_ini_1=13:00" "descanso_fin_1=14:00" >/dev/null
check_db "guarda el horario del lunes" "10:00:00-16:00:00" \
  "$(sql "SELECT CONCAT(opens_at,'-',closes_at) FROM branch_hours WHERE branch_id=$SUC AND weekday=1")"

post "/panel/sucursales/$SUC/editar" "/panel/sucursales/$SUC/horario" \
  "abre_1=18:00" "cierra_1=09:00" >/dev/null
check_db "no acepta cerrar antes de abrir" "10:00:00-16:00:00" \
  "$(sql "SELECT CONCAT(opens_at,'-',closes_at) FROM branch_hours WHERE branch_id=$SUC AND weekday=1")"

# ---- Los feriados quitan turnos de verdad -----------------------------------
echo
echo "== Un feriado deja el dia sin turnos =="
SERVICIO=$(sql "SELECT id FROM services WHERE deleted_at IS NULL ORDER BY id LIMIT 1")
PRINCIPAL=$(sql "SELECT id FROM branches WHERE is_default=1 AND deleted_at IS NULL LIMIT 1")
# Un lunes futuro, para no chocar con el domingo cerrado.
DIA=$(date -u -d "next monday +14 days" +%Y-%m-%d 2>/dev/null || date -u -v+14d +%Y-%m-%d)

turnos() {
  curl -s "$BASE/api/v1/disponibilidad?service_ids[]=$SERVICIO&branch_id=$PRINCIPAL&date=$1" \
    | python3 -c "import sys,json; print(len(json.load(sys.stdin)['data'].get('slots',[])))" 2>/dev/null || echo "?"
}

ANTES=$(turnos "$DIA")
post "/panel/sucursales/$PRINCIPAL/editar" "/panel/sucursales/$PRINCIPAL/cierres" \
  "starts_on=$DIA" "ends_on=$DIA" "reason=Prueba automatica" >/dev/null
check "con el feriado no quedan turnos" "0" "$(turnos "$DIA")"

CIERRE=$(sql "SELECT id FROM branch_closures WHERE branch_id=$PRINCIPAL AND starts_on='$DIA'")
post "/panel/sucursales/$PRINCIPAL/editar" "/panel/sucursales/$PRINCIPAL/cierres/$CIERRE/eliminar" >/dev/null
check "al quitar el feriado vuelven los turnos" "$ANTES" "$(turnos "$DIA")"

# ---- Cupones ----------------------------------------------------------------
echo
echo "== Cupones =="
post /panel/cupones/nuevo /panel/cupones \
  "code=pruebaauto" "discount_type=percent" "discount_value=15" "is_active=1" >/dev/null
CUP=$(sql "SELECT id FROM coupons WHERE code='PRUEBAAUTO' AND deleted_at IS NULL")
check_db "el cupon se guarda en mayusculas" "PRUEBAAUTO" "$(sql "SELECT code FROM coupons WHERE id=$CUP")"
check "rechaza un porcentaje mayor que 100" 422 \
  "$(post /panel/cupones/nuevo /panel/cupones "code=IMPOSIBLE" "discount_type=percent" "discount_value=150")"
check "rechaza un servicio inexistente" 422 \
  "$(post /panel/cupones/nuevo /panel/cupones "code=FANTASMA" "discount_type=fixed" "discount_value=5" "service_id=999999")"
post /panel/cupones/nuevo /panel/cupones "code=PRUEBAAUTO" "discount_type=fixed" "discount_value=5" >/dev/null
check_db "no admite dos cupones con el mismo codigo" "1" \
  "$(sql "SELECT COUNT(*) FROM coupons WHERE code='PRUEBAAUTO' AND deleted_at IS NULL")"
post /panel/cupones "/panel/cupones/$CUP/eliminar" >/dev/null
check_db "al eliminarlo deja de poder canjearse" "0" \
  "$(sql "SELECT is_active FROM coupons WHERE id=$CUP")"

# ---- Suscriptores -----------------------------------------------------------
echo
echo "== Suscriptores =="
CORREO="prueba$(date +%s)@ejemplo.test"
post /panel/suscriptores /panel/suscriptores "email=$CORREO" "name=Cliente De Prueba" >/dev/null
SUB=$(sql "SELECT id FROM subscribers WHERE email='$CORREO'")
check_db "se anade a la lista" "1" "$([ -n "$SUB" ] && echo 1 || echo 0)"
post /panel/suscriptores /panel/suscriptores "email=$CORREO" >/dev/null
check_db "no admite el mismo correo dos veces" "1" \
  "$(sql "SELECT COUNT(*) FROM subscribers WHERE email='$CORREO'")"
post /panel/suscriptores "/panel/suscriptores/$SUB/baja" >/dev/null
check_db "la baja se registra sin borrar la fila" "si" \
  "$(sql "SELECT IF(unsubscribed_at IS NULL,'no','si') FROM subscribers WHERE id=$SUB")"
post /panel/suscriptores "/panel/suscriptores/$SUB/eliminar" >/dev/null
check_db "al borrarlo desaparece de la base" "0" \
  "$(sql "SELECT COUNT(*) FROM subscribers WHERE id=$SUB")"

# ---- Usuarios del panel -----------------------------------------------------
echo
echo "== Usuarios del panel =="
check "no puedo suspenderme a mi mismo" "active" \
  "$(post /panel/usuarios /panel/usuarios/1/estado >/dev/null; sql "SELECT status FROM users WHERE id=1")"
check "no puedo eliminarme a mi mismo" "viva" \
  "$(post /panel/usuarios /panel/usuarios/1/eliminar >/dev/null; sql "SELECT IF(deleted_at IS NULL,'viva','eliminada') FROM users WHERE id=1")"
post /panel/usuarios/nuevo /panel/usuarios \
  "first_name=Debil" "email=debil$(date +%s)@ejemplo.test" "role=staff" "password=123456" >/dev/null
check_db "rechaza una contrasena debil" "0" \
  "$(sql "SELECT COUNT(*) FROM users WHERE first_name='Debil'")"

USUARIO="panel$(date +%s)@ejemplo.test"
post /panel/usuarios/nuevo /panel/usuarios \
  "first_name=Recepcion" "email=$USUARIO" "role=manager" "password=ClaveSegura#2026" "is_active=1" >/dev/null
NUEVO=$(sql "SELECT id FROM users WHERE email='$USUARIO'")
check_db "crea el usuario del panel" "manager" "$(sql "SELECT role FROM users WHERE id=$NUEVO")"
check_db "guarda la clave cifrada, nunca en claro" "si" \
  "$(sql "SELECT IF(password_hash LIKE '%argon2%','si','no') FROM users WHERE id=$NUEVO")"

# ---- Mantenimiento ----------------------------------------------------------
echo
echo "== Mantenimiento =="
sql "INSERT INTO login_attempts (email,ip_address,successful,created_at)
     VALUES ('prueba@x.test','127.0.0.1',0,UTC_TIMESTAMP());" >/dev/null
check "no vacia una tabla sin la confirmacion exacta" "1" \
  "$(post /panel/mantenimiento/tablas /panel/mantenimiento/tablas/vaciar \
      "tabla=login_attempts" "confirm=lo-que-sea" >/dev/null
     sql "SELECT IF(COUNT(*)>0,1,0) FROM login_attempts")"
check "protege las tablas del catalogo" 422 \
  "$(post /panel/mantenimiento/tablas /panel/mantenimiento/tablas/vaciar "tabla=services" "confirm=services")"
check_db "los servicios siguen intactos" "14" "$(sql "SELECT COUNT(*) FROM services WHERE deleted_at IS NULL")"
check "con la confirmacion correcta si vacia" "0" \
  "$(post /panel/mantenimiento/tablas /panel/mantenimiento/tablas/vaciar \
      "tabla=login_attempts" "confirm=login_attempts" >/dev/null
     sql "SELECT COUNT(*) FROM login_attempts")"
check "no compacta una tabla inventada" 404 \
  "$(post /panel/mantenimiento/tablas /panel/mantenimiento/tablas/optimizar "tabla=no_existe")"

echo
echo "== Copias de seguridad =="
post /panel/mantenimiento/copias /panel/mantenimiento/copias >/dev/null
COPIA=$(ls -t "$RAIZ"/storage/backups/*.sql 2>/dev/null | head -1)
check_db "crea el archivo de copia" "1" "$([ -n "$COPIA" ] && echo 1 || echo 0)"
if [ -n "$COPIA" ]; then
  NOMBRE=$(basename "$COPIA")
  check "se puede descargar" 200 "$(get "/panel/mantenimiento/copias/descargar?archivo=$NOMBRE")"
  check "la copia trae todas las tablas" "$(sql "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$DB_NAME'")" \
    "$(grep -c 'CREATE TABLE' "$COPIA")"
  check "rechaza un nombre de copia manipulado" 422 \
    "$(get "/panel/mantenimiento/copias/descargar?archivo=../../.env")"
  post /panel/mantenimiento/copias /panel/mantenimiento/copias/eliminar "archivo=$NOMBRE" >/dev/null
  check_db "la copia se borra del servidor" "0" "$([ -f "$COPIA" ] && echo 1 || echo 0)"
fi

echo
echo "== Archivos subidos =="
check "no borra fuera de la carpeta de subidas" 422 \
  "$(post /panel/mantenimiento/archivos /panel/mantenimiento/archivos/eliminar "archivo=../../../../etc/hostname")"
check "no acepta una ruta absoluta" 404 \
  "$(post /panel/mantenimiento/archivos /panel/mantenimiento/archivos/eliminar "archivo=/etc/hostname")"

# ---- Limpieza de lo que creo la prueba --------------------------------------
sql "DELETE FROM branch_hours WHERE branch_id=$SUC;
     DELETE FROM branches WHERE id=$SUC;
     DELETE FROM coupons WHERE code='PRUEBAAUTO';
     DELETE FROM users WHERE id=$NUEVO;
     DELETE FROM branch_closures WHERE reason='Prueba automatica';" >/dev/null

echo
echo "RESULTADO: $pass correctas, $fail fallidas"
[ "$fail" -eq 0 ]
