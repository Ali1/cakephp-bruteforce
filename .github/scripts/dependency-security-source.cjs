'use strict';
const fs = require('node:fs');

// Read-only handoff from one completed functional attempt; no audit, install or deploy.
async function select(event, config, repository, api) {
  const hinted = event.workflow_run;
  if (!hinted || !Number.isSafeInteger(hinted.id) || hinted.id < 1) throw Error('Missing upstream run identity');
  const run = await api(`actions/runs/${hinted.id}`);
  const branch = event.repository?.default_branch;
  if (event.repository?.full_name !== repository || run.head_repository?.full_name !== repository
      || !branch || run.head_branch !== branch || run.path !== `.github/workflows/${config.functional_workflow}`
      || run.status !== 'completed' || !['schedule', 'workflow_dispatch'].includes(run.event)
      || !/^[a-f0-9]{40}$/.test(run.head_sha || '') || run.head_sha !== hinted.head_sha
      || run.run_attempt !== hinted.run_attempt) throw Error('Untrusted or stale functional run');
  const match = run.display_title?.match(new RegExp(`^${config.source_title_prefix}:([a-f0-9]{40})$`));
  if (!match || (!config.historical_source && match[1] !== run.head_sha)) throw Error('Missing exact upstream source');
  const sha = match[1];
  const base = {source_sha: sha, source_run_id: String(run.id), source_run_attempt: run.run_attempt,
    source_workflow_sha: run.head_sha, source_workflow: run.path};
  if (run.conclusion === 'cancelled') return {...base, run_audit: 'false', reason: 'Cancelled functional attempt'};
  const jobs = [];
  for (let page = 1; ; page++) {
    const data = await api(`actions/runs/${run.id}/attempts/${run.run_attempt}/jobs?per_page=100&page=${page}`);
    if (!Array.isArray(data.jobs)) throw Error('Incomplete functional jobs');
    jobs.push(...data.jobs);
    if (data.jobs.length < 100) break;
  }
  if (config.source_guard && !jobs.some(j => j.name === config.source_guard && j.conclusion === 'success')) {
    return {...base, run_audit: 'false', reason: 'Functional source guard did not pass'};
  }
  if (!config.functional_jobs.some(name => jobs.some(j => j.name === name && j.status === 'completed'
      && j.conclusion && j.conclusion !== 'skipped' && j.conclusion !== 'cancelled'))) {
    return {...base, run_audit: 'false', reason: 'Deduplicated or unattempted functional source'};
  }
  if (config.resolved_graph_artifact) {
    const artifacts = [];
    for (let page = 1; ; page++) {
      const data = await api(`actions/runs/${run.id}/artifacts?per_page=100&page=${page}`);
      if (!Array.isArray(data.artifacts)) throw Error('Incomplete dependency graph artifacts');
      artifacts.push(...data.artifacts);
      if (data.artifacts.length < 100) break;
    }
    const started = Date.parse(run.run_started_at);
    const matches = artifacts.filter(a => a.name === `dependency-security-lock-${sha}` && !a.expired
      && Number.isSafeInteger(a.id) && a.id > 0 && a.workflow_run?.head_sha === run.head_sha
      && Number.isFinite(started) && Date.parse(a.created_at) >= started);
    if (matches.length !== 1) throw Error('Missing, stale or ambiguous exact-attempt dependency graph');
    base.graph_artifact_id = String(matches[0].id);
  }
  return {...base, run_audit: 'true', reason: 'Exact functional attempt selected; raw security status stays separate'};
}

if (require.main === module) {
  const event = JSON.parse(fs.readFileSync(process.env.GITHUB_EVENT_PATH, 'utf8'));
  const config = JSON.parse(fs.readFileSync(require('node:path').join(__dirname, '../dependency-security.json'), 'utf8'));
  const api = async endpoint => {
    const response = await fetch(`${process.env.GITHUB_API_URL || 'https://api.github.com'}/repos/${process.env.GITHUB_REPOSITORY}/${endpoint}`, {
      headers: {Authorization: `Bearer ${process.env.GH_TOKEN}`, Accept: 'application/vnd.github+json'},
      signal: AbortSignal.timeout(30000),
    });
    if (!response.ok) throw Error(`Upstream security source lookup failed (${response.status})`);
    return response.json();
  };
  select(event, config, process.env.GITHUB_REPOSITORY, api).then(result => {
    fs.writeFileSync('dependency-security-source.json', JSON.stringify(result, null, 2) + '\n');
    for (const [key, value] of Object.entries(result)) {
      if (key !== 'reason') fs.appendFileSync(process.env.GITHUB_OUTPUT, `${key}=${value}\n`);
    }
    fs.appendFileSync(process.env.GITHUB_STEP_SUMMARY, `${result.source_sha}: ${result.reason}. This is not functional certification.\n`);
  }).catch(error => { console.error(error.message); process.exitCode = 1; });
}
module.exports = {select};