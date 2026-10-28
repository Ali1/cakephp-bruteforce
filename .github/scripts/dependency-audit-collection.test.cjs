'use strict';
const test=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const os=require('node:os');
const {execFileSync,spawnSync}=require('node:child_process');
const {collect}=require('./dependency-audit-collection.cjs');
const {runAudits,receipt}=require('./dependency-security.cjs');
function npmReport(severity='high') {
  const counts={info:0,low:0,moderate:0,high:0,critical:0,total:1};counts[severity]=1;
  return {auditReportVersion:2,vulnerabilities:{example:{name:'example',severity,range:'<2.0.0',nodes:['node_modules/example'],via:[{source:1,url:'https://github.com/advisories/GHSA-aaaa-bbbb-cccc',severity,range:'<2.0.0'}]}},metadata:{vulnerabilities:counts}};
}
const result=(program,report,exit_code)=>({program,args:['audit'],exit_code,state:exit_code?'failed':'passed',stdout:JSON.stringify(report),stderr:''});
test('complete high and critical finding collection succeeds while raw audit remains failed',()=>{
 for(const severity of ['high','critical']) {
  const raw=result('npm',npmReport(severity),1);const c=collect([raw]);
  assert.equal(c.collection_state,'completed');assert.equal(c.raw_security_state,'failed');
  assert.equal(receipt([raw],'a'.repeat(40),{}).verification_state,'failed');assert.equal(raw.exit_code,1);
 }
});
test('Composer exit bitmasks retain advisories and abandoned-package outcomes separately',()=>{
 const advisory={packageName:'vendor/example',advisoryId:'PKSA-aaaa',affectedVersions:'<2',severity:'high'};
 for(const [advisories,abandoned,exit] of [[[],[],0],[{'vendor/example':[advisory]},[],1],[[],{'vendor/old':null},2],[{'vendor/example':[advisory]},{'vendor/old':'vendor/new'},3]]) {
  const raw=result('composer',{advisories,abandoned},exit);assert.equal(collect([raw]).collection_state,'completed');assert.equal(raw.exit_code,exit);
 }
 for(const abandoned of [['bad'],{'bad package':5},{'vendor/old':{replacement:'vendor/new'}}]) assert.equal(collect([result('composer',{advisories:[],abandoned},2)]).collection_state,'blocked');
});
test('partial/malformed/unavailable/index failures and arbitrary nonzero exits block collection',()=>{
 for(const raw of [result('npm',{error:{code:'EINDEX'}},1),result('npm',{},1),result('npm',npmReport(),2),{...result('npm',npmReport(),1),stdout:'not JSON'},{...result('npm',npmReport(),1),exit_code:null,error:'unavailable'}]) assert.equal(collect([raw]).collection_state,'blocked');
 const partial=npmReport();partial.metadata.vulnerabilities.total=0;assert.equal(collect([result('npm',partial,1)]).collection_state,'blocked');
 const unavailable=runAudits([{program:'npm',args:['audit']}],()=>({status:null,error:Error('not installed')}));assert.equal(collect(unavailable).collection_state,'blocked');
});
test('actual CLI collection preserves failed raw receipt, logs and command exit without masking collection errors',()=>{
 const tmp=fs.mkdtempSync(path.join(os.tmpdir(),'security-collection-'));
 try {
  const scripts=path.join(tmp,'.github/scripts'),bin=path.join(tmp,'bin');fs.mkdirSync(scripts,{recursive:true});fs.mkdirSync(bin);
  for(const f of ['dependency-security.cjs','dependency-audit-collection.cjs']) fs.copyFileSync(path.join(__dirname,f),path.join(scripts,f));
  fs.writeFileSync(path.join(tmp,'.github/dependency-security.json'),JSON.stringify({checks:[{program:'npm',args:['audit','--json']}]}));
  const fake=path.join(bin,'audit.cjs');fs.writeFileSync(fake,'process.stdout.write(process.env.AUDIT_TEST_REPORT);process.exit(Number(process.env.AUDIT_TEST_EXIT));');
  const win=process.platform==='win32';const npm=path.join(bin,win?'npm.cmd':'npm');
  fs.writeFileSync(npm,win?`@"${process.execPath}" "${fake}" %*\r\n`:`#!/bin/sh\nexec "${process.execPath}" "${fake}" "$@"\n`);if(!win)fs.chmodSync(npm,0o755);
  const git=args=>execFileSync('git',['-C',tmp,...args],{stdio:'ignore'});git(['init']);git(['add','.']);git(['-c','user.name=Synthetic test','-c','user.email=test@example.invalid','commit','-m','synthetic fixture']);
  const cli=collectMode=>spawnSync(process.execPath,[path.join(scripts,'dependency-security.cjs'),...(collectMode?['--collect-evidence']:[])],{cwd:tmp,encoding:'utf8',env:{...process.env,PATH:bin+path.delimiter+process.env.PATH,AUDIT_TEST_REPORT:JSON.stringify(npmReport()),AUDIT_TEST_EXIT:'1',GITHUB_STEP_SUMMARY:''}});
  const raw=cli(false);assert.equal(raw.status,1,raw.stderr);
  const collected=cli(true);assert.equal(collected.status,0,collected.stdout+collected.stderr);
  const out=path.join(tmp,'output/dependency-security');const read=f=>JSON.parse(fs.readFileSync(path.join(out,f),'utf8'));
  assert.equal(read('receipt.json').verification_state,'failed');assert.equal(read('receipt.json').checks[0].exit_code,1);
  assert.equal(read('audit-1.json').exit_code,1);assert.equal(read('collection.json').collection_state,'completed');assert.match(fs.readFileSync(path.join(out,'audit-1.log'),'utf8'),/GHSA-aaaa/);
  const broken=spawnSync(process.execPath,[path.join(scripts,'dependency-security.cjs'),'--collect-evidence'],{cwd:tmp,encoding:'utf8',env:{...process.env,PATH:bin+path.delimiter+process.env.PATH,AUDIT_TEST_REPORT:'bad JSON',AUDIT_TEST_EXIT:'1',GITHUB_STEP_SUMMARY:''}});
  assert.equal(broken.status,1);assert.equal(read('collection.json').collection_state,'blocked');
 }finally{assert.equal(path.dirname(tmp),os.tmpdir());assert.ok(path.basename(tmp).startsWith("security-collection-"));fs.rmSync(tmp,{recursive:true,force:true});}
});