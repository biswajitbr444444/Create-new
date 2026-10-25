# Cline logic reference for PocketCat

The two `.source.ts` files are the uploaded Cline source files preserved for audit/reference. PocketCat is PHP + MySQL on InfinityFree, so these TypeScript modules are **not loaded by the production PHP runtime**.

The production implementation mirrors their useful separation of concerns:
- provider/model/auth resolution is kept in one configuration layer;
- Bedrock API-key authentication is used for OpenAI-compatible APIs;
- the transport is isolated behind the Cloudflare Worker relay;
- Agent mode uses the Responses API and tool calls;
- PocketCat keeps workspace tools server-side and scoped to the authenticated user.

Do not paste the TypeScript files into `htdocs`; use the PocketCat PHP files and the Worker under `relay/cloudflare/`.
