// Verificación de contrato API real para PROPIEDADES-B06..B09 — sustituto de la
// verificación visual Playwright bloqueada (ver _state/RUNBOOK.md#E-005).
//
// No usa mocks: pega directo al backend real (docker compose local, localhost:8081)
// con las MISMAS formas de request/response que consumen
// code/web/src/features/propiedades/api/*.ts y los tipos de
// code/web/src/features/propiedades/types/index.ts — así detecta divergencias
// reales entre lo que el backend devuelve y lo que el frontend asume, cosa que
// los tests de componente (con la API mockeada) no pueden ver.
//
// Uso: node scripts/verify-propiedades-contract.mjs
// Requiere: docker compose de code/api corriendo, seed demo aplicado
// (admin@urbania.test / Admin123!).

const API_BASE = "http://localhost:8081";
const EMAIL = "admin@urbania.test";
const PASSWORD = "Admin123!";

let token = "";
let passed = 0;
const failures = [];

function assert(cond, msg) {
  if (cond) {
    passed++;
    console.log(`  \x1b[32m✓\x1b[0m ${msg}`);
  } else {
    failures.push(msg);
    console.log(`  \x1b[31m✗\x1b[0m ${msg}`);
  }
}

async function api(method, path, body) {
  const headers = { "Content-Type": "application/json", Accept: "application/json" };
  if (token) headers["Authorization"] = `Bearer ${token}`;
  const res = await fetch(`${API_BASE}${path}`, {
    method,
    headers,
    body: body !== undefined ? JSON.stringify(body) : undefined,
  });
  const text = await res.text();
  let json;
  try {
    json = text ? JSON.parse(text) : undefined;
  } catch {
    json = text;
  }
  return { status: res.status, body: json };
}

const cleanup = [];

async function main() {
  // ── Login real ──────────────────────────────────────────────────────
  console.log("\n== Login ==");
  const login = await api("POST", "/api/v1/auth/login", { email: EMAIL, password: PASSWORD });
  assert(login.status === 200, `login status 200 (got ${login.status})`);
  assert(typeof login.body?.access_token === "string", "response tiene access_token string");
  token = login.body?.access_token ?? "";

  // ── B06a: property-types (LOCK-PROPIEDADES-01) ─────────────────────
  console.log("\n== B06: GET/POST/PATCH/DELETE property-types ==");
  const typesList = await api("GET", "/api/v1/property-types");
  assert(typesList.status === 200, `GET list status 200 (got ${typesList.status})`);
  assert(Array.isArray(typesList.body?.data), "response tiene data: array (CatalogoListResponse)");
  const typeSample = typesList.body?.data?.[0];
  assert(
    typeSample &&
      "id" in typeSample &&
      "organization_id" in typeSample &&
      "nombre" in typeSample &&
      "descripcion" in typeSample &&
      "created_at" in typeSample,
    "item respeta CatalogoItem (id, organization_id, nombre, descripcion, created_at)",
  );
  const systemType = typesList.body?.data?.find((i) => i.organization_id === null);
  assert(!!systemType, "existe al menos un catálogo de sistema (organization_id null)");

  const typeName = `E2E Verify Tipo ${Date.now()}`;
  const typeCreate = await api("POST", "/api/v1/property-types", { nombre: typeName });
  assert(typeCreate.status === 201, `POST create status 201 (got ${typeCreate.status})`);
  assert(typeCreate.body?.data?.nombre === typeName, "created item.nombre === payload.nombre");
  const createdTypeId = typeCreate.body?.data?.id;

  const typeDup = await api("POST", "/api/v1/property-types", { nombre: typeName });
  assert(typeDup.status === 409, `POST nombre duplicado status 409 (got ${typeDup.status})`);
  assert(
    typeDup.body?.error?.code === "PROPERTY_TYPE_NAME_DUPLICATE",
    `error.code === PROPERTY_TYPE_NAME_DUPLICATE (got ${typeDup.body?.error?.code}) — código que TiposPropiedadPage usa en su switch`,
  );

  const systemPatch = await api("PATCH", `/api/v1/property-types/${systemType.id}`, { nombre: "hack" });
  assert(systemPatch.status === 403, `PATCH catálogo de sistema status 403 (got ${systemPatch.status})`);
  assert(
    systemPatch.body?.error?.code === "SYSTEM_CATALOG_READONLY",
    `error.code === SYSTEM_CATALOG_READONLY (got ${systemPatch.body?.error?.code})`,
  );

  const typeDelete = await api("DELETE", `/api/v1/property-types/${createdTypeId}`);
  assert([200, 204].includes(typeDelete.status), `DELETE creado status 200/204 (got ${typeDelete.status})`);

  // ── B06b: property-statuses (mismo contrato, LOCK-PROPIEDADES-01) ──
  console.log("\n== B06: GET/POST property-statuses ==");
  const statusesList = await api("GET", "/api/v1/property-statuses");
  assert(statusesList.status === 200, `GET list status 200 (got ${statusesList.status})`);
  assert(Array.isArray(statusesList.body?.data), "response tiene data: array");
  const systemStatus = statusesList.body?.data?.find((i) => i.organization_id === null);
  assert(!!systemStatus, "existe al menos un catálogo de sistema");

  // ── B07: condominios + torres (LOCK-PROPIEDADES-02) ────────────────
  console.log("\n== B07: GET/POST condominiums, towers ==");
  const condoList = await api("GET", "/api/v1/condominiums");
  assert(condoList.status === 200, `GET list status 200 (got ${condoList.status})`);
  assert(Array.isArray(condoList.body?.data), "response tiene data: array (CondominioListResponse)");

  const condoName = `E2E Verify Condominio ${Date.now()}`;
  const condoCreate = await api("POST", "/api/v1/condominiums", { nombre: condoName });
  assert(condoCreate.status === 201, `POST create status 201 (got ${condoCreate.status})`);
  assert(
    !!condoCreate.body?.condominium?.id,
    "response envelope es { condominium } (CondominioCreateResponse), no { data }",
  );
  const condoId = condoCreate.body?.condominium?.id;
  cleanup.push({ label: "condominio E2E", fn: () => api("DELETE", `/api/v1/condominiums/${condoId}`) });

  const condoShow = await api("GET", `/api/v1/condominiums/${condoId}`);
  assert(condoShow.status === 200, `GET detail status 200 (got ${condoShow.status})`);
  assert(
    Array.isArray(condoShow.body?.condominium?.towers),
    "detail incluye condominium.towers: array (CondominioDetail)",
  );

  const torreCreate = await api("POST", `/api/v1/condominiums/${condoId}/towers`, { nombre: "Torre E2E" });
  assert(torreCreate.status === 201, `POST torre create status 201 (got ${torreCreate.status})`);
  assert(!!torreCreate.body?.tower?.id, "response envelope es { tower } (TorreCreateResponse)");
  const torreId = torreCreate.body?.tower?.id;

  const condoDeleteBlocked = await api("DELETE", `/api/v1/condominiums/${condoId}`);
  assert(
    condoDeleteBlocked.status === 409,
    `DELETE condominio con torres status 409 (got ${condoDeleteBlocked.status})`,
  );
  assert(
    condoDeleteBlocked.body?.error?.code === "CONDOMINIUM_HAS_TOWERS",
    `error.code === CONDOMINIUM_HAS_TOWERS (got ${condoDeleteBlocked.body?.error?.code}) — código que DetalleCondominioPage usa para el botón "Eliminar condominio"`,
  );

  // ── B08: unidades (LOCK-PROPIEDADES-03) ────────────────────────────
  console.log("\n== B08: GET/POST/PATCH/DELETE properties ==");
  const typeId = systemType.id;
  const statusId = systemStatus.id;

  const propCode = `E2E-${Date.now()}`;
  const propCreate = await api("POST", `/api/v1/condominiums/${condoId}/properties`, {
    codigo: propCode,
    tower_id: torreId,
    property_type_id: typeId,
    property_status_id: statusId,
    piso: 3,
    area_m2: 55.5,
  });
  assert(propCreate.status === 201, `POST create status 201 (got ${propCreate.status})`);
  assert(!!propCreate.body?.property?.id, "response envelope es { property } (PropertyCreateResponse)");
  assert(
    propCreate.body?.property?.area_m2 === 55.5,
    `detail incluye area_m2 (got ${propCreate.body?.property?.area_m2}) — PropertyDetail`,
  );
  const propId = propCreate.body?.property?.id;

  const propsList = await api("GET", `/api/v1/condominiums/${condoId}/properties`);
  assert(propsList.status === 200, `GET list status 200 (got ${propsList.status})`);
  assert(Array.isArray(propsList.body?.data), "response tiene data: array");
  assert(
    propsList.body?.meta && "next_cursor" in propsList.body.meta,
    "response tiene meta.next_cursor (paginación cursor-based)",
  );
  const listItem = propsList.body?.data?.find((p) => p.id === propId);
  assert(
    !!listItem && !("area_m2" in listItem),
    'item de LISTADO NO incluye area_m2 (R-10 — PropertyListItem, distinto de PropertyDetail)',
  );

  const propDup = await api("POST", `/api/v1/condominiums/${condoId}/properties`, {
    codigo: propCode,
    property_type_id: typeId,
    property_status_id: statusId,
  });
  assert(propDup.status === 409, `POST código duplicado status 409 (got ${propDup.status})`);
  assert(
    propDup.body?.error?.code === "PROPERTY_CODE_DUPLICATE",
    `error.code === PROPERTY_CODE_DUPLICATE (got ${propDup.body?.error?.code}) — código que UnidadesTab usa en su switch`,
  );

  // Torre de OTRO condominio -> mismatch
  const condo2Create = await api("POST", "/api/v1/condominiums", { nombre: `E2E Verify Condominio B ${Date.now()}` });
  const condo2Id = condo2Create.body?.condominium?.id;
  cleanup.push({ label: "condominio E2E B (mismatch)", fn: () => api("DELETE", `/api/v1/condominiums/${condo2Id}`) });
  const torre2Create = await api("POST", `/api/v1/condominiums/${condo2Id}/towers`, { nombre: "Torre E2E B" });
  const torre2Id = torre2Create.body?.tower?.id;

  const propMismatch = await api("POST", `/api/v1/condominiums/${condoId}/properties`, {
    codigo: `E2E-MISMATCH-${Date.now()}`,
    tower_id: torre2Id, // torre del condominio 2, creando bajo el condominio 1
    property_type_id: typeId,
    property_status_id: statusId,
  });
  assert(propMismatch.status === 422, `POST torre de otro condominio status 422 (got ${propMismatch.status})`);
  assert(
    propMismatch.body?.error?.code === "TOWER_CONDOMINIUM_MISMATCH",
    `error.code === TOWER_CONDOMINIUM_MISMATCH (got ${propMismatch.body?.error?.code}) — código que UnidadesTab usa en su switch`,
  );

  const propUpdate = await api("PATCH", `/api/v1/properties/${propId}`, { piso: 7 });
  assert(propUpdate.status === 200, `PATCH update status 200 (got ${propUpdate.status})`);
  assert(propUpdate.body?.property?.piso === 7, "PATCH aplica el cambio (piso actualizado)");

  // ── B09: coeficientes + tree (LOCK-PROPIEDADES-04) ─────────────────
  console.log("\n== B09: PATCH coefficients, GET tree ==");
  const coefValid = await api("PATCH", `/api/v1/condominiums/${condoId}/coefficients`, {
    items: [{ property_id: propId, tipo: "copropiedad", valor: 0.5 }],
  });
  assert(coefValid.status === 200, `PATCH coeficiente válido status 200 (got ${coefValid.status})`);
  assert(Array.isArray(coefValid.body?.data), "response tiene data: array (CoefficientItem[])");
  const coefItem = coefValid.body?.data?.[0];
  assert(
    coefItem &&
      "vigente_desde" in coefItem &&
      "vigente_hasta" in coefItem &&
      coefItem.vigente_hasta === null,
    "coeficiente nuevo tiene vigente_hasta: null (vigente actual)",
  );
  assert(
    Array.isArray(coefValid.body?.warnings) &&
      coefValid.body.warnings.some((w) => w.code === "COEFFICIENT_SUM_MISMATCH"),
    "warning COEFFICIENT_SUM_MISMATCH presente (suma 0.5 ≠ 1.0, no bloqueante — R-06)",
  );

  const coefOutOfRange = await api("PATCH", `/api/v1/condominiums/${condoId}/coefficients`, {
    items: [{ property_id: propId, tipo: "copropiedad", valor: 1.5 }],
  });
  assert(coefOutOfRange.status === 422, `PATCH valor fuera de rango status 422 (got ${coefOutOfRange.status})`);
  assert(
    coefOutOfRange.body?.error?.code === "COEFFICIENT_OUT_OF_RANGE",
    `error.code === COEFFICIENT_OUT_OF_RANGE (got ${coefOutOfRange.body?.error?.code}) — CoeficientesTab espera este código en error 422`,
  );

  const coefSupersede = await api("PATCH", `/api/v1/condominiums/${condoId}/coefficients`, {
    items: [{ property_id: propId, tipo: "copropiedad", valor: 0.8 }],
  });
  assert(coefSupersede.status === 200, `PATCH segundo valor status 200 (got ${coefSupersede.status})`);

  const coefHistory = await api("GET", `/api/v1/properties/${propId}/coefficients`);
  assert(coefHistory.status === 200, `GET history status 200 (got ${coefHistory.status})`);
  const historicos = coefHistory.body?.data?.filter((c) => c.tipo === "copropiedad") ?? [];
  assert(historicos.length >= 2, `hay al menos 2 registros históricos tras superseder (got ${historicos.length}) — R-05`);
  assert(
    historicos.some((c) => c.vigente_hasta !== null),
    "el coeficiente anterior quedó con vigente_hasta != null (cerrado automáticamente — R-05)",
  );

  const treeRes = await api("GET", `/api/v1/condominiums/${condoId}/tree`);
  assert(treeRes.status === 200, `GET tree status 200 (got ${treeRes.status})`);
  assert(
    treeRes.body?.tree &&
      Array.isArray(treeRes.body.tree.towers) &&
      "untowered_properties_count" in treeRes.body.tree,
    "response respeta CondominioTreeResponse (tree.towers[], tree.untowered_properties_count)",
  );

  // ── Cleanup ──────────────────────────────────────────────────────────
  console.log("\n== Cleanup ==");
  await api("DELETE", `/api/v1/properties/${propId}`);
  await api("DELETE", `/api/v1/towers/${torreId}`);
  await api("DELETE", `/api/v1/towers/${torre2Id}`);
  for (const { label, fn } of cleanup) {
    const r = await fn();
    console.log(`  cleanup ${label}: status ${r.status}`);
  }

  // ── Resumen ──────────────────────────────────────────────────────────
  console.log(`\n${"=".repeat(60)}`);
  console.log(`Pasaron: ${passed}  Fallaron: ${failures.length}`);
  if (failures.length > 0) {
    console.log("\nFallos:");
    for (const f of failures) console.log(`  - ${f}`);
    process.exitCode = 1;
  } else {
    console.log("Todos los checks de contrato pasaron.");
  }
}

main().catch((err) => {
  console.error("\nError no manejado:", err);
  process.exitCode = 1;
});
