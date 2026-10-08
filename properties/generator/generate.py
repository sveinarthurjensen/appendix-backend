#!/usr/bin/env python3
"""
Base44-skjema → Laravel.

Leser en JSON-eksport fra list_entity_schemas ({schemas:[{entity_name, entity_schema}]})
og genererer, per entitet:
  - database/migrations/<ts>_create_<tabell>_table.php
  - app/Models/<Entity>.php            (casts, fillable, enum-konstanter, valideringsregler)
  - app/Policies/<Entity>Policy.php    (oversatt fra RLS)
samt
  - app/Support/EntityRegistry.php     (navn → modellklasse, brukes av det generiske API-et)
  - database/seeders/EntityRegistrySeeder.php (tom – plass til kataloger/maler)

Bruk:  python3 generate.py <schemas.json> <app_id> <ut-mappe>
       python3 generate.py schema/appendix_properties_schemas.json appendix_properties out/
"""
import json, re, sys, os, datetime
from collections import OrderedDict

# ---------- hjelpere ----------

def snake(name: str) -> str:
    s1 = re.sub(r'(.)([A-Z][a-z]+)', r'\1_\2', name)
    return re.sub(r'([a-z0-9])([A-Z])', r'\1_\2', s1).lower()

# PHP-reserverte ord kan ikke være klassenavn. Entitetsnavnet (ENTITY) og tabellen beholdes.
PHP_RESERVED = {'Case': 'CaseRecord', 'Class': 'ClassRecord', 'Function': 'FunctionRecord', 'Interface': 'InterfaceRecord',
                'Trait': 'TraitRecord', 'List': 'ListRecord', 'Default': 'DefaultRecord', 'Switch': 'SwitchRecord',
                'Match': 'MatchRecord', 'Enum': 'EnumRecord', 'Array': 'ArrayRecord', 'String': 'StringRecord'}

def class_name(entity: str) -> str:
    return PHP_RESERVED.get(entity, entity)

def table_name(entity: str) -> str:
    # Property -> properties, Case -> cases, Equipment -> equipment
    s = snake(entity)
    if s.endswith('s') and not s.endswith('ss'):
        return s
    if s.endswith('y') and s[-2] not in 'aeiou':
        return s[:-1] + 'ies'
    if s.endswith(('ss', 'x', 'ch', 'sh')):
        return s + 'es'
    return s + 's'

def php_str(s):
    return "'" + str(s).replace("\\", "\\\\").replace("'", "\\'") + "'"

# Laravel-tidsstempler er omdøpt til created_date/updated_date i Base44Entity-traiten,
# så skjemafelt som heter created_at kolliderer ikke.
RESERVED_COLS = {'id', 'app_id', 'created_by', 'created_date', 'updated_date', 'deleted_at', 'is_sample'}

def column_for(name, prop):
    """Returner (schema-builder-kall, cast) for ett felt."""
    t = prop.get('type')
    fmt = prop.get('format')
    if isinstance(t, list):            # ["string","null"]
        t = [x for x in t if x != 'null'][0] if any(x != 'null' for x in t) else 'string'
    if t == 'string':
        if fmt == 'date':
            return f"$table->date({php_str(name)})", 'date:Y-m-d'
        if fmt == 'date-time':
            return f"$table->timestampTz({php_str(name)})", 'datetime'
        if prop.get('enum'):
            return f"$table->string({php_str(name)}, 64)", None
        return f"$table->text({php_str(name)})", None
    if t == 'number':
        return f"$table->decimal({php_str(name)}, 18, 4)", 'float'
    if t == 'integer':
        return f"$table->bigInteger({php_str(name)})", 'integer'
    if t == 'boolean':
        return f"$table->boolean({php_str(name)})", 'boolean'
    if t in ('array', 'object'):
        return f"$table->jsonb({php_str(name)})", 'array'
    return f"$table->text({php_str(name)})", None

# ---------- RLS → policy ----------

def rls_rule_to_php(rule, model_var='$model'):
    """
    Oversetter én RLS-regel til et PHP-uttrykk som returnerer bool.
    Støtter: {user_condition:{role:X}}, {$or:[...]}, {$and:[...]},
             {"data.<felt>":"{{user.id}}"} / "{{user.email}}"
    Ukjente regler → false (deny) med kommentar.
    """
    if rule is None:
        return 'false /* ingen regel i Base44 → kun admin via before() */'
    if '$or' in rule:
        return '(' + ' || '.join(rls_rule_to_php(r, model_var) for r in rule['$or']) + ')'
    if '$and' in rule:
        return '(' + ' && '.join(rls_rule_to_php(r, model_var) for r in rule['$and']) + ')'
    if 'user_condition' in rule:
        uc = rule['user_condition']
        if 'role' in uc:
            return f"$user->hasRole({php_str(uc['role'])})"
        if 'id' in uc:
            return f"$user->id === {php_str(uc['id'])}"
        if 'email' in uc:
            return f"$user->email === {php_str(uc['email'])}"
        parts = [f"($user->{k} ?? null) === {php_str(v)}" for k, v in uc.items()]
        return '(' + ' && '.join(parts) + ')'
    exprs = []
    for k, v in rule.items():
        if k.startswith('data.'):
            field = k[5:]
            if v == '{{user.id}}':
                exprs.append(f"{model_var}->{field} === $user->id")
            elif v == '{{user.email}}':
                exprs.append(f"{model_var}->{field} === $user->email")
            elif isinstance(v, dict) and '$in' in v:
                exprs.append(f"in_array({model_var}->{field}, {php_array(v['$in'])}, true)")
            else:
                exprs.append(f"{model_var}->{field} === {php_str(v)}")
        else:
            exprs.append(f"false /* ustøttet RLS-nøkkel {k} */")
    return '(' + ' && '.join(exprs) + ')' if exprs else 'false'

def rls_read_scope(rule):
    """
    Gjør om en read-regel til en Eloquent-scope så lister filtreres i databasen,
    ikke bare per rad. Returnerer liste av (felt, user-attributt) eller None hvis admin-only.
    """
    if rule is None:
        return []
    if '$or' in rule:
        out = []
        for r in rule['$or']:
            s = rls_read_scope(r)
            if s is None:
                continue
            out.extend(s)
        return out
    out = []
    for k, v in rule.items():
        if k.startswith('data.') and isinstance(v, str) and v.startswith('{{user.'):
            out.append((k[5:], v[7:-2]))
    return out

def php_array(xs):
    return '[' + ', '.join(php_str(x) for x in xs) + ']'

# ---------- generering ----------

MIGRATION_TMPL = """<?php

use Illuminate\\Database\\Migrations\\Migration;
use Illuminate\\Database\\Schema\\Blueprint;
use Illuminate\\Support\\Facades\\Schema;

/** Generert fra Base44-entiteten {entity} ({app_id}). Ikke rediger for hånd – endre generatoren. */
return new class extends Migration
{{
    public function up(): void
    {{
        Schema::create('{table}', function (Blueprint $table) {{
            // Standardkolonner (Base44-kompatible)
            $table->string('id', 32)->primary();          // Base44-ID beholdes ved import, ulid på nye
            $table->string('app_id', 64)->index();
            $table->string('created_by', 255)->nullable()->index();   // e-post, som i Base44
            $table->timestampTz('created_date')->nullable()->index();
            $table->timestampTz('updated_date')->nullable();
            $table->boolean('is_sample')->default(false);
            $table->softDeletesTz();

            // Felt fra skjemaet
{columns}
{indexes}
        }});
    }}

    public function down(): void
    {{
        Schema::dropIfExists('{table}');
    }}
}};
"""

MODEL_TMPL = """<?php

namespace App\\Models;

use App\\Models\\Concerns\\Base44Entity;
use Illuminate\\Database\\Eloquent\\Model;
use Illuminate\\Database\\Eloquent\\SoftDeletes;

/**
 * Generert fra Base44-entiteten {entity} ({app_id}).
 * Ikke rediger for hånd – legg relasjoner/forretningslogikk i en trait eller endre generatoren.
{doc}
 */
class {cls} extends Model
{{
    use Base44Entity, SoftDeletes;

    public const CREATED_AT = 'created_date';
    public const UPDATED_AT = 'updated_date';

    public const APP_ID = {app_id_php};
    public const ENTITY = {entity_php};

    protected $table = '{table}';

    protected $fillable = [
{fillable}
    ];

    protected $casts = [
{casts}
    ];

    /** Feltnavn → tillatte verdier (fra enum i skjemaet) */
    public const ENUMS = [
{enums}
    ];

    /** Påkrevde felt ved opprettelse */
    public const REQUIRED = {required};

    /** Felt med read-RLS på rad-nivå: [felt => brukerattributt] – brukes av scopeVisibleTo */
    public const OWNER_FIELDS = {owner_fields};
}}
"""

POLICY_TMPL = """<?php

namespace App\\Policies;

use App\\Models\\User;
use App\\Models\\{cls};

/** Generert fra RLS-blokken til {entity}. before() gir admin alt, som i Base44. */
class {cls}Policy
{{
    public function before(User $user, string $ability): ?bool
    {{
        return $user->hasRole('admin') ? true : null;
    }}

    public function viewAny(User $user): bool
    {{
        return {view_any};
    }}

    public function view(User $user, {cls} $model): bool
    {{
        return {view};
    }}

    public function create(User $user): bool
    {{
        return {create};
    }}

    public function update(User $user, {cls} $model): bool
    {{
        return {update};
    }}

    public function delete(User $user, {cls} $model): bool
    {{
        return {delete};
    }}
}}
"""

def generate(schemas_path, app_id, out_dir):
    data = json.load(open(schemas_path))
    schemas = data['schemas'] if isinstance(data, dict) else data
    mig_dir = os.path.join(out_dir, 'database/migrations')
    model_dir = os.path.join(out_dir, 'app/Models')
    pol_dir = os.path.join(out_dir, 'app/Policies')
    sup_dir = os.path.join(out_dir, 'app/Support')
    for d in (mig_dir, model_dir, pol_dir, sup_dir):
        os.makedirs(d, exist_ok=True)

    ts = datetime.datetime(2026, 10, 6, 0, 0, 0)
    registry = OrderedDict()
    report = []

    for i, item in enumerate(sorted(schemas, key=lambda s: s['entity_name'])):
        entity = item['entity_name']
        schema = item['entity_schema'] or {}
        if entity == 'User':
            # User håndteres av Laravel sin egen users-tabell + migrasjon for ekstra felt
            continue
        props = schema.get('properties', {}) or {}
        required = schema.get('required', []) or []
        rls = schema.get('rls')
        table = table_name(entity)
        cls = class_name(entity)

        cols, idx, fillable, casts, enums, doc = [], [], [], [], [], []
        for name, prop in props.items():
            if name in RESERVED_COLS or not re.match(r'^[a-z_][a-z0-9_]*$', name):
                report.append(f"{entity}.{name}: hoppet over (reservert/ugyldig kolonnenavn)")
                continue
            call, cast = column_for(name, prop)
            if prop.get('default') is not None and not isinstance(prop['default'], (list, dict)):
                d = prop['default']
                dv = 'true' if d is True else 'false' if d is False else (php_str(d) if isinstance(d, str) else str(d))
                call += f"->default({dv})"
            call += "->nullable()"
            desc = prop.get('description')
            cols.append(f"            {call};" + (f"  // {desc}" if desc else ''))
            fillable.append(f"        {php_str(name)},")
            if cast:
                casts.append(f"        {php_str(name)} => {php_str(cast)},")
            if prop.get('enum'):
                enums.append(f"        {php_str(name)} => {php_array(prop['enum'])},")
            if name.endswith('_id') or name in ('status', 'email', 'token', 'signing_token'):
                idx.append(f"            $table->index({php_str(name)});")
            doc.append(f" * @property mixed ${name}")

        mig = MIGRATION_TMPL.format(entity=entity, app_id=app_id, table=table,
                                    columns='\n'.join(cols), indexes='\n'.join(idx))
        stamp = (ts + datetime.timedelta(seconds=i)).strftime('%Y_%m_%d_%H%M%S')
        open(os.path.join(mig_dir, f"{stamp}_create_{table}_table.php"), 'w').write(mig)

        owner_fields = rls_read_scope((rls or {}).get('read')) if rls else []
        of_php = '[' + ', '.join(f"{php_str(f)} => {php_str(a)}" for f, a in owner_fields) + ']'
        model = MODEL_TMPL.format(entity=entity, cls=cls, app_id=app_id, app_id_php=php_str(app_id),
                                  entity_php=php_str(entity), table=table,
                                  fillable='\n'.join(fillable), casts='\n'.join(casts),
                                  enums='\n'.join(enums), required=php_array(required),
                                  owner_fields=of_php, doc='\n'.join(doc))
        open(os.path.join(model_dir, f"{cls}.php"), 'w').write(model)

        r = rls or {}
        # Mangler read-regel i Base44 = kun admin (before() dekker det) → false her
        pol = POLICY_TMPL.format(
            entity=entity, cls=cls,
            view_any=rls_rule_to_php(r.get('read')) if owner_fields else rls_rule_to_php(r.get('read')),
            view=rls_rule_to_php(r.get('read')),
            create=rls_rule_to_php(r.get('create')),
            update=rls_rule_to_php(r.get('update')),
            delete=rls_rule_to_php(r.get('delete')),
        )
        # viewAny: for eier-scopede entiteter er listing tillatt (scope filtrerer), ellers samme som view
        if owner_fields:
            pol = pol.replace(f"return {rls_rule_to_php(r.get('read'))};\n    }}\n\n    public function view(",
                              "return true; // listing filtreres av scopeVisibleTo\n    }\n\n    public function view(", 1)
        open(os.path.join(pol_dir, f"{cls}Policy.php"), 'w').write(pol)

        registry[entity] = f"\\App\\Models\\{cls}::class"
        if not rls:
            report.append(f"{entity}: ingen RLS i Base44 → kun admin i Laravel (strammere enn før)")

    reg = "<?php\n\nnamespace App\\Support;\n\n/** Generert: Base44-entitetsnavn → Eloquent-modell. */\nfinal class EntityRegistry\n{\n    public const MODELS = [\n"
    for k, v in registry.items():
        reg += f"        {php_str(k)} => {v},\n"
    reg += "    ];\n\n    public static function model(string $entity): ?string\n    {\n        return self::MODELS[$entity] ?? null;\n    }\n}\n"
    open(os.path.join(sup_dir, 'EntityRegistry.php'), 'w').write(reg)

    open(os.path.join(out_dir, 'GENERATOR_REPORT.md'), 'w').write(
        f"# Generator-rapport ({app_id})\n\n{len(registry)} entiteter generert.\n\n" +
        ('\n'.join('- ' + r for r in report) if report else '- Ingen avvik.') + '\n')
    print(f"{len(registry)} entiteter → {out_dir}")
    for r in report:
        print('  !', r)

if __name__ == '__main__':
    if len(sys.argv) != 4:
        print(__doc__); sys.exit(1)
    generate(sys.argv[1], sys.argv[2], sys.argv[3])
