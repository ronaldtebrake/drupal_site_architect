/** Build a shareable, allowlisted replay. No provider or network calls. */
import { readFile, writeFile } from 'node:fs/promises';
import { createHash } from 'node:crypto';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const here = dirname(fileURLToPath(import.meta.url));
const input = process.argv[2];
if (!input) throw new Error('Pass the directory containing capture.php results.');
const cases = JSON.parse(await readFile(join(here, 'cases.json'), 'utf8'));
const stop = new Set('a an and are as at be been but by can could each for from have has in into is it its of on or our should that the their them these they this those to using want we when where which who will with would'.split(' '));

// Deliberately simple, documented English lexical normalization, no synonyms.
export function tokens(text) {
  return (text.toLowerCase().match(/[a-z]+/g) || []).filter(t => t.length > 2 && !stop.has(t)).map(t => {
    if (t.length > 5 && t.endsWith('ies')) return t.slice(0, -3) + 'y';
    if (t.length > 5 && t.endsWith('ing')) return t.slice(0, -3);
    if (t.length > 4 && t.endsWith('ed')) return t.slice(0, -2);
    if (t.length > 3 && t.endsWith('s') && !t.endsWith('ss')) return t.slice(0, -1);
    return t;
  });
}

export function index(pool) {
  const documents = pool.map(item => ({ item, words: tokens([item.label, item.description, ...Object.values(item.fields || {}).map(f => `${f.label} ${f.description} ${f.type}`)].join(' ')) }));
  const average = documents.reduce((n, d) => n + d.words.length, 0) / documents.length;
  const frequency = new Map();
  for (const d of documents) for (const term of new Set(d.words)) frequency.set(term, (frequency.get(term) || 0) + 1);
  return query => {
    const terms = [...new Set(tokens(query))];
    return documents.map(({ item, words }) => {
      let score = 0;
      const matches = [];
      for (const term of terms) {
        const tf = words.filter(word => word === term).length;
        if (!tf) continue;
        matches.push(term);
        const df = frequency.get(term);
        const idf = Math.log(1 + (documents.length - df + .5) / (df + .5));
        score += idf * tf * 2.2 / (tf + 1.2 * (1 - .75 + .75 * words.length / average));
      }
      return { id: item.id, label: item.label, score: Number(score.toFixed(6)), matching_terms: matches };
    }).filter(row => row.score > 0).sort((a, b) => b.score - a.score || a.id.localeCompare(b.id));
  };
}

const pick = (obj, keys) => Object.fromEntries(keys.filter(k => obj?.[k] !== undefined).map(k => [k, obj[k]]));
const judgment = answer => answer ? pick(answer, ['choice', 'probabilities', 'confidence', 'needs_review']) : null;
const sha = value => createHash('sha256').update(value).digest('hex');
const results = [];
for (const scenario of cases) {
  const raw = await readFile(join(resolve(input), scenario.id + '.json'), 'utf8');
  const source = JSON.parse(raw);
  if (JSON.stringify(source.case) !== JSON.stringify(scenario)) throw new Error('Capture does not match declared brief: ' + scenario.id);
  const a = source.assessment;
  const pool = Object.entries(source.shared_site.bundles).map(([id, bundle]) => ({
    id: 'bundle__' + id, kind: 'content_type', ...pick(bundle, ['label', 'description', 'fields']), reference: bundle.source,
  }));
  const modules = source.shared_site.available_modules;
  const moduleNames = new Set(Object.values(modules).map(m => m.module_name));
  for (const item of [...Object.values(modules), ...Object.values(source.shared_discovery.items).filter(i => !(i.kind === 'module' && moduleNames.has(i.machine_name)))]) {
    pool.push(pick(item, ['id', 'kind', 'label', 'description', 'package', 'module_name', 'availability', 'dependencies']));
  }
  const rank = index(pool);
  const passages = scenario.brief.split(/(?<=[.!?;])\s+|\n+/).filter(Boolean);
  const baseline = passages.map(text => ({
    text,
    candidates: rank(text).slice(0, 3),
    field_matches: pool.filter(p => p.fields).flatMap(p => Object.entries(p.fields).filter(([, f]) => tokens(f.label).some(t => tokens(text).includes(t))).map(([name, f]) => ({ candidate: p.id, field: name, label: f.label, type: f.type }))),
    status: 'Inspect keyword matches; choose components and verify their relationships.',
  }));
  const areas = Object.values(a.plan.areas);
  const parts = areas.flatMap(area => area.requirements.parts.map(part => ({
    ...pick(part, ['id', 'text', 'kind', 'status', 'option_id', 'option_label', 'needs_review']),
    selection: judgment(part.selection), coverage: judgment(part.coverage), kind_judgment: judgment(part.kind_judgment),
    target: part.target ? pick(part.target, ['entity_type', 'bundle', 'label', 'config']) : null,
    target_selection: judgment(part.target_selection),
    fields: part.fields.map(f => ({ ...pick(f, ['name', 'label', 'type', 'purpose']), judgment: judgment(f.judgment) })),
  })));
  if (parts.length !== passages.length || parts.some((p, i) => p.text !== passages[i])) throw new Error('Passage alignment changed: ' + scenario.id);
  const retained = new Set(Object.keys(a.candidates));
  results.push({
    ...scenario, captured_at: source.captured_at, source_sha256: sha(raw),
    site_fingerprint: source.shared_site.fingerprint,
    evidence_sha256: sha(JSON.stringify(pool)),
    pool,
    baseline: { algorithm: 'BM25 k1=1.2 b=0.75; simple suffix normalization; one query per original sentence; top three positive matches. No module-specific rules, semantic labels or inference.', rankings: baseline, overall: rank(scenario.brief).slice(0, 3) },
    jev: {
      model: a.model, profile: a.profile, status: a.status, summary: a.summary,
      content_model: judgment(a.answers.content_model), presentation: judgment(a.answers.presentation),
      selection: areas[0].selection, parts,
      semantic_screening: { considered: Object.keys(modules).length, retained: Object.keys(a.local_discovery.items).length },
      options: areas[0].options.filter(o => o.package || o.bundle_id).map(o => ({ ...pick(o, ['id', 'label', 'selected', 'requirement_support']), contribution: judgment(o.contribution), brief_relevance: judgment(o.brief_relevance) })),
      retained_candidates: [...retained],
    },
    discovery: { query: scenario.query, returned: source.shared_discovery.returned, truncated: source.shared_discovery.truncated, warnings: source.shared_discovery.warnings },
  });
}
const data = {
  title: 'From matching words to a connected plan',
  method: 'Three recorded cases, fixed searches and shared site/catalog evidence. The baseline uses no model. Jev uses the production semantic screening and planning stages. Automatic search planning is excluded from both. This compares one lexical baseline, not another LLM or every possible no-Jev implementation.',
  scope: 'Editorial summaries explain recorded output. Scores are judgments, not verified correctness or percentages of requirements completed. No installation or integration was performed.',
  cases: results,
};
await writeFile(join(here, 'results.json'), JSON.stringify(data, null, 2) + '\n');
const template = await readFile(join(here, 'template.html'), 'utf8');
await writeFile(join(here, 'index.html'), template.replace('/*__DATA__*/', JSON.stringify(data).replaceAll('<', '\\u003c')));
for (const r of results) {
  console.log(r.id, r.pool.length, 'shared candidates');
  r.jev.parts.forEach((p, i) => console.log(' ', r.baseline.rankings[i].candidates[0]?.label, '→', p.option_label, `[${p.status}]`));
}
