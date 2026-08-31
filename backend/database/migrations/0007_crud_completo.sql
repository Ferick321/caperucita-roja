-- ============================================================================
--  0007 - Permisos de los modulos que faltaban para controlar todo sin codigo
-- ============================================================================
--
--  Sucursales, cupones, suscriptores, lista de espera y usuarios del panel
--  ya tenian tabla, pero no un modulo con el que administrarlos. Aqui se
--  registran sus permisos y se conceden a los roles que corresponden.
--
--  Se usa INSERT IGNORE contra la clave unica del slug para que la migracion
--  se pueda repetir sin duplicar filas.
-- ============================================================================

INSERT IGNORE INTO permissions (slug, module, name, created_at) VALUES
    ('sucursales.ver',    'sucursales',  'Ver las sucursales',              UTC_TIMESTAMP()),
    ('sucursales.editar', 'sucursales',  'Crear y editar sucursales',       UTC_TIMESTAMP()),
    ('cupones.ver',       'cupones',     'Ver los cupones',                 UTC_TIMESTAMP()),
    ('cupones.editar',    'cupones',     'Crear y editar cupones',          UTC_TIMESTAMP()),
    ('suscriptores.ver',  'marketing',   'Ver los suscriptores',            UTC_TIMESTAMP()),
    ('suscriptores.editar','marketing',  'Administrar los suscriptores',    UTC_TIMESTAMP()),
    ('espera.ver',        'citas',       'Ver la lista de espera',          UTC_TIMESTAMP()),
    ('espera.editar',     'citas',       'Administrar la lista de espera',  UTC_TIMESTAMP()),
    ('usuarios.ver',      'seguridad',   'Ver los usuarios del panel',      UTC_TIMESTAMP()),
    ('usuarios.editar',   'seguridad',   'Crear y editar usuarios del panel', UTC_TIMESTAMP());

-- El administrador recibe todo lo nuevo.
-- El super administrador no aparece aqui a proposito: su rol concede '*'
-- por codigo (Auth::permissions), asi que no necesita filas.
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
  FROM roles r
  JOIN permissions p
    ON p.slug IN (
        'sucursales.ver', 'sucursales.editar',
        'cupones.ver', 'cupones.editar',
        'suscriptores.ver', 'suscriptores.editar',
        'espera.ver', 'espera.editar',
        'usuarios.ver', 'usuarios.editar'
    )
 WHERE r.slug = 'admin';

-- Recepcion administra el dia a dia, pero no toca usuarios ni sucursales.
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
  FROM roles r
  JOIN permissions p
    ON p.slug IN (
        'sucursales.ver',
        'cupones.ver', 'cupones.editar',
        'suscriptores.ver',
        'espera.ver', 'espera.editar'
    )
 WHERE r.slug = 'manager';

-- El profesional solo necesita consultar la lista de espera.
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
  FROM roles r
  JOIN permissions p ON p.slug = 'espera.ver'
 WHERE r.slug = 'staff';
