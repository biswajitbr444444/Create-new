const fs = require('fs');
const http = require('http');
const https = require('https');
const crypto = require('crypto');
const { pathToFileURL } = require('url');

const cert = fs.readFileSync('/tmp/pocketcat-ai-e2e/certs/local-cert.pem');
const key = fs.readFileSync('/tmp/pocketcat-ai-e2e/certs/local-key.pem');
const workerPath = '/tmp/pocketcat-ai-e2e/generated-worker.mjs';
const logPath = '/tmp/pocketcat-ai-e2e/events.ndjson';
const credentials = {
  AKIAQAADMIN0000001: { secret: 'admin-sigv4-secret-local', token: 'admin-session-local' },
  ASIAQAUSER00000002: { secret: 'user-sigv4-secret-local', token: 'user-session-local' }
};

function log(event) {
  fs.appendFileSync(logPath, `${JSON.stringify({ at: new Date().toISOString(), ...event })}\n`);
}

function collectBody(request) {
  return new Promise((resolve, reject) => {
    const chunks = [];
    request.on('data', chunk => chunks.push(chunk));
    request.on('end', () => resolve(Buffer.concat(chunks)));
    request.on('error', reject);
  });
}

function respondOpenAI(request, response, body) {
  const auth = request.headers.authorization || '';
  let payload;
  try {
    payload = JSON.parse(body.toString('utf8'));
  } catch {
    response.writeHead(400).end('{"error":{"message":"invalid JSON"}}');
    return;
  }
  const accepted = /^Bearer .+$/.test(auth);
  log({
    type: 'openai',
    path: request.url,
    model: payload.model || null,
    authorizationAccepted: accepted,
    relayTokenForwarded: Boolean(request.headers['x-pocketcat-relay-token']),
    userAgent: request.headers['user-agent'] || ''
  });
  if (!accepted) {
    response.writeHead(401, { 'content-type': 'application/json' }).end('{"error":{"message":"Bearer authorization required"}}');
    return;
  }
  const userText = (payload.messages || []).map(message => typeof message.content === 'string' ? message.content : '').join('\n');
  const content = /task extraction engine/i.test(userText)
    ? '- Verify the end-to-end mock task route [High]'
    : `Mock response for model ${payload.model || 'missing-model'}.`;
  const result = JSON.stringify({
    choices: [{ message: { role: 'assistant', content }, finish_reason: 'stop' }]
  });
  response.writeHead(200, { 'content-type': 'application/json', 'content-length': Buffer.byteLength(result) });
  response.end(result);
}

const upstream = (request, response) => {
  collectBody(request).then(body => {
    if (request.method !== 'POST' || request.url !== '/v1/chat/completions') {
      response.writeHead(404).end();
      return;
    }
    respondOpenAI(request, response, body);
  }).catch(() => response.writeHead(500).end());
};

http.createServer(upstream).listen(9090, '127.0.0.1');
https.createServer({ key, cert }, upstream).listen(9443, '127.0.0.1');

https.createServer({ key, cert }, async (request, response) => {
  try {
    const workerUrl = `${pathToFileURL(workerPath).href}?v=${Date.now()}`;
    const { default: worker } = await import(workerUrl);
    const chunks = await collectBody(request);
    const proxied = new Request(`https://127.0.0.1:9444${request.url}`, {
      method: request.method,
      headers: request.headers,
      body: ['GET', 'HEAD'].includes(request.method) ? undefined : chunks
    });
    const result = await worker.fetch(proxied);
    const resultBody = Buffer.from(await result.arrayBuffer());
    response.writeHead(result.status, Object.fromEntries(result.headers.entries()));
    response.end(resultBody);
  } catch (error) {
    log({ type: 'relay-error', message: String(error) });
    response.writeHead(500, { 'content-type': 'application/json' }).end(JSON.stringify({ error: String(error) }));
  }
}).listen(9444, '127.0.0.1');

function hmac(keyBytes, value) {
  return crypto.createHmac('sha256', keyBytes).update(value, 'utf8').digest();
}

function verifySigV4(request, body) {
  const authorization = String(request.headers.authorization || '');
  const match = authorization.match(/^AWS4-HMAC-SHA256 Credential=([^/]+)\/(\d{8})\/([^/]+)\/([^/]+)\/aws4_request, SignedHeaders=([^,]+), Signature=([a-f0-9]{64})$/);
  if (!match) return { ok: false, reason: 'authorization-format' };
  const [, accessKey, date, region, service, signedHeaderList, suppliedSignature] = match;
  const known = credentials[accessKey];
  if (!known) return { ok: false, reason: 'unknown-access-key' };
  const amzDate = String(request.headers['x-amz-date'] || '');
  if (!/^\d{8}T\d{6}Z$/.test(amzDate) || amzDate.slice(0, 8) !== date) return { ok: false, reason: 'date-scope' };
  if (region !== 'us-east-1' || service !== 'bedrock') return { ok: false, reason: 'scope-region-or-service' };
  if (request.headers['x-amz-security-token'] !== known.token) return { ok: false, reason: 'session-token' };
  const signedHeaders = signedHeaderList.split(';');
  if (!signedHeaders.includes('host') || !signedHeaders.includes('x-amz-date') || !signedHeaders.includes('x-amz-security-token')) {
    return { ok: false, reason: 'required-signed-header-missing' };
  }
  const canonicalHeaders = signedHeaders.map(name => {
    const value = String(request.headers[name] || '').trim().replace(/\s+/g, ' ');
    return `${name}:${value}\n`;
  }).join('');
  const url = new URL(request.url, `https://${request.headers.host}`);
  const canonical = [
    request.method,
    url.pathname,
    url.search.slice(1),
    canonicalHeaders,
    signedHeaderList,
    crypto.createHash('sha256').update(body).digest('hex')
  ].join('\n');
  const scope = `${date}/${region}/${service}/aws4_request`;
  const stringToSign = `AWS4-HMAC-SHA256\n${amzDate}\n${scope}\n${crypto.createHash('sha256').update(canonical).digest('hex')}`;
  const dateKey = hmac(`AWS4${known.secret}`, date);
  const regionKey = hmac(dateKey, region);
  const serviceKey = hmac(regionKey, service);
  const signingKey = hmac(serviceKey, 'aws4_request');
  const expected = crypto.createHmac('sha256', signingKey).update(stringToSign, 'utf8').digest('hex');
  return {
    ok: crypto.timingSafeEqual(Buffer.from(expected, 'hex'), Buffer.from(suppliedSignature, 'hex')),
    reason: 'signature',
    accessKey,
    region,
    service,
    signedHeaders: signedHeaderList,
    model: decodeURIComponent(url.pathname.match(/\/model\/([^/]+)\//)?.[1] || '')
  };
}

https.createServer({ key, cert }, (request, response) => {
  collectBody(request).then(body => {
    const verification = verifySigV4(request, body);
    log({ type: 'bedrock-sigv4', method: request.method, path: request.url, model: request.url.match(/\/model\/([^/]+)\//)?.[1] || null, ...verification });
    if (!verification.ok) {
      response.writeHead(403, { 'content-type': 'application/json' });
      response.end(JSON.stringify({ message: `SigV4 validation failed: ${verification.reason}` }));
      return;
    }
    let requestData = {};
    try { requestData = JSON.parse(body.toString('utf8')); } catch {}
    const requestText = JSON.stringify(requestData.messages || []);
    const text = /task extraction engine/i.test(requestText)
      ? '- Verify the end-to-end mock task route [High]'
      : `Local Bedrock SigV4 response for model ${verification.model}.`;
    const result = JSON.stringify({
      output: { message: { role: 'assistant', content: [{ text }] } }
    });
    response.writeHead(200, { 'content-type': 'application/json', 'content-length': Buffer.byteLength(result) });
    response.end(result);
  }).catch(() => response.writeHead(500).end());
}).listen(443, '0.0.0.0');

console.log('Mock OpenAI upstream, generated Worker relay, and strict SigV4 Bedrock endpoint listening.');
