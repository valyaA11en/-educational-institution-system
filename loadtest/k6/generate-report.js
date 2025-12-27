#!/usr/bin/env node

const fs = require('fs');
const path = require('path');

function generateReport(jsonFile) {
  const data = JSON.parse(fs.readFileSync(jsonFile, 'utf8'));
  
  const metrics = data.metrics || {};
  
  // Extract p95 latency
  const httpReqDuration = metrics['http_req_duration'];
  const p95 = httpReqDuration?.values?.p95 || 0;
  
  // Extract error rate
  const errors = metrics['errors'];
  const errorRate = errors?.values?.rate || 0;
  const errorCount = errors?.values?.count || 0;
  
  // Extract request count
  const httpReqs = metrics['http_reqs'];
  const totalRequests = httpReqs?.values?.count || 0;
  
  // Extract other metrics
  const avgDuration = httpReqDuration?.values?.avg || 0;
  const maxDuration = httpReqDuration?.values?.max || 0;
  const minDuration = httpReqDuration?.values?.min || 0;
  
  console.log('\n=== Load Test Report ===\n');
  console.log(`File: ${jsonFile}`);
  console.log(`\nTotal Requests: ${totalRequests}`);
  console.log(`\nLatency Metrics:`);
  console.log(`  Average: ${avgDuration.toFixed(2)}ms`);
  console.log(`  Min: ${minDuration.toFixed(2)}ms`);
  console.log(`  Max: ${maxDuration.toFixed(2)}ms`);
  console.log(`  p95: ${p95.toFixed(2)}ms`);
  console.log(`\nError Metrics:`);
  console.log(`  Error Count: ${errorCount}`);
  console.log(`  Error Rate: ${(errorRate * 100).toFixed(2)}%`);
  console.log(`\nStatus:`);
  console.log(`  ${errorRate < 0.1 ? '✅ PASS' : '❌ FAIL'} - Error rate ${(errorRate * 100).toFixed(2)}%`);
  console.log(`  ${p95 < 1000 ? '✅ PASS' : '❌ FAIL'} - p95 latency ${p95.toFixed(2)}ms`);
  console.log('\n');
}

// Get file from command line
const jsonFile = process.argv[2];

if (!jsonFile) {
  console.error('Usage: node generate-report.js <results-file.json>');
  process.exit(1);
}

if (!fs.existsSync(jsonFile)) {
  console.error(`File not found: ${jsonFile}`);
  process.exit(1);
}

generateReport(jsonFile);


