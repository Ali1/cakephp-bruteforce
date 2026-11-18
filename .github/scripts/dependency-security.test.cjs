'use strict';
const test = require('node:test');
const assert = require('node:assert/strict');
const {runAudits, receipt} = require('./dependency-security.cjs');
const fs = require('node:fs');
const path = require('node:path');
test('an advisory failure stays failed while remaining independent audits execute', () => {
  const called = [];
  const results = runAudits([{program:'npm',args:['audit']},{program:'composer',args:['audit','--locked']}],
    program => {called.push(program); return {status:program === 'npm' ? 1 : 0,stdout:'',stderr:''};});
  assert.deepEqual(called,['npm','composer']);
  assert.deepEqual(results.map(r=>r.state),['failed','passed']);
  const record=receipt(results,'a'.repeat(40),{});
  assert.equal(record.category,'dependency-security');
  assert.equal(record.source_sha,'a'.repeat(40));
  assert.equal(record.verification_state,'failed');
});
test('installation and process failures remain failures or explicit blocked evidence', () => {
  const install=runAudits([{program:'npm',args:['ci']}],()=>({status:1,stdout:'',stderr:'install failed'}));
  assert.equal(receipt(install,'b'.repeat(40),{}).verification_state,'failed');
  const unavailable=runAudits([{program:'composer',args:['audit']}],()=>({status:null,error:Error('unavailable')}));
  assert.equal(receipt(unavailable,'b'.repeat(40),{}).verification_state,'blocked');
});
test('independent security collection has no application-test handoff', () => {
  const root=path.resolve(__dirname,'../..');
  const config=JSON.parse(fs.readFileSync(path.join(root,'.github/dependency-security.json'),'utf8'));
  const security=fs.readFileSync(path.join(root,'.github/workflows',config.workflow),'utf8');
  assert.match(security,/schedule:/);
  assert.match(security,/workflow_dispatch:/);
  assert.doesNotMatch(security,/workflow_run:|continue-on-error|composer install|npm test|phpunit|phpstan/);
  assert.match(security,/github.ref_name == github.event.repository.default_branch/);
  assert.match(security,/nightly-candidate\.cjs/);
  assert.match(security,/NIGHTLY_ANCHOR_JOBS: Collect dependency security evidence/);
  assert.match(security,/composer update --no-install --no-scripts --no-plugins/);
  assert.match(security,/git merge-base --is-ancestor/);
  assert.match(security,/cp composer.lock output\/dependency-security\/composer.lock/);
  assert.match(security,/always\(\)/);
  assert.match(security,/node \.github\/scripts\/dependency-security\.cjs --collect-evidence/);
});
