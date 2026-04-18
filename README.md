# Growth Insights

Growth Insights is a Laravel + Vue application that analyzes GitHub activity and turns it into evidence-based developer growth signals.

It combines:
- rule-based analytics for scoring and traceable evidence
- GitHub public analysis and token-backed private analysis
- Gemini enhancement for concise coaching, cached by snapshot hash
- a chart-heavy dashboard and workbench built with Vue 3, Element Plus, Tailwind, and ECharts

## What It Does

- fetches GitHub profile, repositories, languages, commits, pull requests, and issues
- computes growth score from consistency, diversity, and contribution
- derives strengths, weaknesses, momentum, skill signals, and weekly plan
- stores analysis runs, metric snapshots, weekly buckets, recommendations, and AI enhancement metadata
- supports:
  - public analysis by username
  - private analysis with an authorized GitHub token
  - score simulation
  - dashboard-friendly API payloads for charts and workbench views

## Stack

- Laravel 11
- Vue 3
- Vite
- Element Plus
- Tailwind CSS
- ECharts
- SQLite by default for local setup

## Setup

```bash
git clone https://github.com/dhrupo/growth-insights.git
cd growth-insights
cp .env.example .env
composer install
npm install
/opt/homebrew/opt/php@8.2/bin/php artisan key:generate
/opt/homebrew/opt/php@8.2/bin/php artisan migrate
```

Set environment values in `.env` as needed:

```env
GITHUB_TOKEN=
GEMINI_API_KEY=
GEMINI_MODEL=gemini-2.5-flash
GEMINI_FALLBACK_MODEL=gemini-2.0-flash
```

Notes:
- `GITHUB_TOKEN` is optional. It is useful for higher GitHub API limits.
- `GEMINI_API_KEY` is optional. Without it, analysis stays rule-based and the AI layer falls back cleanly.
- private analysis is opt-in and uses the user-provided GitHub token through the app API

## Run Locally

Backend:

```bash
/opt/homebrew/opt/php@8.2/bin/php artisan serve
```

Frontend:

```bash
npm run dev
```

Production build:

```bash
npm run build
```

## Test

```bash
/opt/homebrew/opt/php@8.2/bin/php artisan test
```

Current verified state:
- `12` passing tests
- public analysis flow tested with fake GitHub API responses
- private token-backed sync tested with fake GitHub API responses
- Gemini caching and fallback behavior tested with fake Gemini responses
- Vite production build passes

## Main API Endpoints

Public analysis:

```http
POST /api/analysis/public
```

Private connection:

```http
POST /api/github/connections
POST /api/github/connections/{id}/sync
GET  /api/github/connections/{id}/analysis/latest
```

Analysis:

```http
GET  /api/analysis/latest/by-username/{githubUsername}
GET  /api/analysis/{analysisRun}
GET  /api/analysis/{analysisRun}/timeline
GET  /api/analysis/{analysisRun}/recommendations
POST /api/analysis/simulations
```

Dashboard payloads:

```http
GET  /api/dashboard/summary
GET  /api/dashboard/timeline
GET  /api/dashboard/insights
GET  /api/dashboard/simulator
POST /api/dashboard/github/public-analysis
POST /api/dashboard/github/private-connection
```

## Product Behavior

### Public analysis
- enter a GitHub username
- fetch public activity
- compute score, signals, strengths, weaknesses, and weekly plan
- render dashboard and workbench charts

### Private analysis
- provide a GitHub token through the UI
- sync authorized private repositories
- merge private signals into the same analysis model

### Gemini enhancement
- builds a structured prompt from normalized facts only
- hashes the snapshot to avoid duplicate calls
- caches successful AI output
- retries and falls back to `gemini-2.0-flash` if the primary model is overloaded
- never replaces rule-based evidence; it only adds coaching notes

## Limitations

- GitHub activity is only one signal source
- private work is invisible unless the user explicitly connects it
- the app does not predict salary, hiring probability, or career outcomes
- a decline in activity is treated as a momentum signal, not a burnout diagnosis

## UI Areas

- `Dashboard`
  - score cards
  - timeline chart
  - insight mix
  - simulator chart
- `Workbench`
  - username analysis form
  - private token connection form
  - score breakdown chart
  - skill distribution chart
  - strengths, weaknesses, and recommendations

## Implementation Notes

- analysis runs are persisted and can be reused by the dashboard endpoints
- dashboard endpoints reshape domain data into frontend-friendly chart payloads
- Gemini output is stored on the run with snapshot hash, status, model, timestamp, and merged coaching notes
- the app is designed so rule-based analytics remain the source of truth
