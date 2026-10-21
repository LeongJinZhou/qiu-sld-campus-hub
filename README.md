# SLD

## Tech Stack
- **Frontend**: Next.js / React / Tailwind CSS *(or HTML5 / CSS3 / JS)*
- **Backend**: Node.js / TypeScript *(or PHP 8+ PSR-12)*
- **Database**: Supabase / PostgreSQL *(or MySQL / PDO SQLite local fallback)*

## Quick Start

```bash
# 1. Configure environment variables
cp .env.example .env

# 2. Run locally
# Personal Stack (Next.js):
npm install && npm run dev

# University Stack (PHP 8):
php -S localhost:8000 -t public
```

## Project Structure
```text
SLD/
├── public/           # Public entry points & static assets
├── src/
│   ├── components/   # Reusable UI components
│   ├── services/     # Business logic layer
│   └── repositories/ # Database access layer (PDO / Supabase)
├── .env.example      # Environment variable template
└── README.md         # Project documentation
```
