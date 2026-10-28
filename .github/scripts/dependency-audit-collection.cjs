'use strict';
// Same strict collection normalizer as the reviewed estate release helper; no release-admission policy.
const levels = ['info', 'low', 'moderate', 'high', 'critical'];
const demand = (ok, why) => { if (!ok) throw Error(why); };
const object = x => x !== null && typeof x === 'object' && !Array.isArray(x);
const text = x => typeof x === 'string' && x.trim().length > 0;
function normalize(result) {
  demand(result && !result.error && Number.isInteger(result.exit_code), 'Audit tool unavailable');
  let report;
  try { report=JSON.parse(result.stdout); } catch { throw Error('Missing JSON audit report'); }
  demand(object(report) && !report.error, 'Audit report unavailable');
  const findings=[];
  if (result.program === 'npm') {
    demand(report.auditReportVersion === 2 && object(report.vulnerabilities) && object(report.metadata?.vulnerabilities), 'Incomplete npm report');
    for (const [name,v] of Object.entries(report.vulnerabilities)) {
      demand(v.name === name && levels.includes(v.severity) && Array.isArray(v.via) && v.via.length && Array.isArray(v.nodes) && v.nodes.length && text(v.range), 'Malformed npm finding');
      const related={};
      const visit=n=>{
        if (related[n]) return;
        const row=report.vulnerabilities[n];
        demand(object(row) && Array.isArray(row.via), 'Missing advisory chain');
        related[n]=row;
        for (const via of row.via) {
          if (typeof via === 'string') visit(via);
          else demand(object(via) && (Number.isInteger(via.source) || text(via.url)) && levels.includes(via.severity) && text(via.range), 'Malformed advisory');
        }
      };
      visit(name);
      // npm also lists reverse dependants as meta-vulnerabilities. Their actual
      // vulnerable leaf is assessed once; inputs() binds every reverse parent.
      if(!v.via.some(object)) continue;
      const advisories=new Map();
      for(const via of v.via.filter(object)) {
        const id=via.url?.match(/GHSA-[a-z0-9-]+/i)?.[0] || String(via.source);
        advisories.set(id,via);
      }
      demand(advisories.size,'Missing leaf advisory');
      for(const [id,advisory] of advisories) findings.push({id,package:name,severity:advisory.severity,paths:[...v.nodes].sort(),advisory,related});
    }
    const count=report.metadata.vulnerabilities;
    const rows=Object.values(report.vulnerabilities);
    demand(count.total === rows.length && levels.every(s => Number.isInteger(count[s]) && count[s] === rows.filter(f=>f.severity===s).length), 'Partial npm report');
  } else {
    demand(result.program === 'composer' && (object(report.advisories) || Array.isArray(report.advisories)) && (object(report.abandoned) || Array.isArray(report.abandoned)), 'Incomplete Composer report');
    for (const [name,list] of Object.entries(report.advisories)) {
      demand(Array.isArray(list) && list.length, 'Malformed Composer advisory list');
      for (const a of list) {
        demand(a.packageName===name && text(a.advisoryId) && text(a.affectedVersions) && levels.includes(a.severity), 'Malformed Composer advisory');
        findings.push({id:a.advisoryId,package:name,severity:a.severity,paths:[],advisory:a});
      }
    }
    // Do not turn an abandoned-package or unknown audit failure into advisory permission.
    demand(Object.keys(report.abandoned).length===0, 'Abandoned-package finding requires its own resolution');
  }
  demand(result.exit_code === (findings.length ? 1 : 0), 'Audit exit/report mismatch');
  return findings;
}
function collect(results) {
  const out={schema_version:1,collection_state:'blocked',raw_security_state:results.some(r=>r.state==='blocked')?'blocked':results.some(r=>r.state==='failed')?'failed':'passed',reasons:[]};
  try {
    demand(Array.isArray(results) && results.length>0,'Missing audit collection');
    for(const result of results) {
      if(result.program==='composer') {
        demand(!result.error && Number.isInteger(result.exit_code),'Audit tool unavailable');
        let report;try{report=JSON.parse(result.stdout);}catch{throw Error('Missing JSON audit report');}
        demand(object(report) && !report.error && (object(report.advisories)||Array.isArray(report.advisories)) && (object(report.abandoned)||Array.isArray(report.abandoned)),'Incomplete Composer collection');
        demand(!Array.isArray(report.abandoned)||report.abandoned.length===0,'Malformed abandoned-package list');
        demand(Object.entries(report.abandoned).every(([name,replacement])=>/^[a-z0-9_.-]+\/[a-z0-9_.-]+$/i.test(name) && (replacement===null || (text(replacement) && /^[a-z0-9_.-]+\/[a-z0-9_.-]+$/i.test(replacement)))),'Malformed abandoned-package entry');
        const vulnerable=Object.keys(report.advisories).length>0,abandoned=Object.keys(report.abandoned).length>0;
        demand(result.exit_code===Number(vulnerable)+2*Number(abandoned),'Composer audit exit/report mismatch');
        // The admission evaluator deliberately refuses abandoned packages.
        // Collection validates and preserves them without declaring admission.
        normalize({...result,exit_code:vulnerable?1:0,stdout:JSON.stringify({...report,abandoned:[]})});
      } else normalize(result);
    }
    out.collection_state='completed';
  } catch(error) {out.reasons.push(error.message);}
  return out;
}
module.exports={normalize,collect};
