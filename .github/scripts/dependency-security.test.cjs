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
test('functional workflow certification and raw dependency audits have separate conclusions', () => {
  const root=path.resolve(__dirname,'../..');
  const config=JSON.parse(fs.readFileSync(path.join(root,'.github/dependency-security.json'),'utf8'));
  const functional=fs.readFileSync(path.join(root,'.github/workflows',config.functional_workflow),'utf8');
  const security=fs.readFileSync(path.join(root,'.github/workflows',config.workflow),'utf8');
  assert.notEqual(config.functional_workflow,config.workflow);
  assert.doesNotMatch(functional,/^  dependency-security:/m);
  assert.doesNotMatch(functional,/npm audit|composer audit|scripts\/dependency-security\.cjs/);
  assert.match(security,/workflow_run:/);
  assert.doesNotMatch(security,/schedule:|cron:|continue-on-error/);
  assert.match(security,/github\.workflow_sha/);
  assert.match(security,/dependency-security-source\.cjs/);
  assert.match(security,/steps\.source\.outputs\.source_sha/);
  assert.match(security,/always\(\)/);
  assert.match(security,/node \.github\/scripts\/dependency-security\.cjs --collect-evidence/);
  assert.match(security,/audit-source\/output\/dependency-security/);
  if(config.resolved_graph_artifact){
    assert.match(functional,/dependency-security-lock-\$\{\{ github\.sha \}\}/);
    assert.match(security,/artifact-ids: \$\{\{ steps\.source\.outputs\.graph_artifact_id \}\}/);
    assert.match(security,/run-id: \$\{\{ steps\.source\.outputs\.source_run_id \}\}/);
    assert.doesNotMatch(security,/composer install/);
  }
});
