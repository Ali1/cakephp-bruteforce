'use strict';
const test = require('node:test');
const assert = require('node:assert/strict');
const {select} = require('./dependency-security-source.cjs');
const sha = 'a'.repeat(40), older = 'b'.repeat(40);
const config = {functional_workflow:'nightly-core.yml',source_title_prefix:'core-checks',functional_jobs:['Core checks'],resolved_graph_artifact:true};
const event = {repository:{full_name:'Ali1/example',default_branch:'master'},workflow_run:{id:7,head_sha:sha,run_attempt:2}};
const run = {id:7,head_sha:sha,head_branch:'master',head_repository:{full_name:'Ali1/example'},path:'.github/workflows/nightly-core.yml',status:'completed',event:'schedule',run_attempt:2,display_title:`core-checks:${sha}`,run_started_at:'2026-10-28T10:00:00Z'};
const job = {name:'Core checks',status:'completed',conclusion:'success'};
const artifact = {id:9,name:`dependency-security-lock-${sha}`,expired:false,created_at:'2026-10-28T10:01:00Z',workflow_run:{head_sha:sha}};
function fixture({r=run,jobs=[job],artifacts=[artifact],fail=false}={}) {
  return async endpoint => {
    if (fail) throw Error('lookup unavailable');
    if (endpoint === 'actions/runs/7') return r;
    if (endpoint.includes('/attempts/2/jobs?')) return {jobs};
    if (endpoint.includes('/artifacts?')) return {artifacts};
    throw Error('unexpected endpoint');
  };
}
test('one exact-attempt graph is handed off without rerunning dependency installation', async () => {
  const result=await select(event,config,'Ali1/example',fixture());
  assert.equal(result.run_audit,'true');assert.equal(result.source_sha,sha);
  assert.equal(result.graph_artifact_id,'9');assert.equal(result.source_run_attempt,2);
});
test('unchanged-source skipped functional attempts do not duplicate audits', async () => {
  const result=await select(event,config,'Ali1/example',fixture({jobs:[{...job,conclusion:'skipped'}]}));
  assert.equal(result.run_audit,'false');assert.equal(result.graph_artifact_id,undefined);
});
test('functional failures do not suppress the independent security result when graph exists', async () => {
  assert.equal((await select(event,config,'Ali1/example',fixture({jobs:[{...job,conclusion:'failure'}]}))).run_audit,'true');
});
test('fork, wrong workflow/branch, stale attempt and malformed source never gain admission', async () => {
  for(const change of [{head_repository:{full_name:'other/fork'}},{head_branch:'feature'},{path:'.github/workflows/other.yml'},{run_attempt:1},{display_title:'core-checks:bad'},{display_title:`core-checks:${older}`}]) {
    await assert.rejects(select(event,config,'Ali1/example',fixture({r:{...run,...change}})));
  }
});
test('missing, stale, expired and ambiguous graph evidence is explicitly blocked', async () => {
  for(const artifacts of [[],[{...artifact,expired:true}],[{...artifact,created_at:'2026-10-27T10:00:00Z'}],[{...artifact,workflow_run:{head_sha:older}}],[artifact,{...artifact,id:10}]]) {
    await assert.rejects(select(event,config,'Ali1/example',fixture({artifacts})),/dependency graph/);
  }
  await assert.rejects(select(event,config,'Ali1/example',fixture({fail:true})),/unavailable/);
});
test('historical Sentry source remains exact and requires successful source guard', async () => {
  const sentry={...config,functional_workflow:'ci.yml',source_title_prefix:'nightly-checks',functional_jobs:['Backend and dashboard checks'],source_guard:'Exact source guard',historical_source:true,resolved_graph_artifact:false};
  const r={...run,path:'.github/workflows/ci.yml',display_title:`nightly-checks:${older}`};
  const jobs=[{...job,name:'Backend and dashboard checks'},{...job,name:'Exact source guard'}];
  const result=await select(event,sentry,'Ali1/example',fixture({r,jobs}));
  assert.equal(result.source_sha,older);assert.equal(result.source_workflow_sha,sha);assert.equal(result.run_audit,'true');
  assert.equal((await select(event,sentry,'Ali1/example',fixture({r,jobs:[jobs[0]]}))).run_audit,'false');
});