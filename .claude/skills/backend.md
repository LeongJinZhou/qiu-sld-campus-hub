# Backend Rules
- Separate UI/Controllers from business logic and DB calls.
- Validate all incoming parameters (Zod for Node/TS, strict filtering for PHP).
- Return standardized error JSON: `{ "error": "Message" }`.
