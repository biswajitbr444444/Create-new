# PocketCat Bedrock Relay — Cloudflare Worker

Use this relay when InfinityFree blocks or interferes with server-to-AWS requests. The PocketCat PHP server calls the Worker; the Worker calls Amazon Bedrock. Your `pocketcat.online` DNS stays on InfinityFree.

## Worker configuration

Variables:
- `BEDROCK_REGION=us-east-1`
- `BEDROCK_MODEL_ID=openai.gpt-oss-120b`
- `BEDROCK_BASE_URL=https://bedrock-runtime.us-east-1.amazonaws.com/openai/v1`

Secrets:
- `BEDROCK_API_KEY` = your Amazon Bedrock API key
- `RELAY_TOKEN` = a long random shared secret used only between PocketCat and the Worker

Deploy `relay/cloudflare/worker.js`. Then open `/health`; it should show `ok: true` and `api_key_configured: true`.

## PocketCat settings

Admin → AWS Bedrock:
- Transport: Cloudflare Worker Relay
- Relay URL: your Worker `https://...workers.dev` URL
- Relay Shared Token: exactly the same value as Worker `RELAY_TOKEN`
- Region: `us-east-1`
- Model: `openai.gpt-oss-120b`
- API Mode: OpenAI-compatible Chat

Normal Chat uses `/chat/completions`. Agent mode uses `/responses`.

## Security

The Bedrock API key remains a Worker secret and is never sent to the browser. PocketCat authenticates to the Worker with `X-PocketCat-Relay-Token`. Do not put either secret into public source code.


Production target: Amazon Bedrock Mantle (`https://bedrock-mantle.<region>.api.aws/v1`). The Worker exposes `/chat/completions` for normal chat and `/responses` for Agent mode.
