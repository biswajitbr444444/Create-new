# PocketCat V11.56 — AI Chat + Agent Mode Fix

## Fixed
- AI Chat mode now works with both OpenAI-compatible Bedrock connections and AWS IAM/Converse connections.
- AI Agent mode no longer depends on the OpenAI Responses API.
- OpenAI-compatible Agent mode now uses the standard Chat Completions function/tool-calling contract.
- AWS IAM Agent mode now uses the native Bedrock Converse `toolConfig` / `toolUse` / `toolResult` flow.
- Existing Agent workspace tools remain available: search workspace, create task, create note, create project.
- Agent tool calls are bounded to five rounds to prevent runaway loops.
- Clear final errors are returned when the provider returns no usable text.
- Relay connections continue to use the same-origin relay transport for both Chat and Agent modes.

## Compatibility
- Chat mode: OpenAI-compatible and AWS IAM/Converse.
- Agent mode: OpenAI-compatible function calling and AWS IAM/Converse tool use.
- Existing conversation history remains compatible.
- No destructive database migration is required.
