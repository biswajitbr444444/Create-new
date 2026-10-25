/**
 * PocketCat — Cloudflare Worker Bedrock Relay
 *
 * Production transport: Amazon Bedrock Mantle OpenAI-compatible API.
 *
 * Required Worker secrets:
 *   BEDROCK_API_KEY
 *   RELAY_TOKEN
 *
 * Variables:
 *   BEDROCK_REGION
 *   BEDROCK_MODEL_ID
 *   BEDROCK_BASE_URL
 */
const DEFAULT_REGION = "us-east-1";
const DEFAULT_MODEL = "openai.gpt-oss-120b";
const DEFAULT_BASE_URL = "https://bedrock-mantle.us-east-1.api.aws/v1";

const cors = {
  "Access-Control-Allow-Origin": "*",
  "Access-Control-Allow-Methods": "GET, POST, OPTIONS",
  "Access-Control-Allow-Headers": "Content-Type, Authorization, X-PocketCat-Relay-Token",
  "Access-Control-Max-Age": "86400"
};

function json(data, status = 200) {
  return new Response(JSON.stringify(data), {
    status,
    headers: {
      ...cors,
      "Content-Type": "application/json; charset=utf-8",
      "Cache-Control": "no-store",
      "X-Content-Type-Options": "nosniff"
    }
  });
}

function sameSecret(a, b) {
  if (typeof a !== "string" || typeof b !== "string" || a.length !== b.length) return false;
  let result = 0;
  for (let i = 0; i < a.length; i++) result |= a.charCodeAt(i) ^ b.charCodeAt(i);
  return result === 0;
}

function region(env) {
  return String(env.BEDROCK_REGION || DEFAULT_REGION).trim() || DEFAULT_REGION;
}

function model(env) {
  return String(env.BEDROCK_MODEL_ID || DEFAULT_MODEL).trim() || DEFAULT_MODEL;
}

function baseUrl(env) {
  const configured = String(env.BEDROCK_BASE_URL || "").trim();
  const value = configured || `https://bedrock-mantle.${region(env)}.api.aws/v1`;
  return value.replace(/\/$/, "");
}

function authorized(request, env) {
  const expected = String(env.RELAY_TOKEN || "");
  const supplied = request.headers.get("X-PocketCat-Relay-Token") || "";
  return expected !== "" && sameSecret(supplied, expected);
}

function normalizeLimit(value, fallback = 1200) {
  if (value === undefined || value === null) return undefined;
  const n = Number(value);
  if (!Number.isFinite(n)) return fallback;
  return Math.min(Math.max(Math.floor(n), 256), 8192);
}

export default {
  async fetch(request, env) {
    const url = new URL(request.url);
    const path = url.pathname.replace(/\/+$/, "") || "/";

    if (request.method === "OPTIONS") return new Response(null, { status: 204, headers: cors });

    if (request.method === "GET" && path === "/health") {
      const apiKeyConfigured = !!env.BEDROCK_API_KEY;
      const relayTokenConfigured = !!env.RELAY_TOKEN;
      return json({
        ok: apiKeyConfigured && relayTokenConfigured,
        service: "PocketCat Bedrock Relay",
        transport: "Cloudflare Worker",
        region: region(env),
        model: model(env),
        endpoint: baseUrl(env),
        api_key_configured: apiKeyConfigured,
        relay_token_configured: relayTokenConfigured
      });
    }

    if (request.method === "GET" && path === "/") {
      return json({
        ok: true,
        service: "PocketCat Bedrock Relay",
        health: "/health",
        endpoints: ["/responses", "/chat/completions"]
      });
    }

    if (request.method !== "POST" || !["/chat/completions", "/responses"].includes(path)) {
      return json({ ok: false, error: "Not found" }, 404);
    }

    if (!authorized(request, env)) {
      return json({ ok: false, error: "Unauthorized relay request. Check RELAY_TOKEN." }, 401);
    }

    if (!env.BEDROCK_API_KEY) {
      return json({ ok: false, error: "Relay is missing BEDROCK_API_KEY." }, 500);
    }

    let input;
    try {
      input = await request.json();
    } catch {
      return json({ ok: false, error: "Invalid JSON body." }, 400);
    }

    if (!input || typeof input !== "object" || Array.isArray(input)) {
      return json({ ok: false, error: "Request body must be a JSON object." }, 400);
    }

    const payload = { ...input, model: model(env) };

    // Never allow the browser/PHP client to override the Worker's secrets or
    // upstream endpoint.
    delete payload.api_key;
    delete payload.bedrock_api_key;
    delete payload.base_url;
    delete payload.endpoint;
    delete payload.relay_token;

    if (path === "/responses") {
      // PocketCat stores its own chat history. Do not duplicate that content
      // in Bedrock's managed response store.
      payload.store = false;
    }

    // AWS's OpenAI-compatible APIs use max_completion_tokens. Keep legacy
    // max_tokens clients working by translating them at the relay boundary.
    if (payload.max_completion_tokens === undefined && payload.max_tokens !== undefined) {
      payload.max_completion_tokens = payload.max_tokens;
    }
    delete payload.max_tokens;

    if (payload.max_completion_tokens !== undefined) {
      payload.max_completion_tokens = normalizeLimit(payload.max_completion_tokens);
    }
    if (payload.max_output_tokens !== undefined) {
      payload.max_output_tokens = normalizeLimit(payload.max_output_tokens);
    }

    const stream = input.stream === true;
    const upstreamUrl = `${baseUrl(env)}${path}`;
    let upstream;

    try {
      upstream = await fetch(upstreamUrl, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "Accept": stream ? "text/event-stream" : "application/json",
          "Authorization": `Bearer ${env.BEDROCK_API_KEY}`
        },
        body: JSON.stringify(payload)
      });
    } catch (error) {
      return json({
        ok: false,
        error: "AWS connection failed.",
        detail: error instanceof Error ? error.message : String(error)
      }, 502);
    }

    const responseHeaders = new Headers(cors);
    responseHeaders.set("Cache-Control", "no-store");
    responseHeaders.set("X-Content-Type-Options", "nosniff");
    responseHeaders.set("X-PocketCat-Upstream-Status", String(upstream.status));

    if (stream) {
      responseHeaders.set("Content-Type", upstream.headers.get("Content-Type") || "text/event-stream; charset=utf-8");
      return new Response(upstream.body, { status: upstream.status, headers: responseHeaders });
    }

    const text = await upstream.text();
    responseHeaders.set("Content-Type", upstream.headers.get("Content-Type") || "application/json; charset=utf-8");
    return new Response(text, { status: upstream.status, headers: responseHeaders });
  }
};
