'use strict';
const fs = require('node:fs');
const path = require('node:path');
const {spawnSync, execFileSync} = require('node:child_process');

function runAudits(checks, execute = spawnSync, cwd = process.cwd()) {
  return checks.map(check => {
    const result = execute(check.program, check.args, {
      cwd: path.resolve(cwd, check.directory || '.'),
      encoding: 'utf8', maxBuffer: 16 * 1024 * 1024,
      shell: process.platform === 'win32' && check.program === 'npm',
    });
    return {...check, exit_code: result.status,
      state: result.error || result.status === null ? 'blocked' : result.status === 0 ? 'passed' : 'failed',
      stdout: result.stdout || '', stderr: result.stderr || '', error: result.error?.message};
  });
}

function receipt(results, sourceSha, env = process.env) {
  return {schema_version: 1, repository: env.GITHUB_REPOSITORY || null,
    source_sha: sourceSha, category: 'dependency-security',
    workflow_sha: env.GITHUB_WORKFLOW_SHA || null,
    verification_state: results.some(r => r.state === 'blocked') ? 'blocked' : results.some(r => r.state === 'failed') ? 'failed' : 'passed',
    run_url: env.GITHUB_RUN_ID ? `https://github.com/${env.GITHUB_REPOSITORY}/actions/runs/${env.GITHUB_RUN_ID}` : null,
    checks: results.map(({stdout, stderr, error, ...check}) => check)};
}

if (require.main === module) {
  const recipeRoot = path.resolve(__dirname, '../..');
  const root = process.env.AUDIT_SOURCE_DIR ? path.resolve(process.env.AUDIT_SOURCE_DIR) : recipeRoot;
  const config = JSON.parse(fs.readFileSync(path.join(recipeRoot, '.github/dependency-security.json'), 'utf8'));
  const source = execFileSync('git', ['rev-parse', 'HEAD'], {cwd: root, encoding: 'utf8'}).trim();
  if (!/^[0-9a-f]{40}$/.test(source)) throw Error('Missing exact checked source');
  const output = path.join(root, 'output/dependency-security');
  fs.mkdirSync(output, {recursive: true});
  const results = runAudits(config.checks, spawnSync, root);
  results.forEach((result, index) => {
    console.log(`${result.program} ${result.args.join(' ')}: ${result.state} (${result.exit_code})`);
    process.stdout.write(result.stdout); process.stderr.write(result.stderr);
    if (result.error) console.error(result.error);
    fs.writeFileSync(path.join(output, `audit-${index + 1}.log`), result.stdout + result.stderr + (result.error || ''));
  });
  const record = receipt(results, source);
  fs.writeFileSync(path.join(output, 'receipt.json'), JSON.stringify(record, null, 2) + '\n');
  if (process.env.GITHUB_STEP_SUMMARY) fs.appendFileSync(process.env.GITHUB_STEP_SUMMARY,
    `Dependency security: **${record.verification_state}** at \`${source}\`. Functional evidence is reported by separate checks.\n`);
  process.exitCode = record.verification_state === 'passed' ? 0 : 1;
}
module.exports = {runAudits, receipt};
