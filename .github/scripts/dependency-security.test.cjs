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
test('the real workflow keeps advisory status outside every functional job', () => {
  const root=path.resolve(__dirname,'../..');
  const config=JSON.parse(fs.readFileSync(path.join(root,'.github/dependency-security.json'),'utf8'));
  const workflow=fs.readFileSync(path.join(root,'.github/workflows',config.workflow),'utf8');
  const blocks=workflow.split(/^  (?=[a-z][a-z0-9-]*:\s*$)/m).slice(1);
  const security=blocks.find(block=>block.startsWith('dependency-security:'));
  assert.ok(security);
  assert.ok(security.includes('name: Dependency security audits'));
  assert.ok(security.includes('if: ${{ always() }}'));
  assert.ok(!security.includes('continue-on-error'));
  for(const block of blocks.filter(block=>!block.startsWith('dependency-security:'))){
    const dependencies=block.match(/^    needs:[^\n]*(?:\n    -[^\n]*)*/m)?.[0] || '';
    assert.ok(!dependencies.includes('dependency-security'), 'Functional jobs must not depend on the security job');
    assert.doesNotMatch(block,/npm audit|composer audit/,'Audit findings cannot terminate a functional step');
  }
});
